/**
 * Payroll System - Main JavaScript
 * City Mayor's Office Payroll Management
 */

// Global Configuration
const CONFIG = {
    PERA_AMOUNT: 2000.00,
    GSIS_RATE: 0.09,
    PHILHEALTH_RATE: 0.025,
    PAGIBIG_RATE: 200.00
};

// Toast Notification System
const Toast = {
    container: null,
    
    init() {
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    },
    
    show(message, type = 'success', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle'
        };
        
        toast.innerHTML = `
            <i class="${icons[type] || icons.success}"></i>
            <span>${message}</span>
        `;
        
        this.container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideIn 0.3s ease reverse';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },
    
    success(message) { this.show(message, 'success'); },
    error(message) { this.show(message, 'error'); },
    warning(message) { this.show(message, 'warning'); }
};

// Modal Handler
const Modal = {
    open(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    },
    
    close(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    },
    
    closeAll() {
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.classList.remove('active');
        });
        document.body.style.overflow = '';
    }
};

// Format currency
function formatCurrency(amount) {
    return '₱ ' + parseFloat(amount).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Parse currency string to number
function parseCurrency(str) {
    return parseFloat(str.replace(/[₱,\s]/g, '')) || 0;
}

// Calculate payroll deductions
function calculateDeductions(basicSalary) {
    const basic = parseFloat(basicSalary) || 0;
    
    // GSIS - 9% of basic salary
    const gsis = basic * CONFIG.GSIS_RATE;
    
    // PhilHealth - 2.5% of basic salary (employee share)
    let philhealth = basic * CONFIG.PHILHEALTH_RATE;
    philhealth = Math.min(philhealth, 5000); // Max cap
    
    // Pag-IBIG - Fixed 200 for most employees
    const pagibig = basic > 5000 ? 200 : (basic > 1500 ? basic * 0.02 : basic * 0.01);
    
    // PERA (Personnel Economic Relief Allowance)
    const pera = CONFIG.PERA_AMOUNT;
    
    // Gross Pay
    const grossPay = basic + pera;
    
    // Taxable income
    const taxableIncome = grossPay - gsis - philhealth - pagibig;
    
    // Withholding Tax
    const wtax = calculateWithholdingTax(taxableIncome);
    
    return {
        basicSalary: basic,
        pera: pera,
        grossPay: grossPay,
        gsis: gsis,
        philhealth: philhealth,
        pagibig: pagibig,
        wtax: wtax
    };
}

// Calculate withholding tax based on Philippine tax brackets
function calculateWithholdingTax(taxableIncome) {
    const income = parseFloat(taxableIncome) || 0;
    
    if (income <= 20833) {
        return 0;
    } else if (income <= 33333) {
        return (income - 20833) * 0.15;
    } else if (income <= 66667) {
        return 1875 + (income - 33333) * 0.20;
    } else if (income <= 166667) {
        return 8541.80 + (income - 66667) * 0.25;
    } else if (income <= 666667) {
        return 33541.80 + (income - 166667) * 0.30;
    } else {
        return 183541.80 + (income - 666667) * 0.35;
    }
}

// Update payroll calculations in real-time
function updatePayrollCalculations() {
    const basicSalary = document.getElementById('basic_salary')?.value || 0;
    const deductions = calculateDeductions(basicSalary);
    
    // Update display fields
    const fields = ['gsis', 'philhealth', 'pagibig', 'wtax', 'pera', 'grossPay'];
    fields.forEach(field => {
        const element = document.getElementById(`calc_${field}`);
        if (element) {
            element.textContent = formatCurrency(deductions[field] || 0);
        }
    });
    
    // Get additional deductions
    const provident = parseFloat(document.getElementById('provident')?.value) || 0;
    const bcgeu = parseFloat(document.getElementById('bcgeu')?.value) || 0;
    const nocgem = parseFloat(document.getElementById('nocgem')?.value) || 0;
    const bacgem = parseFloat(document.getElementById('bacgem')?.value) || 0;
    const others = parseFloat(document.getElementById('other_deductions')?.value) || 0;
    
    // Calculate total deductions
    const totalDeductions = deductions.gsis + deductions.philhealth + deductions.pagibig + 
                           deductions.wtax + provident + bcgeu + nocgem + bacgem + others;
    
    // Calculate net pay
    const netPay = deductions.grossPay - totalDeductions;
    
    // Update totals
    const totalDeductionsEl = document.getElementById('calc_totalDeductions');
    const netPayEl = document.getElementById('calc_netPay');
    
    if (totalDeductionsEl) totalDeductionsEl.textContent = formatCurrency(totalDeductions);
    if (netPayEl) netPayEl.textContent = formatCurrency(netPay);
    
    // Update hidden fields for form submission
    updateHiddenField('gsis_amount', deductions.gsis);
    updateHiddenField('philhealth_amount', deductions.philhealth);
    updateHiddenField('pagibig_amount', deductions.pagibig);
    updateHiddenField('wtax_amount', deductions.wtax);
    updateHiddenField('gross_pay', deductions.grossPay);
    updateHiddenField('total_deductions', totalDeductions);
    updateHiddenField('net_pay', netPay);
}

function updateHiddenField(id, value) {
    const field = document.getElementById(id);
    if (field) field.value = value.toFixed(2);
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        field.classList.remove('error');
        const errorMsg = field.parentElement.querySelector('.form-error');
        if (errorMsg) errorMsg.remove();
        
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('error');
            
            const error = document.createElement('span');
            error.className = 'form-error';
            error.textContent = 'This field is required';
            field.parentElement.appendChild(error);
        }
    });
    
    return isValid;
}

