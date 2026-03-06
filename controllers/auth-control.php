<?php
session_start();

require_once '../config/db.php';

/*
    Auth Controller - Handles all authentication operations
    - Login
    - Registration
    - Password Reset
    - Logout
*/

class AuthController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /* ========================================
       LOGIN
       ======================================== */
    public function login($username, $password) {
        try {
            // Validate input
            if (empty($username) || empty($password)) {
                return ['success' => false, 'message' => 'Username and password are required'];
            }

            // no complexity enforcement on login; only check emptiness
            // (validation applied during registration/reset to avoid locking out existing users)

            // Query user from database
            $stmt = $this->pdo->prepare('SELECT id, username, email, password FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // Verify user exists and password matches
            if (!$user || !password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }

            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['logged_in'] = true;

            // Log successful login
            error_log("User $username logged in successfully");

            return ['success' => true, 'message' => 'Login successful', 'redirect' => '../public/dashboard.php'];

        } catch (Exception $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during login'];
        }
    }


    /* ========================================
       REGISTER
       ======================================== */
    public function register($username, $email, $password, $confirm_password) {
        try {
            // Validate input
            if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
                return ['success' => false, 'message' => 'All fields are required'];
            }

            // Check password match
            if ($password !== $confirm_password) {
                return ['success' => false, 'message' => 'Passwords do not match'];
            }

            // Validate password strength (minimum 8 characters, max 15, digit, lowercase, uppercase, special char)
            if (
                strlen($password) < 8 ||
                strlen($password) > 15 ||
                !preg_match('/\d/', $password) ||
                !preg_match('/[a-z]/', $password) ||
                !preg_match('/[A-Z]/', $password) ||
                !preg_match('/[^A-Za-z0-9]/', $password)
            ) {
                return ['success' => false, 'message' => 'Password moet minstens 8 tekens bevatten, maximaal 15, een cijfer, een kleine letter, een hoofdletter en een speciaal teken'];
            }

            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }

            // Check if username already exists
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username already exists'];
            }

            // Check if email already exists
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email already registered'];
            }

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            // Insert new user
            $stmt = $this->pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
            $stmt->execute([$username, $email, $hashed_password]);

            error_log("New user registered: $username");

            return ['success' => true, 'message' => 'Registration successful', 'redirect' => '../public/login.php'];

        } catch (Exception $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during registration'];
        }
    }


    /* ========================================
       PASSWORD RESET - REQUEST
       ======================================== */
    public function requestPasswordReset($email) {
        try {
            // Validate input
            if (empty($email)) {
                return ['success' => false, 'message' => 'Email is required'];
            }

            // Check if email exists
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                // Don't reveal if email exists or not (security)
                return ['success' => true, 'message' => 'If the email exists, a reset code will be sent'];
            }

            // Generate 6-digit reset code
            $reset_code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);

            // Set expiration time (30 minutes)
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            // Insert reset request
            $stmt = $this->pdo->prepare('INSERT INTO password_resets (user_id, reset_code, expires_at, used) VALUES (?, ?, ?, 0)');
            $stmt->execute([$user['id'], $reset_code, $expires_at]);

            // TODO: Send email with reset code
            // For now, log the reset code (delete in production)
            error_log("Password reset code for $email: $reset_code");

            return ['success' => true, 'message' => 'If the email exists, a reset code will be sent'];

        } catch (Exception $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'An error occurred'];
        }
    }


    /* ========================================
       PASSWORD RESET - VERIFY & UPDATE
       ======================================== */
    public function verifyAndResetPassword($email, $reset_code, $new_password, $confirm_password) {
        try {
            // Validate input
            if (empty($email) || empty($reset_code) || empty($new_password) || empty($confirm_password)) {
                return ['success' => false, 'message' => 'All fields are required'];
            }

            // Check password match
            if ($new_password !== $confirm_password) {
                return ['success' => false, 'message' => 'Passwords do not match'];
            }

            // Validate password strength
            if (
                strlen($new_password) < 8 ||
                strlen($new_password) > 15 ||
                !preg_match('/\d/', $new_password) ||
                !preg_match('/[a-z]/', $new_password) ||
                !preg_match('/[A-Z]/', $new_password) ||
                !preg_match('/[^A-Za-z0-9]/', $new_password)
            ) {
                return ['success' => false, 'message' => 'Password moet minstens 8 tekens bevatten, maximaal 15, een cijfer, een kleine letter, een hoofdletter en een speciaal teken'];
            }

            // Get user
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'Email not found'];
            }

            // Verify reset code
            $stmt = $this->pdo->prepare('SELECT id FROM password_resets WHERE user_id = ? AND reset_code = ? AND used = 0 AND expires_at > NOW()');
            $stmt->execute([$user['id'], $reset_code]);
            $reset_request = $stmt->fetch();

            if (!$reset_request) {
                return ['success' => false, 'message' => 'Invalid or expired reset code'];
            }

            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

            // Update user password
            $stmt = $this->pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$hashed_password, $user['id']]);

            // Mark reset code as used
            $stmt = $this->pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
            $stmt->execute([$reset_request['id']]);

            error_log("Password reset successful for: $email");

            return ['success' => true, 'message' => 'Password reset successful', 'redirect' => '../public/login.php'];

        } catch (Exception $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during password reset'];
        }
    }


    /* ========================================
       LOGOUT
       ======================================== */
    public function logout() {
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully', 'redirect' => '../public/index.php'];
    }


    /* ========================================
       CHECK IF USER IS LOGGED IN
       ======================================== */
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /* ========================================
       GET LOGGED IN USER INFO
       ======================================== */
    public static function getUser() {
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email']
            ];
        }
        return null;
    }
}

/* ========================================
   ROUTE REQUESTS
   ======================================== */

$auth = new AuthController($pdo);
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : null);
$response = ['success' => false, 'message' => 'Invalid action'];

// Determine action and execute
switch ($action) {
    case 'login':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $response = $auth->login($username, $password);
        break;

    case 'register':
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $response = $auth->register($username, $email, $password, $confirm_password);
        break;

    case 'request_reset':
        $email = $_POST['email'] ?? '';
        $response = $auth->requestPasswordReset($email);
        break;

    case 'verify_reset':
        $email = $_POST['email'] ?? '';
        $reset_code = $_POST['reset_code'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $response = $auth->verifyAndResetPassword($email, $reset_code, $new_password, $confirm_password);
        break;

    case 'logout':
        $response = $auth->logout();
        break;

    default:
        $response = ['success' => false, 'message' => 'Invalid action'];
}

// Return response as JSON or redirect
if (!empty($response['redirect'])) {
    header('Location: ' . $response['redirect']);
} else {
    // if the request is not coming from AJAX, we redirect back to the login page with an error query parameter
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if (!$isAjax) {
        if ($action === 'login') {
            $msg = isset($response['message']) ? urlencode($response['message']) : '';
            header('Location: ../public/login.php' . ($msg ? '?error=' . $msg : ''));
            exit;
        } elseif ($action === 'register') {
            $msg = isset($response['message']) ? urlencode($response['message']) : '';
            header('Location: ../public/register.php' . ($msg ? '?error=' . $msg : ''));
            exit;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
}
exit;
?>
