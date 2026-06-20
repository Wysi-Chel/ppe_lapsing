<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

logout_user();
redirect('auth/login.php');
