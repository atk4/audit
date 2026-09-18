<?php

declare(strict_types=1);

namespace Atk4\Audit\Demos;

use Atk4\Audit\Model\AuditLog;
use Atk4\Audit\View\AuditGrid;
use Atk4\Ui\CardTable;
use Atk4\Ui\Header;
use Atk4\Ui\View;

/** @var App $app */
require_once __DIR__ . '/init-app.php';

Header::addTo($app)->set('AuditLog');

$model = new AuditLog($app->db);
$model->getIdField()->ui['visible'] = true;

$crud = AuditGrid::addTo($app);
$crud->setModel($model);

$crud->addFilterColumn();
$crud->addQuickSearch();

$crud->addModalAction(['icon' => 'binoculars'], 'AuditLog Entry', static function (View $p, int $id) use ($crud) {
    $m = $crud->model->load($id);
    $t = CardTable::addTo($p);
    $t->setEntity($m);
});
// move last column to the beginning in table column array
array_unshift($crud->table->columns, array_pop($crud->table->columns));
