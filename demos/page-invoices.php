<?php

declare(strict_types=1);

namespace Atk4\Audit\Demos;

use Atk4\Audit\Demos\Model\Invoice;
use Atk4\Ui\Crud;
use Atk4\Ui\Header;
use Atk4\Ui\Table\Column;

/** @var App $app */
require_once __DIR__ . '/init-app.php';

Header::addTo($app)->set('Invoices');

$model = new Invoice($app->db);

$crud = Crud::addTo($app);
$crud->setModel($model);

$crud->addDecorator($model->titleField, [
    Column\Link::class,
    ['page-invoicelines'],
    ['_id' => $model->fieldName()->id],
]);
