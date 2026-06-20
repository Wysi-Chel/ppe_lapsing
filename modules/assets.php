<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_asset_id']) && can_manage_assets()) {
    delete_asset($pdo, (int) $_POST['delete_asset_id']);
    set_flash('success', 'Asset deleted successfully.');
    redirect('modules/assets.php');
}

$filters = [
    'q' => trim((string) request_value('q', '')),
    'status' => trim((string) request_value('status', '')),
    'category_id' => trim((string) request_value('category_id', '')),
    'department_id' => trim((string) request_value('department_id', '')),
];

$categories = fetch_categories($pdo);
$departments = fetch_departments($pdo);
$assetQueryFilters = $filters;
unset($assetQueryFilters['status']);
$assets = hydrate_assets_with_metrics(fetch_assets($pdo, $assetQueryFilters));

if ($filters['status'] !== '') {
    $assets = array_values(array_filter(
        $assets,
        static fn (array $asset): bool => (string) ($asset['status'] ?? '') === $filters['status']
    ));
}

$registerMetrics = build_dashboard_metrics($assets);
$activeFilterCount = count(array_filter($filters, static fn (string $value): bool => $value !== ''));

$pageTitle = 'Assets';
$pageHeading = 'PPE Asset Register';

require_once APP_ROOT . '/includes/header.php';
?>
<div class="metric-grid mb-4">
    <section class="metric-card">
        <p class="metric-label mb-2">Records Found</p>
        <h2 class="metric-value mb-1"><?= e((string) $registerMetrics['asset_count']) ?></h2>
        <p class="metric-meta mb-0"><?= e((string) $activeFilterCount) ?> active filter<?= $activeFilterCount === 1 ? '' : 's' ?></p>
    </section>
    <section class="metric-card">
        <p class="metric-label mb-2">Total PPE Cost</p>
        <h2 class="metric-value mb-1"><?= e(money($registerMetrics['total_cost'])) ?></h2>
    </section>
    <section class="metric-card">
        <p class="metric-label mb-2">Net Book Value</p>
        <h2 class="metric-value mb-1"><?= e(money($registerMetrics['total_carrying'])) ?></h2>
    </section>
    <section class="metric-card">
        <p class="metric-label mb-2">Needs Review</p>
        <h2 class="metric-value mb-1"><?= e((string) $registerMetrics['unusual_count']) ?></h2>
    </section>
</div>

