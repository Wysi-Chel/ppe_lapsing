<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_login();

if (!can_manage_assets()) {
    set_flash('danger', 'Only Admin and Accounting Staff can add PPE records.');
    redirect('modules/assets.php');
}

$pdo = db();
$categories = fetch_categories($pdo);
$departments = fetch_departments($pdo);
$errors = [];
$form = normalize_asset_payload([
    'status' => 'Active',
    'depreciation_method' => 'Straight-line',
    'salvage_value' => 0,
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = normalize_asset_payload($_POST);
    $errors = validate_asset_payload($form);

    if ($errors === []) {
        try {
            $assetId = save_asset($pdo, $form);
            set_flash('success', 'Asset added and depreciation schedule generated.');
            redirect('modules/view_asset.php?asset_id=' . $assetId);
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$pageTitle = 'Add Asset';
$pageHeading = 'Add PPE Asset';
$submitLabel = 'Save Asset';
$cancelUrl = 'modules/assets.php';

require_once APP_ROOT . '/includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-8">
        <section class="shell-card">
            <div class="mb-4">
                <p class="eyebrow mb-2">Asset setup</p>
                <h2 class="section-title mb-2">Record a new PPE item</h2>
            </div>
            <?php require APP_ROOT . '/includes/asset_form.php'; ?>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="shell-card">
            <p class="eyebrow mb-2">Formula reference</p>
            <h2 class="section-title mb-3">Straight-line depreciation</h2>
            <div class="helper-card mb-3">
                <strong>Annual Depreciation</strong>
                <p class="mb-0">(Cost - Salvage Value) / Useful Life</p>
            </div>
            <div class="list-panel">
                <div class="list-row">
                    <strong>What the system generates</strong>
                    <p class="text-soft small mb-0">A yearly schedule with beginning value, expense, accumulated depreciation, and ending carrying amount.</p>
                </div>
                <div class="list-row">
                    <strong>What to double-check</strong>
                    <p class="text-soft small mb-0">Make sure salvage value is not higher than cost and the useful life matches the category or policy.</p>
                </div>
            </div>
        </section>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
