<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

$pdo = db();
$type = trim((string) request_value('type', 'assets'));
$printedAt = date('M d, Y h:i A');
$title = '';
$assets = [];
$alerts = [];
$transfers = [];
$asset = null;
$schedule = [];
$metrics = null;
$summaryYear = CURRENT_YEAR;
$selectedCategoryId = 0;
$depreciationSummary = null;
$formatReportAmount = static function (float|int|string|null $value, bool $blankZero = false): string {
    $amount = round((float) $value, 2);

    if ($blankZero && abs($amount) < 0.005) {
        return '';
    }

    return number_format($amount, 2);
};

switch ($type) {
    case 'assets':
        $title = 'PPE Asset Register';
        $assets = hydrate_assets_with_metrics(fetch_assets($pdo));
        break;

    case 'alerts':
        $title = 'PPE Alert Digest';
        $assets = hydrate_assets_with_metrics(fetch_assets($pdo));
        $alerts = build_asset_alerts($assets);
        break;

    case 'transfers':
        $title = 'PPE Transfer History';
        $transfers = fetch_asset_transfers($pdo);
        break;

    case 'depreciation_summary':
        $summaryYear = normalize_depreciation_summary_year(request_value('year', CURRENT_YEAR));
        $selectedCategoryId = (int) request_value('category_id', 0);
        $summaryFilters = $selectedCategoryId > 0 ? ['category_id' => $selectedCategoryId] : [];
        $title = 'Lapsing Schedule of Property and Equipment - ' . $summaryYear;
        $depreciationSummary = build_depreciation_summary(fetch_assets($pdo, $summaryFilters), $summaryYear);
        break;

    case 'schedule':
        $assetId = (int) request_value('asset_id', 0);
        $asset = fetch_asset_by_id($pdo, $assetId);

        if (!$asset) {
            set_flash('danger', 'The selected asset schedule could not be printed.');
            redirect('modules/exports.php');
        }

        $title = 'Depreciation Schedule - ' . $asset['asset_code'];
        $schedule = build_asset_yearly_lapsing_rows($asset);
        $metrics = get_asset_metrics($asset);
        break;

    default:
        set_flash('danger', 'That print view is not available.');
        redirect('modules/exports.php');
}

$exportsBackPath = 'modules/exports.php';
if ($asset) {
    $exportsBackPath .= '?asset_id=' . (int) $asset['asset_id'];
} elseif ($type === 'depreciation_summary') {
    $exportsBackParams = ['year' => $summaryYear];
    if ($selectedCategoryId > 0) {
        $exportsBackParams['category_id'] = $selectedCategoryId;
    }
    $exportsBackPath .= '?' . http_build_query($exportsBackParams);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title . ' - ' . APP_NAME) ?></title>
    <style>
        :root {
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #efe8e0;
            color: #1d1d1d;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.45;
            padding: 24px;
        }

        .sheet {
            max-width: 1200px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #d8d1ca;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.08);
            padding: 32px;
        }

        .toolbar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border: 1px solid #c7bfb8;
            border-radius: 8px;
            color: #1d1d1d;
            text-decoration: none;
            background: #faf7f4;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        p {
            margin: 0 0 8px;
        }

        .meta {
            color: #666666;
            margin-bottom: 20px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin: 22px 0;
        }

        .summary-card {
            border: 1px solid #ddd5cf;
            padding: 14px;
        }

        .summary-card strong {
            display: block;
            margin-bottom: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
            font-size: 13px;
        }

        th,
        td {
            border: 1px solid #ddd5cf;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3eee9;
        }

        .section {
            margin-top: 28px;
        }

        .muted {
            color: #666666;
        }

        .report-fullscreen {
            background: #ffffff;
            padding: 0;
            overflow-x: hidden;
        }

        .report-fullscreen .sheet {
            width: 100vw;
            min-height: 100vh;
            max-width: none;
            border: 0;
            box-shadow: none;
            padding: 10px;
        }

        .report-fullscreen .toolbar {
            margin: 0 0 8px;
        }

        .report-fullscreen header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: end;
            gap: 12px;
            margin-bottom: 6px;
        }

        .report-fullscreen h1 {
            margin: 0;
            font-size: 16px;
        }

        .report-fullscreen header p {
            margin: 0;
            font-size: 10px;
        }

        .report-fullscreen .meta {
            margin: 0 0 6px;
            font-size: 10px;
        }

        .report-fullscreen .summary {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
            margin: 6px 0;
        }

        .report-fullscreen .summary-card {
            padding: 6px 8px;
            font-size: 10px;
        }

        .print-table-wrap {
            width: 100%;
            overflow: visible;
        }

        .depreciation-report-table {
            width: 100%;
            min-width: 0;
            table-layout: fixed;
            font-size: clamp(5.2px, 0.48vw, 7px);
            line-height: 1.12;
            margin-top: 6px;
        }

        .depreciation-report-table th,
        .depreciation-report-table td {
            padding: 2px 2px;
            text-align: center;
            white-space: normal;
            overflow-wrap: anywhere;
            vertical-align: middle;
        }

        .depreciation-report-table td:first-child,
        .depreciation-report-table th:first-child {
            text-align: left;
        }

        .report-category-row th {
            background: #e8edf7;
            color: #111111;
            text-transform: uppercase;
        }

        .report-total-row th,
        .report-grand-total-row th {
            background: #f7f2dc;
            color: #111111;
        }

        .report-grand-total-row th {
            border-top: 2px solid #111111;
        }

        .report-highlight-yellow {
            background: #fff45c !important;
        }

        .report-highlight-red {
            background: #f2c8c8 !important;
        }

        .report-highlight-green {
            background: #dfead4 !important;
        }

        .report-highlight-peach {
            background: #f4dccb !important;
        }

        @media print {
            @page {
                size: landscape;
                margin: 8mm;
            }

            body {
                background: #ffffff;
                padding: 0;
            }

            .sheet {
                max-width: none;
                border: 0;
                box-shadow: none;
                padding: 0;
            }

            .toolbar {
                display: none;
            }

            a {
                color: inherit;
                text-decoration: none;
            }
        }
    </style>
