<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1); // Enable error display for debugging

require_once '../db/connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../actions/login.php");
    exit;
}

// Get user details
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];

// Initialize variables for filtering
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

// Debug output
error_log("User ID: " . $user_id);
error_log("User Role: " . $user_role);
error_log("Course ID: " . ($course_id ?? 'null'));
error_log("Date From: " . ($date_from ?? 'null'));
error_log("Date To: " . ($date_to ?? 'null'));

// Prepare base query
$query = "SELECT s.session_id, s.schedule_date, s.status,
                 c.course_name, c.course_id,
                 u.name as other_user_name
          FROM Sessions s
          JOIN Courses c ON s.course_id = c.course_id
          JOIN Users u ON " . 
          ($user_role === 'student' ? "s.tutor_id = u.user_id" : "s.student_id = u.user_id") .
          " WHERE " . 
          ($user_role === 'student' ? "s.student_id = ?" : "s.tutor_id = ?");

// Create the param types string
$param_types = "i"; // First parameter is always user_id (integer)
$params = [$user_id];

if ($course_id) {
    $query .= " AND s.course_id = ?";
    $param_types .= "i";
    $params[] = $course_id;
}

if ($date_from) {
    $query .= " AND s.schedule_date >= ?";
    $param_types .= "s";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND s.schedule_date <= ?";
    $param_types .= "s";
    $params[] = $date_to;
}

$query .= " ORDER BY s.schedule_date DESC";

// Debug the final query and parameters
error_log("Final Query: " . $query);
error_log("Parameter Types: " . $param_types);
error_log("Parameters: " . print_r($params, true));

// Prepare and execute the query
$stmt = $conn->prepare($query);
if ($stmt) {
    // Only bind parameters if there are any
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        die("Error executing query: " . $stmt->error);
    }
    $sessions = $stmt->get_result();
    error_log("Number of rows returned: " . ($sessions ? $sessions->num_rows : 0));
} else {
    error_log("Query preparation failed: " . $conn->error);
    die("Error preparing query: " . $conn->error);
}

// Get courses for filter
$courses_query = "SELECT DISTINCT c.course_id, c.course_name 
                 FROM Sessions s 
                 JOIN Courses c ON s.course_id = c.course_id 
                 WHERE " . ($user_role === 'student' ? "s.student_id = ?" : "s.tutor_id = ?");
$courses_stmt = $conn->prepare($courses_query);
$courses_stmt->bind_param("i", $user_id);
$courses_stmt->execute();
$courses = $courses_stmt->get_result();

// Calculate statistics
$total_sessions = 0;
$completed_sessions = 0;
$pending_sessions = 0;
$cancelled_sessions = 0;

$sessions_data = [];
while ($row = $sessions->fetch_assoc()) {
    $sessions_data[] = $row;
    $total_sessions++;
    switch ($row['status']) {
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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Progress</title>
    <link rel="stylesheet" href="../assets/css/frontend.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <?php include 'sidemenu.php'; ?>

    <main>
        <h1>Track Progress</h1>

        <!-- Progress Summary -->
        <div class="progress-summary">
            <div class="summary-stats">
                <div class="stat-box">
                    <h3>Total Sessions</h3>
                    <p><?= $total_sessions ?></p>
                </div>
                <div class="stat-box">
                    <h3>Completed</h3>
                    <p><?= $completed_sessions ?></p>
                </div>
                <div class="stat-box">
                    <h3>Pending</h3>
                    <p><?= $pending_sessions ?></p>
                </div>
                <div class="stat-box">
                    <h3>Cancelled</h3>
                    <p><?= $cancelled_sessions ?></p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="course_id">Course:</label>
                    <select name="course_id" id="course_id">
                        <option value="">All Courses</option>
                        <?php while ($course = $courses->fetch_assoc()): ?>
                            <option value="<?= $course['course_id'] ?>" 
                                    <?= $course_id == $course['course_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['course_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_from">From:</label>
                    <input type="date" name="date_from" id="date_from" value="<?= $date_from ?>">
                </div>
                <div class="form-group">
                    <label for="date_to">To:</label>
                    <input type="date" name="date_to" id="date_to" value="<?= $date_to ?>">
                </div>
                <button type="submit" class="action-button">Apply Filters</button>
            </form>
        </div>

        <!-- Session History -->
        <section class="session-history">
            <h2>Session History</h2>
            <?php if (!empty($sessions_data)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th><?= $user_role === 'student' ? 'Tutor' : 'Student' ?></th>
                                <th>Course</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions_data as $session): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($session['schedule_date']))) ?></td>
                                    <td><?= htmlspecialchars($session['other_user_name']) ?></td>
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
                    <p>No sessions found.</p>
                    <?php if ($user_role === 'student'): ?>
                        <a href="tutor_list.php" class="action-button">Find a Tutor</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <style>
        .filters {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .filter-form {
            display: flex;
            gap: 20px;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #ffcc00;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
        }

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

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .status-completed { background: #4CAF50; color: white; }
        .status-pending { background: #2196F3; color: white; }
        .status-cancelled, .status-declined { background: #f44336; color: white; }
    </style>
</body>
</html>
