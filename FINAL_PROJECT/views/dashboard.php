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
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Initialize variables
$error_message = '';
$tutor_sessions = null;
$upcoming_sessions = null;

try {
    // Fetch sessions based on user role
    if ($user_role === 'tutor') {
        // Fetch all sessions for tutor
        $tutor_sessions_sql = "
            SELECT s.session_id, s.schedule_date, c.course_name, 
                   u.name AS student_name, u.email AS student_email, s.status
            FROM Sessions s 
            INNER JOIN Courses c ON s.course_id = c.course_id
            INNER JOIN Users u ON s.student_id = u.user_id
            WHERE s.tutor_id = ?
            ORDER BY s.schedule_date ASC";
        
        if ($stmt = $conn->prepare($tutor_sessions_sql)) {
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $tutor_sessions = $stmt->get_result();
            } else {
                throw new Exception("Failed to execute tutor sessions query");
            }
            $stmt->close();
        }

        // Fetch upcoming accepted sessions
        $upcoming_sql = "
            SELECT s.session_id, s.schedule_date, c.course_name, 
                   u.name AS student_name, u.email AS student_email, s.status
            FROM Sessions s 
            INNER JOIN Courses c ON s.course_id = c.course_id
            INNER JOIN Users u ON s.student_id = u.user_id
            WHERE s.tutor_id = ? AND s.status = 'accepted'
            AND s.schedule_date > NOW()
            ORDER BY s.schedule_date ASC";
        
        if ($stmt = $conn->prepare($upcoming_sql)) {
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $upcoming_sessions = $stmt->get_result();
            } else {
                throw new Exception("Failed to execute upcoming sessions query");
            }
            $stmt->close();
        }
    } else {
        // Fetch all sessions for student
        $upcoming_sql = "
            SELECT s.session_id, s.schedule_date, c.course_name, 
                   u.name AS tutor_name, u.email AS tutor_email, s.status
            FROM Sessions s 
            INNER JOIN Courses c ON s.course_id = c.course_id
            INNER JOIN Users u ON s.tutor_id = u.user_id
            WHERE s.student_id = ? 
            AND s.schedule_date > NOW()
            ORDER BY s.schedule_date ASC";
        
        if ($stmt = $conn->prepare($upcoming_sql)) {
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $upcoming_sessions = $stmt->get_result();
            } else {
                throw new Exception("Failed to execute student sessions query");
            }
            $stmt->close();
        }
    }
} catch (Exception $e) {
    $error_message = "An error occurred while fetching sessions: " . $e->getMessage();
    error_log($error_message);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TutorHive</title>
    <link rel="stylesheet" href="../assets/css/frontend.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <?php include 'sidemenu.php'; ?>

    <main>
        <h1>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h1>

        <?php if (isset($error_message)): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if ($user_role === 'tutor'): ?>
            <div class="grid">
                <div class="stat-box">
                    <h3>Pending Sessions</h3>
                    <p>
                        <?php
                        $pending_count = 0;
                        if ($tutor_sessions) {
                            $tutor_sessions->data_seek(0);
                            while ($session = $tutor_sessions->fetch_assoc()) {
                                if ($session['status'] === 'pending') $pending_count++;
                            }
                            $tutor_sessions->data_seek(0);
                        }
                        echo $pending_count;
                        ?>
                    </p>
                </div>
                <div class="stat-box">
                    <h3>Accepted Sessions</h3>
                    <p>
                        <?php
                        $accepted_count = 0;
                        if ($tutor_sessions) {
                            $tutor_sessions->data_seek(0);
                            while ($session = $tutor_sessions->fetch_assoc()) {
                                if ($session['status'] === 'accepted') $accepted_count++;
                            }
                            $tutor_sessions->data_seek(0);
                        }
                        echo $accepted_count;
                        ?>
                    </p>
                </div>
                <div class="stat-box">
                    <h3>Declined Sessions</h3>
                    <p>
                        <?php
                        $declined_count = 0;
                        if ($tutor_sessions) {
                            $tutor_sessions->data_seek(0);
                            while ($session = $tutor_sessions->fetch_assoc()) {
                                if ($session['status'] === 'declined') $declined_count++;
                            }
                            $tutor_sessions->data_seek(0);
                        }
                        echo $declined_count;
                        ?>
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Session Requests</h2>
                </div>
                <?php if ($tutor_sessions && $tutor_sessions->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Course</th>
                                    <th>Student</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($session = $tutor_sessions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($session['schedule_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($session['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($session['student_name']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $session['status']; ?>">
                                                <?php echo ucfirst($session['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($session['status'] === 'pending'): ?>
                                                <form action="../actions/update_session_status.php" method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                                    <input type="hidden" name="status" value="accepted">
                                                    <button type="submit" class="action-button accept">Accept</button>
                                                </form>
                                                <form action="../actions/update_session_status.php" method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                                    <input type="hidden" name="status" value="declined">
                                                    <button type="submit" class="action-button decline">Decline</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No session requests found.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h2>Your Upcoming Sessions</h2>
                </div>
                <?php if ($upcoming_sessions && $upcoming_sessions->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Course</th>
                                    <th>Tutor</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($session = $upcoming_sessions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($session['schedule_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($session['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($session['tutor_name']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $session['status']; ?>">
                                                <?php echo ucfirst($session['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No upcoming sessions found.</p>
                        <a href="session_booking.php" class="action-button change">Book a Session</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
