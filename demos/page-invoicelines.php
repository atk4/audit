<?php

declare(strict_types=1);

namespace Atk4\Audit\Demos;

use Atk4\Audit\Demos\Model\Invoice;
use Atk4\Audit\Demos\Model\InvoiceLine;
use Atk4\Ui\Crud;
use Atk4\Ui\Header;

/** @var App $app */
require_once __DIR__ . '/init-app.php';

Header::addTo($app)->set('Invoice Lines');

$invoice_id = $app->stickyGet('_id');

$model = new Invoice($app->db);
$invoice = $model->load($invoice_id);

$lines = $invoice->ref('Lines');

$crud = Crud::addTo($app);
$crud->setModel($lines);

$crud->addButton('Back to Invoices')->link('page-invoices.php');
