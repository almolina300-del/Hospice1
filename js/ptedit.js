
function validateDepartment() {
    var input = document.getElementById('departmentInput');
    var datalist = document.getElementById('departmentList');
    var options = datalist.getElementsByTagName('option');
    var inputValue = input.value.trim().toUpperCase();
    var isValid = false;
    var errorMsg = document.getElementById('deptError');
    
    // Check if input matches any option (case-insensitive)
    for (var i = 0; i < options.length; i++) {
        var optionValue = options[i].value.trim().toUpperCase();
        if (optionValue === inputValue) {
            isValid = true;
            break;
        }
    }
    
    // If empty value is not allowed
    if (inputValue === '') {
        isValid = false;
    }
    
    // Show/hide error message
    if (!errorMsg) {
        errorMsg = document.createElement('div');
        errorMsg.id = 'deptError';
        errorMsg.style.color = 'red';
        errorMsg.style.fontSize = '12px';
        errorMsg.style.marginTop = '5px';
        input.parentNode.appendChild(errorMsg);
    }
    
    if (!isValid && inputValue !== '') {
        errorMsg.textContent = '❌ Please select a valid department from the list';
        input.style.borderColor = 'red';
        input.style.backgroundColor = '#fff0f0';
        return false;
    } else {
        errorMsg.textContent = '';
        input.style.borderColor = '#ccc';
        input.style.backgroundColor = '#fff';
        return true;
    }
}

// Real-time validation as user types
document.addEventListener('DOMContentLoaded', function() {
    var deptInput = document.getElementById('departmentInput');
    
    if (deptInput) {
        // Validate on input
        deptInput.addEventListener('input', function() {
            validateDepartment();
        });
        
        // Validate on blur
        deptInput.addEventListener('blur', function() {
            validateDepartment();
        });
    }
    
    // Validate on form submission
    var form = deptInput ? deptInput.closest('form') : null;
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateDepartment()) {
                e.preventDefault();
                alert('Please select a valid department from the list.');
            }
        });
    }
});
