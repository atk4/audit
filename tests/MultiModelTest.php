<?php

declare(strict_types=1);

namespace atk4\audit\tests;

use atk4\audit\Controller;
use atk4\data\Model;
use atk4\data\Persistence;
use atk4\schema\PhpunitTestCase;

class Line extends Model
{
    public $table = 'line';

    public $no_adjust = false;
    protected $old_total;

    protected function init(): void
    {
        parent::init();

        $this->hasOne('invoice_id', Invoice::class);

        $this->addField('item', ['type' => 'string']);
        $this->addField('price', ['type' => 'money', 'default' => 0.00]);
        $this->addField('qty', ['type' => 'integer', 'default' => 0]);
        $this->addField('total', ['type' => 'money', 'default' => 0.00]);

        if ($this->no_adjust) {
            return;
        }

        $this->onHook(Model::HOOK_BEFORE_SAVE, function ($m) {
            $m->set('total', $m->get('price') * $m->get('qty'));
            $m->old_total = $m->isDirty('total') ? $m->dirty['total'] : null;
        });

        $this->onHook(Model::HOOK_AFTER_SAVE, function ($m) {
            if ($m->old_total !== null) {
                $change = $m->get('total') - $m->old_total;
                $this->ref('invoice_id')->adjustTotal($change);
                $m->old_total = null;
            }
        });

        $this->onHook(Model::HOOK_AFTER_DELETE, function ($m) {
            $this->ref('invoice_id')->adjustTotal(-$m->get('total'));
        });
    }
}

class Invoice extends Model
{
    public $table = 'invoice';

    protected function init(): void
    {
        parent::init();

        $this->hasMany('Lines', Line::class);
        $this->addField('ref', ['type' => 'string']);
        $this->addField('total', ['type' => 'money', 'default' => 0.00]);

        $this->onHook(Model::HOOK_BEFORE_DELETE, function ($m) {
            $lines = $m->ref('Lines', ['no_adjust' => true]);
            $lines->each(function ($m) {
                $m->delete();
            });
        });
    }

    public function adjustTotal($change)
    {
        $this->ref('AuditLog')->custom_fields = [
            'action' => 'total_adjusted',
            'descr' => 'Changing total by ' . $change,
        ];

        $this->set('total', $this->get('total') + $change);
        $this->save();
    }
}

/**
 * Tests basic create, update and delete operatiotns.
 */
class MultiModelTest extends PhpunitTestCase
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

    public function testTotals()
    {
        $q = [
            'invoice' => ['_' => ['ref' => '', 'total' => 0.1]],
            'line' => ['_' => ['invoice_id' => 0, 'item' => '', 'price' => 0.01, 'qty' => 0, 'total' => 0.1]],
            'audit_log' => $this->audit_db,
        ];
        $this->setDb($q);

        $audit = new Controller();
        $audit->audit_model->addMethod('undo_total_adjusted', function () {});

        $this->db->onHook(Persistence::HOOK_AFTER_ADD, function ($owner, $model) use ($audit) {
            if ($model instanceof Model) {
                if (isset($model->no_audit) && $model->no_audit) {
                    // Whitelisting this model, won't audit
                    return;
                }

                $audit->setUp($model);
            }
        });

        $m = new Invoice($this->db);
        $m->save(['ref' => 'inv1']);
        $this->assertSame(0.0, $m->get('total'));

        $m->ref('Lines')->insert(['item' => 'Chair', 'price' => 2.50, 'qty' => 3]);
        $m->ref('Lines')->insert(['item' => 'Desk', 'price' => 10.20, 'qty' => 1]);

        $this->assertSame(5, count($this->getDb()['audit_log'])); // invoice + line + adjust + line + adjust
        $this->assertSame(2, count($this->getDb()['line']));
        $this->assertSame(1, count($this->getDb()['invoice']));

        //$m->ref('Lines')->ref('AuditLog')->loadLast()->undo();

        $m = new Invoice($this->db);
        $a = $m->ref('AuditLog')->newInstance();
        $a->load(1);
        $a->undo(); // undo invoice creation - should undo all other nested changes too

/*
        $this->assertSame(8, count($this->getDb()['audit_log']));
        $this->assertSame(0, count($this->getDb()['line']));
        $this->assertSame(0, count($this->getDb()['invoice']));

        // test audit log relations
        $this->assertNull($a->load(1)->get('initiator_audit_log_id')); // create invoice
        $this->assertNull($a->load(2)->get('initiator_audit_log_id')); // create line
        $this->assertSame('2', $a->load(3)->get('initiator_audit_log_id')); // adjust invoice
        $this->assertNull($a->load(4)->get('initiator_audit_log_id')); // create line
        $this->assertSame('4', $a->load(5)->get('initiator_audit_log_id')); // adjust invoice
        $this->assertNull($a->load(6)->get('initiator_audit_log_id')); // delete invoice
        $this->assertSame('6', $a->load(7)->get('initiator_audit_log_id')); // delete line
        $this->assertSame('6', $a->load(8)->get('initiator_audit_log_id')); // delete line

        // test revert audit log id
        $this->assertSame('1', $a->load(6)->get('revert_audit_log_id')); // undo invoice creation
*/
    }
}
