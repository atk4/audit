<?php

declare(strict_types=1);

namespace Atk4\Audit\Demos;

use Atk4\Data\Persistence;
use Atk4\Ui\Exception;

date_default_timezone_set('UTC');

require_once __DIR__ . '/init-autoloader.php';

$app = new App();

try {
    /** @var Persistence\Sql $db */
    require_once __DIR__ . '/init-db.php';
    $app->db = $db;
    unset($db);
} catch (\Throwable $e) {
    throw new Exception('Database error: ' . $e->getMessage());
}

$app->init();
