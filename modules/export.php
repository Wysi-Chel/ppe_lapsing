<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

$pdo = db();
$type = trim((string) request_value('type', 'assets'));
$timestamp = date('Ymd-His');

switch ($type) {
    case 'assets':
        $assets = hydrate_assets_with_metrics(fetch_assets($pdo));
        $rows = [];

        foreach ($assets as $asset) {
            $rows[] = [
                $asset['asset_code'],
                $asset['asset_name'],
                (string) ($asset['category_name'] ?? 'Uncategorized'),
                asset_department_label($asset),
                (string) $asset['acquisition_date'],
                (string) $asset['status'],
                (string) $asset['condition'],
                number_format((float) $asset['acquisition_cost'], 2, '.', ''),
                number_format((float) ($asset['additional_amount'] ?? 0), 2, '.', ''),
                number_format(asset_total_cost($asset), 2, '.', ''),
                (string) $asset['useful_life'],
                number_format((float) $asset['annual_depreciation'], 2, '.', ''),
                number_format((float) $asset['accumulated_depreciation'], 2, '.', ''),
                number_format((float) $asset['carrying_amount'], 2, '.', ''),
                (string) $asset['remaining_years'],
                asset_location_label((string) ($asset['location'] ?? '')),
                (string) ($asset['remarks'] ?? ''),
            ];
        }

        download_excel(
            'ppe-assets-' . $timestamp . '.xlsx',
            [
                'Asset Code',
                'Asset Name',
                'Category',
                'Department',
                'Acquisition Date',
                'Status',
                'Condition',
                'Acquisition Cost',
                'Additional Amount',
                'Total Cost',
                'Useful Life',
                'Annual Depreciation',
                'Accumulated Depreciation',
                'Net Amount',
                'Remaining Years',
                'Location',
                'Remarks',
            ],
            $rows,
            'Asset Register'
        );

    case 'alerts':
        $assets = hydrate_assets_with_metrics(fetch_assets($pdo));
        $rows = [];

        foreach ($assets as $asset) {
            $labels = build_asset_alert_labels($asset);

            if ($labels === []) {
                continue;
            }

            $rows[] = [
                $asset['asset_code'],
                $asset['asset_name'],
                (string) ($asset['category_name'] ?? 'Uncategorized'),
                asset_department_label($asset),
                (string) $asset['status'],
                (string) $asset['condition'],
                (string) $asset['remaining_years'],
                number_format((float) $asset['carrying_amount'], 2, '.', ''),
                implode('; ', $labels),
                implode('; ', build_asset_alert_messages($asset)),
            ];
        }

        download_excel(
            'ppe-alerts-' . $timestamp . '.xlsx',
            [
                'Asset Code',
                'Asset Name',
                'Category',
                'Department',
                'Status',
                'Condition',
                'Remaining Years',
                'Net Amount',
                'Alert Types',
                'Review Notes',
            ],
            $rows,
            'Alerts Queue'
        );

    case 'transfers':
        $transfers = fetch_asset_transfers($pdo);
        $rows = [];

        foreach ($transfers as $transfer) {
            $rows[] = [
                (string) $transfer['transfer_date'],
                $transfer['asset_code'],
                $transfer['asset_name'],
                trim((string) ($transfer['from_department_name'] ?? '')) ?: 'Unassigned',
                asset_location_label((string) ($transfer['from_location'] ?? '')),
                trim((string) ($transfer['to_department_name'] ?? '')) ?: 'Unassigned',
                asset_location_label((string) ($transfer['to_location'] ?? '')),
                (string) ($transfer['transferred_by_name'] ?? 'System'),
                (string) ($transfer['notes'] ?? ''),
            ];
        }

        download_excel(
            'ppe-transfers-' . $timestamp . '.xlsx',
            [
                'Transfer Date',
                'Asset Code',
                'Asset Name',
                'From Department',
                'From Location',
                'To Department',
                'To Location',
                'Recorded By',
                'Notes',
            ],
            $rows,
            'Transfer History'
        );

    case 'depreciation_summary':
        $summaryYear = normalize_depreciation_summary_year(request_value('year', CURRENT_YEAR));
        $selectedCategoryId = (int) request_value('category_id', 0);
        $summaryFilters = $selectedCategoryId > 0 ? ['category_id' => $selectedCategoryId] : [];
        $summary = build_depreciation_summary(fetch_assets($pdo, $summaryFilters), $summaryYear);
        $rows = [];
        $number = static fn (mixed $value): string => number_format((float) $value, 2, '.', '');

        foreach ($summary['groups'] as $group) {
            $rows[] = array_merge(['CATEGORY: ' . $group['label']], array_fill(0, 26, ''));

            foreach ($group['rows'] as $summaryRow) {
                $row = [
                    $summaryRow['particulars'],
                    format_date((string) $summaryRow['acquisition_date'], 'm.d.Y'),
                    (string) $summaryRow['useful_life'],
                    $summaryRow['date_disposed_others'],
                    (string) $summaryRow['remaining_useful_months'],
                    $summaryRow['ref'],
                    $number($summaryRow['cost']),
                    $number($summaryRow['additions']),
                    $number($summaryRow['adjusted_cost']),
                    $number($summaryRow['monthly_depreciation']),
                    $number($summaryRow['book_value_prior']),
                    $number($summaryRow['beginning_accumulated_depreciation']),
                ];

                foreach (array_keys($summary['months']) as $monthNumber) {
                    $row[] = $number($summaryRow['months'][$monthNumber] ?? 0);
                }

                $row[] = $number($summaryRow['total_depreciation']);
                $row[] = $number($summaryRow['accumulated_depreciation']);
                $row[] = $number($summaryRow['book_value']);
                $rows[] = $row;
            }

            $totalRow = [
                'Total',
                '',
                '',
                '',
                '',
                (string) $group['total']['asset_count'] . ' asset(s)',
                $number($group['total']['cost']),
                $number($group['total']['additions']),
                $number($group['total']['adjusted_cost']),
                $number($group['total']['monthly_depreciation']),
                $number($group['total']['book_value_prior']),
                $number($group['total']['beginning_accumulated_depreciation']),
            ];

            foreach (array_keys($summary['months']) as $monthNumber) {
                $totalRow[] = $number($group['total']['months'][$monthNumber] ?? 0);
            }

            $totalRow[] = $number($group['total']['total_depreciation']);
            $totalRow[] = $number($group['total']['accumulated_depreciation']);
            $totalRow[] = $number($group['total']['book_value']);
            $rows[] = $totalRow;
        }

        $grandTotal = [
            $summary['total']['label'],
            '',
            '',
            '',
            '',
            (string) $summary['total']['asset_count'] . ' asset(s)',
            $number($summary['total']['cost']),
            $number($summary['total']['additions']),
            $number($summary['total']['adjusted_cost']),
            $number($summary['total']['monthly_depreciation']),
            $number($summary['total']['book_value_prior']),
            $number($summary['total']['beginning_accumulated_depreciation']),
        ];

        foreach (array_keys($summary['months']) as $monthNumber) {
            $grandTotal[] = $number($summary['total']['months'][$monthNumber] ?? 0);
        }

        $grandTotal[] = $number($summary['total']['total_depreciation']);
        $grandTotal[] = $number($summary['total']['accumulated_depreciation']);
        $grandTotal[] = $number($summary['total']['book_value']);
        $rows[] = $grandTotal;

        download_excel(
            'ppe-depreciation-summary-' . $summaryYear . '-' . $timestamp . '.xlsx',
            [
                'Particulars',
                'Acquired',
                'Est\'d Useful Life',
                'Date Disposed/Others',
                'Remaining Useful Life/In Mos.',
                'Ref.',
                'Cost',
                'Additions (Adjustments)',
                'Cost (Adjusted)',
                'Monthly Dep\'n',
                'Book Value ' . $summary['prior_book_value_label'],
                'Beginning Acc Dep\'n',
                'January',
                'February',
                'March',
                'April',
                'May',
                'June',
                'July',
                'August',
                'September',
                'October',
                'November',
                'December',
                'Total Depreciation',
                'Accum ' . $summary['accumulated_label'],
                'Book ' . $summary['book_value_label'],
            ],
            $rows,
            'Depreciation Summary'
        );

    case 'schedule':
        $assetId = (int) request_value('asset_id', 0);
        $asset = fetch_asset_by_id($pdo, $assetId);

        if (!$asset) {
            set_flash('danger', 'The selected asset schedule could not be exported.');
            redirect('modules/exports.php');
        }

        $schedule = build_asset_yearly_lapsing_rows($asset);
        $rows = [];
        $number = static fn (mixed $value): string => number_format((float) $value, 2, '.', '');

        foreach ($schedule as $row) {
            $exportRow = [
                $asset['asset_code'],
                $asset['asset_name'],
                (string) $row['year'],
                format_date((string) $row['acquisition_date'], 'm.d.Y'),
                (string) $row['useful_life'],
                (string) $row['remaining_useful_months'],
                $row['ref'],
                $number($row['cost']),
                $number($row['additions']),
                $number($row['adjusted_cost']),
                $number($row['monthly_depreciation']),
                $number($row['book_value_prior']),
                $number($row['beginning_accumulated_depreciation']),
            ];

            foreach (array_keys(depreciation_summary_months()) as $monthNumber) {
                $exportRow[] = $number($row['months'][$monthNumber] ?? 0);
            }

            $exportRow[] = $number($row['total_depreciation']);
            $exportRow[] = $number($row['accumulated_depreciation']);
            $exportRow[] = $number($row['book_value']);
            $rows[] = $exportRow;
        }

        download_excel(
            'ppe-schedule-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $asset['asset_code']) . '-' . $timestamp . '.xlsx',
            [
                'Asset Code',
                'Asset Name',
                'Year',
                'Acquired',
                'Useful Life',
                'Remaining Useful Life/In Mos.',
                'Ref.',
                'Cost',
                'Additions (Adjustments)',
                'Cost (Adjusted)',
                'Monthly Dep\'n',
                'Beginning Book Value',
                'Beginning Acc Dep\'n',
                'January',
                'February',
                'March',
                'April',
                'May',
                'June',
                'July',
                'August',
                'September',
                'October',
                'November',
                'December',
                'Total Depreciation',
                'Accum Dep\'n',
                'Book Value',
            ],
            $rows,
            'Schedule ' . (string) $asset['asset_code']
        );

    default:
        set_flash('danger', 'That export type is not available.');
        redirect('modules/exports.php');
}
