<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

if (is_logged_in()) {
    redirect('modules/dashboard.php');
}

redirect('auth/login.php');
