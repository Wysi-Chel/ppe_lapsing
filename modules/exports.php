<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

$pdo = db();
$summaryYear = normalize_depreciation_summary_year(request_value('year', CURRENT_YEAR));
$categories = fetch_categories($pdo);
$selectedCategoryId = (int) request_value('category_id', 0);
$summaryReportParams = [
    'type' => 'depreciation_summary',
    'year' => $summaryYear,
];

if ($selectedCategoryId > 0) {
    $summaryReportParams['category_id'] = $selectedCategoryId;
}

$summaryReportQuery = http_build_query($summaryReportParams);

$pageTitle = 'Exports';
$pageHeading = 'Exports and Print Center';
$pageDescription = 'Download clean CSV files or open print-friendly views for assets, alerts, transfers, and depreciation reports.';

require_once APP_ROOT . '/includes/header.php';
?>
<section class="shell-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="eyebrow mb-2">Report year</p>
            <h2 class="section-title mb-1">Choose depreciation report year</h2>
            <p class="section-copy mb-0">System-wide exports work immediately. The depreciation report uses the selected year below.</p>
        </div>
    </div>

    <form method="get" class="row g-3 align-items-end">
        <div class="col-sm-6 col-lg-3">
            <label class="form-label" for="year">Summary year</label>
            <input class="form-control" id="year" name="year" type="number" min="1900" max="2200" step="1" value="<?= e((string) $summaryYear) ?>">
        </div>
        <div class="col-sm-6 col-lg-4">
            <label class="form-label" for="category_id">Asset type</label>
            <select class="form-select" id="category_id" name="category_id">
                <option value="">All asset types</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['category_id']) ?>" <?= $selectedCategoryId === (int) $category['category_id'] ? 'selected' : '' ?>>
                        <?= e((string) $category['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-lg-3">
            <button class="btn btn-primary w-100" type="submit">
                <i class="bi bi-funnel me-2"></i>Apply Filters
            </button>
        </div>
    </form>
</section>

<div class="row g-4">
    <div class="col-xl-6">
        <section class="shell-card h-100">
            <div class="mb-4">
                <p class="eyebrow mb-2">Downloads</p>
                <h2 class="section-title mb-1">CSV exports</h2>
                <p class="section-copy mb-0">Use these files for audit support, reconciliations, or spreadsheet analysis.</p>
            </div>

            <div class="list-panel">
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Asset register</strong>
                        <p class="text-soft small mb-0">Full PPE listing with net values, conditions, and remarks.</p>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(base_url('modules/export.php?type=assets')) ?>">Download</a>
                </div>
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Alerts queue</strong>
                        <p class="text-soft small mb-0">Near-end, status mismatch, and data-quality issues in one export.</p>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(base_url('modules/export.php?type=alerts')) ?>">Download</a>
                </div>
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Transfer history</strong>
                        <p class="text-soft small mb-0">Department and location movement history for all transferred assets.</p>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(base_url('modules/export.php?type=transfers')) ?>">Download</a>
                </div>
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Depreciation summary</strong>
                        <p class="text-soft small mb-0">Excel-style monthly lapsing report as of December 31, <?= e((string) $summaryYear) ?>.</p>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(base_url('modules/export.php?' . $summaryReportQuery)) ?>">Download</a>
                </div>
            </div>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="shell-card h-100">
            <div class="mb-4">
                <p class="eyebrow mb-2">Output views</p>
                <h2 class="section-title mb-1">Print layouts</h2>
                <p class="section-copy mb-0">Open clean printer-friendly pages for physical files, reviews, or sign-off packs.</p>
            </div>

            <div class="list-panel">
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Asset register print view</strong>
                        <p class="text-soft small mb-0">A printable version of the current PPE register.</p>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(base_url('modules/print_view.php?type=assets')) ?>" target="_blank" rel="noopener">Open</a>
                </div>
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Alert digest print view</strong>
                        <p class="text-soft small mb-0">A review-ready summary of assets needing action.</p>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(base_url('modules/print_view.php?type=alerts')) ?>" target="_blank" rel="noopener">Open</a>
                </div>
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Transfer log print view</strong>
                        <p class="text-soft small mb-0">A printable history of assignment movements and location changes.</p>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(base_url('modules/print_view.php?type=transfers')) ?>" target="_blank" rel="noopener">Open</a>
                </div>
                <div class="list-row d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Depreciation summary print view</strong>
                        <p class="text-soft small mb-0">A printable monthly lapsing report for <?= e((string) $summaryYear) ?>.</p>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(base_url('modules/print_view.php?' . $summaryReportQuery)) ?>" target="_blank" rel="noopener">Open</a>
                </div>
            </div>
        </section>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
