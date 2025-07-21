// Password Validation System with Real-time Feedback
// Requirements: Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special character

class PasswordValidator {
    constructor() {
        this.requirements = {
            minLength: 8,
            hasUppercase: /[A-Z]/,
            hasLowercase: /[a-z]/,
            hasNumber: /\d/,
            hasSpecialChar: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/
        };
        
        this.strengthLevels = {
            weak: { score: 0, color: '#ef4444', text: 'Weak' },
            fair: { score: 1, color: '#f59e0b', text: 'Fair' },
            good: { score: 2, color: '#3b82f6', text: 'Good' },
            strong: { score: 3, color: '#10b981', text: 'Strong' },
            excellent: { score: 4, color: '#059669', text: 'Excellent' }
        };
        
        this.init();
    }
    
    init() {
        // Initialize all password fields
        const passwordFields = document.querySelectorAll('input[type="password"]');
        passwordFields.forEach(field => this.setupPasswordField(field));
        
        // Initialize confirm password fields
        this.setupConfirmPasswordValidation();
    }
    
    setupPasswordField(passwordField) {
        const container = this.createPasswordContainer(passwordField);
        
        // Add real-time validation
        passwordField.addEventListener('input', (e) => {
            this.validatePassword(e.target.value, container);
        });
        
        passwordField.addEventListener('focus', () => {
            this.showPasswordHelp(container);
        });
        
        passwordField.addEventListener('blur', () => {
            this.hidePasswordHelp(container);
        });
    }
    
    createPasswordContainer(passwordField) {
        // Create wrapper container
        const wrapper = document.createElement('div');
        wrapper.className = 'password-field-wrapper';
        
        // Wrap the password field
        passwordField.parentNode.insertBefore(wrapper, passwordField);
        wrapper.appendChild(passwordField);
        
        // Add show/hide toggle
        const toggleContainer = document.createElement('div');
        toggleContainer.className = 'password-toggle-container';
        
        const toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.className = 'password-toggle-btn';
        toggleButton.innerHTML = '<i class="fas fa-eye"></i>';
        toggleButton.title = 'Toggle password visibility';
        
        toggleButton.addEventListener('click', () => {
            this.togglePasswordVisibility(passwordField, toggleButton);
        });
        
        toggleContainer.appendChild(toggleButton);
        wrapper.appendChild(toggleContainer);
        
        // Add strength indicator
        const strengthIndicator = document.createElement('div');
        strengthIndicator.className = 'password-strength-indicator';
        
        const strengthBar = document.createElement('div');
        strengthBar.className = 'password-strength-bar';
        
        const strengthText = document.createElement('div');
        strengthText.className = 'password-strength-text';
        
        strengthIndicator.appendChild(strengthBar);
        strengthIndicator.appendChild(strengthText);
        wrapper.appendChild(strengthIndicator);
        
        // Add requirements checklist
        const requirementsContainer = document.createElement('div');
        requirementsContainer.className = 'password-requirements';
        requirementsContainer.style.display = 'none';
        
        const requirementsList = document.createElement('ul');
        requirementsList.className = 'password-requirements-list';
        
        const requirements = [
            { key: 'minLength', text: 'At least 8 characters' },
            { key: 'hasUppercase', text: 'One uppercase letter (A-Z)' },
            { key: 'hasLowercase', text: 'One lowercase letter (a-z)' },
            { key: 'hasNumber', text: 'One number (0-9)' },
            { key: 'hasSpecialChar', text: 'One special character (!@#$%^&*)' }
        ];
        
        requirements.forEach(req => {
            const li = document.createElement('li');
            li.className = `requirement-${req.key}`;
            li.innerHTML = `<i class="fas fa-times"></i> ${req.text}`;
            requirementsList.appendChild(li);
        });
        
        requirementsContainer.appendChild(requirementsList);
        wrapper.appendChild(requirementsContainer);
        
        return {
            wrapper,
            passwordField,
            strengthBar,
            strengthText,
            requirementsContainer,
            requirementsList
        };
    }
    
    validatePassword(password, container) {
        const results = this.checkPassword(password);
        this.updateStrengthIndicator(results, container);
        this.updateRequirements(results, container);
        
        // Add validation classes
        if (results.isValid) {
            container.passwordField.classList.remove('password-invalid');
            container.passwordField.classList.add('password-valid');
        } else {
            container.passwordField.classList.remove('password-valid');
            container.passwordField.classList.add('password-invalid');
        }
        
        return results.isValid;
    }
    
