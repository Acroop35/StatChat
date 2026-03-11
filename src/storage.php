<?php

function dataDir(): string
{
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function ensureDataFile(string $filename): string
{
    $path = dataDir() . '/' . $filename;

    if (!file_exists($path)) {
        file_put_contents($path, json_encode([], JSON_PRETTY_PRINT));
    }

    return $path;
}

function readJsonFile(string $filename): array
{
    $path = ensureDataFile($filename);
    $contents = file_get_contents($path);

    if ($contents === false || trim($contents) === '') {
        return [];
    }

    $data = json_decode($contents, true);
    return is_array($data) ? $data : [];
}

function writeJsonFile(string $filename, array $data): void
{
    $path = ensureDataFile($filename);
    $fp = fopen($path, 'c+');

    if (!$fp) {
        throw new Exception("Unable to open file: {$filename}");
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            throw new Exception("Unable to lock file: {$filename}");
        }

        ftruncate($fp, 0);
        rewind($fp);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new Exception("Unable to encode JSON for {$filename}");
        }

        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
    } finally {
        fclose($fp);
    }
}

function nextId(array $items): int
{
    $max = 0;
    foreach ($items as $item) {
        $id = (int)($item['id'] ?? 0);
        if ($id > $max) {
            $max = $id;
        }
    }
    return $max + 1;
}

function nowTimestamp(): string
{
    return date('Y-m-d H:i:s');
}

/* Users */

function getUsers(): array
{
    return readJsonFile('users.json');
}

function saveUsers(array $users): void
{
    writeJsonFile('users.json', $users);
}

function findUserByUsername(string $username): ?array
{
    $users = getUsers();
    foreach ($users as $user) {
        if (($user['username'] ?? '') === $username) {
            return $user;
        }
    }
    return null;
}

function findUserById(int $userId): ?array
{
    $users = getUsers();
    foreach ($users as $user) {
        if ((int)$user['id'] === $userId) {
            return $user;
        }
    }
    return null;
}

function createUser(string $username, string $passwordHash): array
{
    $users = getUsers();

    $user = [
        'id' => nextId($users),
        'username' => $username,
        'password_hash' => $passwordHash,
        'created_at' => nowTimestamp(),
    ];

    $users[] = $user;
    saveUsers($users);

    return $user;
}

/* Conversations */

function getConversations(): array
{
    return readJsonFile('conversations.json');
}

function saveConversations(array $conversations): void
{
    writeJsonFile('conversations.json', $conversations);
}

function getUserConversations(int $userId): array
{
    $conversations = array_values(array_filter(getConversations(), function ($c) use ($userId) {
        return (int)$c['user_id'] === $userId;
    }));

    usort($conversations, function ($a, $b) {
        return strcmp($b['updated_at'], $a['updated_at']);
    });

    return $conversations;
}

function findConversationById(int $conversationId): ?array
{
    $conversations = getConversations();
    foreach ($conversations as $conversation) {
        if ((int)$conversation['id'] === $conversationId) {
            return $conversation;
        }
    }
    return null;
}

function userOwnsConversation(int $userId, int $conversationId): bool
{
    $conversation = findConversationById($conversationId);
    if (!$conversation) {
        return false;
    }

    return (int)$conversation['user_id'] === $userId;
}

function createConversation(int $userId, string $title = 'New Chat'): array
{
    $conversations = getConversations();

    $conversation = [
        'id' => nextId($conversations),
        'user_id' => $userId,
        'title' => $title,
        'created_at' => nowTimestamp(),
        'updated_at' => nowTimestamp(),
    ];

    $conversations[] = $conversation;
    saveConversations($conversations);

    return $conversation;
}

function touchConversation(int $conversationId): void
{
    $conversations = getConversations();

    foreach ($conversations as &$conversation) {
        if ((int)$conversation['id'] === $conversationId) {
            $conversation['updated_at'] = nowTimestamp();
            break;
        }
    }

    saveConversations($conversations);
}

function updateConversationTitleIfDefault(int $conversationId, string $firstUserMessage): void
{
    $conversations = getConversations();

    foreach ($conversations as &$conversation) {
        if ((int)$conversation['id'] === $conversationId) {
            if (($conversation['title'] ?? 'New Chat') === 'New Chat') {
                $title = trim(mb_substr($firstUserMessage, 0, 40));
                $conversation['title'] = $title !== '' ? $title : 'New Chat';
            }
            $conversation['updated_at'] = nowTimestamp();
            break;
        }
    }

    saveConversations($conversations);
}

/* Messages */

function getMessages(): array
{
    return readJsonFile('messages.json');
}

function saveMessages(array $messages): void
{
    writeJsonFile('messages.json', $messages);
}

function getConversationMessages(int $conversationId): array
{
    $messages = array_values(array_filter(getMessages(), function ($m) use ($conversationId) {
        return (int)$m['conversation_id'] === $conversationId;
    }));

    usort($messages, function ($a, $b) {
        return (int)$a['id'] <=> (int)$b['id'];
    });

    return $messages;
}

function addMessage(int $conversationId, string $role, string $content): array
{
    $messages = getMessages();

    $message = [
        'id' => nextId($messages),
        'conversation_id' => $conversationId,
        'role' => $role,
        'content' => $content,
        'created_at' => nowTimestamp(),
    ];

    $messages[] = $message;
    saveMessages($messages);
    touchConversation($conversationId);

    return $message;
}