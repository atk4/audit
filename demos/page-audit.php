<?php

declare(strict_types=1);

namespace Atk4\Audit\Demos;

use Atk4\Audit\Model\AuditLog;
use Atk4\Audit\View\AuditGrid;
use Atk4\Ui\Header;

/** @var App $app */
require_once __DIR__ . '/init-app.php';

$app->requireCss('../public/css/audit-detail.css');

Header::addTo($app)->set('AuditLog');

$model = new AuditLog($app->db);

$model->getIdField()->ui['visible'] = true;

$grid = AuditGrid::addTo($app);

$grid->setModel($model, [
    'id',
    'action',
    'model',
    'model_id',
    'start_time',
    'duration',
    'descr',
]);

$grid->addFilterColumn();
$grid->addQuickSearch();
