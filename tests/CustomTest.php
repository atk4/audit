<?php

namespace atk4\audit\tests;

use atk4\data\Model;

class AuditableGenderUser extends \atk4\data\Model {
    public $table = 'user';

    function init()
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
        $this->addField('gender', ['enum' => ['M','F']]);

        $this->add(new \atk4\audit\Controller());

        $this->addHook('beforeSave', function($m) {
            if ($m->isDirty('gender')) {
                $m->audit_log['action'] = 'genderbending';
            }
        });
    }
}

class CustomLog extends \atk4\audit\model\AuditLog {
    function getDescr(){
        return count($this['request_diff']).' fields magically change';
    }
}

/**
 * Tests basic create, update and delete operatiotns
 */
class CustomTest extends \atk4\schema\PHPUnit_SchemaTestCase
{

    private $audit_db = ['_' => [
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
                ]];


    public function testBending()
    {

        $q = [
            'user' => [
                ['name' => 'Vinny', 'surname' => 'Shira', 'gender' => 'M'],
                ['name' => 'Zoe', 'surname' => 'Shatwell', 'gender' => 'F'],
            ],
            'audit_log' => $this->audit_db,
        ];
        $this->setDB($q);

        $m = new AuditableGenderUser($this->db);

        $m->tryLoadAny();
        $m['gender'] = 'F';
        $m->save();

        $l = $m->ref('AuditLog');
        $l->loadLast();

        $this->assertEquals('genderbending', $l['action']);
    }

    public function testLogCustom()
    {

        $q = [
            'user' => [
                ['name' => 'Vinny', 'surname' => 'Shira', 'gender' => 'M'],
                ['name' => 'Zoe', 'surname' => 'Shatwell', 'gender' => 'F'],
            ],
            'audit_log' => $this->audit_db,
        ];
        $this->setDB($q);

        $m = new AuditableGenderUser($this->db);

        $m->load(2);
        $m->audit_log_controller->custom_action = 'married';
        $m['surname'] = 'Shira';
        $m->save();

        $l = $m->ref('AuditLog');
        $l->loadLast();

        $this->assertEquals('married', $l['action']);
    }

    public function testManualLog()
    {

        $q = [
            'user' => [
                ['name' => 'Vinny', 'surname' => 'Shira', 'gender' => 'M'],
                ['name' => 'Zoe', 'surname' => 'Shatwell', 'gender' => 'F'],
            ],
            'audit_log' => $this->audit_db,
        ];
        $this->setDB($q);

        $m = new AuditableGenderUser($this->db);

        $m->load(2);
        $m->log('load', ['foo'=>'bar']);

        $l = $m->ref('AuditLog');
        $l->loadLast();

        $this->assertEquals('load', $l['action']);
        $this->assertEquals(['foo'=>'bar'], $l['request_diff']);
    }

    public function testCustomDescr()
    {

        $q = [
            'user' => [
                ['name' => 'Vinny', 'surname' => 'Shira', 'gender' => 'M'],
                ['name' => 'Zoe', 'surname' => 'Shatwell', 'gender' => 'F'],
            ],
            'audit_log' => $this->audit_db,
        ];
        $this->setDB($q);

        $m = new AuditableGenderUser($this->db, ['audit_model' => new CustomLog()]);

        $m->load(2);
        $m['name'] = 'Joe';
        $m['surname'] = 'XX';
        $m->save();

        $l = $m->ref('AuditLog');
        $l->loadLast();

        $this->assertEquals('2 fields magically change', $l['descr']);
    }


}
