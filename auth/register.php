<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

$databaseError = db_error();
$pdo = $databaseError === null ? db() : null;
$userCount = $pdo instanceof PDO ? count_users($pdo) : 0;
$bootstrapSetup = $userCount === 0;

if (!$bootstrapSetup) {
    require_admin();
}

$errors = [];
$form = [
    'full_name' => trim((string) request_value('full_name', '')),
    'email' => trim((string) request_value('email', '')),
    'role' => (string) request_value('role', 'Accounting Staff'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
    $password = (string) request_value('password', '');
    $confirmPassword = (string) request_value('confirm_password', '');
    $role = $bootstrapSetup ? 'Admin' : $form['role'];

    if ($form['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }

    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!in_array($role, ['Admin', 'Accounting Staff', 'Auditor'], true)) {
        $errors[] = 'Please choose a valid role.';
    }

    if ($errors === []) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $check->execute(['email' => $form['email']]);

        if ((int) $check->fetchColumn() > 0) {
            $errors[] = 'That email address is already being used.';
        }
    }

    if ($errors === []) {
        $insert = $pdo->prepare(
            'INSERT INTO users (full_name, email, password, role) VALUES (:full_name, :email, :password, :role)'
        );
        $insert->execute([
            'full_name' => $form['full_name'],
            'email' => $form['email'],
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
        ]);

        if ($bootstrapSetup) {
            $user = [
                'user_id' => (int) $pdo->lastInsertId(),
                'full_name' => $form['full_name'],
                'email' => $form['email'],
                'role' => 'Admin',
            ];
            login_user($user);
            set_flash('success', 'The first admin account is ready.');
            redirect('modules/dashboard.php');
        }

        set_flash('success', 'User account created successfully.');
        redirect('auth/register.php');
    }
}

$pageTitle = 'User Management';
$pageHeading = $bootstrapSetup ? 'Create the first admin account' : 'User Management';
$pageDescription = $bootstrapSetup
    ? 'Set up the first administrator so the PPE system can be used.'
    : 'Create additional users and assign the right level of access.';

require_once APP_ROOT . '/includes/header.php';
?>
<?php if ($databaseError !== null): ?>
    <div class="alert alert-danger">Database connection failed: <?= e($databaseError) ?></div>
<?php else: ?>
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger">
            <?= e(implode(' ', $errors)) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <section class="shell-card h-100">
                <div class="mb-4">
                    <p class="eyebrow mb-2"><?= $bootstrapSetup ? 'Initial setup' : 'Create account' ?></p>
                    <h2 class="section-title mb-2"><?= $bootstrapSetup ? 'First administrator' : 'New user profile' ?></h2>
                    <p class="section-subtitle">
                        <?= $bootstrapSetup ? 'This first account will automatically become an Admin.' : 'Choose a role based on what the user should manage or review.' ?>
                    </p>
                </div>

                <form method="post" class="stack-gap">
                    <div>
                        <label class="form-label" for="full_name">Full name</label>
                        <input class="form-control" id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" required>
                    </div>
                    <div>
                        <label class="form-label" for="email">Email address</label>
                        <input class="form-control" id="email" name="email" type="email" value="<?= e($form['email']) ?>" required>
                    </div>
                    <div>
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control" id="password" name="password" type="password" minlength="6" required>
                    </div>
                    <div>
                        <label class="form-label" for="confirm_password">Confirm password</label>
                        <input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="6" required>
                    </div>
                    <div>
                        <label class="form-label" for="role">Role</label>
                        <select class="form-select" id="role" name="role" <?= $bootstrapSetup ? 'disabled' : '' ?>>
                            <option value="Admin" <?= selected_if($form['role'], 'Admin') ?>>Admin</option>
                            <option value="Accounting Staff" <?= selected_if($form['role'], 'Accounting Staff') ?>>Accounting Staff</option>
                            <option value="Auditor" <?= selected_if($form['role'], 'Auditor') ?>>Auditor</option>
                        </select>
                        <?php if ($bootstrapSetup): ?>
                            <input type="hidden" name="role" value="Admin">
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-person-plus me-2"></i><?= $bootstrapSetup ? 'Create Admin Account' : 'Create User' ?>
                    </button>
                </form>
            </section>
        </div>

        <?php if (!$bootstrapSetup): ?>
            <div class="col-lg-7">
                <section class="shell-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <p class="eyebrow mb-2">Current access list</p>
                            <h2 class="section-title mb-0">Existing users</h2>
                        </div>
                        <span class="badge text-bg-dark"><?= e((string) $userCount) ?> <?= e(pluralize($userCount, 'user')) ?></span>
                    </div>
                    <div class="table-wrap">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (fetch_users($pdo) as $user): ?>
                                    <tr>
                                        <td><?= e($user['full_name']) ?></td>
                                        <td><?= e($user['email']) ?></td>
                                        <td><span class="badge <?= e(role_badge_class((string) $user['role'])) ?>"><?= e($user['role']) ?></span></td>
                                        <td><?= e(format_date((string) $user['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
