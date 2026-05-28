<?php
declare(strict_types=1);

function build_analysis_snapshot(array $assets, array $metrics, array $alerts): string
{
    $lines = [
        'System date: ' . date('Y-m-d'),
        'Total assets: ' . $metrics['asset_count'],
        'Total PPE cost: ' . money($metrics['total_cost']),
        'Total accumulated depreciation: ' . money($metrics['total_accumulated']),
        'Total carrying amount: ' . money($metrics['total_carrying']),
        'Active assets: ' . $metrics['active_count'],
        'Fully depreciated assets: ' . $metrics['fully_depreciated_count'],
        'Assets near end of useful life: ' . $metrics['near_end_count'],
        'Assets with unusual records: ' . count($alerts['unusual']),
        '',
        'Top asset highlights:',
    ];

    $highlights = array_slice($assets, 0, 6);
    foreach ($highlights as $asset) {
        $lines[] = sprintf(
            '- %s (%s): status=%s, carrying=%s, remaining_years=%d, anomalies=%d',
            $asset['asset_name'],
            $asset['asset_code'],
            $asset['status'],
            money((float) $asset['carrying_amount']),
            (int) $asset['remaining_years'],
            (int) $asset['anomaly_count']
        );
    }

    if ($alerts['unusual'] !== []) {
        $lines[] = '';
        $lines[] = 'Potential unusual records:';

        foreach (array_slice($alerts['unusual'], 0, 8) as $asset) {
            $lines[] = '- ' . $asset['asset_name'] . ' (' . $asset['asset_code'] . '): ' . implode('; ', $asset['anomalies']);
        }
    }

    return implode("\n", $lines);
}

function generate_rule_based_analysis(array $assets, array $metrics, array $alerts): string
{
    if ($metrics['asset_count'] === 0) {
        return "No PPE records are available yet.\n\nAdd assets first so the system can generate a depreciation and condition analysis.";
    }

    $analysis = [];
    $analysis[] = '1. Overall PPE Summary';
    $analysis[] = 'The organization is tracking ' . $metrics['asset_count'] . ' PPE ' . pluralize($metrics['asset_count'], 'item', 'items') . ' with a total acquisition cost of ' . money($metrics['total_cost']) . '. The current carrying amount is ' . money($metrics['total_carrying']) . ' after recognizing ' . money($metrics['total_accumulated']) . ' in depreciation.';
    $analysis[] = '';
    $analysis[] = '2. Depreciation Analysis';
    $analysis[] = $metrics['fully_depreciated_count'] . ' ' . pluralize($metrics['fully_depreciated_count'], 'asset is', 'assets are') . ' fully depreciated, while ' . $metrics['near_end_count'] . ' ' . pluralize($metrics['near_end_count'], 'asset is', 'assets are') . ' close to the end of useful life.';

    if ($alerts['near_end'] !== []) {
        $analysis[] = 'Assets nearing replacement include:';
        foreach (array_slice($alerts['near_end'], 0, 4) as $asset) {
            $analysis[] = '- ' . $asset['asset_name'] . ' (' . $asset['asset_code'] . ') with ' . $asset['remaining_years'] . ' year(s) remaining.';
        }
    }

    $analysis[] = '';
    $analysis[] = '3. Record Quality Review';
    if ($alerts['unusual'] === []) {
        $analysis[] = 'No unusual PPE records were detected by the local rule-based checks.';
    } else {
        $analysis[] = count($alerts['unusual']) . ' record(s) need closer review. Common issues include assets that are fully depreciated but still active, or salvage values higher than cost.';
        foreach (array_slice($alerts['unusual'], 0, 4) as $asset) {
            $analysis[] = '- ' . $asset['asset_name'] . ': ' . implode('; ', $asset['anomalies']);
        }
    }

    $analysis[] = '';
    $analysis[] = '4. Recommendation';
    $analysis[] = 'Review fully depreciated active assets for possible disposal or continued service justification. Prioritize assets with one year or less remaining useful life for budget planning and replacement scheduling.';

    $analysis[] = '';
    $analysis[] = '5. Conclusion';
    $analysis[] = build_risk_summary($metrics, $alerts);

    return implode("\n", $analysis);
}

function get_openai_api_key(): string
{
    $candidates = [
        getenv('OPENAI_API_KEY') ?: '',
        $_ENV['OPENAI_API_KEY'] ?? '',
        $_SERVER['OPENAI_API_KEY'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }

    return '';
}

function extract_openai_output_text(array $response): string
{
    if (!empty($response['output_text']) && is_string($response['output_text'])) {
        return trim($response['output_text']);
    }

    $chunks = [];
    foreach (($response['output'] ?? []) as $item) {
        if (($item['type'] ?? '') !== 'message') {
            continue;
        }

        foreach (($item['content'] ?? []) as $contentPart) {
            if (($contentPart['type'] ?? '') === 'output_text' && !empty($contentPart['text'])) {
                $chunks[] = trim((string) $contentPart['text']);
            }
        }
    }

    return trim(implode("\n\n", array_filter($chunks)));
}

function generate_openai_analysis(string $snapshot): array
{
    $apiKey = get_openai_api_key();

    if ($apiKey === '') {
        return [
            'success' => false,
            'error' => 'OPENAI_API_KEY is not configured.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'error' => 'The PHP cURL extension is not enabled.',
        ];
    }

    $payload = [
        'model' => OPENAI_MODEL,
        'instructions' => 'You are an accounting assistant. Review PPE depreciation data and explain it in concise, practical accounting language. Provide: 1) overall PPE summary, 2) depreciation analysis, 3) unusual records, 4) replacement priorities, and 5) a short conclusion.',
        'input' => $snapshot,
    ];

    $curl = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
    ]);

    $rawResponse = curl_exec($curl);

    if ($rawResponse === false) {
        $error = curl_error($curl);
        curl_close($curl);

        return [
            'success' => false,
            'error' => 'OpenAI request error: ' . $error,
        ];
    }

    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    $decoded = json_decode($rawResponse, true);

    if ($statusCode >= 400) {
        $message = $decoded['error']['message'] ?? 'The OpenAI API returned an error.';

        return [
            'success' => false,
            'error' => 'OpenAI API error: ' . $message,
        ];
    }

    if (!is_array($decoded)) {
        return [
            'success' => false,
            'error' => 'OpenAI API returned an unreadable response.',
        ];
    }

    $text = extract_openai_output_text($decoded);

    if ($text === '') {
        return [
            'success' => false,
            'error' => 'OpenAI API returned no analysis text.',
        ];
    }

    return [
        'success' => true,
        'analysis_type' => 'OpenAI Responses API',
        'content' => $text,
    ];
}

function save_ai_analysis(PDO $pdo, int $userId, string $analysisType, string $analysisResult): void
{
    $statement = $pdo->prepare(
        'INSERT INTO ai_analysis (analysis_type, analysis_result, generated_by)
         VALUES (:analysis_type, :analysis_result, :generated_by)'
    );
    $statement->execute([
        'analysis_type' => $analysisType,
        'analysis_result' => $analysisResult,
        'generated_by' => $userId,
    ]);
}

function fetch_ai_analysis_history(PDO $pdo, int $limit = 10): array
{
    $limit = max(1, $limit);
    $statement = $pdo->prepare(
        'SELECT a.analysis_id, a.analysis_type, a.analysis_result, a.generated_at,
                u.full_name, u.role
         FROM ai_analysis a
         LEFT JOIN users u ON u.user_id = a.generated_by
         ORDER BY a.generated_at DESC
         LIMIT ' . $limit
    );
    $statement->execute();

    return $statement->fetchAll() ?: [];
}
