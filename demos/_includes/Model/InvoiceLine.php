<?php

declare(strict_types=1);

namespace Atk4\Audit\Demos\Model;

use Atk4\Data\Model;
use Atk4\Data\Type\Types;

class InvoiceLine extends Model
{
    public $table = 'demo_line';
    public ?string $titleField = 'item';

    /** @var bool */
    protected $no_adjust = false;

    /** @var ?float */
    private $old_total;

    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->hasOne('invoice_id', ['model' => [Invoice::class]]);

        $this->addField('item', ['type' => 'string']);
        $this->addField('price', ['type' => Types::MONEY, 'default' => 0.00]);
        $this->addField('qty', ['type' => 'float', 'default' => 0.00]);
        $this->addField('total', ['type' => Types::MONEY, 'default' => 0.00, 'ui' => ['editable' => false]]);

        $this->onHook(Model::HOOK_BEFORE_SAVE, static function (InvoiceLine $m) {
            $m->set('total', $m->get('price') * $m->get('qty'));
            $m->old_total = $m->isDirty('total') ? $m->getDirtyRef()['total'] : null;
        });

        $this->onHook(Model::HOOK_AFTER_SAVE, static function (InvoiceLine $m) {
            if ($m->old_total !== null) {
                $change = $m->get('total') - $m->old_total;
                /** @var Invoice $invoice */
                $invoice = $m->ref('invoice_id');
                $invoice->adjustTotal($change);
                $m->old_total = null;
            }
        });

        $this->onHook(Model::HOOK_AFTER_DELETE, static function (InvoiceLine $m) {
            if (!$m->no_adjust) {
                /** @var Invoice $invoice */
                $invoice = $m->ref('invoice_id');
                $invoice->adjustTotal(-$m->get('total'));
            }
        });
    }
}
