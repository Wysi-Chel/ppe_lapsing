<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

if (is_logged_in()) {
    redirect('modules/dashboard.php');
}

$databaseError = db_error();
$firstUserSetup = false;
$email = trim((string) request_value('email', ''));

if ($databaseError === null) {
    $firstUserSetup = count_users(db()) === 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $databaseError === null) {
    $password = (string) request_value('password', '');

    $statement = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();

    if ($user && password_verify($password, (string) $user['password'])) {
        login_user($user);
        set_flash('success', 'Welcome back, ' . $user['full_name'] . '.');
        redirect('modules/dashboard.php');
    }

    set_flash('danger', 'The email or password you entered is incorrect.');
}

$pageTitle = 'Login';
$pageHeading = 'Sign in';
$pageDescription = 'Access the PPE asset dashboard, schedules, and AI analysis tools.';

require_once APP_ROOT . '/includes/header.php';
?>
<div class="auth-grid">
    <section class="hero-panel">
        <div>
            <span class="auth-kicker">
                <i class="bi bi-stars"></i>
                Straight-line depreciation system
            </span>
            <h1 class="auth-title">Track PPE records, depreciation, and analysis in one place.</h1>
            <p class="auth-copy mb-0">
                Manage acquisition cost, useful life, carrying amount, and review notes for admins, accounting staff, and auditors.
            </p>
        </div>

        <div class="feature-grid">
            <article class="feature-card">
                <strong>Dashboard summary</strong>
                <p class="feature-copy mb-0">View total PPE cost, accumulated depreciation, near-end assets, and category totals from one dashboard.</p>
            </article>
            <article class="feature-card">
                <strong>Depreciation schedule</strong>
                <p class="feature-copy mb-0">Generate year-by-year straight-line depreciation without preparing each schedule manually.</p>
            </article>
            <article class="feature-card">
                <strong>Record review</strong>
                <p class="feature-copy mb-0">Check unusual salvage values, fully depreciated active assets, and missing setup details more easily.</p>
            </article>
            <article class="feature-card">
                <strong>Analysis report</strong>
                <p class="feature-copy mb-0">Generate a written PPE summary with OpenAI or use the built-in fallback analysis.</p>
            </article>
        </div>
    </section>

    <section class="auth-card">
        <div>
            <p class="eyebrow mb-2">Login</p>
            <h2 class="auth-title h3 mb-2">Log in to continue</h2>
            <p class="text-soft mb-0">Use the seeded demo accounts below or replace them after setup.</p>
        </div>

        <?php if ($databaseError !== null): ?>
            <div class="alert alert-danger mb-0">
                Database connection failed: <?= e($databaseError) ?>
            </div>
        <?php endif; ?>

        <?php if ($firstUserSetup): ?>
            <div class="alert alert-info mb-0">
                No users were found yet. Create the first admin account here:
                <a class="alert-link" href="<?= e(base_url('auth/register.php')) ?>">open registration</a>.
            </div>
        <?php endif; ?>

        <form method="post" class="stack-gap">
            <div>
                <label class="form-label" for="email">Email address</label>
                <input class="form-control form-control-lg" id="email" name="email" type="email" value="<?= e($email) ?>" placeholder="admin@ppe.local" required>
            </div>
            <div>
                <label class="form-label" for="password">Password</label>
                <input class="form-control form-control-lg" id="password" name="password" type="password" placeholder="Enter your password" required>
            </div>
            <button class="btn btn-primary btn-lg w-100" type="submit" <?= $databaseError !== null ? 'disabled' : '' ?>>
                <i class="bi bi-box-arrow-in-right me-2"></i>Log in
            </button>
        </form>

        <div class="helper-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Seeded demo accounts</strong>
                <span class="badge text-bg-dark">for local testing</span>
            </div>
            <div class="stack-gap">
                <div class="kpi-inline"><i class="bi bi-person-badge"></i><span><strong>Admin:</strong> admin@ppe.local / admin123</span></div>
                <div class="kpi-inline"><i class="bi bi-pencil-square"></i><span><strong>Staff:</strong> staff@ppe.local / staff123</span></div>
                <div class="kpi-inline"><i class="bi bi-search"></i><span><strong>Auditor:</strong> auditor@ppe.local / auditor123</span></div>
            </div>
        </div>
    </section>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
