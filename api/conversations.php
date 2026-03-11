<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../src/env.php';
loadEnv(__DIR__ . '/../.env');

require_once __DIR__ . '/../src/auth.php';

$userId = requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $conversations = getUserConversations($userId);
    echo json_encode(['conversations' => $conversations]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conversation = createConversation($userId, 'New Chat');
    echo json_encode(['conversation' => $conversation]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);