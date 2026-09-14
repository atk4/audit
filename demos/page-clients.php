<?php

declare(strict_types=1);

namespace Atk4\Audit\Demos;

use Atk4\Audit\Demos\Model\Client;
use Atk4\Ui\Crud;
use Atk4\Ui\Header;

/** @var App $app */
require_once __DIR__ . '/init-app.php';

Header::addTo($app)->set('Clients');


$model = new Client($app->db);

Crud::addTo($app)->setModel($model);
