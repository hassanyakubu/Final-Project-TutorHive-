<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../db/connect.php';
require_once '../utils/security.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$error = '';
$success = '';
$tutors = [];
$courses = [];
$sessions = [];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// For tutors: Fetch accepted sessions
if ($user_role === 'tutor') {
    $sql_sessions = "
        SELECT s.*, c.course_name, u.name as student_name, u.email as student_email 
        FROM Sessions s
        INNER JOIN Courses c ON s.course_id = c.course_id
        INNER JOIN Users u ON s.student_id = u.user_id
        WHERE s.tutor_id = ? AND s.status = 'accepted'
        ORDER BY s.schedule_date ASC";
    
    $stmt = $conn->prepare($sql_sessions);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result_sessions = $stmt->get_result();
    
    while ($row = $result_sessions->fetch_assoc()) {
        $sessions[] = $row;
    }
} else {
    // For students: Fetch tutors and courses for booking
    // Fetch tutors
    $sql_tutors = "SELECT user_id, name FROM Users WHERE role = 'tutor'";
    $result_tutors = $conn->query($sql_tutors);
    if ($result_tutors->num_rows > 0) {
        while ($row = $result_tutors->fetch_assoc()) {
            $tutors[] = $row;
        }
    } else {
        $error = "No tutors found.";
    }

    // Fetch courses
    $sql_courses = "SELECT course_id, course_name, course_code, description FROM Courses";
    $result_courses = $conn->query($sql_courses);
    if ($result_courses->num_rows > 0) {
        while ($row = $result_courses->fetch_assoc()) {
            $courses[] = $row;
        }
    } else {
        $error = "No courses found.";
    }

    // Process booking for students
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify CSRF token
        if (!isset($_POST['csrf_token'])) {
            $error = "CSRF token missing.";
        } else {
            verify_csrf_token($_POST['csrf_token']);
            
            // Convert tutor_id and course_id to integers
            $tutor_id = (int)$_POST['tutor_id'];
            $course_id = (int)$_POST['course_id'];
            $session_date = $_POST['session_date'];
            $session_time = $_POST['session_time'];

            // Validate input fields
            if (empty($tutor_id) || empty($course_id) || empty($session_date) || empty($session_time)) {
                $error = "All fields are required.";
            } else {
                // Concatenate session date and time
                $session_datetime = $session_date . ' ' . $session_time;

                // Check availability
                $check_sql = "SELECT * FROM Sessions WHERE tutor_id = ? AND schedule_date = ? AND status IN ('pending', 'accepted')";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("is", $tutor_id, $session_datetime);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $error = "The selected time slot is not available. Please choose another time.";
                } else {
                    // Book the session
                    $insert_sql = "INSERT INTO Sessions (tutor_id, student_id, course_id, schedule_date, status) VALUES (?, ?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($insert_sql);
                    $stmt->bind_param("iiis", $tutor_id, $user_id, $course_id, $session_datetime);

                    if ($stmt->execute()) {
                        $success = "Your session has been booked successfully! Waiting for tutor confirmation.";
                    } else {
                        $error = "Error booking the session. Please try again.";
                    }
                }
            }
        }
    }
}

$conn->close();

// Generate CSRF token for the form
$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $user_role === 'tutor' ? 'Manage Sessions' : 'Book Session'; ?> - TutorHive</title>
    <link rel="stylesheet" href="../assets/css/frontend.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <?php include 'sidemenu.php'; ?>

    <main>
        <h1><?php echo $user_role === 'tutor' ? 'Manage Sessions' : 'Book a Session'; ?></h1>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($user_role === 'tutor'): ?>
            <!-- Tutor View: Show Accepted Sessions -->
            <div class="card">
                <div class="card-header">
                    <h2>Your Accepted Sessions</h2>
                </div>
                <div class="table-responsive">
                    <?php if (!empty($sessions)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Course</th>
                                    <th>Student</th>
                                    <th>Student Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($session['schedule_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($session['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($session['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($session['student_email']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-data">No accepted sessions found.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Student View: Booking Form -->
            <div class="card">
                <div class="card-header">
                    <h2>Book a New Session</h2>
                </div>
                <form method="POST" action="" class="booking-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group">
                        <label for="tutor_id">Select Tutor:</label>
                        <select name="tutor_id" id="tutor_id" required>
                            <option value="">Choose a tutor</option>
                            <?php foreach ($tutors as $tutor): ?>
                                <option value="<?php echo $tutor['user_id']; ?>">
                                    <?php echo htmlspecialchars($tutor['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="course_id">Select Course:</label>
                        <select name="course_id" id="course_id" required>
                            <option value="">Choose a course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name']); ?> (<?php echo htmlspecialchars($course['course_code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="session_date">Session Date:</label>
                        <input type="date" id="session_date" name="session_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="session_time">Session Time:</label>
                        <input type="time" id="session_time" name="session_time" required>
                    </div>

                    <button type="submit" class="action-button change">Book Session</button>
                </form>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Add any necessary JavaScript for form validation or date/time picker enhancements
        document.getElementById('session_date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>
