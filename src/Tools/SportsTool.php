<?php

function callApiSports(string $baseUrl, string $endpoint, array $query = []): array
{
    $apiKey = $_ENV['APISPORTS_KEY'] ?? getenv('APISPORTS_KEY');

    if (!$apiKey) {
        throw new Exception('APISPORTS_KEY is not set in .env');
    }

    $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => ['x-apisports-key: ' . $apiKey],
        CURLOPT_TIMEOUT => 30,
    ]);

    $result = curl_exec($ch);

    if ($result === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('API-Sports cURL error: ' . $error);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($result, true);

    if ($httpCode >= 400) {
        throw new Exception('API-Sports error HTTP ' . $httpCode . ': ' . $result);
    }

    return $data ?? [];
}

function getSportsData(array $args): array
{
    $sport = normalizeSport($args['sport'] ?? '');
    $requestType = normalizeRequestType($args['request_type'] ?? '');
    $team = $args['team'] ?? null;
    $player = $args['player'] ?? null;
    $league = $args['league'] ?? null;

    switch ($sport) {
        case 'nba':
            return getNbaData($requestType, $team, $player);

        case 'nfl':
            return getAmericanFootballData($requestType, $team, $player, 'NFL');

        case 'ncaa':
        case 'college_football':
            return getAmericanFootballData($requestType, $team, $player, 'NCAA');

        case 'mlb':
        case 'baseball':
            return getGenericTeamGameSportData(
                'baseball',
                'https://v1.baseball.api-sports.io',
                $requestType,
                $team
            );

        case 'nhl':
        case 'hockey':
            return getGenericTeamGameSportData(
                'hockey',
                'https://v1.hockey.api-sports.io',
                $requestType,
                $team
            );

        case 'soccer':
            return getSoccerData($requestType, $team, $league);

        default:
            return [
                'error' => 'Sport not supported or not detected.',
                'requested_args' => $args,
                'supported_sports' => ['nba', 'nfl', 'ncaa', 'mlb', 'nhl', 'soccer']
            ];
    }
}

function normalizeSport(string $sport): string
{
    $sport = strtolower(trim($sport));

    $aliases = [
        'basketball' => 'nba',
        'nba basketball' => 'nba',

        'football' => 'nfl',
        'american football' => 'nfl',
        'pro football' => 'nfl',

        'college football' => 'college_football',
        'ncaaf' => 'college_football',

        'baseball' => 'mlb',
        'mlb baseball' => 'mlb',

        'hockey' => 'nhl',
        'ice hockey' => 'nhl',

        'premier league' => 'soccer',
        'champions league' => 'soccer',
        'mls' => 'soccer',
        'football/soccer' => 'soccer'
    ];

    return $aliases[$sport] ?? $sport;
}

function normalizeRequestType(string $requestType): string
{
    $requestType = strtolower(trim($requestType));

    $aliases = [
        'live_score' => 'live_scores',
        'current_scores' => 'live_scores',
        'games_right_now' => 'live_scores',

        'last_result' => 'last_game',
        'latest_result' => 'last_game',
        'previous_game' => 'last_game',
        'most_recent_game' => 'last_game',

        'recent_game' => 'last_game',
        'recent_games' => 'last_game',

        'lookup_team' => 'team_lookup',
        'lookup_player' => 'player_lookup'
    ];

    return $aliases[$requestType] ?? $requestType;
}

function currentSeasonYear(): string
{
    return (string)date('Y');
}

function getNbaSeason(): string
{
    $year = (int)date('Y');
    $month = (int)date('n');

    return $month >= 10 ? (string)$year : (string)($year - 1);
}

