<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../src/env.php';
loadEnv(__DIR__ . '/../.env');

require_once __DIR__ . '/../src/auth.php';

startSessionIfNeeded();

$input = json_decode(file_get_contents('php://input'), true);

$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required']);
    exit;
}

$user = findUserByUsername($username);

if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

$_SESSION['user_id'] = $user['id'];

echo json_encode([
    'success' => true,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username']
    ]
]);