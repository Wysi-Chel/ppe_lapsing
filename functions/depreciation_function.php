<?php
declare(strict_types=1);

function count_users(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function fetch_users(PDO $pdo): array
{
    $statement = $pdo->query('SELECT user_id, full_name, email, role, created_at FROM users ORDER BY created_at DESC');

    return $statement->fetchAll() ?: [];
}

function fetch_user_by_id(PDO $pdo, int $userId): ?array
{
    $statement = $pdo->prepare(
        'SELECT user_id, full_name, email, role, created_at
         FROM users
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $statement->execute(['user_id' => $userId]);
    $user = $statement->fetch();

    return $user ?: null;
}

function update_user_password(PDO $pdo, int $userId, string $password): void
{
    $statement = $pdo->prepare('UPDATE users SET password = :password WHERE user_id = :user_id');
    $statement->execute([
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'user_id' => $userId,
    ]);
}

function fetch_categories(PDO $pdo): array
{
    $statement = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name');

    return $statement->fetchAll() ?: [];
}

function fetch_departments(PDO $pdo): array
{
    $statement = $pdo->query('SELECT department_id, department_name FROM departments ORDER BY department_name');

    return $statement->fetchAll() ?: [];
}

function fetch_asset_lookup(PDO $pdo): array
{
    $statement = $pdo->query('SELECT asset_id, asset_code, asset_name FROM assets ORDER BY asset_name, asset_code');

    return $statement->fetchAll() ?: [];
}

function fetch_asset_by_id(PDO $pdo, int $assetId): ?array
{
    $statement = $pdo->prepare(
        'SELECT a.*, c.category_name, d.department_name
         FROM assets a
         LEFT JOIN categories c ON c.category_id = a.category_id
         LEFT JOIN departments d ON d.department_id = a.department_id
         WHERE a.asset_id = :asset_id
         LIMIT 1'
    );
    $statement->execute(['asset_id' => $assetId]);
    $asset = $statement->fetch();

    return $asset ?: null;
}

function fetch_assets(PDO $pdo, array $filters = []): array
{
    $sql = 'SELECT a.*, c.category_name, d.department_name
            FROM assets a
            LEFT JOIN categories c ON c.category_id = a.category_id
            LEFT JOIN departments d ON d.department_id = a.department_id
            WHERE 1=1';
    $params = [];

    if (!empty($filters['q'])) {
        $sql .= ' AND (
            a.asset_code LIKE :query
            OR a.asset_name LIKE :query
            OR COALESCE(a.location, \'\') LIKE :query
            OR COALESCE(c.category_name, \'\') LIKE :query
            OR COALESCE(d.department_name, \'\') LIKE :query
        )';
        $params['query'] = '%' . trim((string) $filters['q']) . '%';
    }

    if (!empty($filters['status'])) {
        $sql .= ' AND a.status = :status';
        $params['status'] = $filters['status'];
    }

    if (!empty($filters['category_id'])) {
        $sql .= ' AND a.category_id = :category_id';
        $params['category_id'] = (int) $filters['category_id'];
    }

    if (!empty($filters['department_id'])) {
        $sql .= ' AND a.department_id = :department_id';
        $params['department_id'] = (int) $filters['department_id'];
    }

    $sql .= ' ORDER BY a.acquisition_date DESC, a.asset_name ASC';

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll() ?: [];
}

function asset_total_cost(array $asset): float
{
    return round(
        (float) ($asset['acquisition_cost'] ?? 0) + (float) ($asset['additional_amount'] ?? 0),
        2
    );
}

function normalize_asset_payload(array $input): array
{
    return [
        'asset_code' => trim((string) ($input['asset_code'] ?? '')),
        'asset_name' => trim((string) ($input['asset_name'] ?? '')),
        'category_id' => ($input['category_id'] ?? '') !== '' ? (int) $input['category_id'] : null,
        'department_id' => ($input['department_id'] ?? '') !== '' ? (int) $input['department_id'] : null,
        'acquisition_date' => trim((string) ($input['acquisition_date'] ?? '')),
        'acquisition_cost' => (float) ($input['acquisition_cost'] ?? 0),
        'additional_amount' => (float) ($input['additional_amount'] ?? 0),
        'salvage_value' => (float) ($input['salvage_value'] ?? 0),
        'useful_life' => (int) ($input['useful_life'] ?? 0),
        'depreciation_method' => trim((string) ($input['depreciation_method'] ?? 'Straight-line')) ?: 'Straight-line',
        'location' => trim((string) ($input['location'] ?? '')),
        'status' => trim((string) ($input['status'] ?? 'Active')) ?: 'Active',
        'remarks' => trim((string) ($input['remarks'] ?? '')),
    ];
}

function validate_asset_payload(array $payload): array
{
    $errors = [];

    if ($payload['asset_code'] === '') {
        $errors[] = 'Asset code is required.';
    }

    if ($payload['asset_name'] === '') {
        $errors[] = 'Asset name is required.';
    }

    if ($payload['acquisition_date'] === '' || strtotime($payload['acquisition_date']) === false) {
        $errors[] = 'A valid acquisition date is required.';
    }

    if ($payload['acquisition_cost'] <= 0) {
        $errors[] = 'Acquisition cost must be greater than zero.';
    }

    if ($payload['additional_amount'] < 0) {
        $errors[] = 'Additional amount cannot be negative.';
    }

    if ($payload['salvage_value'] < 0) {
        $errors[] = 'Salvage value cannot be negative.';
    }

    if ($payload['salvage_value'] > ($payload['acquisition_cost'] + $payload['additional_amount'])) {
        $errors[] = 'Salvage value cannot exceed the total of acquisition cost and additional amount.';
    }

    if ($payload['useful_life'] <= 0) {
        $errors[] = 'Useful life must be at least 1 year.';
    }

    if (!in_array($payload['status'], ['Active', 'Disposed', 'Fully Depreciated'], true)) {
        $errors[] = 'Please select a valid status.';
    }

    return $errors;
}

function save_asset(PDO $pdo, array $payload, ?int $assetId = null): int
{
    $query = $assetId === null
        ? 'INSERT INTO assets (
                asset_code, asset_name, category_id, department_id, acquisition_date,
                acquisition_cost, additional_amount, salvage_value, useful_life, depreciation_method,
                location, status, remarks
            ) VALUES (
                :asset_code, :asset_name, :category_id, :department_id, :acquisition_date,
                :acquisition_cost, :additional_amount, :salvage_value, :useful_life, :depreciation_method,
                :location, :status, :remarks
            )'
        : 'UPDATE assets SET
                asset_code = :asset_code,
                asset_name = :asset_name,
                category_id = :category_id,
                department_id = :department_id,
                acquisition_date = :acquisition_date,
                acquisition_cost = :acquisition_cost,
                additional_amount = :additional_amount,
                salvage_value = :salvage_value,
                useful_life = :useful_life,
                depreciation_method = :depreciation_method,
                location = :location,
                status = :status,
                remarks = :remarks
            WHERE asset_id = :asset_id';

    $params = $payload;

    if ($assetId !== null) {
        $params['asset_id'] = $assetId;
    }

    try {
        $statement = $pdo->prepare($query);
        $statement->execute($params);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            throw new InvalidArgumentException('Asset code must be unique.');
        }

        throw $exception;
    }

    $savedAssetId = $assetId ?? (int) $pdo->lastInsertId();
    rebuild_depreciation_schedule($pdo, $savedAssetId);

    return $savedAssetId;
}

function delete_asset(PDO $pdo, int $assetId): void
{
    $statement = $pdo->prepare('DELETE FROM assets WHERE asset_id = :asset_id');
    $statement->execute(['asset_id' => $assetId]);
}

function calculate_annual_depreciation(float $cost, float $salvageValue, int $usefulLife): float
{
    if ($usefulLife <= 0 || $cost <= $salvageValue) {
        return 0.0;
    }

    return round(($cost - $salvageValue) / $usefulLife, 2);
}

function schedule_display_net_value(array $asset, array $row): float
{
    return round(
        max(
            (float) ($row['ending_value'] ?? 0) - (float) ($asset['salvage_value'] ?? 0),
            0
        ),
        2
    );
}

function generate_depreciation_schedule(array $asset): array
{
    $cost = asset_total_cost($asset);
    $salvageValue = (float) ($asset['salvage_value'] ?? 0);
    $usefulLife = (int) ($asset['useful_life'] ?? 0);
    $acquisitionDate = (string) ($asset['acquisition_date'] ?? '');

    if ($usefulLife <= 0 || strtotime($acquisitionDate) === false) {
        return [];
    }

    $acquisitionYear = (int) date('Y', strtotime($acquisitionDate));
    $depreciationStartYear = $acquisitionYear + 1;
    $depreciableBase = max($cost - $salvageValue, 0);
    $annualDepreciation = calculate_annual_depreciation($cost, $salvageValue, $usefulLife);
    $accumulated = 0.0;
    $schedule = [
        [
            'depreciation_year' => $acquisitionYear,
            'beginning_value' => round($cost, 2),
            'depreciation_expense' => 0.0,
            'accumulated_depreciation' => 0.0,
            'ending_value' => round($cost, 2),
        ],
    ];

    for ($index = 0; $index < $usefulLife; $index++) {
        $year = $depreciationStartYear + $index;
        $beginningValue = round($cost - $accumulated, 2);
        $remainingDepreciableBase = round($depreciableBase - $accumulated, 2);
        $expense = $index === ($usefulLife - 1)
            ? max($remainingDepreciableBase, 0)
            : min($annualDepreciation, max($remainingDepreciableBase, 0));

        $accumulated = round($accumulated + $expense, 2);
        $endingValue = round(max($cost - $accumulated, $salvageValue), 2);

        $schedule[] = [
            'depreciation_year' => $year,
            'beginning_value' => $beginningValue,
            'depreciation_expense' => round($expense, 2),
            'accumulated_depreciation' => $accumulated,
            'ending_value' => $endingValue,
        ];
    }

    return $schedule;
}

function depreciation_schedule_needs_refresh(array $storedRows, array $generatedSchedule): bool
{
    if (count($storedRows) !== count($generatedSchedule)) {
        return true;
    }

    foreach ($generatedSchedule as $index => $row) {
        $storedRow = $storedRows[$index] ?? null;

        if ($storedRow === null) {
            return true;
        }

        if ((int) $storedRow['depreciation_year'] !== (int) $row['depreciation_year']) {
            return true;
        }

        foreach (['beginning_value', 'depreciation_expense', 'accumulated_depreciation', 'ending_value'] as $key) {
            if (round((float) $storedRow[$key], 2) !== round((float) $row[$key], 2)) {
                return true;
            }
        }
    }

    return false;
}

function rebuild_depreciation_schedule(PDO $pdo, int $assetId): void
{
    $asset = fetch_asset_by_id($pdo, $assetId);

    if (!$asset) {
        throw new InvalidArgumentException('Asset record not found.');
    }

    $schedule = generate_depreciation_schedule($asset);
    $insert = $pdo->prepare(
        'INSERT INTO depreciation_schedule (
            asset_id, depreciation_year, beginning_value, depreciation_expense,
            accumulated_depreciation, ending_value
        ) VALUES (
            :asset_id, :depreciation_year, :beginning_value, :depreciation_expense,
            :accumulated_depreciation, :ending_value
        )'
    );

    $pdo->beginTransaction();

    try {
        $delete = $pdo->prepare('DELETE FROM depreciation_schedule WHERE asset_id = :asset_id');
        $delete->execute(['asset_id' => $assetId]);

        foreach ($schedule as $row) {
            $insert->execute([
                'asset_id' => $assetId,
                'depreciation_year' => $row['depreciation_year'],
                'beginning_value' => $row['beginning_value'],
                'depreciation_expense' => $row['depreciation_expense'],
                'accumulated_depreciation' => $row['accumulated_depreciation'],
                'ending_value' => $row['ending_value'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function fetch_depreciation_rows(PDO $pdo, int $assetId): array
{
    $asset = fetch_asset_by_id($pdo, $assetId);

    if (!$asset) {
        return [];
    }

    $statement = $pdo->prepare(
        'SELECT schedule_id, asset_id, depreciation_year, beginning_value, depreciation_expense,
                accumulated_depreciation, ending_value
         FROM depreciation_schedule
         WHERE asset_id = :asset_id
         ORDER BY depreciation_year'
    );
    $statement->execute(['asset_id' => $assetId]);
    $rows = $statement->fetchAll() ?: [];
    $generatedSchedule = generate_depreciation_schedule($asset);

    if ($rows === [] || depreciation_schedule_needs_refresh($rows, $generatedSchedule)) {
        rebuild_depreciation_schedule($pdo, $assetId);
        $statement->execute(['asset_id' => $assetId]);
        $rows = $statement->fetchAll() ?: [];
    }

    return $rows;
}

function get_asset_metrics(array $asset, ?int $year = null): array
{
    $evaluationYear = $year ?? CURRENT_YEAR;
    $cost = asset_total_cost($asset);
    $salvageValue = (float) ($asset['salvage_value'] ?? 0);
    $usefulLife = max((int) ($asset['useful_life'] ?? 0), 0);
    $annualDepreciation = calculate_annual_depreciation($cost, $salvageValue, $usefulLife);
    $schedule = generate_depreciation_schedule($asset);
    $acquisitionYear = strtotime((string) ($asset['acquisition_date'] ?? '')) !== false
        ? (int) date('Y', strtotime((string) $asset['acquisition_date']))
        : CURRENT_YEAR;
    $depreciationStartYear = $acquisitionYear + 1;

    $selectedRow = null;
    foreach ($schedule as $row) {
        if ((int) $row['depreciation_year'] <= $evaluationYear) {
            $selectedRow = $row;
        }
    }

    if ($selectedRow === null && $schedule !== [] && $evaluationYear >= (int) end($schedule)['depreciation_year']) {
        $selectedRow = end($schedule);
    }

    $elapsedYears = 0;
    if ($evaluationYear >= $depreciationStartYear && $usefulLife > 0) {
        $elapsedYears = min(($evaluationYear - $depreciationStartYear) + 1, $usefulLife);
    }

    $accumulated = $selectedRow['accumulated_depreciation'] ?? 0.0;
    $carryingAmount = $selectedRow['ending_value'] ?? $cost;
    $remainingYears = max($usefulLife - $elapsedYears, 0);
    $lifeUsedRatio = $usefulLife > 0 ? min($elapsedYears / $usefulLife, 1) : 0.0;
    $isFullyDepreciated = $usefulLife > 0 && ($carryingAmount <= ($salvageValue + 0.01) || $elapsedYears >= $usefulLife);

    $condition = 'Healthy';
    if ($isFullyDepreciated || $remainingYears === 0) {
        $condition = 'Critical';
    } elseif ($lifeUsedRatio >= 0.8 || $remainingYears <= 1) {
        $condition = 'Monitor';
    }

    $metrics = [
        'annual_depreciation' => $annualDepreciation,
        'accumulated_depreciation' => round((float) $accumulated, 2),
        'carrying_amount' => round((float) $carryingAmount, 2),
        'remaining_years' => $remainingYears,
        'elapsed_years' => $elapsedYears,
        'life_used_ratio' => round($lifeUsedRatio, 4),
        'is_fully_depreciated' => $isFullyDepreciated,
        'condition' => $condition,
        'schedule_rows' => $schedule,
        'schedule_year_start' => $schedule[0]['depreciation_year'] ?? $depreciationStartYear,
        'schedule_year_end' => $schedule !== [] ? end($schedule)['depreciation_year'] : $depreciationStartYear,
    ];

    $metrics['anomalies'] = detect_asset_anomalies($asset, $metrics);
    $metrics['anomaly_count'] = count($metrics['anomalies']);

    return $metrics;
}

function detect_asset_anomalies(array $asset, array $metrics): array
{
    $anomalies = [];
    $cost = asset_total_cost($asset);
    $salvageValue = (float) ($asset['salvage_value'] ?? 0);
    $status = (string) ($asset['status'] ?? 'Active');

    if ((int) ($asset['useful_life'] ?? 0) <= 0) {
        $anomalies[] = 'Useful life is missing or invalid.';
    }

    if ($salvageValue > $cost) {
        $anomalies[] = 'Salvage value is higher than the total asset cost.';
    }

    if ($metrics['carrying_amount'] < -0.01) {
        $anomalies[] = 'Carrying amount dropped below zero.';
    }

    if ($metrics['is_fully_depreciated'] && $status === 'Active') {
        $anomalies[] = 'Asset is fully depreciated but still marked as active.';
    }

    if ($cost <= 0) {
        $anomalies[] = 'Acquisition cost should be greater than zero.';
    }

    return $anomalies;
}

function hydrate_assets_with_metrics(array $assets, ?int $year = null): array
{
    foreach ($assets as &$asset) {
        $metrics = get_asset_metrics($asset, $year);
        $asset = array_merge($asset, $metrics);
    }
    unset($asset);

    return $assets;
}

function build_dashboard_metrics(array $assets): array
{
    $metrics = [
        'asset_count' => count($assets),
        'total_cost' => 0.0,
        'total_accumulated' => 0.0,
        'total_carrying' => 0.0,
        'active_count' => 0,
        'fully_depreciated_count' => 0,
        'near_end_count' => 0,
        'unusual_count' => 0,
    ];

    foreach ($assets as $asset) {
        $metrics['total_cost'] += asset_total_cost($asset);
        $metrics['total_accumulated'] += (float) ($asset['accumulated_depreciation'] ?? 0);
        $metrics['total_carrying'] += (float) ($asset['carrying_amount'] ?? 0);
        $metrics['active_count'] += (($asset['status'] ?? '') === 'Active') ? 1 : 0;
        $metrics['fully_depreciated_count'] += !empty($asset['is_fully_depreciated']) ? 1 : 0;
        $metrics['near_end_count'] += (!empty($asset['remaining_years']) && (int) $asset['remaining_years'] <= 1 && empty($asset['is_fully_depreciated'])) ? 1 : 0;
        $metrics['unusual_count'] += !empty($asset['anomaly_count']) ? 1 : 0;
    }

    foreach (['total_cost', 'total_accumulated', 'total_carrying'] as $key) {
        $metrics[$key] = round((float) $metrics[$key], 2);
    }

    return $metrics;
}

function build_category_summary(array $assets): array
{
    $summary = [];

    foreach ($assets as $asset) {
        $key = (string) ($asset['category_name'] ?? 'Uncategorized');

        if (!isset($summary[$key])) {
            $summary[$key] = [
                'label' => $key,
                'asset_count' => 0,
                'total_cost' => 0.0,
                'total_accumulated' => 0.0,
                'total_carrying' => 0.0,
            ];
        }

        $summary[$key]['asset_count']++;
        $summary[$key]['total_cost'] += asset_total_cost($asset);
        $summary[$key]['total_accumulated'] += (float) ($asset['accumulated_depreciation'] ?? 0);
        $summary[$key]['total_carrying'] += (float) ($asset['carrying_amount'] ?? 0);
    }

    usort($summary, static fn (array $left, array $right): int => $right['total_cost'] <=> $left['total_cost']);

    return $summary;
}

function build_department_summary(array $assets): array
{
    $summary = [];

    foreach ($assets as $asset) {
        $key = (string) ($asset['department_name'] ?? 'Unassigned');

        if (!isset($summary[$key])) {
            $summary[$key] = [
                'label' => $key,
                'asset_count' => 0,
                'total_cost' => 0.0,
                'total_carrying' => 0.0,
            ];
        }

        $summary[$key]['asset_count']++;
        $summary[$key]['total_cost'] += asset_total_cost($asset);
        $summary[$key]['total_carrying'] += (float) ($asset['carrying_amount'] ?? 0);
    }

    usort($summary, static fn (array $left, array $right): int => $right['total_cost'] <=> $left['total_cost']);

    return $summary;
}

function build_asset_alerts(array $assets): array
{
    $alerts = [
        'near_end' => [],
        'fully_depreciated_active' => [],
        'unusual' => [],
    ];

    foreach ($assets as $asset) {
        if (!empty($asset['remaining_years']) && (int) $asset['remaining_years'] <= 1 && empty($asset['is_fully_depreciated'])) {
            $alerts['near_end'][] = $asset;
        }

        if (!empty($asset['is_fully_depreciated']) && ($asset['status'] ?? '') === 'Active') {
            $alerts['fully_depreciated_active'][] = $asset;
        }

        if (!empty($asset['anomaly_count'])) {
            $alerts['unusual'][] = $asset;
        }
    }

    usort(
        $alerts['near_end'],
        static fn (array $left, array $right): int => ($left['remaining_years'] <=> $right['remaining_years']) ?: strcmp((string) $left['asset_name'], (string) $right['asset_name'])
    );

    usort(
        $alerts['unusual'],
        static fn (array $left, array $right): int => ($right['anomaly_count'] <=> $left['anomaly_count']) ?: strcmp((string) $left['asset_name'], (string) $right['asset_name'])
    );

    return $alerts;
}

function build_risk_summary(array $metrics, array $alerts): string
{
    if ($metrics['asset_count'] === 0) {
        return 'No PPE records are in the system yet, so there are no depreciation risks to summarize.';
    }

    $parts = [
        money($metrics['total_cost']) . ' total PPE cost is currently being monitored.',
    ];

    if ($metrics['near_end_count'] > 0) {
        $parts[] = $metrics['near_end_count'] . ' ' . pluralize($metrics['near_end_count'], 'asset is', 'assets are') . ' close to the end of useful life.';
    }

    if ($metrics['fully_depreciated_count'] > 0) {
        $parts[] = $metrics['fully_depreciated_count'] . ' ' . pluralize($metrics['fully_depreciated_count'], 'asset is', 'assets are') . ' already fully depreciated.';
    }

    if (count($alerts['unusual']) > 0) {
        $parts[] = count($alerts['unusual']) . ' ' . pluralize(count($alerts['unusual']), 'record needs', 'records need') . ' closer review for possible data issues.';
    }

    return implode(' ', $parts);
}
