<?php

namespace atk4\ui\tests;

use atk4\data\Model;

class AuditableUser extends \atk4\data\Model {
    public $table = 'user';

    function init()
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');

        $this->add(new \atk4\audit\Controller());
    }
}

/**
 * Tests basic create, update and delete operatiotns
 */
class CRUDTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testUpdate()
    {

        $q = [
            'user' => [
                ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Steve', 'surname' => 'Jobs'],
            ],
            'audit_log' => [
                '_' => [
                    'initiator_audit_log_id' => 1,
                    'ts' => '', 
                    'model' => '',
                    'model_id' => 1,
                    'action' => '',
                    'user_info' => '',
                    'time_taken' => 1.1,
                    'request_diff' => '',
                    'reactive_diff' => '',
                    'descr' => '',
                    'is_reverted' => '',
                    'revert_audit_log_id' => 1
                ]
            ],
        ];
        $this->setDB($q);

        $m = new AuditableUser($this->db);

        $m->tryLoadAny();
        $m['name'] = 'QQ';
        $m->save();

        $l = $m->ref('AuditLog');
        $l->loadLast();

        $this->assertEquals('update name=QQ', $l['descr']);
        $this->assertEquals(['name' => ['John', 'QQ']], $l['request_diff']);

        $m->load(2);
        $m['name'] = 'XX';
        $m->save();
        $m['name'] = 'YY';
        $m->save();
        $this->assertEquals(2, $m->ref('AuditLog')->action('count')->getOne());
    }

    public function testUndo()
    {

        $q = [
            'user' => [
                ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Steve', 'surname' => 'Jobs'],
            ],
            'audit_log' => [
                '_' => [
                    'initiator_audit_log_id' => 1,
                    'ts' => '', 
                    'model' => '',
                    'model_id' => 1,
                    'action' => '',
                    'user_info' => '',
                    'time_taken' => 1.1,
                    'request_diff' => '',
                    'reactive_diff' => '',
                    'descr' => '',
                    'is_reverted' => '',
                    'revert_audit_log_id' => 1
                ]
            ],
        ];
        $this->setDB($q);

        $m = new AuditableUser($this->db);

        $m->tryLoadAny();
        $m['name'] = 'QQ';
        $m->save();

        $l = $m->ref('AuditLog');
        $l->loadLast()->undo();


        $m->reload();
        $this->assertEquals('John', $m['name']);
        $this->assertEquals(2, $m->ref('AuditLog')->action('count')->getOne());

        $l = $m->ref('AuditLog')->loadLast();

        $this->assertEquals(1, $l['revert_audit_log_id']);
        $this->assertEquals(false, $l['is_reverted']);
    }
}