function getNbaData(string $requestType, ?string $team, ?string $player): array
{
    $baseUrl = 'https://v2.nba.api-sports.io';

    if ($requestType === 'live_scores') {
        return [
            'sport' => 'nba',
            'request_type' => 'live_scores',
            'games' => callApiSports($baseUrl, 'games', ['live' => 'all'])
        ];
    }

    if ($player) {
        return [
            'sport' => 'nba',
            'request_type' => 'player_lookup',
            'player_search' => callApiSports($baseUrl, 'players', ['search' => $player])
        ];
    }

    if (!$team) {
        return ['error' => 'Missing team or player for NBA request.'];
    }

    $teamResult = findTeamBySearch($baseUrl, 'teams', $team);

    if (!$teamResult['found']) {
        return [
            'error' => 'NBA team not found.',
            'searched_team' => $team
        ];
    }

    $teamData = $teamResult['team'];
    $teamId = (int)$teamData['id'];

    if ($requestType === 'team_lookup') {
        return [
            'sport' => 'nba',
            'request_type' => 'team_lookup',
            'team_found' => $teamData
        ];
    }

    if ($requestType === 'last_game') {
        return [
            'sport' => 'nba',
            'request_type' => 'last_game',
            'team_found' => $teamData,
            'last_game_result' => getLastCompletedGame($baseUrl, 'games', $teamId, [getNbaSeason(), (string)((int)getNbaSeason() - 1), '2023'])
        ];
    }

    if ($requestType === 'schedule') {
        return [
            'sport' => 'nba',
            'request_type' => 'schedule',
            'team_found' => $teamData,
            'games' => callApiSports($baseUrl, 'games', [
                'team' => $teamId,
                'season' => getNbaSeason()
            ])
        ];
    }

    return [
        'sport' => 'nba',
        'request_type' => $requestType,
        'team_found' => $teamData,
        'message' => 'NBA team lookup worked, but this request type is not implemented yet.'
    ];
}

function getAmericanFootballData(string $requestType, ?string $team, ?string $player, string $league): array
{
    $baseUrl = 'https://v1.american-football.api-sports.io';

    if ($requestType === 'live_scores') {
        return [
            'sport' => strtolower($league),
            'request_type' => 'live_scores',
            'games' => callApiSports($baseUrl, 'games', ['live' => 'all'])
        ];
    }

    if ($player) {
        return [
            'sport' => strtolower($league),
            'request_type' => 'player_lookup',
            'note' => 'Player lookup varies by API-Sports plan and endpoint support.',
            'player' => $player
        ];
    }

    if (!$team) {
        return ['error' => "Missing team for {$league} request."];
    }

    $teamResult = findTeamBySearch($baseUrl, 'teams', $team);

    if (!$teamResult['found']) {
        return [
            'error' => "{$league} team not found.",
            'searched_team' => $team
        ];
    }

    $teamData = $teamResult['team'];
    $teamId = (int)$teamData['id'];

    if ($requestType === 'team_lookup') {
        return [
            'sport' => strtolower($league),
            'request_type' => 'team_lookup',
            'team_found' => $teamData
        ];
    }

    if ($requestType === 'last_game') {
        return [
            'sport' => strtolower($league),
            'request_type' => 'last_game',
            'team_found' => $teamData,
            'last_game_result' => getLastCompletedGame($baseUrl, 'games', $teamId, [currentSeasonYear(), (string)((int)currentSeasonYear() - 1), (string)((int)currentSeasonYear() - 2)])
        ];
    }

    if ($requestType === 'schedule') {
        return [
            'sport' => strtolower($league),
            'request_type' => 'schedule',
            'team_found' => $teamData,
            'games' => callApiSports($baseUrl, 'games', [
                'team' => $teamId,
                'season' => currentSeasonYear()
            ])
        ];
    }

    return [
        'sport' => strtolower($league),
        'request_type' => $requestType,
        'team_found' => $teamData,
        'message' => "{$league} team lookup worked, but this request type is not implemented yet."
    ];
}

