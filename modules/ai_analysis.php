<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

$pdo = db();
$assets = hydrate_assets_with_metrics(fetch_assets($pdo));
$metrics = build_dashboard_metrics($assets);
$alerts = build_asset_alerts($assets);
$snapshot = build_analysis_snapshot($assets, $metrics, $alerts);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($assets === []) {
        set_flash('warning', 'Add PPE assets first before generating an AI analysis.');
        redirect('modules/ai_analysis.php');
    }

    $result = generate_openai_analysis($snapshot);

    if ($result['success']) {
        $analysisType = $result['analysis_type'];
        $analysisText = $result['content'];
        set_flash('success', 'AI analysis generated with OpenAI and saved successfully.');
    } else {
        $analysisType = 'Rule-Based Fallback';
        $analysisText = "OpenAI fallback reason: " . $result['error'] . "\n\n" . generate_rule_based_analysis($assets, $metrics, $alerts);
        set_flash('warning', 'OpenAI was unavailable, so the system saved a local rule-based analysis instead.');
    }

    save_ai_analysis($pdo, (int) current_user()['user_id'], $analysisType, $analysisText);
    redirect('modules/ai_analysis.php');
}

$history = fetch_ai_analysis_history($pdo, 6);
$latestAnalysis = $history[0] ?? null;
$hasApiKey = get_openai_api_key() !== '';

$pageTitle = 'AI Analysis';
$pageHeading = 'AI Analysis';
$pageDescription = 'Review the generated PPE report and the asset data used to create it.';

require_once APP_ROOT . '/includes/header.php';
?>
<div class="metric-grid mb-4">
    <section class="metric-card">
        <p class="metric-label mb-2">Configured Model</p>
        <h2 class="metric-value mb-1"><?= e(OPENAI_MODEL) ?></h2>
        <p class="metric-meta mb-0">Used when an OpenAI API key is available</p>
    </section>
    <section class="metric-card">
        <p class="metric-label mb-2">API Key Status</p>
        <h2 class="metric-value mb-1"><?= $hasApiKey ? 'Ready' : 'Missing' ?></h2>
        <p class="metric-meta mb-0"><?= $hasApiKey ? 'OpenAI report generation is available.' : 'The local fallback report will still work.' ?></p>
    </section>
    <section class="metric-card">
        <p class="metric-label mb-2">Saved Analyses</p>
        <h2 class="metric-value mb-1"><?= e((string) count($history)) ?></h2>
        <p class="metric-meta mb-0">Most recent analysis records shown below</p>
    </section>
    <section class="metric-card">
        <p class="metric-label mb-2">Flagged Assets</p>
        <h2 class="metric-value mb-1"><?= e((string) count($alerts['unusual'])) ?></h2>
        <p class="metric-meta mb-0">Records that may need closer review</p>
    </section>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <section class="shell-card mb-4">
            <div class="mb-3">
                <p class="eyebrow mb-2">Input data</p>
                <h2 class="section-title mb-0">Data used for the report</h2>
            </div>
            <textarea class="form-control" rows="18" readonly><?= e($snapshot) ?></textarea>
            <form method="post" class="mt-3">
                <button class="btn btn-primary w-100" type="submit" <?= $assets === [] ? 'disabled' : '' ?>>
                    <i class="bi bi-stars me-2"></i>Generate AI Analysis
                </button>
            </form>
        </section>

        <section class="shell-card">
            <div class="mb-3">
                <p class="eyebrow mb-2">Checks</p>
                <h2 class="section-title mb-0">Items reviewed</h2>
            </div>
            <div class="list-panel">
                <div class="list-row"><strong>PPE summary</strong><p class="text-soft small mb-0">Total cost, accumulated depreciation, and current carrying amount.</p></div>
                <div class="list-row"><strong>Replacement risk</strong><p class="text-soft small mb-0">Assets near the end of useful life or already fully depreciated.</p></div>
                <div class="list-row"><strong>Record quality</strong><p class="text-soft small mb-0">Active assets at salvage value, invalid setup values, and other rule-based flags.</p></div>
            </div>
        </section>
    </div>

    <div class="col-lg-7">
        <section class="shell-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-2">Latest result</p>
                    <h2 class="section-title mb-0">Saved report</h2>
                </div>
                <?php if ($latestAnalysis): ?>
                    <span class="badge <?= e(analysis_badge_class((string) $latestAnalysis['analysis_type'])) ?>"><?= e($latestAnalysis['analysis_type']) ?></span>
                <?php endif; ?>
            </div>

            <?php if (!$latestAnalysis): ?>
                <div class="empty-state">No analysis has been generated yet.</div>
            <?php else: ?>
                <p class="text-soft small mb-3">
                    Generated by <?= e((string) ($latestAnalysis['full_name'] ?? 'Unknown user')) ?>
                    on <?= e(format_date((string) $latestAnalysis['generated_at'], 'M d, Y h:i A')) ?>
                </p>
                <div class="analysis-output"><?= format_analysis_text((string) $latestAnalysis['analysis_result']) ?></div>
            <?php endif; ?>
        </section>

        <section class="shell-card">
            <div class="mb-3">
                <p class="eyebrow mb-2">History</p>
                <h2 class="section-title mb-0">Previous reports</h2>
            </div>

            <?php if ($history === []): ?>
                <div class="empty-state">No saved analysis history yet.</div>
            <?php else: ?>
                <div class="list-panel">
                    <?php foreach ($history as $analysis): ?>
                        <div class="list-row">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="badge-strip">
                                    <span class="badge <?= e(analysis_badge_class((string) $analysis['analysis_type'])) ?>"><?= e($analysis['analysis_type']) ?></span>
                                    <?php if (!empty($analysis['role'])): ?>
                                        <span class="badge <?= e(role_badge_class((string) $analysis['role'])) ?>"><?= e($analysis['role']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-soft small"><?= e(format_date((string) $analysis['generated_at'], 'M d, Y h:i A')) ?></span>
                            </div>
                            <p class="mb-1"><strong><?= e((string) ($analysis['full_name'] ?? 'Unknown user')) ?></strong></p>
                            <p class="text-soft small mb-0"><?= e(excerpt((string) $analysis['analysis_result'], 220)) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
