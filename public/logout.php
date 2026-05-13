<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

logout();
flash_set('ok', 'Logged out.');
redirect('/index.php');

