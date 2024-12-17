<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../db/connect.php';
require_once '../utils/security.php';

// Set secure headers
set_secure_headers();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        error_log("Starting registration process");
        
        // Verify CSRF token
        verify_csrf_token($_POST['csrf_token']);
        error_log("CSRF verification passed");
        
        // Check rate limiting
        if (!rate_limit_check('register_' . $_SERVER['REMOTE_ADDR'], 10, 3600)) {
            throw new Exception('Too many registration attempts. Please try again in an hour.');
        }
        error_log("Rate limit check passed");

        // Validate and sanitize input
        $name = filter_var(trim($_POST['name']), FILTER_SANITIZE_STRING);
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $role = filter_var(trim($_POST['role']), FILTER_SANITIZE_STRING);
        $bio = filter_var(trim($_POST['bio'] ?? ''), FILTER_SANITIZE_STRING);

        error_log("Input data: name=$name, email=$email, role=$role");

        // Validate required fields
        if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
            throw new Exception('All fields are required.');
        }
        error_log("Required fields validation passed");

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format.');
        }
        error_log("Email format validation passed");

        // Validate password
        $password_validation = validate_password($password);
        if ($password_validation !== true) {
            throw new Exception($password_validation);
        }
        error_log("Password validation passed");

        // Check password match
        if ($password !== $confirm_password) {
            throw new Exception('Passwords do not match.');
        }
        error_log("Password match validation passed");

        // Handle profile picture upload
        $profile_picture = null;
        if (isset($_FILES['profile_picture']) && !empty($_FILES['profile_picture']['name'])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            // Additional error checking
            if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in the HTML form',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
                ];
                $error_message = isset($upload_errors[$_FILES['profile_picture']['error']]) 
                    ? $upload_errors[$_FILES['profile_picture']['error']] 
                    : 'Unknown upload error';
                throw new Exception('Upload error: ' . $error_message);
            }

            // Verify MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $file_mime = finfo_file($finfo, $_FILES['profile_picture']['tmp_name']);
            finfo_close($finfo);

            if (!in_array($file_mime, $allowed_types)) {
                throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
            }

            if ($_FILES['profile_picture']['size'] > $max_size) {
                throw new Exception('File size too large. Maximum size is 2MB.');
            }

            // Create upload directory if it doesn't exist
            $upload_dir = __DIR__ . '/../uploads/profile_pictures/';
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    throw new Exception('Failed to create upload directory.');
                }
            }

            if (!is_writable($upload_dir)) {
                throw new Exception('Upload directory is not writable.');
            }

            // Generate unique filename
            $extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            $filename = uniqid('profile_', true) . '.' . $extension;
            $upload_path = $upload_dir . $filename;

            // Save file and store relative path in database
            if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                error_log("Failed to move uploaded file from {$_FILES['profile_picture']['tmp_name']} to {$upload_path}");
                throw new Exception('Failed to upload profile picture. Please try again.');
            }

            // Store relative path in database
            $profile_picture = 'uploads/profile_pictures/' . $filename;
        }
        error_log("Profile picture processed successfully");

        // Check if email already exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Users WHERE email = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            throw new Exception('Database error occurred.');
        }
        
        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            throw new Exception('Database error occurred.');
        }
        
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            throw new Exception('Email already registered.');
        }
        error_log("Email uniqueness check passed");

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        error_log("Password hashed successfully");

        // Insert new user
        $sql = "INSERT INTO Users (name, email, password, role, profile_picture, bio) VALUES (?, ?, ?, ?, ?, ?)";
        error_log("Preparing SQL: $sql");
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            throw new Exception('Database error occurred.');
        }

        $stmt->bind_param("ssssss", $name, $email, $hashed_password, $role, $profile_picture, $bio);
        error_log("Parameters bound successfully");

        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            throw new Exception('Failed to create account: ' . $stmt->error);
        }
        error_log("User inserted successfully");

        $stmt->close();
        $conn->close();

        // Set success message and redirect
        $_SESSION['registration_success'] = "Registration successful! Please login with your credentials.";
        error_log("Registration completed successfully, redirecting to login page");
        
        header("Location: login.php");
        exit();

    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        $error = $e->getMessage();
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
    <title>Register - TutorHive</title>
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
    <header>
        <h1>TutorHive Registration</h1>
    </header>

    <main class="content">
        <h2>Create Your Account</h2>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST" enctype="multipart/form-data" class="registration-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="form-group">
                <label for="name">Full Name:</label>
                <input type="text" id="name" name="name" required maxlength="100"
                       value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required maxlength="255"
                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <small>Must be at least 8 characters long and include uppercase, lowercase, number, and special character.</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="student" <?php echo (isset($role) && $role === 'student') ? 'selected' : ''; ?>>Student</option>
                    <option value="tutor" <?php echo (isset($role) && $role === 'tutor') ? 'selected' : ''; ?>>Tutor</option>
                </select>
            </div>

            <div class="form-group">
                <label for="bio">Bio:</label>
                <textarea id="bio" name="bio" maxlength="1000"><?php echo isset($bio) ? htmlspecialchars($bio) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label for="profile_picture">Profile Picture:</label>
                <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/gif">
                <small>Max size: 2MB. Allowed types: JPG, PNG, GIF</small>
            </div>

            <div class="form-group">
                <button type="submit">Register</button>
            </div>
        </form>

        <p>Already have an account? <a href="login.php">Login here</a></p>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> TutorHive. All rights reserved.</p>
    </footer>
</body>
</html>
