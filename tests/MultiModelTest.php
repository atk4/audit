<?php

declare(strict_types=1);

namespace atk4\audit\tests;

use atk4\data\Model;
use atk4\data\Persistence;

class Line extends Model
{
    public $table = 'line';

    public $no_adjust = false;

    public function init(): void
    {
        parent::init();

        $this->hasOne('invoice_id', new Invoice());

        $this->addField('item', ['type' => 'string']);
        $this->addField('price', ['type' => 'money', 'default' => 0.00]);
        $this->addField('qty', ['type' => 'integer', 'default' => 0]);
        $this->addField('total', ['type' => 'money', 'default' => 0.00]);

        if ($this->no_adjust) {
            return;
        }

        $this->onHook(Model::HOOK_BEFORE_SAVE, function ($m) {
            $m->set('total', $m->get('price') * $m->get('qty'));
        });

        $this->onHook(Model::HOOK_AFTER_SAVE, function ($m) {
            if ($m->isDirty('total')) {
                $change = $m->get('total') - $m->dirty['total'];

                $this->ref('invoice_id')->adjustTotal($change);
            }
        });

        $this->onHook(Model::HOOK_AFTER_DELETE, function ($m) {
            $this->ref('invoice_id')->adjustTotal(-$m->get('total'));
        });
    }
}

class Invoice extends \atk4\data\Model
{
    public $table = 'invoice';

    public function init(): void
    {
        parent::init();

        $this->hasMany('Lines', new Line());
        $this->addField('ref', ['type' => 'string']);
        $this->addField('total', ['type' => 'money', 'default' => 0.00]);

        $this->onHook(Model::HOOK_BEFORE_DELETE, function ($m) {
            $m->ref('Lines', ['no_adjust' => true])->each('delete');
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
class MultiModelTest extends \atk4\schema\PhpunitTestCase
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
        $this->setDB($q);

        $audit = new \atk4\audit\Controller();
        $audit->audit_model->addMethod('undo_total_adjusted', function () {});

        $this->db->onHook(Persistence::HOOK_AFTER_ADD, function ($owner, $element) use ($audit) {
            if ($element instanceof \atk4\data\Model) {
                if (isset($element->no_audit) && $element->no_audit) {
                    // Whitelisting this model, won't audit
                    return;
                }

                $audit->setUp($element);
            }
        });

        $m = new Invoice($this->db);
        $m->save(['ref' => 'inv1']);
        $this->assertSame(0.0, $m->get('total'));

        $m->ref('Lines')->insert(['item' => 'Chair', 'price' => 2.50, 'qty' => 3]);
        $m->ref('Lines')->insert(['item' => 'Desk', 'price' => 10.20, 'qty' => 1]);

        $this->assertSame(5, count($this->getDB()['audit_log'])); // invoice + line + adjust + line + adjust
        $this->assertSame(2, count($this->getDB()['line']));
        $this->assertSame(1, count($this->getDB()['invoice']));

        //$m->ref('Lines')->ref('AuditLog')->loadLast()->undo();

        $m = new Invoice($this->db);
        $a = $m->ref('AuditLog')->newInstance();
        $a->load(1)->undo(); // undo invoice creation - should undo all other nested changes too

        $this->assertSame(8, count($this->getDB()['audit_log']));
        $this->assertSame(0, count($this->getDB()['line']));
        $this->assertSame(0, count($this->getDB()['invoice']));

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
    }
}
