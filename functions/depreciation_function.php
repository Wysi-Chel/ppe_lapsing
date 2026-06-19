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
    if (defined('ASSET_CATEGORY_NAMES') && ASSET_CATEGORY_NAMES !== []) {
        $names = ASSET_CATEGORY_NAMES;
        $placeholders = implode(', ', array_fill(0, count($names), '?'));
        $statement = $pdo->prepare(
            'SELECT category_id, category_name
             FROM categories
             WHERE category_name IN (' . $placeholders . ')
             ORDER BY FIELD(category_name, ' . $placeholders . ')'
        );
        $statement->execute(array_merge($names, $names));

        return $statement->fetchAll() ?: [];
    }

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
        'salvage_value' => 0.0,
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

function calculate_annual_depreciation(float $cost, int $usefulLife): float
{
    if ($usefulLife <= 0 || $cost <= 0) {
        return 0.0;
    }

    return round($cost / $usefulLife, 2);
}

function schedule_display_net_value(array $asset, array $row): float
{
    return round(max((float) ($row['ending_value'] ?? 0), 0), 2);
}

function normalize_depreciation_summary_year(mixed $year): int
{
    $candidate = filter_var($year, FILTER_VALIDATE_INT);

    if ($candidate === false || $candidate < 1900 || $candidate > 2200) {
        return CURRENT_YEAR;
    }

    return (int) $candidate;
}

function depreciation_summary_period_label(int $year): string
{
    return 'AS OF DECEMBER 31, ' . $year;
}

function depreciation_summary_months(): array
{
    return [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];
}

function depreciation_summary_total_template(string $label): array
{
    return [
        'label' => $label,
        'asset_count' => 0,
        'cost' => 0.0,
        'additions' => 0.0,
        'adjusted_cost' => 0.0,
        'monthly_depreciation' => 0.0,
        'book_value_prior' => 0.0,
        'beginning_accumulated_depreciation' => 0.0,
        'months' => array_fill_keys(array_keys(depreciation_summary_months()), 0.0),
        'total_depreciation' => 0.0,
        'accumulated_depreciation' => 0.0,
        'book_value' => 0.0,
    ];
}

function depreciation_summary_add_to_total(array &$total, array $row): void
{
    $total['asset_count']++;

    foreach ([
        'cost',
        'additions',
        'adjusted_cost',
        'monthly_depreciation',
        'book_value_prior',
        'beginning_accumulated_depreciation',
        'total_depreciation',
        'accumulated_depreciation',
        'book_value',
    ] as $field) {
        $total[$field] += (float) ($row[$field] ?? 0);
    }

    foreach (array_keys(depreciation_summary_months()) as $monthNumber) {
        $total['months'][$monthNumber] += (float) ($row['months'][$monthNumber] ?? 0);
    }
}

function depreciation_summary_round_total(array $total): array
{
    foreach ([
        'cost',
        'additions',
        'adjusted_cost',
        'monthly_depreciation',
        'book_value_prior',
        'beginning_accumulated_depreciation',
        'total_depreciation',
        'accumulated_depreciation',
        'book_value',
    ] as $field) {
        $total[$field] = round((float) $total[$field], 2);
    }

    foreach (array_keys(depreciation_summary_months()) as $monthNumber) {
        $total['months'][$monthNumber] = round((float) ($total['months'][$monthNumber] ?? 0), 2);
    }

    return $total;
}

