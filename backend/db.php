<?php
// ====================================================================
// Eanne Boarding House - Database Connection Helper (PDO MySQL)
// ====================================================================

function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = '127.0.0.1';
    $user = 'root';
    $pass = '';
    $dbname = 'eanne_boarding_house_db';

    try {
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // If DB doesn't exist yet, attempt automatic creation
        if ($e->getCode() === 1049) {
            require_once __DIR__ . '/../database/init_db.php';
            return getDbConnection();
        }

        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed: ' . $e->getMessage()
        ]);
        exit;
    }
}
