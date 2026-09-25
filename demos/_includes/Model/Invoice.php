<?php

declare(strict_types=1);

namespace Atk4\Audit\Demos\Model;

use Atk4\Data\Model;
use Atk4\Data\Reference\HasOneSql;
use Atk4\Data\Type\Types;

class Invoice extends Model
{
    public $table = 'demo_invoice';
    public ?string $titleField = 'ref';

    #[\Override]
    protected function init(): void
    {
        parent::init();

        /** @var HasOneSql */
        $r = $this->hasOne('client_id', ['model' => [Client::class]]);
        $r->addTitle();

        $this->addField('ref', ['type' => 'string']);
        $this->addField('doc_date', ['type' => 'date']);
        $this->addField('total', ['type' => Types::MONEY, 'default' => 0.00, 'ui' => ['editable' => false]]);

        $this->hasMany('Lines', ['model' => [InvoiceLine::class], 'theirField' => 'invoice_id']);

        $this->onHook(Model::HOOK_BEFORE_DELETE, static function ($m) {
            $lines = $m->ref('Lines', ['no_adjust' => true]); // need to avoid infinite loops
            foreach ($lines as $line) {
                $line->delete();
            }
        });
    }

    public function adjustTotal(float $change): void
    {
        $this->set('total', $this->get('total') + $change);
        $this->save();
    }
}