function getGenericTeamGameSportData(string $sport, string $baseUrl, string $requestType, ?string $team): array
{
    if ($requestType === 'live_scores') {
        return [
            'sport' => $sport,
            'request_type' => 'live_scores',
            'games' => callApiSports($baseUrl, 'games', ['live' => 'all'])
        ];
    }

    if (!$team) {
        return ['error' => "Missing team for {$sport} request."];
    }

    $teamResult = findTeamBySearch($baseUrl, 'teams', $team);

    if (!$teamResult['found']) {
        return [
            'error' => ucfirst($sport) . ' team not found.',
            'searched_team' => $team
        ];
    }

    $teamData = $teamResult['team'];
    $teamId = (int)$teamData['id'];

    if ($requestType === 'team_lookup') {
        return [
            'sport' => $sport,
            'request_type' => 'team_lookup',
            'team_found' => $teamData
        ];
    }

    if ($requestType === 'last_game') {
        return [
            'sport' => $sport,
            'request_type' => 'last_game',
            'team_found' => $teamData,
            'last_game_result' => getLastCompletedGame($baseUrl, 'games', $teamId, [currentSeasonYear(), (string)((int)currentSeasonYear() - 1), (string)((int)currentSeasonYear() - 2)])
        ];
    }

    if ($requestType === 'schedule') {
        return [
            'sport' => $sport,
            'request_type' => 'schedule',
            'team_found' => $teamData,
            'games' => callApiSports($baseUrl, 'games', [
                'team' => $teamId,
                'season' => currentSeasonYear()
            ])
        ];
    }

    return [
        'sport' => $sport,
        'request_type' => $requestType,
        'team_found' => $teamData,
        'message' => ucfirst($sport) . ' team lookup worked, but this request type is not implemented yet.'
    ];
}

function getSoccerData(string $requestType, ?string $team, ?string $league): array
{
    $baseUrl = 'https://v3.football.api-sports.io';

    if ($requestType === 'live_scores') {
        return [
            'sport' => 'soccer',
            'request_type' => 'live_scores',
            'fixtures' => callApiSports($baseUrl, 'fixtures', ['live' => 'all'])
        ];
    }

    if (!$team) {
        return ['error' => 'Missing soccer team.'];
    }

    $teamResult = findSoccerTeam($baseUrl, $team);

    if (!$teamResult['found']) {
        return [
            'error' => 'Soccer team not found.',
            'searched_team' => $team
        ];
    }

    $teamData = $teamResult['team'];
    $teamId = (int)$teamData['id'];

    if ($requestType === 'team_lookup') {
        return [
            'sport' => 'soccer',
            'request_type' => 'team_lookup',
            'team_found' => $teamData
        ];
    }

    if ($requestType === 'last_game') {
        $fixtures = callApiSports($baseUrl, 'fixtures', [
            'team' => $teamId,
            'last' => 10
        ]);

        $response = $fixtures['response'] ?? [];
        $response = sortSoccerFixturesNewestFirst($response);

        return [
            'sport' => 'soccer',
            'request_type' => 'last_game',
            'team_found' => $teamData,
            'fixtures' => [
                'results' => count($response) > 0 ? 1 : 0,
                'response' => array_slice($response, 0, 1),
                'raw' => $fixtures
            ]
        ];
    }

    if ($requestType === 'schedule') {
        return [
            'sport' => 'soccer',
            'request_type' => 'schedule',
            'team_found' => $teamData,
            'fixtures' => callApiSports($baseUrl, 'fixtures', [
                'team' => $teamId,
                'next' => 10
            ])
        ];
    }

    return [
        'sport' => 'soccer',
        'request_type' => $requestType,
        'team_found' => $teamData,
        'message' => 'Soccer team lookup worked, but this request type is not implemented yet.'
    ];
}

function findTeamBySearch(string $baseUrl, string $endpoint, string $team): array
{
    $searchTerms = getTeamSearchTerms($team);

    foreach ($searchTerms as $term) {
        $result = callApiSports($baseUrl, $endpoint, ['search' => $term]);

        if (!empty($result['response'][0])) {
            return [
                'found' => true,
                'team' => $result['response'][0],
                'search_term_used' => $term
            ];
        }
    }

    return [
        'found' => false,
        'team' => null,
        'search_terms_used' => $searchTerms
    ];
}

