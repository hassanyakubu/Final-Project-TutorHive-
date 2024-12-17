<?php
session_start();
require_once '../db/connect.php';
require_once '../utils/security.php';

// Set secure headers
set_secure_headers();

// Redirect if user is not logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../actions/login.php");
    exit;
}

// Initialize variables
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        verify_csrf_token($_POST['csrf_token']);
        
        // Check rate limiting
        if (!rate_limit_check('booking_' . $_SESSION['user_id'], 10, 3600)) {
            throw new Exception('Too many booking attempts. Please try again later.');
        }

        $action = filter_var($_POST['action'], FILTER_SANITIZE_STRING);
        $student_id = $_SESSION['user_id'];

        if ($action === 'book') {
            // Booking a session
            $tutor_id = filter_var($_POST['tutor_id'], FILTER_SANITIZE_NUMBER_INT);
            $course_id = filter_var($_POST['course_id'], FILTER_SANITIZE_NUMBER_INT);
            $schedule_date = filter_var($_POST['schedule_date'], FILTER_SANITIZE_STRING);

            // Validate inputs
            if (!$tutor_id || !$course_id || !$schedule_date) {
                throw new Exception('Invalid input parameters.');
            }

            // Validate date format and future date
            if (!validate_date($schedule_date)) {
                throw new Exception('Invalid date format.');
            }

            if (!is_future_date($schedule_date)) {
                throw new Exception('Cannot book sessions in the past.');
            }

            // Verify tutor exists and is active
            $stmt = $conn->prepare("SELECT user_id FROM Users WHERE user_id = ? AND role = 'tutor' AND status = 'active'");
            $stmt->bind_param("i", $tutor_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                throw new Exception('Invalid tutor selected.');
            }

            // Verify course exists
            $stmt = $conn->prepare("SELECT course_id FROM Courses WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                throw new Exception('Invalid course selected.');
            }

            // Check if tutor teaches this course
            $stmt = $conn->prepare("SELECT * FROM TutorCourses WHERE tutor_id = ? AND course_id = ?");
            $stmt->bind_param("ii", $tutor_id, $course_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                throw new Exception('This tutor does not teach the selected course.');
            }

            // Check tutor availability
            $stmt = $conn->prepare("SELECT * FROM Sessions WHERE tutor_id = ? AND schedule_date = ? AND status != 'cancelled'");
            $stmt->bind_param("is", $tutor_id, $schedule_date);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception('Tutor is not available at the selected time.');
            }

            // Check if student already has a session at this time
            $stmt = $conn->prepare("SELECT * FROM Sessions WHERE student_id = ? AND schedule_date = ? AND status != 'cancelled'");
            $stmt->bind_param("is", $student_id, $schedule_date);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception('You already have a session scheduled at this time.');
            }

            // Insert new session
            $stmt = $conn->prepare("INSERT INTO Sessions (student_id, tutor_id, course_id, schedule_date, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->bind_param("iiis", $student_id, $tutor_id, $course_id, $schedule_date);
            if (!$stmt->execute()) {
                throw new Exception('Failed to book the session. Please try again.');
            }

            $success = "Session successfully booked!";

        } elseif ($action === 'reschedule') {
            $session_id = filter_var($_POST['session_id'], FILTER_SANITIZE_NUMBER_INT);
            $new_schedule_date = filter_var($_POST['new_schedule_date'], FILTER_SANITIZE_STRING);

            // Validate inputs
            if (!$session_id || !$new_schedule_date) {
                throw new Exception('Invalid input parameters.');
            }

            // Validate date format and future date
            if (!validate_date($new_schedule_date)) {
                throw new Exception('Invalid date format.');
            }

            if (!is_future_date($new_schedule_date)) {
                throw new Exception('Cannot reschedule to a past date.');
            }

            // Verify session exists and belongs to student
            $stmt = $conn->prepare("SELECT tutor_id FROM Sessions WHERE session_id = ? AND student_id = ? AND status != 'cancelled'");
            $stmt->bind_param("ii", $session_id, $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Invalid session selected.');
            }
            
            $session = $result->fetch_assoc();
            
            // Check tutor availability for new date
            $stmt = $conn->prepare("SELECT * FROM Sessions WHERE tutor_id = ? AND schedule_date = ? AND status != 'cancelled'");
            $stmt->bind_param("is", $session['tutor_id'], $new_schedule_date);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception('Tutor is not available at the selected time.');
            }

            // Update session
            $stmt = $conn->prepare("UPDATE Sessions SET schedule_date = ?, status = 'rescheduled' WHERE session_id = ? AND student_id = ?");
            $stmt->bind_param("sii", $new_schedule_date, $session_id, $student_id);
            if (!$stmt->execute()) {
                throw new Exception('Failed to reschedule the session. Please try again.');
            }

            $success = "Session successfully rescheduled!";

        } elseif ($action === 'cancel') {
            $session_id = filter_var($_POST['session_id'], FILTER_SANITIZE_NUMBER_INT);

            // Validate input
            if (!$session_id) {
                throw new Exception('Invalid session ID.');
            }

            // Verify session exists and belongs to student
            $stmt = $conn->prepare("SELECT schedule_date FROM Sessions WHERE session_id = ? AND student_id = ? AND status != 'cancelled'");
            $stmt->bind_param("ii", $session_id, $student_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception('Invalid session selected.');
            }

            $session = $result->fetch_assoc();
            
            // Check if session is in the future
            if (!is_future_date($session['schedule_date'])) {
                throw new Exception('Cannot cancel past sessions.');
            }

            // Update session status
            $stmt = $conn->prepare("UPDATE Sessions SET status = 'cancelled' WHERE session_id = ? AND student_id = ?");
            $stmt->bind_param("ii", $session_id, $student_id);
            if (!$stmt->execute()) {
                throw new Exception('Failed to cancel the session. Please try again.');
            }

            $success = "Session successfully canceled!";
        } else {
            throw new Exception('Invalid action.');
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Fetch available courses and tutors for booking
try {
    // Fetch active courses
    $coursesSQL = "SELECT c.* FROM Courses c WHERE c.status = 'active' ORDER BY c.course_name";
    $courses = $conn->query($coursesSQL);
    if (!$courses) {
        throw new Exception('Failed to fetch courses.');
    }

    // Fetch active tutors
    $tutorsSQL = "SELECT u.user_id, u.name, u.bio FROM Users u WHERE u.role = 'tutor' AND u.status = 'active' ORDER BY u.name";
    $tutors = $conn->query($tutorsSQL);
    if (!$tutors) {
        throw new Exception('Failed to fetch tutors.');
    }

    // Fetch student's active sessions
    $stmt = $conn->prepare("SELECT s.session_id, s.schedule_date, s.status, 
                                  u.name AS tutor_name, c.course_name 
                           FROM Sessions s 
                           JOIN Users u ON s.tutor_id = u.user_id 
                           JOIN Courses c ON s.course_id = c.course_id 
                           WHERE s.student_id = ? AND s.status != 'cancelled'
                           ORDER BY s.schedule_date DESC");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $student_sessions = $stmt->get_result();
    if (!$student_sessions) {
        throw new Exception('Failed to fetch sessions.');
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Session - TutorHive</title>
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
    <header>
        <h1>Book a Session</h1>
    </header>

    <main class="content">
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <section class="booking-form">
            <h2>Book New Session</h2>
            <form action="book_session.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="book">

                <div class="form-group">
                    <label for="tutor_id">Select Tutor:</label>
                    <select id="tutor_id" name="tutor_id" required>
                        <option value="">Choose a tutor</option>
                        <?php while ($tutor = $tutors->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($tutor['user_id']); ?>">
                                <?php echo htmlspecialchars($tutor['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="course_id">Select Course:</label>
                    <select id="course_id" name="course_id" required>
                        <option value="">Choose a course</option>
                        <?php while ($course = $courses->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($course['course_id']); ?>">
                                <?php echo htmlspecialchars($course['course_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="schedule_date">Select Date and Time:</label>
                    <input type="datetime-local" id="schedule_date" name="schedule_date" required>
                </div>

                <div class="form-group">
                    <button type="submit">Book Session</button>
                </div>
            </form>
        </section>

        <section class="upcoming-sessions">
            <h2>Your Sessions</h2>
            <?php if ($student_sessions->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Tutor</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($session = $student_sessions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($session['schedule_date']))); ?></td>
                                <td><?php echo htmlspecialchars($session['tutor_name']); ?></td>
                                <td><?php echo htmlspecialchars($session['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($session['status']); ?></td>
                                <td>
                                    <?php if (is_future_date($session['schedule_date'])): ?>
                                        <form action="book_session.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($session['session_id']); ?>">
                                            <button type="submit" onclick="return confirm('Are you sure you want to cancel this session?')">Cancel</button>
                                        </form>
                                        
                                        <button onclick="showRescheduleForm('<?php echo htmlspecialchars($session['session_id']); ?>')">Reschedule</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No sessions scheduled.</p>
            <?php endif; ?>
        </section>
    </main>

    <div id="reschedule-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Reschedule Session</h3>
            <form action="book_session.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="reschedule">
                <input type="hidden" name="session_id" id="reschedule_session_id">
                
                <div class="form-group">
                    <label for="new_schedule_date">New Date and Time:</label>
                    <input type="datetime-local" id="new_schedule_date" name="new_schedule_date" required>
                </div>

                <div class="form-group">
                    <button type="submit">Confirm Reschedule</button>
                    <button type="button" onclick="hideRescheduleForm()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showRescheduleForm(sessionId) {
            document.getElementById('reschedule_session_id').value = sessionId;
            document.getElementById('reschedule-modal').style.display = 'block';
        }

        function hideRescheduleForm() {
            document.getElementById('reschedule-modal').style.display = 'none';
        }
    </script>
</body>
</html>
