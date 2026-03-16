
// shared client-side scripting for authentication forms

// password requirements: at least 8 characters, max 15, contains a number, letter, uppercase, and special char
function validatePassword(password) {
    const minLength = 8;
    const maxLength = 15;
    const hasNumber = /\d/.test(password);
    const hasLetter = /[a-z]/.test(password);
    const hasUpper = /[A-Z]/.test(password);
    // any character that is not a letter or digit counts as special
    const hasSpecial = /[^A-Za-z0-9]/.test(password);
    return password.length >= minLength && password.length <= maxLength && hasNumber && hasLetter && hasUpper && hasSpecial;
}

function showError(input, message) {
    input.classList.add('input-error');
    const formError = document.getElementById('form-error');
    if (formError) {
        formError.textContent = message;
        formError.classList.add('visible');
    }
}

function clearErrors(form) {
    // remove red borders
    form.querySelectorAll('.input-error').forEach(i => i.classList.remove('input-error'));
    const formError = document.getElementById('form-error');
    if (formError) {
        formError.textContent = '';
        formError.classList.remove('visible');
    }
}

/* password criteria popup helpers */
function createCriteriaPopup() {
    const div = document.createElement('div');
    div.className = 'password-popup';
    div.innerHTML = `
        <ul>
            <li class="length"><span class="status">✖</span> At least 8 characters</li>
            <li class="maxlength"><span class="status">✖</span> Maximum 15 characters</li>
            <li class="number"><span class="status">✖</span> A number</li>
            <li class="letter"><span class="status">✖</span> A lowercase letter</li>
            <li class="upper"><span class="status">✖</span> An uppercase letter</li>
            <li class="special"><span class="status">✖</span> A special character (non-alphanumeric)</li>
        </ul>
    `;
    return div;
}

function updateCriteria(password, popup) {
    const lengthOk = password.length >= 8;
    const maxLengthOk = password.length <= 15;
    const numberOk = /\d/.test(password);
    const letterOk = /[a-z]/.test(password);
    const upperOk = /[A-Z]/.test(password);
    const specialOk = /[^A-Za-z0-9]/.test(password);

    const lengthStatus = popup.querySelector('.length .status');
    const maxLengthStatus = popup.querySelector('.maxlength .status');
    const numberStatus = popup.querySelector('.number .status');
    const letterStatus = popup.querySelector('.letter .status');
    const upperStatus = popup.querySelector('.upper .status');
    const specialStatus = popup.querySelector('.special .status');

    function updateStatus(el, ok) {
        const newChar = ok ? '✔' : '✖';
        if (el.textContent !== newChar) {
            el.textContent = newChar;
            if (ok) {
                el.classList.add('animate');
                setTimeout(() => el.classList.remove('animate'), 300);
            }
        }
    }

    updateStatus(lengthStatus, lengthOk);
    updateStatus(maxLengthStatus, maxLengthOk);
    updateStatus(numberStatus, numberOk);
    updateStatus(letterStatus, letterOk);
    updateStatus(upperStatus, upperOk);
    updateStatus(specialStatus, specialOk);

    if (lengthOk && maxLengthOk && numberOk && letterOk && upperOk && specialOk) {
        popup.classList.add('valid');
    } else {
        popup.classList.remove('valid');
    }
}

function attachPopupToField(pwInput) {
    // ignore confirmation fields
    if (pwInput.name === 'confirm_password' || pwInput.id === 'confirm-password') return;

    // only attach to register or reset forms
    const form = pwInput.closest('form');
    if (!form) return;
    const actionInput = form.querySelector('input[name="action"]');
    const action = actionInput ? actionInput.value : '';
    if (action !== 'register' && action !== 'verify_reset') return;

    // wrapper for positioning
    const wrapper = pwInput.closest('.password-input-wrapper');
    if (!wrapper) return;
    wrapper.style.position = 'relative';

    const popup = createCriteriaPopup();
    wrapper.appendChild(popup);

    pwInput.addEventListener('focus', () => popup.style.display = 'block');
    pwInput.addEventListener('blur', () => popup.style.display = 'none');
    pwInput.addEventListener('input', () => updateCriteria(pwInput.value, popup));
}

function resolveFormEndpoint(form, actionName) {
    const rawAction = form.getAttribute('action') || '';

    if (actionName === 'login' || actionName === 'register' || actionName === 'request_reset' || actionName === 'verify_reset' || actionName === 'logout') {
        const path = window.location.pathname;
        const publicIndex = path.toLowerCase().indexOf('/public/');
        if (publicIndex !== -1) {
            const basePath = path.slice(0, publicIndex);
            const normalizedBase = basePath.endsWith('/') ? basePath.slice(0, -1) : basePath;
            return `${normalizedBase}/controllers/auth-control.php`;
        }
        return '/controllers/auth-control.php';
    }

    return rawAction ? new URL(rawAction, window.location.href).toString() : window.location.href;
}


// attach to any authentication form (login, register, reset)
document.addEventListener('DOMContentLoaded', function() {
    // attach criteria popup to all primary password inputs on auth pages
    document.querySelectorAll('input[type="password"]').forEach(attachPopupToField);

    document.querySelectorAll('form').forEach(form => {
        if (!form.classList.contains('login-form')) return; // only our auth forms

        const actionInput = form.querySelector('input[name="action"]');
        const action = actionInput ? actionInput.value : '';

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            clearErrors(form);

            const endpoint = resolveFormEndpoint(form, action);

            const pwdField = form.querySelector('input[name="password"]');
            // only enforce complexity on register or password-reset actions
            if (pwdField && (action === 'register' || action === 'verify_reset')) {
                const pw = pwdField.value;
                if (!validatePassword(pw)) {
                    showError(pwdField, 'Password must be 8-15 characters and include a number, a lowercase letter, an uppercase letter, and a special character.');
                    return;
                }
            }

            const data = new FormData(form);
            fetch(endpoint, { method: 'POST', body: data })
                .then(async res => {
                    const contentType = res.headers.get('content-type') || '';
                    if (!contentType.includes('application/json')) {
                        const text = await res.text();
                        throw new Error(`Expected JSON response, got: ${text.slice(0, 120)}`);
                    }
                    return res.json();
                })
                .then(response => {
                    if (response.success) {
                        if (response.redirect) {
                            window.location = response.redirect;
                        } else {
                            const formError = document.getElementById('form-error');
                            if (formError && response.message) {
                                formError.textContent = response.message;
                                formError.classList.add('visible');
                            }
                            form.reset();
                        }
                    } else {
                        // highlight fields and show message
                        if (pwdField) pwdField.classList.add('input-error');
                        const userField = form.querySelector('input[name="username"]') || form.querySelector('input[name="email"]');
                        if (userField) userField.classList.add('input-error');
                        if (response.message) {
                            const formError = document.getElementById('form-error');
                            if (formError) {
                                formError.textContent = response.message;
                                formError.classList.add('visible');
                            }
                        }
                    }
                })
                .catch(err => {
                    console.error('Fetch error', err);
                    showError(pwdField || form, 'An error occurred, please try again.');
                });
        });
    });
});
