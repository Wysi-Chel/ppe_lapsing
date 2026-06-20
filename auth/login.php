<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

$errors = [];

if (is_logged_in()) {
    redirect('modules/dashboard.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $password = (string) request_value('password', '');

    if (login_with_access_password($password)) {
        redirect('modules/dashboard.php');
    }

    $errors[] = 'The password you entered is incorrect.';
}

$pageTitle = 'Login';
$pageHeading = 'Login';

require_once APP_ROOT . '/includes/header.php';
?>
<section class="auth-card">
    <div>
        <p class="eyebrow mb-2">Secure access</p>
        <h1 class="auth-title mb-2"><?= e(APP_NAME) ?></h1>
    </div>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger">
            <?= e(implode(' ', $errors)) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="stack-gap">
        <div>
            <label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" autofocus required>
        </div>
        <button class="btn btn-primary" type="submit">
            <i class="bi bi-box-arrow-in-right me-2"></i>Login
        </button>
    </form>
</section>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