</head>
<body class="<?= $type === 'depreciation_summary' ? 'report-fullscreen' : '' ?>">
    <div class="sheet">
        <div class="toolbar">
            <a class="btn" href="javascript:window.print()">Print</a>
            <a class="btn" href="<?= e(base_url($exportsBackPath)) ?>">Back to Exports</a>
        </div>

        <header>
            <h1><?= e($title) ?></h1>
            <p><?= e(APP_NAME) ?></p>
            <p class="meta">Generated on <?= e($printedAt) ?></p>
        </header>

        <?php if ($type === 'assets'): ?>
            <div class="summary">
                <div class="summary-card">
                    <strong>Total assets</strong>
                    <span><?= e((string) count($assets)) ?></span>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Asset</th>
                        <th>Category</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Condition</th>
                        <th>Total Cost</th>
                        <th>Net Amount</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $assetRow): ?>
                        <tr>
                            <td>
                                <strong><?= e($assetRow['asset_name']) ?></strong><br>
                                <span class="muted"><?= e($assetRow['asset_code']) ?></span>
                            </td>
                            <td><?= e((string) ($assetRow['category_name'] ?? 'Uncategorized')) ?></td>
                            <td><?= e(asset_department_label($assetRow)) ?></td>
                            <td><?= e((string) $assetRow['status']) ?></td>
                            <td><?= e((string) $assetRow['condition']) ?></td>
                            <td><?= e(money(asset_total_cost($assetRow))) ?></td>
                            <td><?= e(money((float) $assetRow['carrying_amount'])) ?></td>
                            <td><?= e(asset_location_label((string) ($assetRow['location'] ?? ''))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ($type === 'alerts'): ?>
            <?php
            $flaggedAssetIds = [];
            foreach ($alerts as $group) {
                foreach ($group as $assetRow) {
                    $flaggedAssetIds[(int) $assetRow['asset_id']] = true;
                }
            }
            ?>
            <div class="summary">
                <div class="summary-card">
                    <strong>Flagged Assets</strong>
                    <span><?= e((string) count($flaggedAssetIds)) ?></span>
                </div>
                <div class="summary-card">
                    <strong>Near End of Life</strong>
                    <span><?= e((string) count($alerts['near_end'])) ?></span>
                </div>
                <div class="summary-card">
                    <strong>Fully Depreciated Active</strong>
                    <span><?= e((string) count($alerts['fully_depreciated_active'])) ?></span>
                </div>
                <div class="summary-card">
                    <strong>Data Quality Flags</strong>
                    <span><?= e((string) count($alerts['unusual'])) ?></span>
                </div>
            </div>

            <section class="section">
                <h2>Near-end assets</h2>
                <?php if ($alerts['near_end'] === []): ?>
                    <p class="muted">No assets are currently near the end of useful life.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Department</th>
                                <th>Remaining Years</th>
                                <th>Carrying Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alerts['near_end'] as $assetRow): ?>
                                <tr>
                                    <td><?= e($assetRow['asset_name'] . ' (' . $assetRow['asset_code'] . ')') ?></td>
                                    <td><?= e(asset_department_label($assetRow)) ?></td>
                                    <td><?= e((string) $assetRow['remaining_years']) ?></td>
                                    <td><?= e(money((float) $assetRow['carrying_amount'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section class="section">
                <h2>Fully depreciated but active</h2>
                <?php if ($alerts['fully_depreciated_active'] === []): ?>
                    <p class="muted">No active assets are fully depreciated at this time.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Carrying Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alerts['fully_depreciated_active'] as $assetRow): ?>
                                <tr>
                                    <td><?= e($assetRow['asset_name'] . ' (' . $assetRow['asset_code'] . ')') ?></td>
                                    <td><?= e(asset_department_label($assetRow)) ?></td>
                                    <td><?= e((string) $assetRow['status']) ?></td>
                                    <td><?= e(money((float) $assetRow['carrying_amount'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section class="section">
                <h2>Unusual records</h2>
                <?php if ($alerts['unusual'] === []): ?>
                    <p class="muted">No unusual records were detected by the current validation rules.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Condition</th>
                                <th>Review Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alerts['unusual'] as $assetRow): ?>
                                <tr>
                                    <td><?= e($assetRow['asset_name'] . ' (' . $assetRow['asset_code'] . ')') ?></td>
                                    <td><?= e((string) $assetRow['condition']) ?></td>
                                    <td><?= e(implode('; ', $assetRow['anomalies'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php elseif ($type === 'transfers'): ?>
            <div class="summary">
                <div class="summary-card">
                    <strong>Total transfer entries</strong>
                    <span><?= e((string) count($transfers)) ?></span>
                </div>
            </div>

            <?php if ($transfers === []): ?>
                <p class="muted">No transfers have been recorded yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Asset</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Recorded By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transfers as $transfer): ?>
                            <tr>
                                <td><?= e(format_date((string) $transfer['transfer_date'])) ?></td>
                                <td><?= e($transfer['asset_name'] . ' (' . $transfer['asset_code'] . ')') ?></td>
                                <td><?= e((trim((string) ($transfer['from_department_name'] ?? '')) ?: 'Unassigned') . ' / ' . asset_location_label((string) ($transfer['from_location'] ?? ''))) ?></td>
                                <td><?= e((trim((string) ($transfer['to_department_name'] ?? '')) ?: 'Unassigned') . ' / ' . asset_location_label((string) ($transfer['to_location'] ?? ''))) ?></td>
                                <td><?= e((string) ($transfer['transferred_by_name'] ?? 'System')) ?></td>
                                <td><?= e((string) ($transfer['notes'] ?: 'No notes')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php elseif ($type === 'depreciation_summary' && $depreciationSummary): ?>
            <p class="meta"><?= e($depreciationSummary['period_label']) ?></p>
            <div class="summary">
                <div class="summary-card">
                    <strong>Total assets</strong>
                    <span><?= e((string) $depreciationSummary['total']['asset_count']) ?></span>
                </div>
                <div class="summary-card">
                    <strong>Adjusted cost</strong>
                    <span><?= e(money((float) $depreciationSummary['total']['adjusted_cost'])) ?></span>
                </div>
                <div class="summary-card">
                    <strong>Total depreciation</strong>
                    <span><?= e(money((float) $depreciationSummary['total']['total_depreciation'])) ?></span>
                </div>
            </div>

            <div class="print-table-wrap">
                <table class="depreciation-report-table">
                    <colgroup>
                        <col style="width: 12%;">
                        <col style="width: 4%;">
                        <col style="width: 3%;">
                        <col style="width: 4%;">
                        <col style="width: 4%;">
                        <col style="width: 5%;">
                        <col style="width: 5%;">
                        <col style="width: 4%;">
                        <col style="width: 5%;">
                        <col style="width: 4%;">
                        <col style="width: 5%;">
                        <col style="width: 5%;">
                        <?php foreach ($depreciationSummary['months'] as $_monthName): ?>
                            <col style="width: 2.2%;">
                        <?php endforeach; ?>
                        <col style="width: 5%;">
                        <col style="width: 5%;">
                        <col style="width: 5%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th rowspan="2">Particulars</th>
                            <th rowspan="2">Acquired</th>
                            <th>Est'd</th>
                            <th>Date</th>
                            <th>Remaining</th>
                            <th rowspan="2">Ref.</th>
                            <th rowspan="2">Cost</th>
                            <th>Additions</th>
                            <th>Cost</th>
                            <th rowspan="2">Monthly<br>Dep'n</th>
                            <th class="report-highlight-yellow">Book Value</th>
                            <th class="report-highlight-yellow">Beginning</th>
                            <th colspan="3">1st Quarter</th>
                            <th colspan="3">2nd Quarter</th>
                            <th colspan="3">3rd Quarter</th>
                            <th colspan="3">4th Quarter</th>
                            <th class="report-highlight-red">Total</th>
                            <th class="report-highlight-green">Accum</th>
                            <th class="report-highlight-peach">Book</th>
                        </tr>
                        <tr>
                            <th>Useful<br>Life</th>
                            <th>Disposed/<br>Others</th>
                            <th>Useful Life/<br>In Mos.</th>
                            <th>(Adjustments)</th>
                            <th>(Adjusted)</th>
                            <th class="report-highlight-yellow"><?= e($depreciationSummary['prior_book_value_label']) ?></th>
                            <th class="report-highlight-yellow">Acc Dep'n</th>
                            <?php foreach ($depreciationSummary['months'] as $monthName): ?>
                                <th><?= e($monthName) ?></th>
                            <?php endforeach; ?>
                            <th class="report-highlight-red">Depreciation</th>
                            <th class="report-highlight-green"><?= e($depreciationSummary['accumulated_label']) ?></th>
                            <th class="report-highlight-peach"><?= e($depreciationSummary['book_value_label']) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($depreciationSummary['groups'] as $group): ?>
                            <tr class="report-category-row">
                                <th colspan="27"><?= e($group['label']) ?></th>
                            </tr>
                            <?php foreach ($group['rows'] as $summaryRow): ?>
                                <tr>
                                    <td><?= e($summaryRow['particulars']) ?></td>
                                    <td><?= e(format_date((string) $summaryRow['acquisition_date'], 'm.d.Y')) ?></td>
                                    <td><?= e((string) $summaryRow['useful_life']) ?></td>
                                    <td><?= e($summaryRow['date_disposed_others']) ?></td>
                                    <td><?= e((string) $summaryRow['remaining_useful_months']) ?></td>
                                    <td><?= e($summaryRow['ref']) ?></td>
                                    <td><?= e($formatReportAmount($summaryRow['cost'])) ?></td>
                                    <td><?= e($formatReportAmount($summaryRow['additions'], true)) ?></td>
                                    <td><?= e($formatReportAmount($summaryRow['adjusted_cost'])) ?></td>
                                    <td><?= e($formatReportAmount($summaryRow['monthly_depreciation'], true)) ?></td>
                                    <td><?= e($formatReportAmount($summaryRow['book_value_prior'], true)) ?></td>
                                    <td><?= e($formatReportAmount($summaryRow['beginning_accumulated_depreciation'], true)) ?></td>
                                    <?php foreach (array_keys($depreciationSummary['months']) as $monthNumber): ?>
                                        <td><?= e($formatReportAmount($summaryRow['months'][$monthNumber] ?? 0, true)) ?></td>
                                    <?php endforeach; ?>
                                    <td><?= e($formatReportAmount($summaryRow['total_depreciation'], true)) ?></td>
                                    <td><?= e($formatReportAmount($summaryRow['accumulated_depreciation'], true)) ?></td>
                                    <td><?= e($formatReportAmount($summaryRow['book_value'], true)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="report-total-row">
                                <th>Total</th>
                                <th colspan="5"><?= e((string) $group['total']['asset_count']) ?> asset<?= (int) $group['total']['asset_count'] === 1 ? '' : 's' ?></th>
                                <th><?= e($formatReportAmount($group['total']['cost'])) ?></th>
                                <th><?= e($formatReportAmount($group['total']['additions'], true)) ?></th>
                                <th><?= e($formatReportAmount($group['total']['adjusted_cost'])) ?></th>
                                <th><?= e($formatReportAmount($group['total']['monthly_depreciation'], true)) ?></th>
                                <th><?= e($formatReportAmount($group['total']['book_value_prior'], true)) ?></th>
                                <th><?= e($formatReportAmount($group['total']['beginning_accumulated_depreciation'], true)) ?></th>
                                <?php foreach (array_keys($depreciationSummary['months']) as $monthNumber): ?>
                                    <th><?= e($formatReportAmount($group['total']['months'][$monthNumber] ?? 0, true)) ?></th>
                                <?php endforeach; ?>
                                <th><?= e($formatReportAmount($group['total']['total_depreciation'], true)) ?></th>
                                <th><?= e($formatReportAmount($group['total']['accumulated_depreciation'], true)) ?></th>
                                <th><?= e($formatReportAmount($group['total']['book_value'], true)) ?></th>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="report-grand-total-row">
                            <th>
                                TOTAL<br>
                                <span class="muted"><?= e((string) $depreciationSummary['total']['asset_count']) ?> asset<?= (int) $depreciationSummary['total']['asset_count'] === 1 ? '' : 's' ?></span>
                            </th>
                            <th colspan="5"></th>
                            <th><?= e($formatReportAmount($depreciationSummary['total']['cost'])) ?></th>
                            <th><?= e($formatReportAmount($depreciationSummary['total']['additions'], true)) ?></th>
                            <th><?= e($formatReportAmount($depreciationSummary['total']['adjusted_cost'])) ?></th>
                            <th><?= e($formatReportAmount($depreciationSummary['total']['monthly_depreciation'], true)) ?></th>
                            <th><?= e($formatReportAmount($depreciationSummary['total']['book_value_prior'], true)) ?></th>
                            <th><?= e($formatReportAmount($depreciationSummary['total']['beginning_accumulated_depreciation'], true)) ?></th>
                            <?php foreach (array_keys($depreciationSummary['months']) as $monthNumber): ?>
                                <th><?= e($formatReportAmount($depreciationSummary['total']['months'][$monthNumber] ?? 0, true)) ?></th>
                            <?php endforeach; ?>
                            <th><?= e($formatReportAmount($depreciationSummary['total']['total_depreciation'], true)) ?></th>
                            <th><?= e($formatReportAmount($depreciationSummary['total']['accumulated_depreciation'], true)) ?></th>
                            <th><?= e($formatReportAmount($depreciationSummary['total']['book_value'], true)) ?></th>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php elseif ($type === 'schedule' && $asset && $metrics): ?>
            <div class="summary">
                <div class="summary-card">
                    <strong>Asset</strong>
                    <span><?= e($asset['asset_name']) ?></span>
                </div>
                <div class="summary-card">
                    <strong>Annual Depreciation</strong>
                    <span><?= e(money($metrics['annual_depreciation'])) ?></span>
                </div>
                <div class="summary-card">
                    <strong>Carrying Amount</strong>
                    <span><?= e(money($metrics['carrying_amount'])) ?></span>
                </div>
                <div class="summary-card">
                    <strong>Status</strong>
                    <span><?= e((string) $asset['status']) ?></span>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Year</th>
                        <th>Beginning Book</th>
                        <th>Beginning Acc Dep'n</th>
                        <?php foreach (depreciation_summary_months() as $monthName): ?>
                            <th><?= e($monthName) ?></th>
                        <?php endforeach; ?>
                        <th>Total Depreciation</th>
                        <th>Accum Dep'n</th>
                        <th>Book Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedule as $row): ?>
                        <tr>
                            <td><?= e((string) $row['year']) ?></td>
                            <td><?= e($formatReportAmount($row['book_value_prior'], true)) ?></td>
                            <td><?= e($formatReportAmount($row['beginning_accumulated_depreciation'], true)) ?></td>
                            <?php foreach (array_keys(depreciation_summary_months()) as $monthNumber): ?>
                                <td><?= e($formatReportAmount($row['months'][$monthNumber] ?? 0, true)) ?></td>
                            <?php endforeach; ?>
                            <td><?= e($formatReportAmount($row['total_depreciation'], true)) ?></td>
                            <td><?= e($formatReportAmount($row['accumulated_depreciation'], true)) ?></td>
                            <td><?= e($formatReportAmount($row['book_value'], true)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
