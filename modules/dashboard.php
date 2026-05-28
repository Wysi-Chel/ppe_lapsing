<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

$pdo = db();
$assets = hydrate_assets_with_metrics(fetch_assets($pdo));
$metrics = build_dashboard_metrics($assets);
$alerts = build_asset_alerts($assets);
$categorySummary = array_slice(build_category_summary($assets), 0, 5);
$recentAnalyses = fetch_ai_analysis_history($pdo, 3);

$pageTitle = 'Dashboard';
$pageHeading = 'PPE Dashboard';
$pageDescription = 'A quick view of cost, depreciation, carrying amount, and emerging asset risks.';

require_once APP_ROOT . '/includes/header.php';
?>
<?php if ($assets === []): ?>
    <section class="shell-card">
        <div class="empty-state">
            <h2 class="section-title mb-2">No assets have been recorded yet</h2>
            <p class="mb-3">Start by adding PPE items so the system can generate dashboards, schedules, reports, and AI insights.</p>
            <?php if (can_manage_assets()): ?>
                <a class="btn btn-primary" href="<?= e(base_url('modules/add_asset.php')) ?>">
                    <i class="bi bi-plus-circle me-2"></i>Add the first asset
                </a>
            <?php endif; ?>
        </div>
    </section>
<?php else: ?>
    <div class="metric-grid mb-4">
        <section class="metric-card">
            <p class="metric-label mb-2">Total PPE Cost</p>
            <h2 class="metric-value mb-1"><?= e(money($metrics['total_cost'])) ?></h2>
            <p class="metric-meta mb-0"><?= e((string) $metrics['asset_count']) ?> tracked assets</p>
        </section>
        <section class="metric-card">
            <p class="metric-label mb-2">Accumulated Depreciation</p>
            <h2 class="metric-value mb-1"><?= e(money($metrics['total_accumulated'])) ?></h2>
            <p class="metric-meta mb-0">Recognized straight-line expense to date</p>
        </section>
        <section class="metric-card">
            <p class="metric-label mb-2">Carrying Amount</p>
            <h2 class="metric-value mb-1"><?= e(money($metrics['total_carrying'])) ?></h2>
            <p class="metric-meta mb-0">Current remaining book value</p>
        </section>
        <section class="metric-card">
            <p class="metric-label mb-2">Near End of Life</p>
            <h2 class="metric-value mb-1"><?= e((string) $metrics['near_end_count']) ?></h2>
            <p class="metric-meta mb-0">Assets that may need replacement soon</p>
        </section>
        <section class="metric-card">
            <p class="metric-label mb-2">Fully Depreciated</p>
            <h2 class="metric-value mb-1"><?= e((string) $metrics['fully_depreciated_count']) ?></h2>
            <p class="metric-meta mb-0">Items that have reached salvage value</p>
        </section>
        <section class="metric-card">
            <p class="metric-label mb-2">Flagged Records</p>
            <h2 class="metric-value mb-1"><?= e((string) count($alerts['unusual'])) ?></h2>
            <p class="metric-meta mb-0">Potential data quality issues to review</p>
        </section>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <section class="shell-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="eyebrow mb-2">AI-style risk summary</p>
                        <h2 class="section-title mb-0">Current portfolio picture</h2>
                    </div>
                    <?php if (can_manage_assets()): ?>
                        <a class="btn btn-primary" href="<?= e(base_url('modules/add_asset.php')) ?>">
                            <i class="bi bi-plus-circle me-2"></i>Add Asset
                        </a>
                    <?php endif; ?>
                </div>
                <p class="mb-0"><?= e(build_risk_summary($metrics, $alerts)) ?></p>
            </section>

            <section class="shell-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="eyebrow mb-2">Latest activity</p>
                        <h2 class="section-title mb-0">Recent assets</h2>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(base_url('modules/assets.php')) ?>">View all assets</a>
                </div>
                <div class="table-wrap">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Status</th>
                                <th>Condition</th>
                                <th>Carrying Amount</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($assets, 0, 6) as $asset): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($asset['asset_name']) ?></strong>
                                        <div class="text-soft small"><?= e($asset['asset_code']) ?> • <?= e((string) ($asset['category_name'] ?? 'Uncategorized')) ?></div>
                                    </td>
                                    <td><span class="badge <?= e(status_badge_class((string) $asset['status'])) ?>"><?= e($asset['status']) ?></span></td>
                                    <td><span class="badge <?= e(condition_badge_class((string) $asset['condition'])) ?>"><?= e($asset['condition']) ?></span></td>
                                    <td><?= e(money((float) $asset['carrying_amount'])) ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-light" href="<?= e(base_url('modules/view_asset.php?asset_id=' . $asset['asset_id'])) ?>">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="shell-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="eyebrow mb-2">Mix overview</p>
                        <h2 class="section-title mb-0">Assets by category</h2>
                    </div>
                    <a class="btn btn-outline-light btn-sm" href="<?= e(base_url('modules/reports.php')) ?>">Full report</a>
                </div>
                <div class="list-panel">
                    <?php foreach ($categorySummary as $category): ?>
                        <div class="list-row">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?= e($category['label']) ?></strong>
                                    <div class="text-soft small"><?= e((string) $category['asset_count']) ?> <?= e(pluralize((int) $category['asset_count'], 'asset')) ?></div>
                                </div>
                                <div class="text-end">
                                    <div><?= e(money($category['total_cost'])) ?></div>
                                    <div class="text-soft small"><?= e(money($category['total_carrying'])) ?> carrying</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="shell-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="eyebrow mb-2">Recent insights</p>
                        <h2 class="section-title mb-0">Saved analyses</h2>
                    </div>
                    <a class="btn btn-outline-light btn-sm" href="<?= e(base_url('modules/ai_analysis.php')) ?>">Open AI module</a>
                </div>

                <?php if ($recentAnalyses === []): ?>
                    <div class="empty-state">No analysis has been saved yet.</div>
                <?php else: ?>
                    <div class="list-panel">
                        <?php foreach ($recentAnalyses as $analysis): ?>
                            <div class="list-row">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge <?= e(analysis_badge_class((string) $analysis['analysis_type'])) ?>"><?= e($analysis['analysis_type']) ?></span>
                                    <span class="text-soft small"><?= e(format_date((string) $analysis['generated_at'], 'M d, Y h:i A')) ?></span>
                                </div>
                                <p class="mb-1"><strong><?= e((string) ($analysis['full_name'] ?? 'Unknown user')) ?></strong></p>
                                <p class="text-soft small mb-0"><?= e(excerpt((string) $analysis['analysis_result'], 180)) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
<?php endif; ?>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
