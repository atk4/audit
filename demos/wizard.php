<?php

declare(strict_types=1);

namespace Atk4\Audit\Demo;

require_once 'include/init.php';
require_once 'include/database.php';

\Atk4\Ui\Header::addTo($app, ['Quickly checking if database is OK']);
$console = MigratorConsole::addTo($app);

$button = \Atk4\Ui\Button::addTo($app, ['<< Back', 'huge wide blue'])
    ->addStyle('display', 'none')
    ->link(['index']);

// do migration
$console->migrateModels(['\Country', '\Atk4\Audit\Model\AuditLog']);
