<?php

declare(strict_types=1);

namespace Atk4\Audit\Tests;

use Atk4\Audit\Controller;
use Atk4\Audit\Model\AuditLog;
use Atk4\Data\Model;
use Atk4\Schema\PhpunitTestCase;

class AuditableGenderUser extends Model
{
    public $table = 'user';

    public $audit_model;

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
        $this->addField('gender', ['enum' => ['M', 'F']]);

        $this->add(new Controller());

        $this->onHook(self::HOOK_BEFORE_SAVE, function ($m) {
            if ($m->isDirty('gender')) {
                $m->auditController->custom_action = 'genderbending';
            }
        });
    }
}

class CustomLog extends AuditLog
{
    public function getDescr()
    {
        return count($this->get('request_diff')) . ' fields magically change';
    }
}

/**
 * Tests basic create, update and delete operations.
 */
class CustomTest extends PhpunitTestCase
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
        'revert_audit_log_id' => 1,
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
        $this->setDb($q);

        $m = new AuditableGenderUser($this->db);

        $m->load(1); // load Vinny
        $m->set('gender', 'F');
        $m->save();

        $l = $m->ref('AuditLog')->loadLast();

        $this->assertSame('genderbending', $l->get('action'));
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
        $this->setDb($q);

        $m = new AuditableGenderUser($this->db);

        $m->load(2); // load Zoe
        $m->auditController->custom_action = 'married';
        $m->set('surname', 'Shira');
        $m->save();

        $l = $m->ref('AuditLog')->loadLast();

        $this->assertSame('married', $l->get('action'));
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
        $this->setDb($q);

        $m = new AuditableGenderUser($this->db);

        $m->load(2); // load Zoe
        $m->log('load', 'Testing', ['request_diff' => ['foo' => 'bar']]);

        $l = $m->ref('AuditLog')->loadLast();

        $this->assertSame('load', $l->get('action'));
        $this->assertSame(['foo' => 'bar'], $l->get('request_diff'));
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
        $this->setDb($q);

        $m = new AuditableGenderUser($this->db, ['audit_model' => new CustomLog()]);

        $m->load(2); // load Zoe
        $m->set('name', 'Joe');
        $m->set('surname', 'XX');
        $m->save();

        $l = $m->ref('AuditLog')->loadLast();

        $this->assertSame('2 fields magically change', $l->get('descr'));
    }
}
