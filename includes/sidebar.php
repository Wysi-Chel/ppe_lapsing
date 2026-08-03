<?php
declare(strict_types=1);

$navItems = [
    ['label' => 'Dashboard', 'href' => 'modules/dashboard.php', 'icon' => 'bi-speedometer2', 'match' => '/modules/dashboard.php'],
    ['label' => 'Assets', 'href' => 'modules/assets.php', 'icon' => 'bi-pc-display', 'match' => '/modules/assets.php'],
    ['label' => 'Depreciation', 'href' => 'modules/depreciation.php', 'icon' => 'bi-graph-up-arrow', 'match' => '/modules/depreciation.php'],
    ['label' => 'Reports', 'href' => 'modules/reports.php', 'icon' => 'bi-bar-chart-line', 'match' => '/modules/reports.php'],
];


$activeOrganization = current_organization();
$activeOrganizationCode = current_organization_code();
$organizationOptions = organization_options();
?>
<aside class="sidebar" id="app-sidebar" aria-label="Primary navigation">
    <div class="sidebar-brand-row">
        <a class="sidebar-brand" href="<?= e(base_url('modules/dashboard.php')) ?>">
            <span class="sidebar-brand-mark">
                <img src="<?= e(base_url('assets/favicon.svg')) ?>" alt="">
            </span>
            <span>
                <small>MICEI Portal</small>
                <strong>PPE Lapsing</strong>
            </span>
        </a>
        <button class="sidebar-close" type="button" data-sidebar-close aria-label="Close navigation">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    <section class="brand-block company-workspace-panel sidebar-panel sidebar-company-panel">
        <div class="sidebar-panel-label">Company Workspace</div>
        <div class="organization-switch organization-switch-sidebar">
            <?php foreach ($organizationOptions as $code => $organization): ?>
                <a
                    class="organization-option <?= $code === $activeOrganizationCode ? 'active' : '' ?>"
                    href="<?= e(current_route(['organization_switch' => 1, 'organization_code' => $code])) ?>"
                    aria-current="<?= $code === $activeOrganizationCode ? 'page' : 'false' ?>"
                >
                    <span class="organization-option-label"><?= e((string) ($organization['label'] ?? strtoupper($code))) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="sidebar-panel sidebar-workspace-panel">
        <div class="sidebar-panel-label">Workspace</div>
        <nav class="sidebar-nav">
            <?php foreach ($navItems as $item): ?>
                <?php $isActive = active_path($item['match']); ?>
                <a class="sidebar-link <?= $isActive ? 'active' : '' ?>" href="<?= e(base_url($item['href'])) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </section>

    <div class="sidebar-footer">
        <div class="sidebar-theme-control">
            <span class="sidebar-theme-label">Appearance</span>
            <button class="theme-toggle theme-switch" type="button" data-theme-toggle aria-pressed="false" aria-label="Switch to dark mode" title="Switch to dark mode">
                <span class="theme-switch-icon theme-switch-sun" aria-hidden="true"><i class="bi bi-sun"></i></span>
                <span class="theme-switch-icon theme-switch-moon" aria-hidden="true"><i class="bi bi-moon-stars"></i></span>
                <span class="theme-switch-thumb" aria-hidden="true"></span>
                <span class="visually-hidden">Switch theme</span>
            </button>
        </div>
        <div class="sidebar-user">
            <span class="user-avatar"><?= e((string) ($activeOrganization['short_label'] ?? 'PPE')) ?></span>
            <span class="sidebar-user-copy">
                <strong><?= e((string) ($activeOrganization['label'] ?? APP_NAME)) ?></strong>
                <small>PPE workspace</small>
            </span>
        </div>
        <a class="sidebar-launcher-link" href="/micei_mis/systems.php" title="Return to system launcher">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            <span>Back to Launcher</span>
        </a>
    </div>
</aside>
