<?php

declare(strict_types=1);

namespace Atk4\Audit\Demo;

use Atk4\Audit\Controller;
use Atk4\Audit\View\History;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Ui\App;
use Atk4\Ui\Columns;
use Atk4\Ui\Crud;
use Atk4\Ui\Header;

require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/database.php';

/** @var App $app */
/** @var Persistence $db */

$audit = new Controller();

$db->onHook(Persistence::HOOK_AFTER_ADD, static function ($owner, $element) use ($audit) {
    if ($element instanceof Model) {
        if (isset($element->no_audit) && $element->no_audit) { // @phpstan-ignore property.notFound
            // Whitelisting this model, won't audit
            return;
        }

        $audit->setUp($element);
    }
});

// set up data model with audit add-on enabled
$m = new Country($db);

// 2 columns
$cols = Columns::addTo($app);
$c1 = $cols->addColumn();
$c2 = $cols->addColumn();

// left side country CRUD
Header::addTo($c1)->set('Countries');
$crud = Crud::addTo($c1);
$crud->setModel($m, ['id', 'name', 'iso', 'iso3']);
$crud->setIpp(5);

// Delete audit data button
$crud->menu
    ->addItem(['Delete ALL audit data', 'icon' => 'trash'])
    ->on('click', static function () use ($m, $c2) {
        $m->ref('AuditLog')->action('delete')->executeStatement();

        return $c2->jsReload();
    });

// add CRUD action to load jailed audit records in lister
$crud->addActionButton('Audit ->', static function ($js, $id) use ($c2) {
    return $c2->jsReload(['model_id' => $id]);
});

// right side Audit History

// create model for form
$m2 = clone $m;
if ($id = $app->stickyGet('model_id')) {
    $m2->addCondition($m2->idField, $id);
    $e2 = $m2->tryLoadAny();
}

Header::addTo($c2)->set(isset($e2) && $e2->isLoaded() ? 'History of ' . $e2->getTitle() : 'All History');
$h = History::addTo($c2);
$h->setModel($m2);
