<?php

date_default_timezone_set('UTC');

require'../vendor/autoload.php';

$app = new \atk4\ui\App('Audit Demo');
$app->initLayout('Admin');

$app->layout->leftMenu->addItem(['Migration', 'icon'=>'gift'], ['wizard']);
$app->layout->leftMenu->addItem(['Audit Demo', 'icon'=>'list'], ['demo']);
