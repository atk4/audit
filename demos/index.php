<?php

require_once 'init.php';
require_once 'database.php';

// set up data model with audit add-on enabled
$m = new Country($db);
$c = $m->add(new \atk4\audit\Controller());

// jail in particular record
if (isset($_GET['model_id'])) {
    $m->ref('AuditLog')->addCondition('model_id', $_GET['model_id']);
}

// do schema migration
(new \atk4\schema\Migration\MySQL($m))->migrate();
(new \atk4\schema\Migration\MySQL($m->ref('AuditLog')))->migrate();



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



// right side Audit Lister
$c2->add('Header')->set($m->loaded() ? 'History of '.$m->getTitle() : 'All History');
$h = $c2->add(new \atk4\audit\view\History());
$h->setModel($m);



// add CRUD action to load jailed audit records in lister
$crud->addAction('Audit ->', function($js, $id)use($c2){
    return $c2->jsReload(['model_id'=>$id]);
});
