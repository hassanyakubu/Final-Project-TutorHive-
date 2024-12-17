<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once '../db/connect.php';
require_once '../utils/security.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /FINAL_PROJECT/actions/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Verify session_id is provided
if (!isset($_GET['session_id'])) {
    $_SESSION['error'] = 'Session ID is required.';
    header('Location: dashboard.php');
    exit();
}

$session_id = filter_var($_GET['session_id'], FILTER_SANITIZE_NUMBER_INT);

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Fetch session details
try {
    $session_sql = "
        SELECT s.*, c.course_name, 
               student.name AS student_name, student.email AS student_email,
               tutor.name AS tutor_name, tutor.email AS tutor_email
        FROM Sessions s
        JOIN Courses c ON s.course_id = c.course_id
        JOIN Users student ON s.student_id = student.user_id
        JOIN Users tutor ON s.tutor_id = tutor.user_id
        WHERE s.session_id = ?";
    
    $stmt = $conn->prepare($session_sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $session = $result->fetch_assoc();

    if (!$session) {
        throw new Exception('Session not found.');
    }

    // Verify user has permission to edit this session
    if ($user_role === 'tutor' && $session['tutor_id'] !== $user_id) {
        throw new Exception('You do not have permission to edit this session.');
    } elseif ($user_role === 'student' && $session['student_id'] !== $user_id) {
        throw new Exception('You do not have permission to edit this session.');
    }

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Session - TutorHive</title>
    <link rel="stylesheet" href="../assets/css/frontend.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container">
        <aside class="sidemenu">
            <nav>
                <ul>
                    <li><a href="home.php">Home</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="tutor_list.php">Find a Tutor</a></li>
                    <li><a href="session_booking.php">Book a Session</a></li>
                    <li><a href="feedback_page.php">Leave Feedback</a></li>
                    <li><a href="progress_page.php">Track Progress</a></li>
                    <li><a href="../actions/logout.php">Logout</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <header class="content-header">
                <h1>Edit Session</h1>
            </header>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert error">
                    <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form action="../actions/update_session.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                        
                        <div class="form-group">
                            <label>Course:</label>
                            <input type="text" value="<?php echo htmlspecialchars($session['course_name']); ?>" readonly class="form-control">
                        </div>

                        <div class="form-group">
                            <label>Student:</label>
                            <input type="text" value="<?php echo htmlspecialchars($session['student_name']); ?>" readonly class="form-control">
                        </div>

                        <div class="form-group">
                            <label>Tutor:</label>
                            <input type="text" value="<?php echo htmlspecialchars($session['tutor_name']); ?>" readonly class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="schedule_date">Session Date and Time:</label>
                            <input type="datetime-local" name="schedule_date" id="schedule_date" 
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($session['schedule_date'])); ?>" 
                                   class="form-control" required>
                        </div>

                        <?php if ($user_role === 'tutor'): ?>
                            <div class="form-group">
                                <label for="status">Status:</label>
                                <select name="status" id="status" class="form-control" required>
                                    <option value="pending" <?php echo $session['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="accepted" <?php echo $session['status'] === 'accepted' ? 'selected' : ''; ?>>Accept</option>
                                    <option value="declined" <?php echo $session['status'] === 'declined' ? 'selected' : ''; ?>>Decline</option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="form-actions">
                            <button type="submit" class="action-button update">Update Session</button>
                            <a href="dashboard.php" class="action-button decline">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
