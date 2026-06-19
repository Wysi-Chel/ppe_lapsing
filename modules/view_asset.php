<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

$pdo = db();
$assetId = (int) request_value('asset_id', 0);
$asset = fetch_asset_by_id($pdo, $assetId);

if (!$asset) {
    set_flash('danger', 'The requested asset could not be found.');
    redirect('modules/assets.php');
}

$metrics = get_asset_metrics($asset);
$yearlyLapsingRows = build_asset_yearly_lapsing_rows($asset);
$reportMonths = depreciation_summary_months();
$formatReportAmount = static function (float|int|string|null $value, bool $blankZero = false): string {
    $amount = round((float) $value, 2);

    if ($blankZero && abs($amount) < 0.005) {
        return '';
    }

    return number_format($amount, 2);
};
$transferHistory = fetch_asset_transfers($pdo, $assetId, 6);

$pageTitle = 'View Asset';
$pageHeading = $asset['asset_name'];
$pageDescription = 'Review the complete PPE profile, depreciation path, and any record-quality flags tied to this item.';

require_once APP_ROOT . '/includes/header.php';
?>
<div class="page-actions mb-4">
    <a class="btn btn-outline-light" href="<?= e(base_url('modules/assets.php')) ?>">
        <i class="bi bi-arrow-left me-2"></i>Back to Assets
    </a>
    <a class="btn btn-outline-light" href="<?= e(base_url('modules/depreciation.php')) ?>">Open Depreciation Preview</a>
    <?php if (can_manage_assets()): ?>
        <a class="btn btn-outline-light" href="<?= e(base_url('modules/transfers.php?asset_id=' . $assetId)) ?>">
            <i class="bi bi-arrow-left-right me-2"></i>Transfer Asset
        </a>
        <a class="btn btn-warning" href="<?= e(base_url('modules/edit_asset.php?asset_id=' . $assetId)) ?>">
            <i class="bi bi-pencil-square me-2"></i>Edit Asset
        </a>
    <?php endif; ?>
</div>

<div class="metric-grid mb-4">
    <section class="metric-card">
        <p class="metric-label mb-2">Acquisition Price</p>
        <h2 class="metric-value mb-1"><?= e(money((float) $asset['acquisition_cost'])) ?></h2>
        <p class="metric-meta mb-0">Purchased on <?= e(format_date((string) $asset['acquisition_date'])) ?></p>
    </section>
    <section class="metric-card">
        <p class="metric-label mb-2">Useful Life</p>
        <h2 class="metric-value mb-1"><?= e((string) $asset['useful_life']) ?> years</h2>
        <p class="metric-meta mb-0">Applied depreciation period</p>
    </section>
    <section class="metric-card">
        <p class="metric-label mb-2">Depreciation Expense</p>
        <h2 class="metric-value mb-1"><?= e(money($metrics['annual_depreciation'])) ?></h2>
        <p class="metric-meta mb-0">Full-year depreciation rate</p>
    </section>
    <section class="metric-card">
        <p class="metric-label mb-2">Net Book Value</p>
        <h2 class="metric-value mb-1"><?= e(money($metrics['carrying_amount'])) ?></h2>
        <p class="metric-meta mb-0">Cost less accumulated depreciation</p>
    </section>
</div>

