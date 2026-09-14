<?php

declare(strict_types=1);

namespace Atk4\Audit\Demos;

use Atk4\Audit\Model\AuditLog;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Schema\Migrator;

require_once __DIR__ . '/../init-autoloader.php';

$sqliteFile = __DIR__ . '/db.sqlite';
if (!file_exists($sqliteFile)) {
    new Persistence\Sql('sqlite:' . $sqliteFile);
}
unset($sqliteFile);

/** @var Persistence\Sql $db */
require_once __DIR__ . '/../init-db.php';

$model = new AuditLog($db);
(new Migrator($model))->create();

echo 'import complete!' . "\n\n";
