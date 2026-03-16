<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Collection Checker</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>

<body>
    <div class="homepage">
        <!-- Logo -->
        <img src="../assets/images/logo.png" class="logo" alt="Collection Checker">

        <!-- Register Container -->
        <div class="login-container">
            <h2>Register</h2>

            <form method="POST" action="../controllers/auth-control.php" class="login-form">
                <!-- Hidden action field -->
                <input type="hidden" name="action" value="register">

                <!-- Error message placeholder -->
                <div id="form-error" class="form-error<?php echo isset($_GET['error']) ? ' visible' : ''; ?>">
                    <?php if (isset($_GET['error'])) echo htmlspecialchars($_GET['error']); ?>
                </div>
                <?php if (isset($_GET['error'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const user = document.getElementById('username');
                        const pwd = document.getElementById('password');
                        const confirm = document.getElementById('confirm-password');
                        if (user) user.classList.add('input-error');
                        if (pwd) pwd.classList.add('input-error');
                        if (confirm) confirm.classList.add('input-error');
                    });
                </script>
                <?php endif; ?>

                <!-- Username Field -->
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="Enter your username" 
                        required
                    >
                </div>

                <!-- Email Field -->
                <div class="form-group">
                    <label for="email">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="Enter your email" 
                        required
                    >
                </div>

                <!-- Password Field -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Enter your password" 
                            required
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <span class="eye-icon" id="eye-password">👁️</span>
                        </button>
                    </div>
                </div>

                <!-- Confirm Password Field -->
                <div class="form-group">
                    <label for="confirm-password">Repeat password:</label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="confirm-password" 
                            name="confirm_password" 
                            placeholder="Confirm your password" 
                            required
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm-password')">
                            <span class="eye-icon" id="eye-confirm">👁️</span>
                        </button>
                    </div>
                </div>

                <!-- Register Button -->
                <button type="submit" class="login-btn">Register</button>
            </form>

            <!-- Login Link -->
            <div class="register-link">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <p>© <?php echo date("Y"); ?> Collection Checker</p>
            <a href="privacy.php">Privacy Policy</a>
        </footer>
    </div>

    <script src="../assets/js/scripts.js?v=20260313i"></script>
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const eyeIcon = passwordField.nextElementSibling.querySelector('.eye-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.textContent = '🙈';
            } else {
                passwordField.type = 'password';
                eyeIcon.textContent = '👁️';
            }
        }
    </script>
</body>

</html>
