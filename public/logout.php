<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/session.php';

logout_user();
start_session();
flash_set('success', 'You have logged out.');
redirect(url('login.php'));
