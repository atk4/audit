<?php

namespace atk4\audit\tests;

use atk4\data\Model;

class Line extends \atk4\data\Model
{
    public $table = 'line';

    public $no_adjust = false;

    public function init():void
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

        $this->addHook('beforeSave', function ($m) {
            $m['total'] = $m['price'] * $m['qty'];
        });

        $this->addHook('afterSave', function ($m) {
            if ($m->isDirty('total')) {
                $change = $m['total'] - $m->dirty['total'];

                $this->ref('invoice_id')->adjustTotal($change);
            }
        });

        $this->addHook('afterDelete', function ($m) {
            $this->ref('invoice_id')->adjustTotal(-$m['total']);
        });
    }
}

class Invoice extends \atk4\data\Model
{
    public $table = 'invoice';

    public function init():void
    {
        parent::init();

        $this->hasMany('Lines', new Line());
        $this->addField('ref', ['type' => 'string']);
        $this->addField('total', ['type' => 'money', 'default' => 0.00]);

        $this->addHook('beforeDelete', function ($m) {
            $m->ref('Lines', ['no_adjust'=>true])->each('delete');
        });
    }

    public function adjustTotal($change)
    {
        if ($this->auditController) {
            $this->auditController->custom_fields = [
                'action'=>'total_adjusted',
                'descr'=>'Changing total by ' . $change
            ];
        }
        $this['total'] += $change;
        $this->save();
    }
}


/**
 * Tests basic create, update and delete operatiotns
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
                    'revert_audit_log_id' => 1
                ]];


    public function testTotals()
    {
        $q = [
            'invoice' => ['_'=>['ref'=>'', 'total'=>0.1]],
            'line' => ['_'=>['invoice_id'=>0, 'item'=>'', 'price'=>0.01, 'qty'=>0, 'total'=>0.1]],
            'audit_log' => $this->audit_db,
        ];
        $this->setDB($q);

        $audit = new \atk4\audit\Controller();
        $audit->audit_model->addMethod('undo_total_adjusted', function () {});

        $this->db->addHook('afterAdd', function ($owner, $element) use ($audit) {
            if ($element instanceof \atk4\data\Model) {
                if (isset($element->no_audit) && $element->no_audit) {
                    // Whitelisting this model, won't audit
                    return;
                }

                $audit->setUp($element);
            }
        });

        $m = new Invoice($this->db);
        $m->save(['ref'=>'inv1']);
        $this->assertEquals(0, $m['total']);

        $m->ref('Lines')->insert(['item'=>'Chair', 'price'=>2.50, 'qty'=>3]);
        $m->ref('Lines')->insert(['item'=>'Desk', 'price'=>10.20, 'qty'=>1]);

        $this->assertEquals(5, count($this->getDB()['audit_log'])); // invoice + line + adjust + line + adjust
        $this->assertEquals(2, count($this->getDB()['line']));
        $this->assertEquals(1, count($this->getDB()['invoice']));

        //$m->ref('Lines')->ref('AuditLog')->loadLast()->undo();

        $a = $this->db->add(clone $audit->audit_model);
        $a->load(1);
        $a->undo(); // undo invoice creation - should undo all other nested changes too

        $this->assertEquals(8, count($this->getDB()['audit_log']));
        $this->assertEquals(0, count($this->getDB()['line']));
        $this->assertEquals(0, count($this->getDB()['invoice']));

        // test audit log relations
        $this->assertEquals(null, $a->load(1)['initiator_audit_log_id']); // create invoice
        $this->assertEquals(null, $a->load(2)['initiator_audit_log_id']); // create line
        $this->assertEquals(2, $a->load(3)['initiator_audit_log_id']); // adjust invoice
        $this->assertEquals(null, $a->load(4)['initiator_audit_log_id']); // create line
        $this->assertEquals(4, $a->load(5)['initiator_audit_log_id']); // adjust invoice
        $this->assertEquals(null, $a->load(6)['initiator_audit_log_id']); // delete invoice
        $this->assertEquals(6, $a->load(7)['initiator_audit_log_id']); // delete line
        $this->assertEquals(6, $a->load(8)['initiator_audit_log_id']); // delete line

        // test revert audit log id
        $this->assertEquals(1, $a->load(6)['revert_audit_log_id']); // undo invoice creation
    }
}
