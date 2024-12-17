<?php
// Security utility functions

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        throw new Exception('CSRF token validation failed');
    }
    return true;
}

function set_secure_headers() {
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('X-Content-Type-Options: nosniff');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\';');
}

function validate_password($password) {
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain at least one lowercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one number';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Password must contain at least one special character';
    }
    return true;
}

function validate_date($date) {
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $date);
    return $d && $d->format('Y-m-d H:i:s') === $date;
}

function is_future_date($date) {
    $date_obj = new DateTime($date);
    $now = new DateTime();
    return $date_obj > $now;
}

function sanitize_filename($filename) {
    // Remove any path components
    $filename = basename($filename);
    // Replace any non-alphanumeric characters except dots and dashes
    $filename = preg_replace('/[^a-zA-Z0-9.-]/', '_', $filename);
    // Ensure the filename is unique by adding a timestamp
    $info = pathinfo($filename);
    return $info['filename'] . '_' . time() . '.' . $info['extension'];
}

function rate_limit_check($key, $limit = 5, $window = 300) {
    // Use session-based rate limiting instead of Redis
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    
    $now = time();
    
    // Clean up old entries
    foreach ($_SESSION['rate_limits'] as $k => $data) {
        if ($data['timestamp'] < ($now - $window)) {
            unset($_SESSION['rate_limits'][$k]);
        }
    }
    
    // Check current key
    if (!isset($_SESSION['rate_limits'][$key])) {
        $_SESSION['rate_limits'][$key] = [
            'count' => 1,
            'timestamp' => $now
        ];
        return true;
    }
    
    // Update count if within window
    if ($_SESSION['rate_limits'][$key]['timestamp'] > ($now - $window)) {
        $_SESSION['rate_limits'][$key]['count']++;
        return $_SESSION['rate_limits'][$key]['count'] <= $limit;
    }
    
    // Reset if window expired
    $_SESSION['rate_limits'][$key] = [
        'count' => 1,
        'timestamp' => $now
    ];
    return true;
}
