<?php

function callOpenAI(array $messages): string
{
    $response = callOpenAIWithTools($messages);
    return extractOpenAIText($response);
}

function callOpenAIWithTools(array $messages): array
{
    $payload = [
        'model' => 'gpt-4o',
        'input' => $messages,
        'tools' => [
            [
                'type' => 'function',
                'name' => 'get_sports_data',
                'description' => 'Gets sports data from API-Sports. Use this for NBA, NFL, college football, MLB, NHL, soccer, live scores, last games, schedules, team lookup, player lookup, standings, rosters, and sports stats. Always use this for current or recent sports questions.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'sport' => [
                            'type' => 'string',
                            'description' => 'Sport or league: nba, nfl, college_football, mlb, nhl, soccer'
                        ],
                        'request_type' => [
                            'type' => 'string',
                            'description' => 'Request type: live_scores, last_game, schedule, team_lookup, player_lookup, roster, standings, stats'
                        ],
                        'team' => [
                            'type' => 'string',
                            'description' => 'Team name, if relevant. Examples: Lakers, Eagles, Yankees, Flyers, Manchester City'
                        ],
                        'player' => [
                            'type' => 'string',
                            'description' => 'Player name, if relevant. Examples: LeBron James, Patrick Mahomes, Shohei Ohtani'
                        ],
                        'league' => [
                            'type' => 'string',
                            'description' => 'Optional league name for soccer or other sports. Examples: Premier League, MLS, Champions League'
                        ]
                    ],
                    'required' => ['sport', 'request_type']
                ]
            ]
        ]
    ];

    return openAIRequest($payload);
}

function callOpenAIWithToolResult(array $previousMessages, array $firstResponse, string $callId, array $toolResult): string
{
    $input = $previousMessages;

    foreach ($firstResponse['output'] ?? [] as $item) {
        $input[] = $item;
    }

    $input[] = [
        'type' => 'function_call_output',
        'call_id' => $callId,
        'output' => json_encode($toolResult)
    ];

    $payload = [
        'model' => 'gpt-4o',
        'input' => $input
    ];

    $finalResponse = openAIRequest($payload);

    return extractOpenAIText($finalResponse);
}

function openAIRequest(array $payload): array
{
    $apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');

    if (!$apiKey) {
        throw new Exception('OPENAI_API_KEY is not set in .env');
    }

    $ch = curl_init('https://api.openai.com/v1/responses');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 60,
    ]);

    $result = curl_exec($ch);

    if ($result === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('OpenAI cURL error: ' . $error);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($result, true);

    if ($httpCode >= 400) {
        $message = $data['error']['message'] ?? 'Unknown OpenAI API error';
        throw new Exception("OpenAI API error ({$httpCode}): {$message}");
    }

    return $data ?? [];
}

function extractOpenAIText(array $data): string
{
    if (!empty($data['output_text'])) {
        return $data['output_text'];
    }

    foreach ($data['output'] ?? [] as $outputItem) {
        foreach ($outputItem['content'] ?? [] as $contentItem) {
            if (($contentItem['type'] ?? '') === 'output_text' && isset($contentItem['text'])) {
                return $contentItem['text'];
            }
        }
    }

    return 'No response text returned.';
}

function getOpenAIToolCall(array $response): ?array
{
    foreach ($response['output'] ?? [] as $item) {
        if (($item['type'] ?? '') === 'function_call') {
            return $item;
        }
    }

    return null;
}