<?php

declare(strict_types=1);

require_once 'include/init.php';
require_once 'include/database.php';

\atk4\ui\Header::addTo($app, ['Quickly checking if database is OK']);
$console = \atk4\schema\MigratorConsole::addTo($app);

$button = \atk4\ui\Button::addTo($app, ['<< Back', 'huge wide blue'])
    ->addStyle('display', 'none')
    ->link(['index']);

// do migration
$console->migrateModels(['\Country', '\atk4\audit\model\AuditLog']);
