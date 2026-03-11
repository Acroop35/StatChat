<?php

require_once __DIR__ . '/storage.php';

function startSessionIfNeeded(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function requireAuth(): int
{
    startSessionIfNeeded();

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    return (int)$_SESSION['user_id'];
}

function currentUser(): ?array
{
    startSessionIfNeeded();

    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return findUserById((int)$_SESSION['user_id']);
}