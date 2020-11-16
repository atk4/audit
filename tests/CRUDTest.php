<?php

declare(strict_types=1);

namespace atk4\audit\tests;

use atk4\audit\Controller;
use atk4\data\Model;
use atk4\schema\PhpunitTestCase;

class AuditableUser extends Model
{
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
class CRUDTest extends PhpunitTestCase
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
        $this->assertSame(1, $m->ref('AuditLog')->action('count')->getOne());
        $this->assertSame('update Ken: name=Ken', $l['descr']);
        $this->assertSame(['name' => ['Vinny', 'Ken']], $l['request_diff']);

        $m->load(2); // Zoe
        $m['name'] = 'Brett';
        $m->save();
        $m['name'] = 'Doug';
        $m->save();

        // two audit records for Zoe
        $this->assertSame(2, $m->ref('AuditLog')->action('count')->getOne());

        // three audit records in total (ref when model is not loaded)
        $m->unload();
        $this->assertSame(3, $m->ref('AuditLog')->action('count')->getOne());
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
        $this->assertSame('Jawshua', $m['name']);
        $this->assertSame(2, $m->ref('AuditLog')->action('count')->getOne());

        $l = $m->ref('AuditLog')->loadLast();

        $this->assertSame(1, $l['revert_audit_log_id']);
        $this->assertSame(false, $l['is_reverted']);

        // table is back to how it was
        $this->assertSame($zz, $this->getDb('user'));
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
        $this->assertSame($zz, $this->getDb('user'));
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

        $m = new AuditableUser($this->db);

        $m->load(1); // load Vinny
        $m->set('name', 'Vinny'); // false change
        $m->save();

        // should be no audit records for Vinny because there were no actual changes
        //$this->assertSame(0, $m->ref('AuditLog')->action('count')->getOne());

        // but in reality because of https://github.com/atk4/audit/issues/17#issuecomment-453544884
        // it's one empty audit record:
        $this->assertSame('1', $m->ref('AuditLog')->action('count')->getOne());
    }
}
