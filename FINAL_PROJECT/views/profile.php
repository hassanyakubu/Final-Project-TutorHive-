<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once('../config/database.php');

// Get user details
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    $updateStmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
    $updateStmt->bind_param("sssi", $name, $email, $phone, $userId);
    
    if ($updateStmt->execute()) {
        $success = "Profile updated successfully!";
        // Update session variables
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
    } else {
        $error = "Error updating profile: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - TutorHive</title>
    <link rel="stylesheet" href="../assets/css/frontend.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <?php include 'sidemenu.php'; ?>

    <main>
        <h1>Profile Settings</h1>

        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2>Personal Information</h2>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <input type="text" value="<?php echo ucfirst(htmlspecialchars($user['role'])); ?>" readonly>
                </div>

                <button type="submit" class="action-button change">Update Profile</button>
            </form>
        </div>
    </main>
</body>
</html>
