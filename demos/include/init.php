<?php

declare(strict_types=1);

namespace Atk4\Audit\Demo;

date_default_timezone_set('UTC');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/MigratorConsole.php';

$app = new \Atk4\Ui\App('Audit Demo');
$app->initLayout([\Atk4\Ui\Layout\Admin::class]);

$app->layout->menuLeft->addItem(['Migration', 'icon' => 'gift'], ['wizard']);
$app->layout->menuLeft->addItem(['Audit Demo', 'icon' => 'list'], ['demo']);
