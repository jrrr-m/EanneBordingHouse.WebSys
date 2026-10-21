<?php
// ====================================================================
// Eanne Boarding House - Authentication API (Session + MySQL)
// Authenticates credentials against the users table.
// ====================================================================

session_start();
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo = getDbConnection();

// Check if checking session status
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check'])) {
    if (isset($_SESSION['user'])) {
        echo json_encode([
            'authenticated' => true,
            'user' => $_SESSION['user']
        ]);
    } else {
        echo json_encode([
            'authenticated' => false
        ]);
    }
    exit;
}

// Handle Logout
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['logout'])) {
    session_destroy();
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully.'
    ]);
    exit;
}

// Handle Login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please enter both username and password.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Check password (plain comparison or password_verify if hashed)
        $valid = false;
        if ($user) {
            if ($password === $user['password'] || password_verify($password, $user['password'])) {
                $valid = true;
            }
        }

        if (!$valid) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid username or password.']);
            exit;
        }

        // Store session
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'full_name' => $user['full_name'],
            'unit_code' => $user['unit_code'],
            'monthly_rent' => floatval($user['monthly_rent']),
            'phone' => $user['phone'],
            'email' => $user['email']
        ];

        $redirect = ($user['role'] === 'admin') 
            ? 'admin-dashboard.php' 
            : 'tenant-dashboard.php';

        echo json_encode([
            'success' => true,
            'message' => 'Login successful! Redirecting...',
            'role' => $user['role'],
            'redirect' => $redirect,
            'user' => $_SESSION['user']
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