<div class="row g-4 align-items-stretch mb-4">
    <div class="col-xl-5 col-lg-6">
        <section class="shell-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="section-title mb-0">Asset profile</h2>
                <span class="badge <?= e(status_badge_class((string) $asset['status'])) ?>"><?= e($asset['status']) ?></span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <tbody>
                        <tr><th>Asset code</th><td><?= e($asset['asset_code']) ?></td></tr>
                        <tr><th>Category</th><td><?= e((string) ($asset['category_name'] ?? 'Uncategorized')) ?></td></tr>
                        <tr><th>Department</th><td><?= e((string) ($asset['department_name'] ?? 'Unassigned')) ?></td></tr>
                        <tr><th>Location</th><td><?= e((string) ($asset['location'] ?? 'Not specified')) ?></td></tr>
                        <tr><th>Additional</th><td><?= e(money((float) ($asset['additional_amount'] ?? 0))) ?></td></tr>
                        <tr><th>Useful life</th><td><?= e((string) $asset['useful_life']) ?> years</td></tr>
                        <tr><th>Remarks</th><td><?= e((string) ($asset['remarks'] ?: 'None')) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

    </div>

    <div class="col-xl-7 col-lg-6 d-flex flex-column gap-4">
        <section class="shell-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="section-title mb-0">Condition check</h2>
                <span class="badge <?= e(condition_badge_class((string) $metrics['condition'])) ?>"><?= e($metrics['condition']) ?></span>
            </div>
            <p class="text-soft mb-2">Useful life consumed</p>
            <div class="progress mb-3" role="progressbar" aria-valuenow="<?= e((string) round($metrics['life_used_ratio'] * 100)) ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar bg-info" style="width: <?= e((string) round($metrics['life_used_ratio'] * 100)) ?>%"></div>
            </div>
            <?php if ($metrics['anomalies'] === []): ?>
                <p class="mb-0">No unusual record issues were detected for this asset.</p>
            <?php else: ?>
                <div class="list-panel">
                    <?php foreach ($metrics['anomalies'] as $anomaly): ?>
                        <div class="list-row">
                            <strong>Review item</strong>
                            <p class="text-soft small mb-0"><?= e($anomaly) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="shell-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="section-title mb-1">Transfer history</h2>
                    <p class="section-copy mb-0">Recent department and location changes for this asset.</p>
                </div>
                <?php if (can_manage_assets()): ?>
                    <a class="btn btn-sm btn-outline-light" href="<?= e(base_url('modules/transfers.php?asset_id=' . $assetId)) ?>">Open Transfers</a>
                <?php endif; ?>
            </div>

            <?php if ($transferHistory === []): ?>
                <div class="empty-state">No transfers have been recorded for this asset yet.</div>
            <?php else: ?>
                <div class="list-panel">
                    <?php foreach ($transferHistory as $transfer): ?>
                        <?php
                        $fromDepartment = trim((string) ($transfer['from_department_name'] ?? '')) ?: 'Unassigned';
                        $toDepartment = trim((string) ($transfer['to_department_name'] ?? '')) ?: 'Unassigned';
                        ?>
                        <div class="list-row">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <strong><?= e(format_date((string) $transfer['transfer_date'])) ?></strong>
                                <span class="text-soft small"><?= e((string) ($transfer['transferred_by_name'] ?? 'System')) ?></span>
                            </div>
                            <p class="text-soft small mb-1">
                                <?= e($fromDepartment . ' / ' . asset_location_label((string) ($transfer['from_location'] ?? ''))) ?>
                            </p>
                            <p class="mb-1">
                                <?= e($toDepartment . ' / ' . asset_location_label((string) ($transfer['to_location'] ?? ''))) ?>
                            </p>
                            <p class="text-soft small mb-0"><?= e((string) ($transfer['notes'] ?: 'No transfer notes')) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<section class="shell-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-2">Generated schedule</p>
                    <h2 class="section-title mb-1">Yearly lapsing table</h2>
                    <p class="section-copy mb-0">Each year follows the report format: beginning balances, monthly depreciation, total depreciation, accumulated depreciation, and book value.</p>
                </div>
                <div class="stack-inline">
                    <a class="btn btn-sm btn-outline-light" href="<?= e(base_url('modules/export.php?type=schedule&asset_id=' . $assetId)) ?>">Export Excel</a>
                    <a class="btn btn-sm btn-outline-light" href="<?= e(base_url('modules/print_view.php?type=schedule&asset_id=' . $assetId)) ?>" target="_blank" rel="noopener">Print</a>
                    <span class="badge text-bg-dark"><?= e((string) count($yearlyLapsingRows)) ?> rows</span>
                </div>
            </div>
            <?php if ($yearlyLapsingRows === []): ?>
                <div class="empty-state">This asset needs a valid acquisition date and useful life before a yearly lapsing table can be generated.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table align-middle asset-lapsing-table">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Beginning Book</th>
                                <th>Beginning Acc Dep'n</th>
                                <?php foreach ($reportMonths as $monthName): ?>
                                    <th><?= e($monthName) ?></th>
                                <?php endforeach; ?>
                                <th>Total Depreciation</th>
                                <th>Accum Dep'n</th>
                                <th>Book Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($yearlyLapsingRows as $row): ?>
                                <tr>
                                    <td><?= e((string) $row['year']) ?></td>
                                    <td><?= e($formatReportAmount($row['book_value_prior'], true)) ?></td>
                                    <td><?= e($formatReportAmount($row['beginning_accumulated_depreciation'], true)) ?></td>
                                    <?php foreach (array_keys($reportMonths) as $monthNumber): ?>
                                        <td><?= e($formatReportAmount($row['months'][$monthNumber] ?? 0, true)) ?></td>
                                    <?php endforeach; ?>
                                    <td><?= e($formatReportAmount($row['total_depreciation'], true)) ?></td>
                                    <td><?= e($formatReportAmount($row['accumulated_depreciation'], true)) ?></td>
                                    <td><?= e($formatReportAmount($row['book_value'], true)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
</section>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