function calculate_depreciation_summary_asset(array $asset, int $year): array
{
    $cost = round((float) ($asset['acquisition_cost'] ?? 0), 2);
    $additions = round((float) ($asset['additional_amount'] ?? 0), 2);
    $adjustedCost = round($cost + $additions, 2);
    $usefulLife = (int) ($asset['useful_life'] ?? 0);
    $acquisitionTimestamp = strtotime((string) ($asset['acquisition_date'] ?? ''));
    $monthlyDepreciation = 0.0;
    $currentYearMonths = 0;
    $accumulatedMonths = 0;
    $beginningAccumulated = 0.0;
    $bookValuePrior = 0.0;
    $monthlyValues = array_fill_keys(array_keys(depreciation_summary_months()), 0.0);

    if ($adjustedCost > 0 && $usefulLife > 0 && $acquisitionTimestamp !== false) {
        $totalMonths = $usefulLife * 12;
        $monthlyDepreciation = $adjustedCost / $totalMonths;
        $acquisitionMonthIndex = ((int) date('Y', $acquisitionTimestamp) * 12) + ((int) date('n', $acquisitionTimestamp) - 1);
        $startMonthIndex = $acquisitionMonthIndex + 1;
        $endMonthIndex = $startMonthIndex + $totalMonths - 1;
        $yearStartMonthIndex = $year * 12;
        $yearEndMonthIndex = $yearStartMonthIndex + 11;
        $priorYearEndMonthIndex = $yearStartMonthIndex - 1;
        $existedBeforeYear = $acquisitionMonthIndex <= $priorYearEndMonthIndex;

        if ($existedBeforeYear) {
            $priorAccumulatedMonths = 0;
            $priorAccumulatedPeriodEnd = min($endMonthIndex, $priorYearEndMonthIndex);

            if ($priorAccumulatedPeriodEnd >= $startMonthIndex) {
                $priorAccumulatedMonths = ($priorAccumulatedPeriodEnd - $startMonthIndex) + 1;
            }

            $beginningAccumulated = min($adjustedCost, $monthlyDepreciation * $priorAccumulatedMonths);
            $bookValuePrior = max($adjustedCost - $beginningAccumulated, 0);
        }

        $currentPeriodStart = max($startMonthIndex, $yearStartMonthIndex);
        $currentPeriodEnd = min($endMonthIndex, $yearEndMonthIndex);
        if ($currentPeriodEnd >= $currentPeriodStart) {
            $currentYearMonths = ($currentPeriodEnd - $currentPeriodStart) + 1;
        }

        $accumulatedPeriodEnd = min($endMonthIndex, $yearEndMonthIndex);
        if ($accumulatedPeriodEnd >= $startMonthIndex) {
            $accumulatedMonths = ($accumulatedPeriodEnd - $startMonthIndex) + 1;
        }

        foreach (array_keys(depreciation_summary_months()) as $monthNumber) {
            $monthIndex = $yearStartMonthIndex + ($monthNumber - 1);

            if ($monthIndex < $startMonthIndex || $monthIndex > $endMonthIndex) {
                continue;
            }

            $accumulatedBeforeMonth = min(
                $adjustedCost,
                $monthlyDepreciation * max($monthIndex - $startMonthIndex, 0)
            );
            $remainingBeforeMonth = max($adjustedCost - $accumulatedBeforeMonth, 0);
            $monthlyValues[$monthNumber] = min($monthlyDepreciation, $remainingBeforeMonth);
        }
    }

    $totalDepreciation = array_sum($monthlyValues);
    $accumulated = min($adjustedCost, $beginningAccumulated + $totalDepreciation);
    $roundedMonthlyValues = [];
    foreach ($monthlyValues as $monthNumber => $value) {
        $roundedMonthlyValues[$monthNumber] = round((float) $value, 2);
    }

    return [
        'asset_id' => (int) ($asset['asset_id'] ?? 0),
        'asset_code' => (string) ($asset['asset_code'] ?? ''),
        'asset_name' => (string) ($asset['asset_name'] ?? ''),
        'particulars' => (string) ($asset['asset_name'] ?? ''),
        'category_name' => trim((string) ($asset['category_name'] ?? '')) ?: 'Uncategorized',
        'acquisition_date' => (string) ($asset['acquisition_date'] ?? ''),
        'useful_life' => $usefulLife,
        'date_disposed_others' => (($asset['status'] ?? '') !== 'Active') ? (string) ($asset['status'] ?? '') : '',
        'remaining_useful_months' => max(($usefulLife * 12) - $accumulatedMonths, 0),
        'ref' => (string) ($asset['asset_code'] ?? ''),
        'cost' => round($cost, 2),
        'additions' => round($additions, 2),
        'adjusted_cost' => round($adjustedCost, 2),
        'monthly_depreciation' => round($monthlyDepreciation, 2),
        'book_value_prior' => round($bookValuePrior, 2),
        'beginning_accumulated_depreciation' => round($beginningAccumulated, 2),
        'months' => $roundedMonthlyValues,
        'total_depreciation' => round($totalDepreciation, 2),
        'accumulated_depreciation' => round($accumulated, 2),
        'book_value' => round(max($adjustedCost - $accumulated, 0), 2),
        'current_year_months' => $currentYearMonths,
        'accumulated_months' => $accumulatedMonths,
    ];
}

