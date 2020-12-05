<?php

declare(strict_types=1);

namespace Atk4\Audit\Demo;

use Atk4\Audit\Model\AuditLog;
use Atk4\Data\Model;

class Country extends Model
{
    public $table = 'country';

    protected function init(): void
    {
        parent::init();

        $this->addField('name', ['actual' => 'nicename', 'required' => true, 'type' => 'string']);
        $this->addField('sys_name', ['actual' => 'name', 'system' => true]);

        $this->addField('iso', ['caption' => 'ISO', 'required' => true, 'type' => 'string']);
        $this->addField('iso3', ['caption' => 'ISO3', 'required' => true, 'type' => 'string']);
        $this->addField('numcode', ['caption' => 'ISO Numeric Code', 'type' => 'integer', 'required' => true]);
        $this->addField('phonecode', ['caption' => 'Phone Prefix', 'type' => 'integer', 'required' => true]);

        $this->onHook(Model::HOOK_BEFORE_SAVE, function ($m) {
            if (!$m->get('sys_name')) {
                $m->set('sys_name', strtoupper($m->get('name')));
            }
        });

        $this->addUserAction('undo', [
            'fields' => false,
            'appliesTo' => Model\UserAction::APPLIES_TO_SINGLE_RECORD,
            'callback' => 'undo',
            'ui' => [
                'icon' => 'undo',
                //???'button' => [null, 'icon' => 'undo'],
                'execButton' => [Button::class, 'undo', 'blue'],
            ],
        ]);
    }

    public function undo()
    {
        /** @var AuditLog $audit */
        $audit = $this->ref('AuditLog');
        $audit->loadLast();
        $audit->undo();
    }
}
