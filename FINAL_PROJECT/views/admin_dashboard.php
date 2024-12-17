<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../actions/login.php');
    exit();
}

require_once '../db/connect.php';
require_once '../utils/security.php';

// Initialize variables
$analytics = [];
$user_management = [];
$platform_usage = [];
$error_message = '';
$success_message = '';

// Handle update user request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    try {
        // Verify CSRF token
        verify_csrf_token($_POST['csrf_token']);

        $user_id = filter_var($_POST['user_id'], FILTER_SANITIZE_NUMBER_INT);
        $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $role = filter_var($_POST['role'], FILTER_SANITIZE_STRING);
        $bio = filter_var($_POST['bio'], FILTER_SANITIZE_STRING);

        // Validate role
        if (!in_array($role, ['student', 'tutor', 'admin'])) {
            throw new Exception('Invalid role selected.');
        }

        // Update user in database
        $update_sql = "UPDATE Users SET name = ?, email = ?, role = ?, bio = ? WHERE user_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssssi", $name, $email, $role, $bio, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "User updated successfully!";
        } else {
            throw new Exception("Failed to update user.");
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch analytics data
$sql_analytics = "SELECT 
    (SELECT COUNT(*) FROM Users) as total_users,
    (SELECT COUNT(*) FROM Sessions) as total_sessions,
    (SELECT COUNT(*) FROM Reviews) as total_reviews,
    (SELECT COUNT(*) FROM Sessions WHERE status = 'active') as active_sessions";
$result_analytics = $conn->query($sql_analytics);

if ($result_analytics && $result_analytics->num_rows > 0) {
    $analytics = $result_analytics->fetch_assoc();
} else {
    $analytics = [
        'total_users' => 0,
        'total_sessions' => 0,
        'total_reviews' => 0,
        'active_sessions' => 0
    ];
}

// Fetch all users
$sql_users = "SELECT user_id, name, email, role, bio FROM Users";
$stmt = $conn->prepare($sql_users);
$stmt->execute();
$result_users = $stmt->get_result();

if ($result_users->num_rows > 0) {
    while ($row = $result_users->fetch_assoc()) {
        $user_management[] = $row;
    }
}

// Handle delete user request
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = filter_var($_GET['delete_id'], FILTER_SANITIZE_NUMBER_INT);
        
        // Don't allow deleting yourself
        if ($delete_id == $_SESSION['user_id']) {
            throw new Exception("You cannot delete your own account.");
        }
        
        $delete_sql = "DELETE FROM Users WHERE user_id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $delete_id);
        
        if ($stmt->execute()) {
            $success_message = "User deleted successfully!";
            // Refresh the page to update the user list
            header("Location: admin_dashboard.php");
            exit();
        } else {
            throw new Exception("Failed to delete user.");
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Generate CSRF token for forms
$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TutorHive</title>
    <link rel="stylesheet" href="../assets/css/frontend.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <aside class="sidemenu">
        <nav>
            <ul>
                <li><a href="admin_dashboard.php" class="active">Dashboard</a></li>
                <li><a href="../actions/logout.php">Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main>
        <h1>Admin Dashboard</h1>

        <?php if (!empty($error_message)): ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <!-- Analytics Section -->
        <div class="card">
            <div class="card-header">
                <h2>Platform Analytics</h2>
            </div>
            <div class="analytics-grid">
                <div class="analytics-item">
                    <h3>Total Users</h3>
                    <p class="stat-number"><?php echo $analytics['total_users']; ?></p>
                </div>
                <div class="analytics-item">
                    <h3>Total Sessions</h3>
                    <p class="stat-number"><?php echo $analytics['total_sessions']; ?></p>
                </div>
                <div class="analytics-item">
                    <h3>Total Reviews</h3>
                    <p class="stat-number"><?php echo $analytics['total_reviews']; ?></p>
                </div>
                <div class="analytics-item">
                    <h3>Active Sessions</h3>
                    <p class="stat-number"><?php echo $analytics['active_sessions']; ?></p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>User Management</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Bio</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_management as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                <td><?php echo htmlspecialchars($user['bio']); ?></td>
                                <td>
                                    <button class="action-button edit" onclick='editUser(<?php echo json_encode($user); ?>)'>Edit</button>
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <a href="admin_dashboard.php?delete_id=<?php echo $user['user_id']; ?>" 
                                           class="action-button decline"
                                           onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div id="editUserModal" class="modal" style="color: #333;">
            <div class="modal-content" style="background-color: white;">
                <div class="card" style="background-color: white;">
                    <div class="card-header" style="background-color: #f5f5f5; color: #333;">
                        <h2>Edit User</h2>
                        <span class="close">&times;</span>
                    </div>
                    <form method="POST" action="" style="background-color: white;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="form-group">
                            <label for="edit_name" style="color: #333;">Name:</label>
                            <input type="text" id="edit_name" name="name" required style="color: #333; background-color: white;">
                        </div>

                        <div class="form-group">
                            <label for="edit_email" style="color: #333;">Email:</label>
                            <input type="email" id="edit_email" name="email" required style="color: #333; background-color: white;">
                        </div>

                        <div class="form-group">
                            <label for="edit_role" style="color: #333;">Role:</label>
                            <select id="edit_role" name="role" required style="color: #333; background-color: white;">
                                <option value="student">Student</option>
                                <option value="tutor">Tutor</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="edit_bio" style="color: #333;">Bio:</label>
                            <textarea id="edit_bio" name="bio" rows="4" style="color: #333; background-color: white;"></textarea>
                        </div>

                        <button type="submit" name="update_user" class="action-button change">Update User</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Get the modal
        var modal = document.getElementById("editUserModal");
        var span = document.getElementsByClassName("close")[0];

        function editUser(user) {
            document.getElementById("edit_user_id").value = user.user_id;
            document.getElementById("edit_name").value = user.name;
            document.getElementById("edit_email").value = user.email;
            document.getElementById("edit_role").value = user.role;
            document.getElementById("edit_bio").value = user.bio || '';
            modal.style.display = "block";
        }

        // When the user clicks on <span> (x), close the modal
        span.onclick = function() {
            modal.style.display = "none";
        }

        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
