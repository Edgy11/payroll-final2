<?php
/**
 * Step Increment History
 * Reads/writes the step_increment table for persistent DB-backed history logging.
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireSuperAdmin();

$pageTitle = 'Step Increment History';
$alert = '';
$alertType = '';

$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
if ($employeeId <= 0) { header('Location: employees.php'); exit; }

// ── Fetch employee ────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT e.*, d.department_name, d.department_code,
           p.position_title, p.salary_grade, p.id AS pos_id
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions   p ON e.position_id   = p.id
    WHERE e.id = ?
");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
if (!$employee) { header('Location: employees.php'); exit; }

// ── Helper: compute step info from date_hired ─────────────────────────────────
function calcSteps($dateHired) {
    if (!$dateHired) return ['current' => 0, 'years' => 0, 'months' => 0, 'effectiveDates' => []];
    $hire  = new DateTime($dateHired);
    $today = new DateTime();
    $diff  = $hire->diff($today);
    $years  = $diff->y;
    $months = $diff->m;
    $current = min(8, (int)floor($years / 3) + 1);
    $dates = [];
    for ($s = 1; $s <= $current; $s++) {
        $d = clone $hire;
        $d->modify('+' . (($s - 1) * 3) . ' years');
        $dates[$s] = $d->format('Y-m-d');
    }
    return ['current' => $current, 'years' => $years, 'months' => $months, 'effectiveDates' => $dates];
}

$calc = calcSteps($employee['date_hired']);

// ── POST Actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');

    // --- SYNC ---
    if ($action === 'sync') {
        if (!$employee['date_hired'] || !$employee['salary_grade']) {
            $alert = 'Cannot sync: employee is missing Date Hired or Salary Grade.';
            $alertType = 'warning';
        } else {
            $synced = 0;
            foreach ($calc['effectiveDates'] as $step => $effDate) {
                $sq = $conn->prepare("SELECT salary_id FROM salary WHERE salary_grade = ? AND step_no = ? LIMIT 1");
                $sq->bind_param("si", $employee['salary_grade'], $step);
                $sq->execute();
                $sr = $sq->get_result()->fetch_assoc();
                $salaryId   = $sr ? (int)$sr['salary_id'] : null;
                $positionId = $employee['pos_id'] ? (int)$employee['pos_id'] : null;
                $isActive   = ($step === $calc['current']) ? 'Y' : 'N';
                $ins = $conn->prepare("INSERT INTO step_increment (employee_id, step_inc, salary_id, position_id, is_active) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE salary_id=VALUES(salary_id), position_id=VALUES(position_id), is_active=VALUES(is_active), updated_at=current_timestamp()");
                $ins->bind_param("iiiis", $employeeId, $step, $salaryId, $positionId, $isActive);
                $ins->execute(); $ins->close(); $synced++;
            }
            $alert = "Synced $synced step(s) to the database successfully.";
            $alertType = 'success';
        }
    }

    // --- SAVE (manual add/update) ---
    if ($action === 'save') {
        $stepInc    = (int)($_POST['step_inc'] ?? 0);
        $salaryId   = !empty($_POST['salary_id'])   ? (int)$_POST['salary_id']   : null;
        $positionId = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
        $isActive   = in_array($_POST['is_active'] ?? '', ['Y','N']) ? $_POST['is_active'] : 'N';

        if ($stepInc < 1 || $stepInc > 8) {
            $alert = 'Invalid step number.'; $alertType = 'danger';
        } else {
            if ($isActive === 'Y') {
                $conn->query("UPDATE step_increment SET is_active='N' WHERE employee_id=$employeeId");
            }
            $ins = $conn->prepare("INSERT INTO step_increment (employee_id, step_inc, salary_id, position_id, is_active) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE salary_id=VALUES(salary_id), position_id=VALUES(position_id), is_active=VALUES(is_active), updated_at=current_timestamp()");
            $ins->bind_param("iiiis", $employeeId, $stepInc, $salaryId, $positionId, $isActive);
            if ($ins->execute()) { $alert = "Step $stepInc saved successfully."; $alertType = 'success'; }
            else { $alert = 'DB error: ' . $conn->error; $alertType = 'danger'; }
            $ins->close();
        }
    }

    // --- SET ACTIVE ---
    if ($action === 'set_active') {
        $recId = (int)($_POST['record_id'] ?? 0);
        $conn->query("UPDATE step_increment SET is_active='N' WHERE employee_id=$employeeId");
        $u = $conn->prepare("UPDATE step_increment SET is_active='Y' WHERE id=? AND employee_id=?");
        $u->bind_param("ii", $recId, $employeeId);
        $u->execute();
        $alert = 'Active step updated.'; $alertType = 'success';
    }

    // --- DELETE ---
    if ($action === 'delete') {
        $recId = (int)($_POST['record_id'] ?? 0);
        $d = $conn->prepare("DELETE FROM step_increment WHERE id=? AND employee_id=?");
        $d->bind_param("ii", $recId, $employeeId);
        if ($d->execute()) { $alert = 'Record deleted.'; $alertType = 'success'; }
        else { $alert = 'Error: ' . $conn->error; $alertType = 'danger'; }
        $d->close();
    }

    header("Location: step_inc_history.php?employee_id=$employeeId&msg=" . urlencode($alert) . "&type=$alertType");
    exit;
}

if (isset($_GET['msg'])) { $alert = sanitize($_GET['msg']); $alertType = sanitize($_GET['type']); }

// ── Load DB records — active first, then descending step number ───────────────
$records = $conn->query("
    SELECT si.*,
           p.position_title,
           p.salary_grade AS pos_sg,
           ep.salary_grade AS emp_sg
    FROM step_increment si
    LEFT JOIN positions p  ON si.position_id = p.id
    LEFT JOIN employees e  ON si.employee_id = e.id
    LEFT JOIN positions ep ON e.position_id  = ep.id
    WHERE si.employee_id = $employeeId
    ORDER BY
        CASE WHEN si.is_active = 'Y' THEN 0 ELSE 1 END ASC,
        si.step_inc DESC
");
$dbRows = [];
while ($r = $records->fetch_assoc()) {
    $sg = $r['pos_sg'] ?: $r['emp_sg'];
    $r['sg'] = $sg;
    if ($sg) {
        $sq2 = $conn->prepare("SELECT salary_code, salary_rate FROM salary WHERE salary_grade = ? AND step_no = ? LIMIT 1");
        $sq2->bind_param("si", $sg, $r['step_inc']);
        $sq2->execute();
        $sr2 = $sq2->get_result()->fetch_assoc();
        $sq2->close();
        $r['salary_code'] = $sr2 ? $sr2['salary_code'] : null;
        $r['salary_rate'] = $sr2 ? $sr2['salary_rate'] : null;
    } else {
        $r['salary_code'] = null;
        $r['salary_rate'] = null;
    }
    $dbRows[] = $r;
}
$dbSteps = array_column($dbRows, 'step_inc');

// ── Dropdowns ─────────────────────────────────────────────────────────────────
$salaryList = $conn->query("SELECT salary_id, salary_code, salary_rate, salary_grade, step_no FROM salary ORDER BY salary_grade+0, step_no");
$posListRes = $conn->query("SELECT id, position_title, salary_grade FROM positions ORDER BY salary_grade+0, position_title");
$posListArr = [];
while ($p = $posListRes->fetch_assoc()) { $posListArr[] = $p; }

require_once 'includes/header.php';
?>

<link rel="stylesheet" href="css/step_inc_history.css">
<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a><span>/</span>
        <a href="employees.php">Employees</a><span>/</span>
        <span>Step Increment History</span>
    </div>
    <h1 class="page-title">Step Increment History</h1>
    <p class="page-subtitle">
        <?php
        $fn = trim($employee['last_name'].', '.$employee['first_name']
            .($employee['middle_name'] ? ' '.substr($employee['middle_name'],0,1).'.' : '')
            .($employee['suffix'] ? ' '.$employee['suffix'] : ''));
        echo htmlspecialchars($fn);
        ?>
        <code style="margin-left:.75rem;"><?php echo htmlspecialchars($employee['employee_id']); ?></code>
    </p>
</div>

<?php if ($alert): ?>
<div class="alert alert-<?php echo htmlspecialchars($alertType); ?>" style="margin-bottom:1rem;">
    <i class="alert-icon fas fa-<?php echo $alertType==='success'?'check-circle':($alertType==='warning'?'exclamation-triangle':'exclamation-circle'); ?>"></i>
    <div class="alert-content"><?php echo htmlspecialchars($alert); ?></div>
</div>
<?php endif; ?>

<!-- Employee Summary -->
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-body">
        <div class="emp-grid">
            <div>
                <div class="emp-label">Department</div>
                <div class="emp-value">
                    <?php if ($employee['department_name']): ?>
                        <span class="badge badge-primary"><?php echo htmlspecialchars($employee['department_code']); ?></span>
                        <?php echo htmlspecialchars($employee['department_name']); ?>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </div>
            </div>
            <div>
                <div class="emp-label">Position</div>
                <div class="emp-value">
                    <?php echo $employee['position_title'] ? htmlspecialchars($employee['position_title']) : '<span class="text-muted">—</span>'; ?>
                </div>
            </div>
            <div>
                <div class="emp-label">Salary Grade</div>
                <div class="emp-value">
                    <?php if ($employee['salary_grade']): ?>
                        <span class="badge badge-info" style="font-size:.9rem;padding:.35em .75em;">SG-<?php echo $employee['salary_grade']; ?></span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </div>
            </div>
            <div>
                <div class="emp-label">Date Hired</div>
                <div class="emp-value">
                    <?php echo $employee['date_hired'] ? '<strong>'.date('F d, Y', strtotime($employee['date_hired'])).'</strong>' : '<span class="text-muted">—</span>'; ?>
                </div>
            </div>
            <div>
                <div class="emp-label">Years of Service</div>
                <div class="emp-value">
                    <?php if ($employee['date_hired']): ?>
                        <strong style="color:var(--primary);font-size:1.1rem;"><?php echo $calc['years']; ?></strong> yr<?php echo $calc['years']!=1?'s':''; ?>
                        <?php if ($calc['months']): ?> <strong><?php echo $calc['months']; ?></strong> mo<?php echo $calc['months']!=1?'s':''; ?><?php endif; ?>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </div>
            </div>
            <div>
                <div class="emp-label">Step Inc (Calculated)</div>
                <div class="emp-value">
                    <?php if ($calc['current']): ?>
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:#e0f2fe;border:2px solid #bae6fd;color:#0369a1;font-weight:800;font-size:.95rem;">
                            <?php echo $calc['current']; ?>
                        </span>
                        <small class="text-muted" style="margin-left:.4rem;">from date hired</small>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </div>
            </div>
            <div>
                <div class="emp-label">Step Inc (Active / DB)</div>
                <div class="emp-value">
                    <?php
                    $activeStepQ = $conn->prepare("SELECT si.step_inc FROM step_increment si WHERE si.employee_id = ? AND si.is_active = 'Y' LIMIT 1");
                    $activeStepQ->bind_param("i", $employeeId);
                    $activeStepQ->execute();
                    $activeStepRow = $activeStepQ->get_result()->fetch_assoc();
                    $activeStepQ->close();

                    if ($activeStepRow):
                        $activeSalaryRate = null;
                        if ($employee['salary_grade']) {
                            $rq = $conn->prepare("SELECT salary_rate FROM salary WHERE salary_grade = ? AND step_no = ? LIMIT 1");
                            $rq->bind_param("si", $employee['salary_grade'], $activeStepRow['step_inc']);
                            $rq->execute();
                            $rr = $rq->get_result()->fetch_assoc();
                            if ($rr) $activeSalaryRate = $rr['salary_rate'];
                            $rq->close();
                        }
                    ?>
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#d97706);border:2px solid #f59e0b;color:#fff;font-weight:800;font-size:.95rem;">
                            <?php echo $activeStepRow['step_inc']; ?>
                        </span>
                        <?php if ($activeSalaryRate): ?>
                            <small style="margin-left:.4rem;color:#059669;font-weight:600;"><?php echo formatCurrency($activeSalaryRate); ?>/mo</small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">— not set</span>
                        <br><small class="text-muted">Use Sync or Add Record</small>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div class="emp-label">Next Increment</div>
                <div class="emp-value">
                    <?php if ($calc['current'] && $calc['current'] < 8):
                        $nextD = (new DateTime($employee['date_hired']))->modify('+'.($calc['current']*3).' years');
                        $dLeft = (new DateTime())->diff($nextD)->days;
                        echo '<strong style="color:var(--warning);">'.$nextD->format('F d, Y').'</strong>';
                        echo "<br><small class='text-muted'>{$dLeft} day(s) away</small>";
                    elseif ($calc['current'] >= 8): ?>
                        <span class="badge badge-info">Maximum Step Reached</span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sync Banner -->
<?php if ($employee['date_hired'] && $employee['salary_grade']): ?>
<div class="sync-bar">
    <div>
        <strong style="color:#1d4ed8;"><i class="fas fa-sync-alt"></i> Auto-Sync Available</strong><br>
        <small style="color:#3b82f6;">
            <?php echo count($calc['effectiveDates']); ?> step(s) calculated from date hired.
            Sync writes them all to the <code>step_increment</code> table (safe to run multiple times).
        </small>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="sync">
        <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Sync Steps to DB</button>
    </form>
</div>
<?php endif; ?>

<!-- History Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-history"></i> Step Increment Log
            <small class="text-muted" style="font-size:.78rem;font-weight:400;margin-left:.5rem;">— <code>step_increment</code> table</small>
        </h2>
        <button class="btn btn-primary btn-sm" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Promote
        </button>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="data-table" style="width:100%;">
            <thead>
                <tr>
                    <th>Step</th>
                    <th>Salary Code</th>
                    <th>Grade</th>
                    <th>Monthly Rate</th>
                    <th>Position on Record</th>
                    <th>Effective Date</th>
                    <th>Date Logged</th>
                    <th>Last Updated</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($dbRows)): ?>
                <tr>
                    <td colspan="10" class="text-center text-muted" style="padding:3rem;">
                        <i class="fas fa-database" style="font-size:2rem;opacity:.25;display:block;margin-bottom:.75rem;"></i>
                        <strong>No records in the database yet.</strong><br>
                        <small>Use <em>Sync Steps to DB</em> to auto-populate, or click <em>Add Record</em> to log manually.</small>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($dbRows as $row):
                    $effDate  = $calc['effectiveDates'][$row['step_inc']] ?? null;
                    $isActive = $row['is_active'] === 'Y';
                ?>
                <tr class="<?php echo $isActive ? 'row-active' : ''; ?>">
                    <td>
                        <div style="display:flex;align-items:center;gap:.6rem;">
                            <div class="step-circle <?php echo $isActive?'is-active':''; ?>"><?php echo $row['step_inc']; ?></div>
                            <?php if ($isActive): ?><span style="font-size:.65rem;font-weight:700;color:#d97706;text-transform:uppercase;">Active</span><?php endif; ?>
                        </div>
                    </td>
                    <td><?php echo $row['salary_code'] ? '<code>'.htmlspecialchars($row['salary_code']).'</code>' : '<span class="text-muted">—</span>'; ?></td>
                    <td><?php echo $row['sg'] ? '<span class="badge badge-info">SG-'.htmlspecialchars($row['sg']).'</span>' : '<span class="text-muted">—</span>'; ?></td>
                    <td><?php echo $row['salary_rate'] ? '<strong style="color:var(--success);">'.formatCurrency($row['salary_rate']).'</strong>' : '<span class="text-muted">—</span>'; ?></td>
                    <td><?php echo $row['position_title'] ? htmlspecialchars($row['position_title']) : '<span class="text-muted">—</span>'; ?></td>
                    <td>
                        <?php if ($effDate): ?>
                            <strong><?php echo date('M d, Y', strtotime($effDate)); ?></strong><br>
                            <small class="text-muted"><?php
                                $d2 = (new DateTime($effDate))->diff(new DateTime());
                                if ($d2->y)      echo $d2->y.' yr(s) ago';
                                elseif ($d2->m)  echo $d2->m.' mo(s) ago';
                                elseif ($d2->d)  echo $d2->d.' day(s) ago';
                                else             echo 'Today';
                            ?></small>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td><small><?php echo date('M d, Y', strtotime($row['created_at'])); ?></small><br><small class="text-muted"><?php echo date('g:i A', strtotime($row['created_at'])); ?></small></td>
                    <td><small><?php echo date('M d, Y', strtotime($row['updated_at'])); ?></small><br><small class="text-muted"><?php echo date('g:i A', strtotime($row['updated_at'])); ?></small></td>
                    <td>
                        <?php if ($isActive): ?>
                            <span class="badge badge-success"><i class="fas fa-star"></i> Current</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Previous</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <?php if (!$isActive): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="set_active">
                                <input type="hidden" name="record_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="action-btn abtn-warn">Set Active</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete Step <?php echo $row['step_inc']; ?> record?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="record_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="action-btn abtn-danger">Del</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($dbRows)): ?>
    <div style="padding:.9rem 1.4rem;background:var(--gray-50);border-top:1px solid var(--gray-200);display:flex;justify-content:space-between;flex-wrap:wrap;gap:.5rem;font-size:.82rem;color:var(--gray-600);">
        <span><i class="fas fa-database"></i> <?php echo count($dbRows); ?> record(s) stored · <code>step_increment</code> table</span>
        <span><i class="fas fa-info-circle"></i> Step increases every 3 years · Maximum Step 8 (21+ years)</span>
    </div>
    <?php endif; ?>
</div>

<!-- Add Record Modal -->
<div id="addModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:14px; width:100%; max-width:520px; margin:auto; box-shadow:0 20px 60px rgba(0,0,0,0.4); overflow:hidden; position:relative; z-index:100000;">
        <!-- Header -->
        <div style="background:#1a3a5c; padding:1.1rem 1.4rem; display:flex; align-items:center; justify-content:space-between;">
            <span style="font-size:1rem; font-weight:700; color:#fff;"><i class="fas fa-plus-circle" style="margin-right:7px;"></i>Add / Update Step Record</span>
            <button type="button" onclick="closeAddModal()" style="background:rgba(255,255,255,0.15); border:none; width:30px; height:30px; border-radius:6px; color:#fff; cursor:pointer; font-size:1rem; line-height:1;">&#10005;</button>
        </div>
        <!-- Form -->
        <form method="POST" id="addStepForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="salary_id" id="hidden_salary_id">
            <div style="padding:1.25rem 1.4rem; display:flex; flex-direction:column; gap:1rem;">

                <!-- Status -->
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:700; margin-bottom:5px; color:#374151;">Status <span style="color:#ef4444;">*</span></label>
                    <select name="is_active" required style="width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:7px; font-size:0.9rem; color:#111827;">
                        <option value="N">Previous</option>
                        <option value="Y">Active / Current</option>
                    </select>
                </div>

                <!-- Position — choosing this drives the SG dropdown -->
                <div>
                    <label style="display:block; font-size:0.8rem; font-weight:700; margin-bottom:5px; color:#374151;">Position</label>
                    <select name="position_id" id="modal_position" onchange="onPositionChange(this)"
                            style="width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:7px; font-size:0.9rem; color:#111827;">
                        <option value="">— None —</option>
                        <?php foreach ($posListArr as $p): ?>
                        <option value="<?php echo $p['id']; ?>"
                                data-sg="<?php echo htmlspecialchars($p['salary_grade']); ?>"
                                <?php echo $p['id'] == $employee['pos_id'] ? 'selected' : ''; ?>>
                            SG-<?php echo $p['salary_grade']; ?> | <?php echo htmlspecialchars($p['position_title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:#6b7280; font-size:.75rem;">Selecting a position will auto-fill the Salary Grade below.</small>
                </div>

                <!-- Salary Grade + Step (side by side) -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div>
                        <label style="display:block; font-size:0.8rem; font-weight:700; margin-bottom:5px; color:#374151;">Salary Grade <span style="color:#ef4444;">*</span></label>
                        <select id="modal_sg" onchange="onSGChange()"
                                style="width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:7px; font-size:0.9rem; color:#111827;">
                            <option value="">— Select SG —</option>
                            <?php
                            $sgList = $conn->query("SELECT DISTINCT salary_grade FROM salary ORDER BY salary_grade+0");
                            while ($sg = $sgList->fetch_assoc()):
                            ?>
                            <option value="<?php echo htmlspecialchars($sg['salary_grade']); ?>"
                                <?php echo $sg['salary_grade'] == $employee['salary_grade'] ? 'selected' : ''; ?>>
                                SG-<?php echo htmlspecialchars($sg['salary_grade']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:0.8rem; font-weight:700; margin-bottom:5px; color:#374151;">Step # <span style="color:#ef4444;">*</span></label>
                        <select name="step_inc" id="modal_step" required onchange="onStepChange()"
                                style="width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:7px; font-size:0.9rem; color:#111827;">
                            <option value="">— Select SG first —</option>
                        </select>
                    </div>
                </div>

                <!-- Salary Rate preview (read-only) -->
                <div id="salaryPreviewBox" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:.75rem 1rem;">
                    <div style="font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#15803d; margin-bottom:.2rem;">Monthly Salary Rate</div>
                    <div id="salaryPreviewVal" style="font-size:1.2rem; font-weight:800; color:#15803d;"></div>
                    <div id="salaryCodeVal" style="font-size:.75rem; color:#6b7280; margin-top:.15rem;"></div>
                </div>

            </div>
            <!-- Footer -->
            <div style="padding:0.9rem 1.4rem; background:#f9fafb; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:0.6rem;">
                <button type="button" onclick="closeAddModal()" style="padding:0.45rem 1.1rem; border:2px solid #d1d5db; background:#fff; border-radius:8px; font-weight:600; cursor:pointer; color:#374151; font-size:0.875rem;">Cancel</button>
                <button type="submit" style="padding:0.45rem 1.25rem; background:#1a3a5c; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer; font-size:0.875rem;"><i class="fas fa-save" style="margin-right:5px;"></i>Save Record</button>
            </div>
        </form>
    </div>
</div>

<!-- Salary data for JS -->
<?php
$allSalaries = $conn->query("SELECT salary_id, salary_grade, step_no, salary_rate, salary_code FROM salary ORDER BY salary_grade+0, step_no");
$salaryData = [];
while ($row = $allSalaries->fetch_assoc()) { $salaryData[] = $row; }
?>
<script>
var salaryData = <?php echo json_encode($salaryData); ?>;
var dbSteps    = <?php echo json_encode($dbSteps); ?>;

function openAddModal() {
    var el = document.getElementById('addModal');
    if (el) { el.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
    onSGChange(); // pre-load steps if SG already selected
}
function closeAddModal() {
    var el = document.getElementById('addModal');
    if (el) { el.style.display = 'none'; document.body.style.overflow = ''; }
}
document.addEventListener('click', function(e) {
    var el = document.getElementById('addModal');
    if (el && e.target === el) closeAddModal();
});

// Selecting a position auto-fills the Salary Grade, then reloads steps
function onPositionChange(sel) {
    var opt = sel.options[sel.selectedIndex];
    var sg  = opt ? opt.getAttribute('data-sg') : null;
    if (!sg) return;
    var sgSel = document.getElementById('modal_sg');
    sgSel.value = sg;
    onSGChange();
}

// Reload step dropdown whenever SG changes
function onSGChange() {
    var sg      = document.getElementById('modal_sg').value;
    var stepSel = document.getElementById('modal_step');
    var preview = document.getElementById('salaryPreviewBox');

    stepSel.innerHTML = '<option value="">— Select Step —</option>';
    preview.style.display = 'none';
    document.getElementById('hidden_salary_id').value = '';

    if (!sg) { stepSel.innerHTML = '<option value="">— Select SG first —</option>'; return; }

    var steps = salaryData.filter(function(s) { return s.salary_grade == sg; });
    steps.forEach(function(s) {
        var exists = dbSteps.indexOf(parseInt(s.step_no)) !== -1;
        var opt = document.createElement('option');
        opt.value = s.step_no;
        opt.textContent = 'Step ' + s.step_no + (exists ? ' (exists)' : '');
        opt.dataset.salaryId   = s.salary_id;
        opt.dataset.salaryRate = s.salary_rate;
        opt.dataset.salaryCode = s.salary_code;
        stepSel.appendChild(opt);
    });
}

// Show salary preview when a step is picked
function onStepChange() {
    var stepSel = document.getElementById('modal_step');
    var sel     = stepSel.options[stepSel.selectedIndex];
    var preview = document.getElementById('salaryPreviewBox');

    if (!sel || !sel.dataset.salaryId) {
        preview.style.display = 'none';
        document.getElementById('hidden_salary_id').value = '';
        return;
    }

    document.getElementById('hidden_salary_id').value      = sel.dataset.salaryId;
    document.getElementById('salaryPreviewVal').textContent = '₱ ' + parseFloat(sel.dataset.salaryRate).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('salaryCodeVal').textContent   = sel.dataset.salaryCode;
    preview.style.display = 'block';
}
</script>

<?php require_once 'includes/footer.php'; ?>