function findSoccerTeam(string $baseUrl, string $team): array
{
    $searchTerms = getTeamSearchTerms($team);

    foreach ($searchTerms as $term) {
        $result = callApiSports($baseUrl, 'teams', ['search' => $term]);

        if (!empty($result['response'][0]['team'])) {
            return [
                'found' => true,
                'team' => $result['response'][0]['team'],
                'search_term_used' => $term
            ];
        }
    }

    return [
        'found' => false,
        'team' => null,
        'search_terms_used' => $searchTerms
    ];
}

function getTeamSearchTerms(string $team): array
{
    $team = trim($team);
    $lower = strtolower($team);

    $aliases = [
        'sixers' => ['Philadelphia 76ers', '76ers', 'Philadelphia'],
        'mavs' => ['Dallas Mavericks', 'Mavericks'],

        'eagles' => ['Philadelphia Eagles', 'Eagles'],
        'cowboys' => ['Dallas Cowboys', 'Cowboys'],
        'chiefs' => ['Kansas City Chiefs', 'Chiefs'],
        'giants' => ['New York Giants', 'Giants'],
        'jets' => ['New York Jets', 'Jets'],

        'yankees' => ['New York Yankees', 'Yankees'],
        'phillies' => ['Philadelphia Phillies', 'Phillies'],
        'dodgers' => ['Los Angeles Dodgers', 'Dodgers'],

        'flyers' => ['Philadelphia Flyers', 'Flyers'],
        'rangers' => ['New York Rangers', 'Rangers'],

        'man city' => ['Manchester City', 'Man City'],
        'man united' => ['Manchester United', 'Man United'],
        'inter miami' => ['Inter Miami', 'Inter Miami CF']
    ];

    $terms = [$team];

    if (isset($aliases[$lower])) {
        $terms = array_merge($terms, $aliases[$lower]);
    }

    return array_values(array_unique($terms));
}

function getLastCompletedGame(string $baseUrl, string $endpoint, int $teamId, array $seasons): array
{
    foreach (array_values(array_unique($seasons)) as $season) {
        $gamesResponse = callApiSports($baseUrl, $endpoint, [
            'team' => $teamId,
            'season' => $season
        ]);

        $games = $gamesResponse['response'] ?? [];

        if (empty($games)) {
            continue;
        }

        $completed = filterCompletedGames($games);

        if (empty($completed)) {
            $completed = $games;
        }

        $completed = sortGamesNewestFirst($completed);

        return [
            'season_used' => $season,
            'game' => $completed[0],
            'raw_results_count' => count($games),
            'completed_results_count' => count($completed)
        ];
    }

    return [
        'error' => 'No games found for this team in tested seasons.',
        'seasons_tried' => $seasons
    ];
}

function filterCompletedGames(array $games): array
{
    return array_values(array_filter($games, function ($game) {
        $status = strtolower(
            $game['status']['long']
            ?? $game['game']['status']['long']
            ?? ''
        );

        return str_contains($status, 'finished') ||
               str_contains($status, 'ended') ||
               str_contains($status, 'after over time') ||
               str_contains($status, 'final');
    }));
}

function sortGamesNewestFirst(array $games): array
{
    usort($games, function ($a, $b) {
        $dateA = $a['date']['start'] ?? $a['game']['date']['date'] ?? $a['date'] ?? '';
        $dateB = $b['date']['start'] ?? $b['game']['date']['date'] ?? $b['date'] ?? '';

        return strtotime($dateB) <=> strtotime($dateA);
    });

    return $games;
}

function sortSoccerFixturesNewestFirst(array $fixtures): array
{
    usort($fixtures, function ($a, $b) {
        $dateA = $a['fixture']['date'] ?? '';
        $dateB = $b['fixture']['date'] ?? '';

        return strtotime($dateB) <=> strtotime($dateA);
    });

    return $fixtures;
}