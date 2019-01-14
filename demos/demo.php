<?php

require_once 'include/init.php';
require_once 'include/database.php';

// set up data model with audit add-on enabled
$m = new Country($db);
$c = $m->add(new \atk4\audit\Controller());



// 2 columns
$cols = $app->add('Columns');
$c1 = $cols->addColumn();
$c2 = $cols->addColumn();



// left side country CRUD
$c1->add('Header')->set('Countries');
$crud = $c1->add('CRUD', []);
$crud->setModel($m, ['id', 'name', 'iso', 'iso3']);
$crud->setIpp(5);

// Delete audit data button
$crud->menu
    ->addItem(['Delete ALL audit data', 'icon' => 'trash'])
    ->on('click', function()use($m, $c2){
        $m->ref('AuditLog')->action('delete')->execute();
        return $c2->jsReload();
    });

// add CRUD action to load jailed audit records in lister
$crud->addAction('Audit ->', function($js, $id)use($c2){
    return $c2->jsReload(['model_id'=>$id]);
});



// right side Audit History

// create model for form
$m2 = clone $m;
if ($id = $app->stickyGet('model_id')) {
    $m2->load($id);
}

$c2->add('Header')->set($m2->loaded() ? 'History of '.$m2->getTitle() : 'All History');
$h = $c2->add(new \atk4\audit\view\History());
$h->setModel($m2);