// AJAX Form Submission
async function submitForm(formId, url, successCallback) {
    if (!validateForm(formId)) {
        Toast.error('Please fill in all required fields');
        return;
    }
    
    const form = document.getElementById(formId);
    const formData = new FormData(form);
    
    try {
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Toast.success(result.message || 'Operation successful');
            if (successCallback) successCallback(result);
        } else {
            Toast.error(result.message || 'Operation failed');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('An error occurred. Please try again.');
    }
}

// Delete confirmation
function confirmDelete(id, type, callback) {
    if (confirm(`Are you sure you want to delete this ${type}? This action cannot be undone.`)) {
        if (callback) callback(id);
    }
}

// Search and filter table
function filterTable(tableId, searchValue) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    const search = searchValue.toLowerCase();
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
}

// Sidebar toggle for mobile
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
}

function initNavigation() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        const href = item.getAttribute('href') || item.getAttribute('data-page');
        if (href && href === currentPage) {
            item.classList.add('active');
        }
    });
}

// Load employee position/salary on select
async function loadEmployeeDetails(employeeId) {
    if (!employeeId) return;
    
    try {
        const response = await fetch(`api/get_employee.php?id=${employeeId}`);
        const data = await response.json();
        
        if (data.success && data.employee) {
            const emp = data.employee;
            
            // Update form fields
            if (document.getElementById('basic_salary')) {
                document.getElementById('basic_salary').value = emp.basic_salary || 0;
            }
            if (document.getElementById('position_display')) {
                document.getElementById('position_display').textContent = emp.position_title || 'N/A';
            }
            if (document.getElementById('department_display')) {
                document.getElementById('department_display').textContent = emp.department_name || 'N/A';
            }
            
            // Recalculate deductions
            updatePayrollCalculations();
        }
    } catch (error) {
        console.error('Error loading employee details:', error);
    }
}

// Print payroll
function printPayroll(payrollId) {
    window.open(`print_payroll.php?id=${payrollId}`, '_blank', 'width=800,height=600');
}

// Export to Excel
function exportToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            // Clean the text content
            let text = col.textContent.replace(/"/g, '""').trim();
            rowData.push(`"${text}"`);
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `${filename}_${new Date().toISOString().slice(0,10)}.csv`;
    link.click();
}

// Generate payroll for period
async function generatePayroll() {
    const month = document.getElementById('payroll_month')?.value;
    const year = document.getElementById('payroll_year')?.value;
    const period = document.getElementById('payroll_period')?.value;
    const department = document.getElementById('payroll_department')?.value;
    
    if (!month || !year || !period) {
        Toast.error('Please select month, year, and period');
        return;
    }
    
    if (!confirm('Generate payroll for all employees in the selected period?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('month', month);
        formData.append('year', year);
        formData.append('period', period);
        formData.append('department', department || '');
        
        const response = await fetch('api/generate_payroll.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Toast.success(result.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            Toast.error(result.message || 'Failed to generate payroll');
        }
    } catch (error) {
        console.error('Error:', error);
        Toast.error('An error occurred while generating payroll');
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize toast system
    Toast.init();
    
    // Initialize navigation
    initNavigation();
    
    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            Modal.closeAll();
        }
    });
    
    // Initialize salary calculations if on payroll form
    const basicSalaryField = document.getElementById('basic_salary');
    if (basicSalaryField) {
        basicSalaryField.addEventListener('input', updatePayrollCalculations);
        
        // Also listen to deduction fields
        ['provident', 'bcgeu', 'nocgem', 'bacgem', 'other_deductions'].forEach(field => {
            const el = document.getElementById(field);
            if (el) el.addEventListener('input', updatePayrollCalculations);
        });
        
        // Initial calculation
        updatePayrollCalculations();
    }
    
    // Employee select change handler
    const employeeSelect = document.getElementById('employee_id');
    if (employeeSelect) {
        employeeSelect.addEventListener('change', function() {
            loadEmployeeDetails(this.value);
        });
    }
    
    // Search functionality
    const searchInputs = document.querySelectorAll('[data-search-table]');
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            filterTable(this.dataset.searchTable, this.value);
        });
    });
    
    // Auto-format currency inputs
    document.querySelectorAll('.currency-input').forEach(input => {
        input.addEventListener('blur', function() {
            const value = parseFloat(this.value.replace(/[^0-9.-]/g, ''));
            if (!isNaN(value)) {
                this.value = value.toFixed(2);
            }
        });
    });
});

// Expose functions globally for inline handlers
window.Modal = Modal;
window.Toast = Toast;
window.confirmDelete = confirmDelete;
window.submitForm = submitForm;
window.filterTable = filterTable;
window.toggleSidebar = toggleSidebar;
window.printPayroll = printPayroll;
window.exportToExcel = exportToExcel;
window.generatePayroll = generatePayroll;
window.loadEmployeeDetails = loadEmployeeDetails;
window.updatePayrollCalculations = updatePayrollCalculations;
