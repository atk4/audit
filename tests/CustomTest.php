<?php

declare(strict_types=1);

namespace Atk4\Audit\Tests;

use Atk4\Audit\AuditableModelTrait;
use Atk4\Audit\Controller;
use Atk4\Audit\Model\AuditLog;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class AuditableGenderUser extends Model
{
    use AuditableModelTrait;

    public $table = 'user';

    public $audit_model;

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
        $this->addField('gender', ['enum' => ['M', 'F']]);

        $this->add(new Controller());

        $this->onHook(self::HOOK_BEFORE_SAVE, static function ($m) {
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
class CustomTest extends TestCase
{
    protected $audit_db = [[
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

        $entity = $m->load(1); // load Vinny
        $entity->set('gender', 'F');
        $entity->save();

        $l = $entity->ref('AuditLog')->loadLast();

        self::assertSame('genderbending', $l->get('action'));
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

        $entity = $m->load(2); // load Zoe
        $entity->auditController->custom_action = 'married';
        $entity->set('surname', 'Shira');
        $entity->save();

        $l = $entity->ref('AuditLog')->loadLast();

        self::assertSame('married', $l->get('action'));
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

        $entity = $m->load(2); // load Zoe
        $entity->log('load', 'Testing', ['request_diff' => ['foo' => 'bar']]);

        $l = $entity->ref('AuditLog')->loadLast();

        self::assertSame('load', $l->get('action'));
        self::assertSame(['foo' => 'bar'], $l->get('request_diff'));
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

        $entity = $m->load(2); // load Zoe
        $entity->set('name', 'Joe');
        $entity->set('surname', 'XX');
        $entity->save();

        $l = $entity->ref('AuditLog')->loadLast();

        self::assertSame('2 fields magically change', $l->get('descr'));
    }
}
