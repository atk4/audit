<?php

require_once 'include/init.php';
require_once 'include/database.php';

$app->add(['Header', 'Quickly checking if database is OK']);
$console = $app->add('\atk4\schema\MigratorConsole');

$button = $app->add(['Button', '<< Back', 'huge wide blue'])
    ->addStyle('display', 'none')
    ->link(['index']);

// do migration
$console->migrateModels(['\Country', '\atk4\audit\model\AuditLog']);
