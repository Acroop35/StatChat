<?php

function callOpenAI(array $messages): string
{
    $apiKey = getenv('OPENAI_API_KEY');

    if (!$apiKey) {
        throw new Exception('OPENAI_API_KEY is not set.');
    }

    $payload = [
        'model' => 'gpt-4o',
        'input' => $messages
    ];

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
        throw new Exception('cURL error: ' . $error);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($result, true);

    if ($httpCode >= 400) {
        $message = $data['error']['message'] ?? 'Unknown OpenAI API error';
        throw new Exception("OpenAI API error ({$httpCode}): {$message}");
    }

    if (!empty($data['output_text'])) {
        return $data['output_text'];
    }

    if (!empty($data['output']) && is_array($data['output'])) {
        foreach ($data['output'] as $outputItem) {
            if (!empty($outputItem['content']) && is_array($outputItem['content'])) {
                foreach ($outputItem['content'] as $contentItem) {
                    if (($contentItem['type'] ?? '') === 'output_text' && isset($contentItem['text'])) {
                        return $contentItem['text'];
                    }
                }
            }
        }
    }

    return 'No response text returned.';
}