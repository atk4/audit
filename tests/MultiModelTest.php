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

    /** @var float */
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
                $m->ref('invoice_id')->adjustTotal($change);
                $m->old_total = null;
            }
        });

        $this->onHook(Model::HOOK_AFTER_DELETE, static function (Line $m) {
            if (!$m->no_adjust) {
                $m->ref('invoice_id')->adjustTotal(-$m->get('total'));
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

    public function testTotals()
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
        $data = $this->audit->auditModel->export(['id', 'model', 'model_id', 'action', 'request_diff', 'reactive_diff', 'initiator_audit_log_id']);
        // print_r($data);

        self::assertSame([
            // create invoice
            [
                'id' => 1,
                'model' => 'Atk4\Audit\Tests\Invoice',
                'model_id' => 1,
                'action' => AuditController::ACTION_CREATE,
                'request_diff' => [
                    'ref' => [null, '#123'],
                    'doc_date' => [null, serialize(new \DateTime('2026-09-01'))],
                ],
                'reactive_diff' => [
                    'id' => 1,
                    'total' => 0,
                ],
                'initiator_audit_log_id' => null,
            ],
            // create line #1
            [
                'id' => 2,
                'model' => 'Atk4\Audit\Tests\Line',
                'model_id' => 1,
                'action' => AuditController::ACTION_CREATE,
                'request_diff' => [
                    'item' => [null, 'Laptop'],
                    'price' => [null, 100],
                    'qty' => [null, 2],
                ],
                'reactive_diff' => [
                    'id' => 1,
                    'invoice_id' => 1,
                    'total' => 200,
                ],
                'initiator_audit_log_id' => null,
            ],
            // automatically updates invoice, linked to previous audit record
            [
                'id' => 3,
                'model' => 'Atk4\Audit\Tests\Invoice',
                'model_id' => 1,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [
                    'total' => [0, 200],
                ],
                'reactive_diff' => [
                ],
                'initiator_audit_log_id' => 2,
            ],
            // create line #2
            [
                'id' => 4,
                'model' => 'Atk4\Audit\Tests\Line',
                'model_id' => 2,
                'action' => AuditController::ACTION_CREATE,
                'request_diff' => [
                    'item' => [null, 'Monitor'],
                    'price' => [null, 120],
                    'qty' => [null, 3],
                ],
                'reactive_diff' => [
                    'id' => 2,
                    'invoice_id' => 1,
                    'total' => 360,
                ],
                'initiator_audit_log_id' => null,
            ],
            // automatically updates invoice, linked to previous audit record
            [
                'id' => 5,
                'model' => 'Atk4\Audit\Tests\Invoice',
                'model_id' => 1,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [
                    'total' => [200, 560],
                ],
                'reactive_diff' => [
                ],
                'initiator_audit_log_id' => 4,
            ],
            // update line #2 quantity
            [
                'id' => 6,
                'model' => 'Atk4\Audit\Tests\Line',
                'model_id' => 2,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [
                    'qty' => [3, 2],
                ],
                'reactive_diff' => [
                    'total' => 240,
                ],
                'initiator_audit_log_id' => null,
            ],
            // automatically updates invoice, linked to previous audit record
            [
                'id' => 7,
                'model' => 'Atk4\Audit\Tests\Invoice',
                'model_id' => 1,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [
                    'total' => [560, 440],
                ],
                'reactive_diff' => [
                ],
                'initiator_audit_log_id' => 6,
            ],
            // delete line #2
            [
                'id' => 8,
                'model' => 'Atk4\Audit\Tests\Line',
                'model_id' => 2,
                'action' => AuditController::ACTION_DELETE,
                'request_diff' => [
                    'id' => [2, null],
                    'invoice_id' => [1, null],
                    'item' => ['Monitor', null],
                    'price' => [120, null],
                    'qty' => [2, null],
                    'total' => [240, null],
                ],
                'reactive_diff' => [
                ],
                'initiator_audit_log_id' => null,
            ],
            // automatically updates invoice, linked to previous audit record
            [
                'id' => 9,
                'model' => 'Atk4\Audit\Tests\Invoice',
                'model_id' => 1,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [
                    'total' => [440, 200],
                ],
                'reactive_diff' => [
                ],
                'initiator_audit_log_id' => 8,
            ],
        ], $data);

    }
}