function build_depreciation_summary(array $assets, int $year): array
{
    $groups = [];
    $total = depreciation_summary_total_template('TOTAL');
    $yearEndTimestamp = strtotime($year . '-12-31');

    foreach ($assets as $asset) {
        $acquisitionTimestamp = strtotime((string) ($asset['acquisition_date'] ?? ''));
        if ($acquisitionTimestamp !== false && $yearEndTimestamp !== false && $acquisitionTimestamp > $yearEndTimestamp) {
            continue;
        }

        $summaryAsset = calculate_depreciation_summary_asset($asset, $year);
        $key = $summaryAsset['category_name'];

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'label' => $key,
                'rows' => [],
                'total' => depreciation_summary_total_template('Total'),
            ];
        }

        $groups[$key]['rows'][] = $summaryAsset;
        depreciation_summary_add_to_total($groups[$key]['total'], $summaryAsset);
        depreciation_summary_add_to_total($total, $summaryAsset);
    }

    foreach ($groups as &$group) {
        usort(
            $group['rows'],
            static fn (array $left, array $right): int => strnatcasecmp((string) $left['particulars'], (string) $right['particulars'])
        );
        $group['total'] = depreciation_summary_round_total($group['total']);
    }
    unset($group);

    uasort(
        $groups,
        static fn (array $left, array $right): int => strnatcasecmp((string) $left['label'], (string) $right['label'])
    );

    $flatRows = [];
    foreach ($groups as $group) {
        foreach ($group['rows'] as $row) {
            $flatRows[] = $row;
        }
    }

    return [
        'year' => $year,
        'period_label' => depreciation_summary_period_label($year),
        'prior_book_value_label' => 'Dec-' . substr((string) ($year - 1), -2),
        'accumulated_label' => 'DEPN ' . $year,
        'book_value_label' => 'VALUE ' . $year,
        'months' => depreciation_summary_months(),
        'groups' => array_values($groups),
        'rows' => $flatRows,
        'total' => depreciation_summary_round_total($total),
    ];
}

function build_asset_yearly_lapsing_rows(array $asset): array
{
    $usefulLife = (int) ($asset['useful_life'] ?? 0);
    $acquisitionTimestamp = strtotime((string) ($asset['acquisition_date'] ?? ''));

    if ($usefulLife <= 0 || $acquisitionTimestamp === false) {
        return [];
    }

    $acquisitionYear = (int) date('Y', $acquisitionTimestamp);
    $acquisitionMonthIndex = ($acquisitionYear * 12) + ((int) date('n', $acquisitionTimestamp) - 1);
    $startMonthIndex = $acquisitionMonthIndex + 1;
    $endMonthIndex = $startMonthIndex + ($usefulLife * 12) - 1;
    $endYear = intdiv($endMonthIndex, 12);
    $rows = [];

    for ($year = $acquisitionYear; $year <= $endYear; $year++) {
        $row = calculate_depreciation_summary_asset($asset, $year);
        $row['year'] = $year;
        $rows[] = $row;
    }

    return $rows;
}

