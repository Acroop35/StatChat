<?php
require_once __DIR__ . '/../src/env.php';
loadEnv(__DIR__ . '/../.env');

header('Content-Type: application/json');

require_once __DIR__ . '/../src/auth.php';

$userId = requireAuth();

$conversationId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;

if ($conversationId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'conversation_id is required']);
    exit;
}

if (!userOwnsConversation($userId, $conversationId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$messages = getConversationMessages($conversationId);

echo json_encode(['messages' => $messages]);