<?php

declare(strict_types=1);

namespace Atk4\Audit\Model;

use Atk4\Audit\View\Table\Column;
use Atk4\Data\Model;

class AuditLog extends Model
{
    /** @var Model|string|false Table name */
    public $table = 'audit_log';

    /** @var ?string Title field */
    public ?string $titleField = 'descr';

    /** @var string Order records by this field by default */
    public $orderField = 'id';

    /**
     * Initialization.
     */
    protected function init(): void
    {
        parent::init();

        $this->addField('model', ['required' => true, 'type' => 'string']); // model class name
        $this->addField('model_id', ['type' => 'bigint']); // id of related model record

        $this->addField('start_time_ms', [
            'required' => true,
            'type' => 'bigint',
            'ui' => ['visible' => false, 'editable' => false],
        ]);
        $this->addField('start_time', [
            'required' => true,
            'type' => 'datetime',
        ]);

        $this->addField('duration', [
            'type' => 'float',
        ]);

        $this->addField('action', [
            'required' => true,
            'ui' => ['table' => [Column\Action::class]],
        ]);
        $this->addField('request_diff', ['type' => 'json']); // requested changes
        $this->addField('reactive_diff', ['type' => 'json']); // reactive changes

        // JSON containing keys for browser etc
        $this->addField('user_info', ['type' => 'json']);

        // link to audit log entry which generated this event (parent event)
        $this->hasOne('initiator_audit_log_id', [
            'model' => [static::class],
            'caption' => 'Caused By',
        ]);

        $this->addField('descr', [
            'caption' => 'Description',
            'type' => 'text',
        ]);

        $this->setOrder($this->orderField, 'desc');

        $this->onHook(Model::HOOK_BEFORE_SAVE, static function (Model $m, bool $is_update) {
            if ($m->isDirty('start_time_ms')) {
                $m->set('start_time', \DateTime::createFromFormat(
                    'U.v',
                    sprintf('%d.%03d', intdiv($m->get('start_time_ms'), 1000), $m->get('start_time_ms') % 1000)
                ));
            }
        });
    }

    /**
     * Loads most recent audit record.
     *
     * @return static|null
     */
    public function loadLast()
    {
        return $this->setOrder($this->idField, 'desc')->tryLoadAny();
    }
}
