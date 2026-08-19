<?php

declare(strict_types=1);

namespace Atk4\Audit\Tests;

use Atk4\Audit\AuditableModelTrait;
use Atk4\Audit\Controller;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class AuditableUser extends Model
{
    use AuditableModelTrait;

    public $table = 'user';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');

        $this->add(new Controller());
    }
}

/**
 * Tests basic create, update and delete operations.
 */
class CRUDTest extends TestCase
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

    /*
    public function testUpdate()
    {
        $q = [
            'user' => [
                ['name' => 'Vinny', 'surname' => 'Shira'],
                ['name' => 'Zoe', 'surname' => 'Shatwell'],
            ],
            'audit_log' => $this->audit_db,
        ];
        $this->setDb($q);

        $m = new AuditableUser($this->db);

        $m->load(1); // load Vinny
        $m['name'] = 'Ken';
        $m->save();

        // more audit record for Vinny
        $l = $m->ref('AuditLog')->loadLast();
        self::assertSame(1, $m->ref('AuditLog')->action('count')->getOne());
        self::assertSame('update Ken: name=Ken', $l['descr']);
        self::assertSame(['name' => ['Vinny', 'Ken']], $l['request_diff']);

        $m->load(2); // Zoe
        $m['name'] = 'Brett';
        $m->save();
        $m['name'] = 'Doug';
        $m->save();

        // two audit records for Zoe
        self::assertSame(2, $m->ref('AuditLog')->action('count')->getOne());

        // three audit records in total (ref when model is not loaded)
        $m->unload();
        self::assertSame(3, $m->ref('AuditLog')->action('count')->getOne());
    }

    public function testUndo()
    {
        $q = [
            'user' => [
                ['name' => 'Jawshua', 'surname' => 'Lo'],
                ['name' => 'Jessica', 'surname' => 'Fish'],
            ],
            'audit_log' => $this->audit_db,
        ];
        $this->setDb($q);
        $zz = $this->getDb('user');

        $m = new AuditableUser($this->db);

        $m->tryLoadAny();
        $m['name'] = 'Donald';
        $m->save();

        $l = $m->ref('AuditLog')->loadLast();
        $l->undo();


        $m->reload();
        self::assertSame('Jawshua', $m['name']);
        self::assertSame(2, $m->ref('AuditLog')->action('count')->getOne());

        $l = $m->ref('AuditLog')->loadLast();

        self::assertSame(1, $l['revert_audit_log_id']);
        self::assertSame(false, $l['is_reverted']);

        // table is back to how it was
        self::assertSame($zz, $this->getDb('user'));
    }

    public function testAddDelete()
    {
        $q = [
            'user' => [
                ['name' => 'Jason', 'surname' => 'Dyck'],
                ['name' => 'James', 'surname' => 'Knight'],
            ],
            'audit_log' => $this->audit_db,
        ];
        $this->setDb($q);
        $zz = $this->getDb('user');

        $m = new AuditableUser($this->db);

        $m->getElement('surname')->default = 'Pen';

        $m->save(['name'=>'Robert']);

        $m->loadBy('name', 'Jason')->delete();

        $log = $m->ref('AuditLog');

        $log->each('undo');

        // table is back to how it was
        self::assertSame($zz, $this->getDb('user'));
    }
    */

    public function testEmptyUpdate()
    {
        $q = [
            'user' => [
                ['name' => 'Vinny', 'surname' => 'Shira'],
                ['name' => 'Zoe', 'surname' => 'Shatwell'],
            ],
            'audit_log' => $this->audit_db,
        ];
        $this->setDb($q);

        $users = new AuditableUser($this->db);

        $user = $users->load(1); // load Vinny
        $user->set('name', 'Vinny'); // false change
        $user->save();

        // should be no audit records for Vinny because there were no actual changes
        // self::assertSame(0, $user->ref('AuditLog')->action('count')->getOne());

        // but in reality because of https://github.com/atk4/audit/issues/17#issuecomment-453544884
        // it's one empty audit record:
        self::assertSame('1', $user->ref('AuditLog')->action('count')->getOne());
    }
}
