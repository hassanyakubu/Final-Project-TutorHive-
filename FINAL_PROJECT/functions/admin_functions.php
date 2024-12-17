<?php
require_once '../db/connect.php';

/**
 * Fetch all users from the database.
 *
 * @return array
 */
function getAllUsers() {
    global $conn;
    $sql = "SELECT user_id, name, email, role, profile_picture, bio FROM Users";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    return $users;
}

/**
 * Fetch user by ID.
 *
 * @param int $user_id
 * @return array
 */
function getUserById($user_id) {
    global $conn;
    $sql = "SELECT * FROM Users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Add a new user to the database.
 *
 * @param string $name
 * @param string $email
 * @param string $role
 * @param string $profile_picture
 * @param string $bio
 * @return bool
 */
function addUser($name, $email, $role, $profile_picture, $bio) {
    global $conn;
    $sql = "INSERT INTO Users (name, email, role, profile_picture, bio) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $name, $email, $role, $profile_picture, $bio);
    
    return $stmt->execute();
}

/**
 * Update user details in the database.
 *
 * @param int $user_id
 * @param string $name
 * @param string $email
 * @param string $role
 * @param string $profile_picture
 * @param string $bio
 * @return bool
 */
function updateUser($user_id, $name, $email, $role, $profile_picture, $bio) {
    global $conn;
    $sql = "UPDATE Users SET name = ?, email = ?, role = ?, profile_picture = ?, bio = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $name, $email, $role, $profile_picture, $bio, $user_id);
    
    return $stmt->execute();
}

/**
 * Delete a user by their ID.
 *
 * @param int $user_id
 * @return bool
 */
function deleteUser($user_id) {
    global $conn;
    $sql = "DELETE FROM Users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    
    return $stmt->execute();
}

/**
 * Fetch analytics data from the platform.
 *
 * @return array
 */
function getAnalyticsData() {
    global $conn;
    $sql = "SELECT COUNT(DISTINCT user_id) AS total_users,
                   COUNT(session_id) AS total_sessions,
                   COUNT(review_id) AS total_reviews
            FROM Users, Sessions, Reviews";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Fetch the count of active sessions.
 *
 * @return array
 */
function getActiveSessions() {
    global $conn;
    $sql = "SELECT COUNT(session_id) AS active_sessions FROM Sessions WHERE status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

$conn->close();
?>
