<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../src/env.php';
loadEnv(__DIR__ . '/../.env');

require_once __DIR__ . '/../src/ChatController.php';

try {
    $controller = new ChatController();
    echo json_encode($controller->handle());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}