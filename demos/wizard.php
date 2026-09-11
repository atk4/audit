<?php

declare(strict_types=1);

namespace Atk4\Audit\Demo;

use Atk4\Data\Persistence;
use Atk4\Ui\App;
use Atk4\Ui\Button;
use Atk4\Ui\Header;

require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/database.php';

// @var App $app
// @var Persistence $db

Header::addTo($app, ['Quickly checking if database is OK']);
$console = MigratorConsole::addTo($app);

$button = Button::addTo($app, ['<< Back', 'huge wide blue'])
    ->setStyle('display', 'none')
    ->link(['index']);

// do migration
$console->migrateModels(['\Country', '\Atk4\Audit\Model\AuditLog']);
