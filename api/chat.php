<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/env.php';
loadEnv(__DIR__ . '/../.env');

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/openai.php';

$userId = requireAuth();

$input = json_decode(file_get_contents('php://input'), true);

$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
$userMessage = trim($input['message'] ?? '');

if ($conversationId <= 0 || $userMessage === '') {
    http_response_code(400);
    echo json_encode(['error' => 'conversation_id and message are required']);
    exit;
}

if (!userOwnsConversation($userId, $conversationId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

/* Save user message */
$userMsg = addMessage($conversationId, 'user', $userMessage);

/* If title is still default, set it from first message */
updateConversationTitleIfDefault($conversationId, $userMessage);

/* Load recent history */
$history = getConversationMessages($conversationId);
$history = array_slice($history, -20);

/* Build Responses API input */
$messages = [
    [
        'role' => 'developer',
        'content' => 'You are a helpful chatbot for this web application. Keep responses clear and useful.'
    ]
];

foreach ($history as $msg) {
    $messages[] = [
        'role' => $msg['role'],
        'content' => $msg['content']
    ];
}

try {
    $assistantReply = callOpenAI($messages);
    $assistantMsg = addMessage($conversationId, 'assistant', $assistantReply);

    echo json_encode([
        'reply' => $assistantReply,
        'user_message' => $userMsg,
        'assistant_message' => $assistantMsg
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}