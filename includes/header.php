<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;
$pageHeading = $pageHeading ?? $pageTitle;
$pageDescription = $pageDescription ?? 'Keep your PPE schedules, assets, and analysis in one place.';
$loggedInUser = current_user();
$showShell = $loggedInUser !== null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="<?= $showShell ? 'dashboard-body' : 'auth-body' ?>">
    <div class="app-noise"></div>
    <div class="orb orb-one"></div>
    <div class="orb orb-two"></div>
    <?php if ($showShell): ?>
        <div class="app-shell">
            <?php require APP_ROOT . '/includes/sidebar.php'; ?>
            <div class="main-stage">
                <header class="topbar">
                    <div>
                        <p class="eyebrow mb-2">PPE lifecycle intelligence</p>
                        <h1 class="page-title mb-1"><?= e($pageHeading) ?></h1>
                        <p class="page-description mb-0"><?= e($pageDescription) ?></p>
                    </div>
                    <div class="topbar-meta">
                        <div class="role-pill">
                            <i class="bi bi-shield-check"></i>
                            <span><?= e($loggedInUser['role']) ?></span>
                        </div>
                        <div class="user-pill">
                            <strong><?= e($loggedInUser['full_name']) ?></strong>
                            <small><?= e($loggedInUser['email']) ?></small>
                        </div>
                    </div>
                </header>
                <main class="content-stage">
                    <?php render_flash_messages(); ?>
    <?php else: ?>
        <main class="auth-stage">
            <?php render_flash_messages(); ?>
    <?php endif; ?>
