<?php

declare(strict_types=1);

namespace Atk4\Audit\Model;

use Atk4\Audit\AuditableModelTrait;
//use Atk4\Data\Exception;
use Atk4\Data\Model;
//use Atk4\Data\Type\Types;

class AuditLog extends Model
{
    use AuditableModelTrait;

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

        // don't audit audit model itself
        $this->noAudit = true;

        $this->addField('model', ['required' => true, 'type' => 'string']); // model class name
        $this->addField('model_id', ['type' => 'integer']); // id of related model record

        $this->addField('start_time_ms', ['required' => true, 'type' => 'float']);
        $this->addField('duration_ms', ['type' => 'float']);

        $this->addField('action', ['required' => true]);
        $this->addField('request_diff', ['type' => 'json']); // requested changes
        $this->addField('reactive_diff', ['type' => 'json']); // reactive changes

        // JSON containing keys for browser etc
        $this->addField('user_info', ['type' => 'json']);

        $this->hasOne('initiator_audit_log_id', ['model' => [static::class]]);

        $this->addField('descr', [
            'caption' => 'Description',
            'type' => 'text',
        ]);

        $this->setOrder($this->orderField, 'desc');
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
