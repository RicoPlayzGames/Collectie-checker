
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
            <li class="length"><span class="status">✖</span> Minstens 8 tekens</li>
            <li class="maxlength"><span class="status">✖</span> Maximaal 15 tekens</li>
            <li class="number"><span class="status">✖</span> Een cijfer</li>
            <li class="letter"><span class="status">✖</span> Een kleine letter</li>
            <li class="upper"><span class="status">✖</span> Een hoofdletter</li>
            <li class="special"><span class="status">✖</span> Een speciaal teken (niet-alfanumeriek)</li>
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

            const pwdField = form.querySelector('input[name="password"]');
            // only enforce complexity on register or password-reset actions
            if (pwdField && (action === 'register' || action === 'verify_reset')) {
                const pw = pwdField.value;
                if (!validatePassword(pw)) {
                    showError(pwdField, 'Wachtwoord voldoet niet aan de eisen (8-15 tekens, cijfer, kleine letter, hoofdletter, speciaal teken).');
                    return;
                }
            }

            const data = new FormData(form);
            fetch(form.action, { method: 'POST', body: data })
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        if (response.redirect) {
                            window.location = response.redirect;
                        } else {
                            window.location.reload();
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
                    showError(pwdField || form, 'Er is een fout opgetreden, probeer het opnieuw.');
                });
        });
    });
});
