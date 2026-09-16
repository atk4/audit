<?php

declare(strict_types=1);

namespace Atk4\Audit\Tests;

use Atk4\Audit\AuditController;
use Atk4\Data\Model;
use Atk4\Data\Type\Types;

class Invoice extends Model
{
    public $table = 'invoice';

    protected function init(): void
    {
        parent::init();

        $this->addField('ref', ['type' => 'string']);
        $this->addField('doc_date', ['type' => 'date']);
        $this->addField('total', ['type' => Types::MONEY, 'default' => 0.00]);

        $this->hasMany('Lines', ['model' => [Line::class]]);

        $this->onHook(Model::HOOK_BEFORE_DELETE, static function ($m) {
            $lines = $m->ref('Lines', ['no_adjust' => true]); // need to avoid infinite loops
            foreach ($lines as $line) {
                $line->delete();
            }
        });
    }

    public function adjustTotal(float $change): void
    {
        /*
        $this->ref('AuditLog')->custom_fields = [
            'action' => 'total_adjusted',
            'descr' => 'Changing total by ' . $change,
        ];
        */

        $this->set('total', $this->get('total') + $change);
        $this->save();
    }
}

class Line extends Model
{
    public $table = 'line';

    /** @var bool */
    protected $no_adjust = false;

    /** @var ?float */
    private $old_total;

    protected function init(): void
    {
        parent::init();

        $this->hasOne('invoice_id', ['model' => [Invoice::class]]);

        $this->addField('item', ['type' => 'string']);
        $this->addField('price', ['type' => Types::MONEY, 'default' => 0.00]);
        $this->addField('qty', ['type' => 'float', 'default' => 0.00]);
        $this->addField('total', ['type' => Types::MONEY, 'default' => 0.00]);

        $this->onHook(Model::HOOK_BEFORE_SAVE, static function (Line $m) {
            $m->set('total', $m->get('price') * $m->get('qty'));
            $m->old_total = $m->isDirty('total') ? $m->getDirtyRef()['total'] : null;
        });

        $this->onHook(Model::HOOK_AFTER_SAVE, static function (Line $m) {
            if ($m->old_total !== null) {
                $change = $m->get('total') - $m->old_total;
                /** @var Invoice $invoice */
                $invoice = $m->ref('invoice_id');
                $invoice->adjustTotal($change);
                $m->old_total = null;
            }
        });

        $this->onHook(Model::HOOK_AFTER_DELETE, static function (Line $m) {
            if (!$m->no_adjust) {
                /** @var Invoice $invoice */
                $invoice = $m->ref('invoice_id');
                $invoice->adjustTotal(-$m->get('total'));
            }
        });
    }
}

/**
 * Tests multi-model auditing.
 */
class MultiModelTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // test models
        $this->createMigrator(new Invoice($this->db))->create();
        $this->createMigrator(new Line($this->db))->create();

        // audit persistence as we want to audit all models
        $this->audit->observePersistence($this->db);
    }

    public function testTotals(): void
    {
        // invoice model
        $invoices = new Invoice($this->db);

        // create invoice
        $invoice = $invoices->createEntity();
        $invoice->save([
            'ref' => '#123',
            'doc_date' => new \DateTime('2026-09-01'),
        ]);

        // create invoice line
        $lines = $invoice->ref('Lines');
        $lines->import([
            ['item' => 'Laptop', 'price' => 100, 'qty' => 2],
            ['item' => 'Monitor', 'price' => 120, 'qty' => 3],
        ]);

        // change invoice line
        $lines->loadBy('item', 'Monitor')->set('qty', 2)->save();

        // delete invoice line
        $lines->loadBy('item', 'Monitor')->delete();

        // test audit log
        $data = $this->audit->auditModel->export(['id', 'model', 'model_id', 'action', 'request_diff', 'reactive_diff', 'descr', 'initiator_audit_log_id']);
        // print_r($data);

        self::assertSame([
            // create invoice
            [
                'id' => 1,
                'model' => 'Invoice',
                'model_id' => 1,
                'action' => AuditController::ACTION_CREATE,
                'request_diff' => [
                    'ref' => [null, '#123'],
                    'doc_date' => [null, '2026-09-01'],
                ],
                'reactive_diff' => [
                    'id' => 1,
                    'total' => 0.0,
                ],
                'descr' => 'create #1: ref=#123, doc_date=2026-09-01',
                'initiator_audit_log_id' => null,
            ],
            // create line #1
            [
                'id' => 2,
                'model' => 'Line',
                'model_id' => 1,
                'action' => AuditController::ACTION_CREATE,
                'request_diff' => [
                    'item' => [null, 'Laptop'],
                    'price' => [0.0, 100.0], // 0.0 because it's default value
                    'qty' => [0.0, 2.0], // 0.0 because it's default value
                ],
                'reactive_diff' => [
                    'id' => 1,
                    'invoice_id' => 1,
                    'total' => 200.0,
                ],
                'descr' => 'create #1: item=Laptop, price=100, qty=2',
                'initiator_audit_log_id' => null,
            ],
            // automatically updates invoice, linked to previous audit record
            [
                'id' => 3,
                'model' => 'Invoice',
                'model_id' => 1,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [
                    'total' => [0.0, 200.0],
                ],
                'reactive_diff' => [],
                'descr' => 'update #1: total=200',
                'initiator_audit_log_id' => 2,
            ],
            // create line #2
            [
                'id' => 4,
                'model' => 'Line',
                'model_id' => 2,
                'action' => AuditController::ACTION_CREATE,
                'request_diff' => [
                    'item' => [null, 'Monitor'],
                    'price' => [0.0, 120.0], // 0.0 because it's default value
                    'qty' => [0.0, 3.0], // 0.0 because it's default value
                ],
                'reactive_diff' => [
                    'id' => 2,
                    'invoice_id' => 1,
                    'total' => 360.0,
                ],
                'descr' => 'create #2: item=Monitor, price=120, qty=3',
                'initiator_audit_log_id' => null,
            ],
            // automatically updates invoice, linked to previous audit record
            [
                'id' => 5,
                'model' => 'Invoice',
                'model_id' => 1,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [
                    'total' => [200.0, 560.0],
                ],
                'reactive_diff' => [],
                'descr' => 'update #1: total=560',
                'initiator_audit_log_id' => 4,
            ],
            // update line #2 quantity
            [
                'id' => 6,
                'model' => 'Line',
                'model_id' => 2,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [
                    'qty' => [3.0, 2.0],
                ],
                'reactive_diff' => [
                    'total' => 240.0,
                ],
                'descr' => 'update #2: qty=2',
                'initiator_audit_log_id' => null,
            ],
            // automatically updates invoice, linked to previous audit record
            [
                'id' => 7,
                'model' => 'Invoice',
                'model_id' => 1,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [
                    'total' => [560.0, 440.0],
                ],
                'reactive_diff' => [],
                'descr' => 'update #1: total=440',
                'initiator_audit_log_id' => 6,
            ],
            // delete line #2
            [
                'id' => 8,
                'model' => 'Line',
                'model_id' => 2,
                'action' => AuditController::ACTION_DELETE,
                'request_diff' => [
                    'id' => [2, null],
                    'invoice_id' => [1, null],
                    'item' => ['Monitor', null],
                    'price' => [120.0, null],
                    'qty' => [2.0, null],
                    'total' => [240.0, null],
                ],
                'reactive_diff' => [],
                'descr' => 'delete #2',
                'initiator_audit_log_id' => null,
            ],
            // automatically updates invoice, linked to previous audit record
            [
                'id' => 9,
                'model' => 'Invoice',
                'model_id' => 1,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [
                    'total' => [440.0, 200.0],
                ],
                'reactive_diff' => [],
                'descr' => 'update #1: total=200',
                'initiator_audit_log_id' => 8,
            ],
        ], $data);
    }
}
