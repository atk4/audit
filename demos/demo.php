<?php

use atk4\data\Persistence;

require_once 'include/init.php';
require_once 'include/database.php';

$audit = new \atk4\audit\Controller();
/** @var Persistence $db */
$db->onHook(Persistence::HOOK_AFTER_ADD, function ($owner, $element) use ($audit) {
    if ($element instanceof \atk4\data\Model) {
        if (isset($element->no_audit) && $element->no_audit) {
            // Whitelisting this model, won't audit
            return;
        }

        $audit->setUp($element);
    }
});
// set up data model with audit add-on enabled
$m = new Country($db);

// 2 columns
$cols = \atk4\ui\Columns::addTo($app);
$c1 = $cols->addColumn();
$c2 = $cols->addColumn();



// left side country CRUD
\atk4\ui\Header::addTo($c1)->set('Countries');
$crud = \atk4\ui\CRUD::addTo($c1);
$crud->setModel($m, ['id', 'name', 'iso', 'iso3']);
$crud->setIpp(5);

// Delete audit data button
$crud->menu
    ->addItem(['Delete ALL audit data', 'icon' => 'trash'])
    ->on('click', function () use ($m, $c2) {
        $m->ref('AuditLog')->action('delete')->execute();
        return $c2->jsReload();
    });

// add CRUD action to load jailed audit records in lister
$crud->addActionButton('Audit ->', function ($js, $id) use ($c2) {
    return $c2->jsReload(['model_id'=>$id]);
});



// right side Audit History

// create model for form
$m2 = clone $m;
if ($id = $app->stickyGet('model_id')) {
    $m2->load($id);
}

\atk4\ui\Header::addTo($c2)->set($m2->loaded() ? 'History of ' . $m2->getTitle() : 'All History');
$h = $c2->add(new \atk4\audit\view\History());
$h->setModel($m2);
