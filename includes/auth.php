<?php
declare(strict_types=1);

function normalize_auth_user(array $user): array
{
    return [
        'user_id' => isset($user['user_id']) && $user['user_id'] !== null ? (int) $user['user_id'] : null,
        'full_name' => trim((string) ($user['full_name'] ?? 'Local Operator')) ?: 'Local Operator',
        'email' => trim((string) ($user['email'] ?? 'local@ppe.local')) ?: 'local@ppe.local',
        'role' => trim((string) ($user['role'] ?? 'Admin')) ?: 'Admin',
    ];
}

function default_user(): array
{
    return [
        'user_id' => null,
        'full_name' => 'MICEI Operator',
        'email' => 'operator@ppe.local',
        'role' => 'Admin',
    ];
}

function current_user(): ?array
{
    if (isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])) {
        return normalize_auth_user($_SESSION['auth_user']);
    }

    return null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function login_user(array $user): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['auth_user'] = normalize_auth_user($user);
}

function login_with_access_password(string $password): bool
{
    if (!hash_equals((string) APP_ACCESS_PASSWORD, $password)) {
        return false;
    }

    login_user(default_user());

    return true;
}

function logout_user(): void
{
    unset($_SESSION['auth_user']);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
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
