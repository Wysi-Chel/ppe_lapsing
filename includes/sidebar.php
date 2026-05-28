<?php
declare(strict_types=1);

$navItems = [
    ['label' => 'Dashboard', 'href' => 'modules/dashboard.php', 'icon' => 'bi-speedometer2', 'match' => '/modules/dashboard.php'],
    ['label' => 'Assets', 'href' => 'modules/assets.php', 'icon' => 'bi-pc-display', 'match' => '/modules/assets.php'],
    ['label' => 'Depreciation', 'href' => 'modules/depreciation.php', 'icon' => 'bi-graph-up-arrow', 'match' => '/modules/depreciation.php'],
    ['label' => 'Reports', 'href' => 'modules/reports.php', 'icon' => 'bi-bar-chart-line', 'match' => '/modules/reports.php'],
    ['label' => 'AI Analysis', 'href' => 'modules/ai_analysis.php', 'icon' => 'bi-stars', 'match' => '/modules/ai_analysis.php'],
];

if (can_manage_users()) {
    $navItems[] = ['label' => 'Users', 'href' => 'auth/register.php', 'icon' => 'bi-people', 'match' => '/auth/register.php'];
}
?>
<aside class="sidebar">
    <div class="brand-block">
        <div class="brand-mark">PPE</div>
        <div>
            <p class="brand-kicker mb-1">Fixed asset records</p>
            <h2 class="brand-title mb-0">PPE Lapsing System</h2>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
            <?php $isActive = active_path($item['match']); ?>
            <a class="sidebar-link <?= $isActive ? 'active' : '' ?>" href="<?= e(base_url($item['href'])) ?>">
                <i class="bi <?= e($item['icon']) ?>"></i>
                <span><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-note">
            <span>Today</span>
            <strong><?= e(date('M d, Y')) ?></strong>
        </div>
        <a class="btn btn-outline-light w-100" href="<?= e(base_url('auth/logout.php')) ?>">
            <i class="bi bi-box-arrow-right me-2"></i>Log out
        </a>
    </div>
</aside>
