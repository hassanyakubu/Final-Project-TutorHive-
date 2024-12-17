<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../db/connect.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$error = '';
$progress = [];
$session_history = [];
$student_id = $_SESSION['user_id'];

// Fetch session history (all sessions)
$sql_sessions = "SELECT 
                    S.session_id, 
                    U.name as tutor_name, 
                    C.course_name, 
                    S.schedule_date, 
                    S.status
                 FROM Sessions S
                 JOIN Users U ON S.tutor_id = U.user_id
                 JOIN Courses C ON S.course_id = C.course_id
                 WHERE S.student_id = ?
                 ORDER BY S.schedule_date DESC";

$stmt = $conn->prepare($sql_sessions);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result_sessions = $stmt->get_result();

if ($result_sessions->num_rows > 0) {
    while ($row = $result_sessions->fetch_assoc()) {
        $session_history[] = $row;
    }
}

// Calculate progress statistics
$total_sessions = count($session_history);
$completed_sessions = 0;
$pending_sessions = 0;
$cancelled_sessions = 0;

foreach ($session_history as $session) {
    switch ($session['status']) {
        case 'completed':
            $completed_sessions++;
            break;
        case 'pending':
            $pending_sessions++;
            break;
        case 'cancelled':
        case 'declined':
            $cancelled_sessions++;
            break;
    }
}

$progress = [
    'total_sessions' => $total_sessions,
    'completed_sessions' => $completed_sessions,
    'pending_sessions' => $pending_sessions,
    'cancelled_sessions' => $cancelled_sessions
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Progress</title>
    <link rel="stylesheet" href="../assets/css/frontend.css">
</head>
<body>
    <aside class="sidemenu">
        <nav>
            <ul>
                <li><a href="home.php">Home</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="tutor_list.php">Find a Tutor</a></li>
                <li><a href="session_booking.php">Book a Session</a></li>
                <li><a href="feedback_page.php">Leave Feedback</a></li>
                <li><a href="progress_page.php" class="active">Track Progress</a></li>
                <li><a href="../actions/logout.php">Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main>
        <header>
            <h1>Track Your Progress</h1>
        </header>

        <!-- Display error or success messages -->
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Progress Summary -->
        <div class="progress-summary">
            <div class="summary-stats">
                <div class="stat-box">
                    <h3>Total Sessions</h3>
                    <p><?= $progress['total_sessions'] ?></p>
                </div>
                <div class="stat-box">
                    <h3>Completed</h3>
                    <p><?= $progress['completed_sessions'] ?></p>
                </div>
                <div class="stat-box">
                    <h3>Pending</h3>
                    <p><?= $progress['pending_sessions'] ?></p>
                </div>
                <div class="stat-box">
                    <h3>Cancelled</h3>
                    <p><?= $progress['cancelled_sessions'] ?></p>
                </div>
            </div>
        </div>

        <!-- Session History -->
        <section class="session-history">
            <h2>Session History</h2>
            <?php if (!empty($session_history)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Tutor</th>
                                <th>Course</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($session_history as $session): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($session['schedule_date']))) ?></td>
                                    <td><?= htmlspecialchars($session['tutor_name']) ?></td>
                                    <td><?= htmlspecialchars($session['course_name']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($session['status']) ?>">
                                            <?= ucfirst(htmlspecialchars($session['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No sessions found in your history.</p>
                    <?php if ($_SESSION['user_role'] === 'student'): ?>
                        <a href="tutor_list.php" class="action-button">Find a Tutor</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>&copy; 2024 TutorHive. All rights reserved.</p>
    </footer>

    <style>
        .progress-summary {
            margin-bottom: 30px;
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-box {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 5px;
            text-align: center;
        }

        .stat-box h3 {
            margin: 0 0 10px 0;
            color: #ffcc00;
            font-size: 1.1em;
        }

        .stat-box p {
            font-size: 2em;
            margin: 0;
            color: #fff;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }

        .empty-state p {
            margin-bottom: 20px;
            font-size: 1.2em;
        }

        .session-history {
            margin-top: 30px;
        }

        .session-history h2 {
            margin-bottom: 20px;
            color: #ffcc00;
        }
    </style>
</body>
</html>
