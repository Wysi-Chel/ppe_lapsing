<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

$pdo = db();
$summaryYear = normalize_depreciation_summary_year(request_value('year', CURRENT_YEAR));
$categories = fetch_categories($pdo);
$selectedCategoryId = (int) request_value('category_id', 0);
$summaryFilters = $selectedCategoryId > 0 ? ['category_id' => $selectedCategoryId] : [];
$depreciationSummary = build_depreciation_summary(fetch_assets($pdo, $summaryFilters), $summaryYear);
$summaryReportParams = [
    'type' => 'depreciation_summary',
    'year' => $summaryYear,
];

if ($selectedCategoryId > 0) {
    $summaryReportParams['category_id'] = $selectedCategoryId;
}

$summaryReportQuery = http_build_query($summaryReportParams);
$formatReportAmount = static function (float|int|string|null $value, bool $blankZero = false): string {
    $amount = round((float) $value, 2);

    if ($blankZero && abs($amount) < 0.005) {
        return '';
    }

    return number_format($amount, 2);
};

$pageTitle = 'Depreciation';
$pageHeading = 'Depreciation Report Preview';

require_once APP_ROOT . '/includes/header.php';
?>
<section class="shell-card mb-4">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div>
            <p class="eyebrow mb-2">Report preview</p>
            <h2 class="section-title mb-1">Lapsing schedule of property and equipment</h2>
            <p class="section-copy mb-0"><?= e($depreciationSummary['period_label']) ?>. This preview keeps the key columns on screen; generate the detailed report for the complete monthly schedule.</p>
        </div>
        <div class="stack-inline justify-content-end">
            <a class="btn btn-primary" href="<?= e(base_url('modules/print_view.php?' . $summaryReportQuery)) ?>" target="_blank" rel="noopener">
                <i class="bi bi-file-earmark-spreadsheet me-2"></i>Generate Detailed Report
            </a>
            <a class="btn btn-outline-light" href="<?= e(base_url('modules/export.php?' . $summaryReportQuery)) ?>">Download CSV</a>
        </div>
    </div>

    <form method="get" class="row g-3 align-items-end mb-4">
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
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-funnel me-2"></i>Apply Filters</button>
        </div>
    </form>

    <?php if ($depreciationSummary['groups'] === []): ?>
        <div class="empty-state">No assets match the selected report filters.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table align-middle depreciation-preview-table">
                <thead>
                    <tr>
                        <th>Particulars</th>
                        <th>Acquired</th>
                        <th>Useful Life</th>
                        <th>Remaining Mos.</th>
                        <th>Ref.</th>
                        <th>Adjusted Cost</th>
                        <th>Monthly Dep'n</th>
                        <th>Book <?= e($depreciationSummary['prior_book_value_label']) ?></th>
                        <th>Total Depreciation</th>
                        <th>Accum <?= e($depreciationSummary['accumulated_label']) ?></th>
                        <th>Book <?= e($depreciationSummary['book_value_label']) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($depreciationSummary['groups'] as $group): ?>
                        <tr class="report-category-row">
                            <th colspan="11"><?= e($group['label']) ?></th>
                        </tr>
                        <?php foreach ($group['rows'] as $summaryRow): ?>
                            <tr>
                                <td class="text-start"><?= e($summaryRow['particulars']) ?></td>
                                <td><?= e(format_date((string) $summaryRow['acquisition_date'], 'm.d.Y')) ?></td>
                                <td><?= e((string) $summaryRow['useful_life']) ?></td>
                                <td><?= e((string) $summaryRow['remaining_useful_months']) ?></td>
                                <td><?= e($summaryRow['ref']) ?></td>
                                <td><?= e($formatReportAmount($summaryRow['adjusted_cost'])) ?></td>
                                <td><?= e($formatReportAmount($summaryRow['monthly_depreciation'], true)) ?></td>
                                <td><?= e($formatReportAmount($summaryRow['book_value_prior'], true)) ?></td>
                                <td><?= e($formatReportAmount($summaryRow['total_depreciation'], true)) ?></td>
                                <td><?= e($formatReportAmount($summaryRow['accumulated_depreciation'], true)) ?></td>
                                <td><?= e($formatReportAmount($summaryRow['book_value'], true)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="report-total-row">
                            <th>Total</th>
                            <th colspan="4"><?= e((string) $group['total']['asset_count']) ?> asset<?= (int) $group['total']['asset_count'] === 1 ? '' : 's' ?></th>
                            <th><?= e($formatReportAmount($group['total']['adjusted_cost'])) ?></th>
                            <th><?= e($formatReportAmount($group['total']['monthly_depreciation'], true)) ?></th>
                            <th><?= e($formatReportAmount($group['total']['book_value_prior'], true)) ?></th>
                            <th><?= e($formatReportAmount($group['total']['total_depreciation'], true)) ?></th>
                            <th><?= e($formatReportAmount($group['total']['accumulated_depreciation'], true)) ?></th>
                            <th><?= e($formatReportAmount($group['total']['book_value'], true)) ?></th>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="report-grand-total-row">
                        <th>
                            TOTAL
                            <div class="text-soft small"><?= e((string) $depreciationSummary['total']['asset_count']) ?> asset<?= (int) $depreciationSummary['total']['asset_count'] === 1 ? '' : 's' ?></div>
                        </th>
                        <th colspan="4"></th>
                        <th><?= e($formatReportAmount($depreciationSummary['total']['adjusted_cost'])) ?></th>
                        <th><?= e($formatReportAmount($depreciationSummary['total']['monthly_depreciation'], true)) ?></th>
                        <th><?= e($formatReportAmount($depreciationSummary['total']['book_value_prior'], true)) ?></th>
                        <th><?= e($formatReportAmount($depreciationSummary['total']['total_depreciation'], true)) ?></th>
                        <th><?= e($formatReportAmount($depreciationSummary['total']['accumulated_depreciation'], true)) ?></th>
                        <th><?= e($formatReportAmount($depreciationSummary['total']['book_value'], true)) ?></th>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