    checkPassword(password) {
        const results = {
            score: 0,
            isValid: false,
            requirements: {}
        };
        
        // Check each requirement
        results.requirements.minLength = password.length >= this.requirements.minLength;
        results.requirements.hasUppercase = this.requirements.hasUppercase.test(password);
        results.requirements.hasLowercase = this.requirements.hasLowercase.test(password);
        results.requirements.hasNumber = this.requirements.hasNumber.test(password);
        results.requirements.hasSpecialChar = this.requirements.hasSpecialChar.test(password);
        
        // Calculate score
        Object.values(results.requirements).forEach(passed => {
            if (passed) results.score++;
        });
        
        // Check if password is valid (all requirements met)
        results.isValid = Object.values(results.requirements).every(req => req);
        
        // Additional complexity checks for strength
        if (password.length >= 12) results.score += 0.5;
        if (password.length >= 16) results.score += 0.5;
        if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]{2,}/.test(password)) results.score += 0.5;
        if (/\d{2,}/.test(password)) results.score += 0.5;
        
        return results;
    }
    
    updateStrengthIndicator(results, container) {
        const { score } = results;
        let level, width;
        
        if (score < 2) {
            level = this.strengthLevels.weak;
            width = '20%';
        } else if (score < 3) {
            level = this.strengthLevels.fair;
            width = '40%';
        } else if (score < 4) {
            level = this.strengthLevels.good;
            width = '60%';
        } else if (score < 5) {
            level = this.strengthLevels.strong;
            width = '80%';
        } else {
            level = this.strengthLevels.excellent;
            width = '100%';
        }
        
        container.strengthBar.style.width = width;
        container.strengthBar.style.backgroundColor = level.color;
        container.strengthText.textContent = level.text;
        container.strengthText.style.color = level.color;
    }
    
    updateRequirements(results, container) {
        Object.entries(results.requirements).forEach(([key, passed]) => {
            const requirement = container.requirementsList.querySelector(`.requirement-${key}`);
            if (requirement) {
                const icon = requirement.querySelector('i');
                if (passed) {
                    requirement.classList.add('requirement-passed');
                    requirement.classList.remove('requirement-failed');
                    icon.className = 'fas fa-check';
                } else {
                    requirement.classList.add('requirement-failed');
                    requirement.classList.remove('requirement-passed');
                    icon.className = 'fas fa-times';
                }
            }
        });
    }
    
    showPasswordHelp(container) {
        container.requirementsContainer.style.display = 'block';
    }
    
    hidePasswordHelp(container) {
        // Only hide if password is valid
        const password = container.passwordField.value;
        const results = this.checkPassword(password);
        if (results.isValid) {
            container.requirementsContainer.style.display = 'none';
        }
    }
    
    togglePasswordVisibility(passwordField, toggleButton) {
        const icon = toggleButton.querySelector('i');
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            icon.className = 'fas fa-eye-slash';
            toggleButton.title = 'Hide password';
        } else {
            passwordField.type = 'password';
            icon.className = 'fas fa-eye';
            toggleButton.title = 'Show password';
        }
    }
    
    setupConfirmPasswordValidation() {
        const confirmFields = document.querySelectorAll('input[name="confirm_password"], input[name="password_confirmation"]');
        
        confirmFields.forEach(confirmField => {
            const passwordField = this.findPasswordField(confirmField);
            if (passwordField) {
                this.setupPasswordMatch(passwordField, confirmField);
            }
        });
    }
    
    findPasswordField(confirmField) {
        // Look for password field in the same form
        const form = confirmField.closest('form');
        if (form) {
            return form.querySelector('input[name="password"]:not([name="confirm_password"]):not([name="password_confirmation"])');
        }
        return null;
    }
    
    setupPasswordMatch(passwordField, confirmField) {
        const validateMatch = () => {
            const password = passwordField.value;
            const confirmPassword = confirmField.value;
            
            if (confirmPassword === '') {
                this.clearMatchValidation(confirmField);
                return;
            }
            
            if (password === confirmPassword) {
                this.showMatchSuccess(confirmField);
            } else {
                this.showMatchError(confirmField);
            }
        };
        
        passwordField.addEventListener('input', validateMatch);
        confirmField.addEventListener('input', validateMatch);
    }
    
    showMatchSuccess(confirmField) {
        confirmField.classList.remove('password-match-error');
        confirmField.classList.add('password-match-success');
        
        // Remove existing feedback
        const existingFeedback = confirmField.parentNode.querySelector('.password-match-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
        
        // Add success feedback
        const feedback = document.createElement('div');
        feedback.className = 'password-match-feedback password-match-success-text';
        feedback.innerHTML = '<i class="fas fa-check"></i> Passwords match';
        confirmField.parentNode.appendChild(feedback);
    }
    
    showMatchError(confirmField) {
        confirmField.classList.remove('password-match-success');
        confirmField.classList.add('password-match-error');
        
        // Remove existing feedback
        const existingFeedback = confirmField.parentNode.querySelector('.password-match-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
        
        // Add error feedback
        const feedback = document.createElement('div');
        feedback.className = 'password-match-feedback password-match-error-text';
        feedback.innerHTML = '<i class="fas fa-times"></i> Passwords do not match';
        confirmField.parentNode.appendChild(feedback);
    }
    
    clearMatchValidation(confirmField) {
        confirmField.classList.remove('password-match-success', 'password-match-error');
        
        const existingFeedback = confirmField.parentNode.querySelector('.password-match-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
    }
    
    // Public method to validate a password programmatically
    isPasswordValid(password) {
        const results = this.checkPassword(password);
        return results.isValid;
    }
    
    // Public method to get password strength
    getPasswordStrength(password) {
        const results = this.checkPassword(password);
        
        if (results.score < 2) return this.strengthLevels.weak;
        if (results.score < 3) return this.strengthLevels.fair;
        if (results.score < 4) return this.strengthLevels.good;
        if (results.score < 5) return this.strengthLevels.strong;
        return this.strengthLevels.excellent;
    }
}

// Auto-initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.passwordValidator = new PasswordValidator();
});

// Form validation enhancement
document.addEventListener('submit', (e) => {
    const form = e.target;
    const passwordFields = form.querySelectorAll('input[type="password"][name="password"]');
    
    let hasInvalidPassword = false;
    
    passwordFields.forEach(field => {
        if (!window.passwordValidator.isPasswordValid(field.value)) {
            hasInvalidPassword = true;
            
            // Show error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger';
            errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Password does not meet security requirements. Please use at least 8 characters with uppercase, lowercase, number, and special character.';
            
            // Remove existing error if any
            const existingError = form.querySelector('.password-validation-error');
            if (existingError) {
                existingError.remove();
            }
            
            errorDiv.className += ' password-validation-error';
            form.insertBefore(errorDiv, form.firstChild);
            
            // Scroll to error
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
    
    if (hasInvalidPassword) {
        e.preventDefault();
        return false;
    }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PasswordValidator;
}