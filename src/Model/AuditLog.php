<?php

declare(strict_types=1);

namespace Atk4\Audit\Model;

use Atk4\Audit\AuditController;
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

        // link to audit log entry which generated this event (parent event)
        $this->hasOne('initiator_audit_log_id', [
            'model' => [static::class],
            'caption' => 'Caused By',
        ]);

        // request action
        $this->addField('action', [
            'required' => true,
            'enum' => [
                AuditController::ACTION_CREATE,
                AuditController::ACTION_UPDATE,
                AuditController::ACTION_DELETE,
                AuditController::ACTION_LOG,
            ],
            'ui' => ['table' => [Column\Action::class]],
        ]);

        // model class and ID of model record
        $this->addField('model', [
            'required' => true,
            //'ui' => ['table' => [\Atk4\Ui\Table\Column\Labels::class]],
        ]);
        $this->addField('model_id', ['type' => 'bigint']);

        // request time and duration
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

        $this->addField('request_diff', ['type' => 'json']); // requested changes
        $this->addField('reactive_diff', ['type' => 'json']); // reactive changes

        // optional user_id
        $this->addField('user_id', ['type' => 'integer']);

        // additional session info, for example, browser config, ip address etc.
        $this->addField('session_info', ['type' => 'json']);

        // generated description
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
