<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

$pdo = db();
$assetLookup = fetch_asset_lookup($pdo);
$selectedAssetId = (int) request_value('asset_id', $assetLookup[0]['asset_id'] ?? 0);
$selectedAsset = $selectedAssetId > 0 ? fetch_asset_by_id($pdo, $selectedAssetId) : null;
$schedule = $selectedAsset ? fetch_depreciation_rows($pdo, $selectedAssetId) : [];
$metrics = $selectedAsset ? get_asset_metrics($selectedAsset) : null;

$pageTitle = 'Depreciation';
$pageHeading = 'Depreciation Schedule';

require_once APP_ROOT . '/includes/header.php';
?>
<?php if ($assetLookup === []): ?>
    <section class="shell-card">
        <div class="empty-state">
            No assets are available yet. Add a PPE record first to generate a depreciation schedule.
        </div>
    </section>
<?php else: ?>
    <section class="shell-card mb-4">
        <div class="mb-4">
            <p class="eyebrow mb-2">Schedule lookup</p>
            <h2 class="section-title mb-1">Choose an asset</h2>
        </div>
        <form method="get" class="row g-3 align-items-end">
            <div class="col-lg-8">
                
                <select class="form-select" id="asset_id" name="asset_id">
                    <?php foreach ($assetLookup as $assetOption): ?>
                        <option value="<?= e((string) $assetOption['asset_id']) ?>" <?= selected_if($selectedAssetId, $assetOption['asset_id']) ?>>
                            <?= e($assetOption['asset_code'] . ' - ' . $assetOption['asset_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-4 d-flex gap-2">
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-2"></i>Load Schedule</button>
                <?php if ($selectedAsset): ?>
                    <a class="btn btn-outline-light w-100" href="<?= e(base_url('modules/view_asset.php?asset_id=' . $selectedAssetId)) ?>">Asset Detail</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <?php if ($selectedAsset && $metrics): ?>
        <div class="metric-grid mb-4">
            <section class="metric-card">
                <p class="metric-label mb-2">Acquisition Price</p>
                <h2 class="metric-value mb-1"><?= e(money((float) $selectedAsset['acquisition_cost'])) ?></h2>
                <p class="metric-meta mb-0">Initial recorded cost</p>
            </section>
            <section class="metric-card">
                <p class="metric-label mb-2">Salvage Value</p>
                <h2 class="metric-value mb-1"><?= e(money((float) $selectedAsset['salvage_value'])) ?></h2>
                <p class="metric-meta mb-0">Expected residual value</p>
            </section>
            <section class="metric-card">
                <p class="metric-label mb-2">Useful Life</p>
                <h2 class="metric-value mb-1"><?= e((string) $selectedAsset['useful_life']) ?> years</h2>
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

        <section class="shell-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-2"><?= e($selectedAsset['asset_code']) ?></p>
                    <h2 class="section-title mb-1"><?= e($selectedAsset['asset_name']) ?></h2>
                    <p class="section-copy mb-0">Net value in this table already reflects the salvage deduction from the opening year.</p>
                </div>
                <span class="badge <?= e(status_badge_class((string) $selectedAsset['status'])) ?>"><?= e($selectedAsset['status']) ?></span>
            </div>
            <div class="table-wrap">
                <table class="table align-middle lapsing-table">
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Cost</th>
                            <th>Additional</th>
                            <th>Annual Depreciation</th>
                            <th>Accumulated Depreciation</th>
                            <th>Net Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedule as $row): ?>
                            <tr>
                                <td><?= e((string) $row['depreciation_year']) ?></td>
                                <td><?= e(money((float) $selectedAsset['acquisition_cost'])) ?></td>
                                <td><?= e(money((float) ($selectedAsset['additional_amount'] ?? 0))) ?></td>
                                <td><?= e(money((float) $row['depreciation_expense'])) ?></td>
                                <td><?= e(money((float) $row['accumulated_depreciation'])) ?></td>
                                <td><?= e(money(schedule_display_net_value($selectedAsset, $row))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
