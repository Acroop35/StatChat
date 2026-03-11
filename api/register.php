<?php

require_once __DIR__ . '/../src/env.php';
loadEnv(__DIR__ . '/../.env');

header('Content-Type: application/json');

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

if (strlen($username) < 3 || strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Username must be 3+ chars and password 6+ chars']);
    exit;
}

if (findUserByUsername($username)) {
    http_response_code(409);
    echo json_encode(['error' => 'Username already exists']);
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$user = createUser($username, $passwordHash);

$_SESSION['user_id'] = $user['id'];

echo json_encode([
    'success' => true,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username']
    ]
]);