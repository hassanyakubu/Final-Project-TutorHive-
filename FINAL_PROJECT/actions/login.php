<?php
session_start();

error_reporting(E_ALL); // Show all errors, warnings, and notices
ini_set('display_errors', 1); // Display errors on the screen

require_once '../db/connect.php';
require_once '../utils/security.php';

// Set secure headers
set_secure_headers();

// Initialize error variable
$error = '';

// Handle POST request for login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug log
        error_log("Login attempt for email: " . $_POST['email']);
        
        // Verify CSRF token
        verify_csrf_token($_POST['csrf_token']);
        error_log("CSRF verification passed");
        
        // Check rate limiting
        if (!rate_limit_check('login_' . $_SERVER['REMOTE_ADDR'])) {
            throw new Exception('Too many login attempts. Please try again later.');
        }
        error_log("Rate limit check passed");

        // Sanitize and validate input
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = trim($_POST['password']);

        if (empty($email) || empty($password)) {
            throw new Exception('Both email and password are required.');
        }
        error_log("Input validation passed");

        // Prepare SQL query with additional security measures
        $sql = "SELECT user_id, name, email, password, role, profile_picture, bio FROM Users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        error_log("Database query executed");

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            error_log("User found in database");

            if (password_verify($password, $user['password'])) {
                error_log("Password verified successfully");
                
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                error_log("Session ID regenerated");
                
                // Store user data in session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_profile_picture'] = $user['profile_picture'];
                $_SESSION['user_bio'] = $user['bio'];
                $_SESSION['last_activity'] = time();
                error_log("Session variables set");

                error_log("User role: " . $user['role']);
                error_log("Current script path: " . $_SERVER['SCRIPT_NAME']);
                
                // Use simple relative paths
                if ($user['role'] === 'admin') {
                    header("Location: ../views/admin_dashboard.php");
                } else {
                    header("Location: ../views/dashboard.php");
                }
                
                // Force the output of any remaining buffered content
                ob_end_flush();
                exit();
            } else {
                error_log("Password verification failed");
            }
        } else {
            error_log("No user found with email: " . $email);
        }
        throw new Exception('Invalid login credentials.');
    } catch (Exception $e) {
        $error = $e->getMessage();
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

// Generate CSRF token for the form
$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TutorHive</title>
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
    <header>
        <h1>TutorHive Login</h1>
    </header>

    <main class="content">
        <h2>Login to Your Account</h2>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php 
        // Display registration success message if set
        if (isset($_SESSION['registration_success'])): ?>
            <div class="success"><?php echo htmlspecialchars($_SESSION['registration_success']); ?></div>
            <?php unset($_SESSION['registration_success']); // Clear the message after displaying ?>
        <?php endif; ?>

        <form action="login.php" method="POST" class="login-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required 
                       placeholder="Enter your email" 
                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Enter your password">
            </div>

            <div class="form-group">
                <button type="submit">Login</button>
            </div>
        </form>

        <p>Don't have an account? <a href="../actions/register.php">Sign up</a></p>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> TutorHive. All rights reserved.</p>
    </footer>
</body>
</html>
