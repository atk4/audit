<?php

require_once 'init.php';
require_once 'database.php';

// set up data model with audit add-on enabled
$m = new Country($db);
$c = $m->add(new \atk4\audit\Controller());

// do schema migration
(new \atk4\schema\Migration\MySQL($m))->migrate();
(new \atk4\schema\Migration\MySQL($c->getAuditModel()))->migrate();



// columns
$cols = $app->add('Columns');

// left side country CRUD
$c1 = $cols->addColumn();
$c1->add('Header')->set('Countries');
$crud = $c1->add('CRUD', []);
$crud->setModel($m, ['id', 'name', 'iso', 'iso3']);
$crud->setIpp(5);


// right side Audit Lister
$c2 = $cols->addColumn();

// load model
if (isset($_GET['id'])) {
    $m->load($_GET['id']);
}
$c2->add('Header')->set($m->loaded() ? 'Audit List for '.$m->getTitle() : 'Full Audit List');



// Delete audit data button
$c2->add('Button')->set('Delete ALL audit data')->on('click', function()use($c, $c2){
    $c->getAuditModel()->action('delete')->execute();
    return $c2->jsReload();
});


// Lister
$l = $c2->add(new \atk4\audit\view\Lister());
$l->setModel($m);



// add CRUD action to load jailed audit records in lister
$crud->addAction('Audit ->', function($js, $id)use($c2){
    return $c2->jsReload(['id'=>$id]);
});
