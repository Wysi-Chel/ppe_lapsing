<?php
declare(strict_types=1);

function current_user(): ?array
{
    return isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])
        ? $_SESSION['auth_user']
        : null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function login_user(array $user): void
{
    $_SESSION['auth_user'] = [
        'user_id' => (int) $user['user_id'],
        'full_name' => (string) $user['full_name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];
}

function logout_user(): void
{
    unset($_SESSION['auth_user']);
}

function user_has_role(array|string $roles): bool
{
    $user = current_user();

    if (!$user) {
        return false;
    }

    $roles = is_array($roles) ? $roles : [$roles];

    return in_array($user['role'], $roles, true);
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please log in to continue.');
        redirect('auth/login.php');
    }
}

function require_admin(): void
{
    require_login();

    if (!user_has_role('Admin')) {
        set_flash('danger', 'Admin access is required for that page.');
        redirect('modules/dashboard.php');
    }
}

function can_manage_assets(): bool
{
    return user_has_role(['Admin', 'Accounting Staff']);
}

function can_manage_users(): bool
{
    return user_has_role('Admin');
}
