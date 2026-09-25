<?php

declare(strict_types=1);

namespace Atk4\Audit\Demos\Model;

use Atk4\Data\Model;
use Atk4\Data\Type\Types;

class Client extends Model
{
    public $table = 'demo_client';
    public $caption = 'Client';

    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->addField('name', ['required' => true]);
        $this->addField('vat_number');
        $this->addField('balance', ['type' => Types::MONEY]);
        $this->addField('active', ['type' => 'boolean', 'default' => true]);

        $this->hasMany('Invoices', ['model' => [Invoice::class], 'theirField' => 'client_id']);

        // custom action
        /*
        $this->addUserAction('test', static function (self $m) {
            return 'Test action run for ' . $m->getTitle() . ' !';
        });
        */
    }
}
