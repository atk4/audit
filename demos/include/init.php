<?php

declare(strict_types=1);

namespace Atk4\Audit\Demo;

use Atk4\Ui\App;
use Atk4\Ui\Layout\Admin;

date_default_timezone_set('UTC');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/MigratorConsole.php';

$app = new App(['title' => 'Audit Demo']);
$app->initLayout([Admin::class]);

/** @var Admin $app->layout */
$app->layout->menuLeft->addItem(['Migration', 'icon' => 'gift'], ['wizard']);
$app->layout->menuLeft->addItem(['Audit Demo', 'icon' => 'list'], ['demo']);
