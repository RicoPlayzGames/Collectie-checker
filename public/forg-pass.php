<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Collection Checker</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>

<body>
    <div class="homepage">
        <!-- Logo -->
        <img src="../assets/images/logo.png" class="logo" alt="Collection Checker">

        <!-- Password Reset Container -->
        <div class="password-reset-container">
            <h2>Reset Password</h2>
            <p class="reset-description">Enter your email address and we'll send you a code to reset your password.</p>

            <form method="POST" action="../controllers/auth-control.php" class="reset-form">
                <!-- Hidden action field -->
                <input type="hidden" name="action" value="request_reset">

                <!-- Email Input -->
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="Enter your email" 
                        required
                    >
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-reset">Send Reset Code</button>
            </form>

            <!-- Back to Login Link -->
            <div class="back-to-login">
                <a href="login.php">Back to Login</a>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <p>© <?php echo date("Y"); ?> Collection Checker</p>
            <a href="privacy.php">Privacy Policy</a>
        </footer>
    </div>
</body>

</html>
