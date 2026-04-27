<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../src/env.php';
loadEnv(__DIR__ . '/../.env');

require_once __DIR__ . '/../src/Tools/SportsTool.php';

try {
    $tests = [
        'lakers_last_game' => getSportsData([
            'sport' => 'nba',
            'request_type' => 'last_game',
            'team' => 'Lakers'
        ]),

        'sixers_last_game' => getSportsData([
            'sport' => 'nba',
            'request_type' => 'last_game',
            'team' => 'Philadelphia 76ers'
        ]),

        'live_nba_scores' => getSportsData([
            'sport' => 'nba',
            'request_type' => 'live_scores'
        ])
    ];

    echo json_encode($tests, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}