function generate_depreciation_schedule(array $asset): array
{
    $cost = asset_total_cost($asset);
    $usefulLife = (int) ($asset['useful_life'] ?? 0);
    $acquisitionDate = (string) ($asset['acquisition_date'] ?? '');

    if ($usefulLife <= 0 || strtotime($acquisitionDate) === false) {
        return [];
    }

    $acquisitionYear = (int) date('Y', strtotime($acquisitionDate));
    $schedule = [];

    foreach (build_asset_yearly_lapsing_rows($asset) as $row) {
        $year = (int) $row['year'];
        $beginningValue = $year === $acquisitionYear
            ? $cost
            : (float) $row['book_value_prior'];

        $schedule[] = [
            'depreciation_year' => $year,
            'beginning_value' => round($beginningValue, 2),
            'depreciation_expense' => round((float) $row['total_depreciation'], 2),
            'accumulated_depreciation' => round((float) $row['accumulated_depreciation'], 2),
            'ending_value' => round((float) $row['book_value'], 2),
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
    $usefulLife = max((int) ($asset['useful_life'] ?? 0), 0);
    $annualDepreciation = calculate_annual_depreciation($cost, $usefulLife);
    $schedule = generate_depreciation_schedule($asset);
    $evaluationSummary = calculate_depreciation_summary_asset($asset, $evaluationYear);
    $acquisitionYear = strtotime((string) ($asset['acquisition_date'] ?? '')) !== false
        ? (int) date('Y', strtotime((string) $asset['acquisition_date']))
        : CURRENT_YEAR;

    $selectedRow = null;
    foreach ($schedule as $row) {
        if ((int) $row['depreciation_year'] <= $evaluationYear) {
            $selectedRow = $row;
        }
    }

    if ($selectedRow === null && $schedule !== [] && $evaluationYear >= (int) end($schedule)['depreciation_year']) {
        $selectedRow = end($schedule);
    }

    $accumulated = $selectedRow['accumulated_depreciation'] ?? 0.0;
    $carryingAmount = $selectedRow['ending_value'] ?? $cost;
    $totalMonths = $usefulLife * 12;
    $elapsedMonths = $totalMonths > 0
        ? min(max((int) ($evaluationSummary['accumulated_months'] ?? 0), 0), $totalMonths)
        : 0;
    $remainingMonths = max($totalMonths - $elapsedMonths, 0);
    $elapsedYears = $totalMonths > 0 ? (int) floor($elapsedMonths / 12) : 0;
    $remainingYears = $totalMonths > 0 ? (int) ceil($remainingMonths / 12) : 0;
    $lifeUsedRatio = $totalMonths > 0 ? min($elapsedMonths / $totalMonths, 1) : 0.0;
    $isFullyDepreciated = $usefulLife > 0 && ($carryingAmount <= 0.01 || $elapsedMonths >= $totalMonths);

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
        'schedule_year_start' => $schedule[0]['depreciation_year'] ?? $acquisitionYear,
        'schedule_year_end' => $schedule !== [] ? end($schedule)['depreciation_year'] : $acquisitionYear,
    ];

    $metrics['anomalies'] = detect_asset_anomalies($asset, $metrics);
    $metrics['anomaly_count'] = count($metrics['anomalies']);

    return $metrics;
}

function detect_asset_anomalies(array $asset, array $metrics): array
{
    $anomalies = [];
    $cost = asset_total_cost($asset);
    $status = (string) ($asset['status'] ?? 'Active');

    if ((int) ($asset['useful_life'] ?? 0) <= 0) {
        $anomalies[] = 'Useful life is missing or invalid.';
    }

    if ($metrics['carrying_amount'] < -0.01) {
        $anomalies[] = 'Net amount dropped below zero.';
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
        $metrics['near_end_count'] += (isset($asset['remaining_years']) && (int) $asset['remaining_years'] <= 1 && empty($asset['is_fully_depreciated'])) ? 1 : 0;
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
        if (isset($asset['remaining_years']) && (int) $asset['remaining_years'] <= 1 && empty($asset['is_fully_depreciated'])) {
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

    usort(
        $alerts['fully_depreciated_active'],
        static fn (array $left, array $right): int => strcmp((string) $left['asset_name'], (string) $right['asset_name'])
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
