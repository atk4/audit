<?php

declare(strict_types=1);

namespace Atk4\Audit\Demos;

use Atk4\Ui\Exception;
use Atk4\Audit\AuditController;
use Atk4\Data\Persistence;

try {
    require_once file_exists(__DIR__ . '/db.php')
        ? __DIR__ . '/db.php'
        : __DIR__ . '/db.default.php';
} catch (\PDOException $e) {
    // do not show $e unless you can secure DSN!
    throw (new Exception('This demo requires access to the database. See "demos/init-db.php"'))
        ->addMoreInfo('PDO error', $e->getMessage());
}

/** @var Persistence $db */

// enable audit on all persistence
$audit = new AuditController($db);
$audit->observePersistence($db);
