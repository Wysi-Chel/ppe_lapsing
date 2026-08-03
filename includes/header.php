<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;
$pageHeading = $pageHeading ?? $pageTitle;
$pageDescription = $pageDescription ?? '';
$documentTitle = $pageTitle === APP_NAME ? APP_NAME : $pageTitle . ' - ' . APP_NAME;
$loggedInUser = current_user();
$showShell = $loggedInUser !== null;
$activeOrganization = current_organization();
$activeOrganizationCode = current_organization_code();
$themeColor = $activeOrganizationCode === 'ntrprising' ? '#2d6fd6' : '#bf1f2f';
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$showAddAssetAction = $showShell
    && can_manage_assets()
    && !in_array($currentPage, ['add_asset.php', 'edit_asset.php'], true);
?>
<!doctype html>
<html lang="en" data-organization="<?= e($activeOrganizationCode) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($documentTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="<?= e(base_url('assets/favicon.svg')) ?>">
    <link rel="shortcut icon" href="<?= e(base_url('assets/favicon.svg')) ?>">
    <meta name="theme-color" content="<?= e($themeColor) ?>">
    <script>
        (() => {
            try {
                const savedTheme = window.localStorage.getItem('ppe-theme');
                const theme = savedTheme === 'light' || savedTheme === 'dark'
                    ? savedTheme
                    : 'light';
                document.documentElement.dataset.theme = theme;
            } catch (error) {
                document.documentElement.dataset.theme = 'light';
            }
        })();
    </script>
    <link href="<?= e(base_url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="<?= $showShell ? 'dashboard-body' : 'auth-body' ?>">
    <?php if (!$showShell): ?>
        <button class="theme-toggle theme-toggle-floating" type="button" data-theme-toggle aria-label="Switch to light mode" title="Switch to light mode">
            <i class="bi bi-sun-fill" data-theme-toggle-icon></i>
            <span data-theme-toggle-label>Light mode</span>
        </button>
    <?php endif; ?>
    <?php if ($showShell): ?>
        <div class="app-shell">
            <?php require APP_ROOT . '/includes/sidebar.php'; ?>
            <button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Close navigation"></button>
            <div class="main-stage">
                <header class="topbar">
                    <div class="topbar-copy">
                        <p class="eyebrow mb-2"><?= e($activeOrganization['label']) ?> workspace</p>
                        <h1 class="page-title mb-1"><?= e($pageHeading) ?></h1>
                        <?php if ($pageDescription !== ''): ?>
                            <p class="page-description mb-0"><?= e($pageDescription) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="topbar-actions">
                        <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false">
                            <i class="bi bi-list" aria-hidden="true"></i>
                            <span>Menu</span>
                        </button>
                        <?php if ($showAddAssetAction): ?>
                            <a class="btn btn-primary" href="<?= e(base_url('modules/add_asset.php')) ?>">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                <span>Add asset</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </header>
                <main class="content-stage">
                    <?php render_flash_messages(); ?>
    <?php else: ?>
        <main class="auth-stage">
            <div class="auth-flash-stack">
                <?php render_flash_messages(); ?>
            </div>
    <?php endif; ?>
