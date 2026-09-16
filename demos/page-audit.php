<?php

declare(strict_types=1);

namespace Atk4\Audit\Demos;

use Atk4\Audit\Model\AuditLog;
use Atk4\Audit\View\AuditCrud;
use Atk4\Ui\Header;

/** @var App $app */
require_once __DIR__ . '/init-app.php';

Header::addTo($app)->set('AuditLog');

$model = new AuditLog($app->db);
$model->removeUserAction('add');
$model->removeUserAction('delete');
$model->getIdField()->ui['visible'] = true;

$crud = AuditCrud::addTo($app);
$crud->setModel($model);

$crud->addFilterColumn();
$crud->addQuickSearch();
