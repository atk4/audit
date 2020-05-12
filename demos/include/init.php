<?php

date_default_timezone_set('UTC');

require __DIR__.'/../../vendor/autoload.php';

$app = new \atk4\ui\App('Audit Demo');
$app->initLayout(\atk4\ui\Layout\Admin::class);

$app->layout->menuLeft->addItem(['Migration', 'icon'=>'gift'], ['wizard']);
$app->layout->menuLeft->addItem(['Audit Demo', 'icon'=>'list'], ['demo']);