<section class="shell-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="eyebrow mb-2">Asset filters</p>
            <h2 class="section-title mb-1">Search the register</h2>
        </div>
        <div class="stack-inline">
            <span class="badge text-bg-dark"><?= e((string) $activeFilterCount) ?> active filter<?= $activeFilterCount === 1 ? '' : 's' ?></span>
            <?php if (can_manage_assets()): ?>
                <a class="btn btn-primary" href="<?= e(base_url('modules/add_asset.php')) ?>">
                    <i class="bi bi-plus-circle me-2"></i>Add Asset
                </a>
            <?php endif; ?>
        </div>
    </div>

    <form method="get" class="row g-3">
        <div class="col-xl-4 col-lg-6">
            <label class="form-label" for="q">Keyword</label>
            <input class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Asset code, name, location, category">
        </div>
        <div class="col-xl-2 col-lg-6">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All statuses</option>
                <option value="Active" <?= selected_if($filters['status'], 'Active') ?>>Active</option>
                <option value="Fully Depreciated" <?= selected_if($filters['status'], 'Fully Depreciated') ?>>Fully Depreciated</option>
            </select>
        </div>
        <div class="col-xl-3 col-lg-6">
            <label class="form-label" for="category_id">Category</label>
            <select class="form-select" id="category_id" name="category_id">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['category_id']) ?>" <?= selected_if($filters['category_id'], $category['category_id']) ?>>
                        <?= e($category['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-xl-3 col-lg-6">
            <label class="form-label" for="department_id">Department</label>
            <select class="form-select" id="department_id" name="department_id">
                <option value="">All departments</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= e((string) $department['department_id']) ?>" <?= selected_if($filters['department_id'], $department['department_id']) ?>>
                        <?= e($department['department_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-2"></i>Apply Filters</button>
            <a class="btn btn-outline-light" href="<?= e(base_url('modules/assets.php')) ?>">Reset</a>
        </div>
    </form>
</section>

<section class="shell-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="eyebrow mb-2">Asset list</p>
            <h2 class="section-title mb-1"><?= e((string) count($assets)) ?> <?= e(pluralize(count($assets), 'record')) ?> found</h2>
        </div>
        <div class="stack-inline justify-content-end">
        </div>
    </div>

    <?php if ($assets === []): ?>
        <div class="empty-state">
            <h2 class="section-title mb-2">No matching assets</h2>
            <p class="mb-3">Try clearing one or more filters, or add a new asset if the record has not been created yet.</p>
            <div class="page-actions justify-content-center">
                <a class="btn btn-outline-light" href="<?= e(base_url('modules/assets.php')) ?>">Clear filters</a>
                <?php if (can_manage_assets()): ?>
                    <a class="btn btn-primary" href="<?= e(base_url('modules/add_asset.php')) ?>">Add Asset</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table align-middle asset-register-table">
                <thead>
                    <tr>
                        <th>Asset</th>
                        <th>Classification</th>
                        <th>Assignment</th>
                        <th>Useful Life</th>
                        <th>Book Values</th>
                        <th>Condition</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td>
                                <div class="asset-name-cell">
                                    <strong><?= e($asset['asset_name']) ?></strong>
                                    <span class="asset-code-pill"><?= e($asset['asset_code']) ?></span>
                                    <span class="text-soft small">Acquired <?= e(format_date((string) $asset['acquisition_date'])) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="asset-meta-list">
                                    <strong><?= e((string) ($asset['category_name'] ?? 'Uncategorized')) ?></strong>
                                    <span class="badge <?= e(status_badge_class((string) $asset['status'])) ?>"><?= e($asset['status']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="asset-meta-list">
                                    <strong><?= e((string) ($asset['department_name'] ?? 'Unassigned')) ?></strong>
                                    <span class="text-soft small"><?= e(asset_location_label((string) ($asset['location'] ?? ''))) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="asset-meta-list">
                                    <strong><?= e((string) $asset['useful_life']) ?> year<?= (int) $asset['useful_life'] === 1 ? '' : 's' ?></strong>
                                    <span class="text-soft small"><?= e((string) $asset['remaining_years']) ?> year<?= (int) $asset['remaining_years'] === 1 ? '' : 's' ?> remaining</span>
                                </div>
                            </td>
                            <td>
                                <div class="asset-value-stack">
                                    <div><span>Cost</span><strong><?= e(money(asset_total_cost($asset))) ?></strong></div>
                                    <div><span>Accum.</span><strong><?= e(money((float) $asset['accumulated_depreciation'])) ?></strong></div>
                                    <div><span>Net</span><strong><?= e(money((float) $asset['carrying_amount'])) ?></strong></div>
                                </div>
                            </td>
                            <td>
                                <div class="asset-meta-list">
                                    <span class="badge <?= e(condition_badge_class((string) $asset['condition'])) ?>"><?= e($asset['condition']) ?></span>
                                    <?php if (!empty($asset['anomaly_count'])): ?>
                                        <span class="text-soft small"><?= e((string) $asset['anomaly_count']) ?> flag<?= (int) $asset['anomaly_count'] === 1 ? '' : 's' ?></span>
                                    <?php else: ?>
                                        <span class="text-soft small">No flags</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="asset-actions-cell">
                                <div class="asset-action-stack">
                                    <a class="btn btn-sm btn-outline-light" href="<?= e(base_url('modules/view_asset.php?asset_id=' . $asset['asset_id'])) ?>">
                                        <i class="bi bi-eye me-1"></i>View
                                    </a>
                                    <?php if (can_manage_assets()): ?>
                                        <a class="btn btn-sm btn-warning" href="<?= e(base_url('modules/edit_asset.php?asset_id=' . $asset['asset_id'])) ?>">
                                            <i class="bi bi-pencil-square me-1"></i>Edit
                                        </a>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="delete_asset_id" value="<?= e((string) $asset['asset_id']) ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" data-confirm-delete="Delete this asset and its depreciation schedule?">
                                                <i class="bi bi-trash me-1"></i>Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
