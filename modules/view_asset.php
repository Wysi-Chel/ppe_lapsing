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
$schedule = fetch_depreciation_rows($pdo, $assetId);

$pageTitle = 'View Asset';
$pageHeading = $asset['asset_name'];
$pageDescription = 'Review the complete PPE profile, depreciation path, and any record-quality flags tied to this item.';

require_once APP_ROOT . '/includes/header.php';
?>
<div class="d-flex flex-wrap gap-2 mb-4">
    <a class="btn btn-outline-light" href="<?= e(base_url('modules/assets.php')) ?>">
        <i class="bi bi-arrow-left me-2"></i>Back to Assets
    </a>
    <a class="btn btn-outline-light" href="<?= e(base_url('modules/depreciation.php?asset_id=' . $assetId)) ?>">Open Depreciation View</a>
    <?php if (can_manage_assets()): ?>
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
        <p class="metric-meta mb-0">Applied straight-line period</p>
    </section>
    <section class="metric-card">
        <p class="metric-label mb-2">Depreciation Expense</p>
        <h2 class="metric-value mb-1"><?= e(money($metrics['annual_depreciation'])) ?></h2>
        <p class="metric-meta mb-0">Recognized each year</p>
    </section>
    <section class="metric-card">
        <p class="metric-label mb-2">Net Book Value</p>
        <h2 class="metric-value mb-1"><?= e(money($metrics['carrying_amount'])) ?></h2>
        <p class="metric-meta mb-0">Cost less accumulated depreciation</p>
    </section>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <section class="shell-card mb-4">
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
                        <tr><th>Useful life</th><td><?= e((string) $asset['useful_life']) ?> years</td></tr>
                        <tr><th>Salvage value</th><td><?= e(money((float) $asset['salvage_value'])) ?></td></tr>
                        <tr><th>Method</th><td><?= e($asset['depreciation_method']) ?></td></tr>
                        <tr><th>Remarks</th><td><?= e((string) ($asset['remarks'] ?: 'None')) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

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
    </div>

    <div class="col-lg-7">
        <section class="shell-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-2">Generated schedule</p>
                    <h2 class="section-title mb-0">Yearly lapsing table</h2>
                </div>
                <span class="badge text-bg-dark"><?= e((string) count($schedule)) ?> rows</span>
            </div>
            <div class="table-wrap">
                <table class="table align-middle lapsing-table">
                    <thead>
                        <tr>
                            <th>year</th>
                            <th>cost</th>
                            <th>additional</th>
                            <th>annual depreciation</th>
                            <th>accumulated depreciation</th>
                            <th>net value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedule as $row): ?>
                            <tr>
                                <td><?= e((string) $row['depreciation_year']) ?></td>
                                <td><?= e(money((float) $asset['acquisition_cost'])) ?></td>
                                <td><?= e(money(0.0)) ?></td>
                                <td><?= e(money((float) $row['depreciation_expense'])) ?></td>
                                <td><?= e(money((float) $row['accumulated_depreciation'])) ?></td>
                                <td><?= e(money((float) $row['ending_value'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
