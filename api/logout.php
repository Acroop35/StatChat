<?php
require_once __DIR__ . '/../src/env.php';
loadEnv(__DIR__ . '/../.env');

header('Content-Type: application/json');

require_once __DIR__ . '/../src/auth.php';

startSessionIfNeeded();
session_unset();
session_destroy();

echo json_encode(['success' => true]);