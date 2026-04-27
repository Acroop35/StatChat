<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/openai.php';
require_once __DIR__ . '/Tools/SportsTool.php';

class ChatController
{
    public function handle(): array
    {
        $userId = requireAuth();

        $input = json_decode(file_get_contents('php://input'), true);

        $conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
        $userMessage = trim($input['message'] ?? '');

        if ($conversationId <= 0 || $userMessage === '') {
            http_response_code(400);
            return ['error' => 'conversation_id and message are required'];
        }

        if (!userOwnsConversation($userId, $conversationId)) {
            http_response_code(403);
            return ['error' => 'Forbidden'];
        }

        $userMsg = addMessage($conversationId, 'user', $userMessage);
        updateConversationTitleIfDefault($conversationId, $userMessage);

        $history = array_slice(getConversationMessages($conversationId), -20);

        $messages = [
            [
                'role' => 'developer',
                'content' =>
    "You are StatChat, a helpful sports and statistics chatbot.

You MUST use the get_sports_data tool whenever the user asks about sports scores, last games, recent games, live games, schedules, standings, teams, or players.

For NBA teams, use sport='nba'.

Use request_type='last_game' when the user asks:
- last game
- most recent game
- previous game
- latest result

Use request_type='live_scores' when the user asks:
- live scores
- games right now
- current scores

Do not say you cannot access sports data unless the tool returns an actual error.

When tool data is returned, summarize the result clearly with:
- teams
- score
- date
- game status
- winner, if obvious."
            ]
        ];

        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }

        $firstResponse = callOpenAIWithTools($messages);
        $toolCall = getOpenAIToolCall($firstResponse);

        if ($toolCall) {
            $args = json_decode($toolCall['arguments'] ?? '{}', true);

            if (!is_array($args)) {
                $args = [];
            }

            $toolResult = getSportsData($args);

            $assistantReply = callOpenAIWithToolResult(
                $messages,
                $firstResponse,
                $toolCall['call_id'],
                $toolResult
            );
        } else {
            $assistantReply = extractOpenAIText($firstResponse);
        }

        $assistantMsg = addMessage($conversationId, 'assistant', $assistantReply);

        return [
            'reply' => $assistantReply,
            'user_message' => $userMsg,
            'assistant_message' => $assistantMsg
        ];
    }
}