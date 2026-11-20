<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/guard.php';

if (!is_logged_in()) {
    http_response_code(403);
    exit;
}

$user = current_user();
$name = basename((string)($_GET['f'] ?? ''));
$path = dirname(__DIR__, 2) . '/uploads/' . $name;

if (!preg_match('/^[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp)$/i', $name) || !is_file($path)) {
    http_response_code(404);
    exit;
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

if (($user['role'] ?? '') !== ROLE_ADMIN) {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM items
         WHERE photo_path = :p
           AND (status IN ('Approved','Matched','Claimed','Returned') OR user_id = :uid)
         LIMIT 1"
    );
    $stmt->execute([':p' => 'uploads/' . $name, ':uid' => (int)($user['user_id'] ?? $user['id'])]);
    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        exit;
    }
}

header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($path));
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($path);