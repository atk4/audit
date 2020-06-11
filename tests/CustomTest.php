<?php

namespace atk4\audit\tests;

use atk4\data\Model;

class AuditableGenderUser extends \atk4\data\Model
{
    public $table = 'user';

    public $audit_model;

    public function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
        $this->addField('gender', ['enum' => ['M','F']]);

        $this->add(new \atk4\audit\Controller());

        $this->onHook(self::HOOK_BEFORE_SAVE, function ($m) {
            if ($m->isDirty('gender')) {
                //$m->audit_log['action'] = 'genderbending'; // deprecated usage
                //$m->auditController->audit_log_stack[0]['action'] = 'genderbending';
                $m->auditController->custom_action = 'genderbending';
            }
        });
    }
}

class CustomLog extends \atk4\audit\model\AuditLog
{
    public function getDescr()
    {
        return count($this->get('request_diff')) . ' fields magically change';
    }
}

/**
 * Tests basic create, update and delete operatiotns
 */
class CustomTest extends \atk4\schema\PhpunitTestCase
{
    protected $audit_db = ['_' => [
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

        $m->load(1); // load Vinny
        $m->set('gender', 'F');
        $m->save();

        $l = $m->ref('AuditLog')->loadLast();

        $this->assertEquals('genderbending', $l->get('action'));
    }

    public function testCustomAction()
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

        $m->load(2); // load Zoe
        $m->auditController->custom_action = 'married';
        $m->set('surname', 'Shira');
        $m->save();

        $l = $m->ref('AuditLog')->loadLast();

        $this->assertEquals('married', $l->get('action'));
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

        $m->load(2); // load Zoe
        $m->log('load', 'Testing', ['request_diff' => ['foo'=>'bar']]);

        $l = $m->ref('AuditLog')->loadLast();

        $this->assertEquals('load', $l->get('action'));
        $this->assertEquals(['foo'=>'bar'], $l->get('request_diff'));
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

        $m->load(2); // load Zoe
        $m->set('name', 'Joe');
        $m->set('surname', 'XX');
        $m->save();

        $l = $m->ref('AuditLog')->loadLast();

        $this->assertEquals('2 fields magically change', $l->get('descr'));
    }
}
