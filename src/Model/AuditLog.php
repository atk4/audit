<?php

declare(strict_types=1);

namespace Atk4\Audit\Model;

use Atk4\Audit\Controller;
use Atk4\Core\Exception;
use Atk4\Data\Model;

class AuditLog extends Model
{
    /** @var string Table name */
    public $table = 'audit_log';

    /** @var string Title field */
    public $title_field = 'descr';

    /** @var bool Don't audit audit model itself */
    public $no_audit = true;

    /** @var Controller */
    public $auditController;

    /** @var string Order records by this field by default */
    public $order_field = 'id';

    /**
     * Initialization.
     */
    protected function init(): void
    {
        parent::init();

        $c = static::class;

        $this->hasOne('initiator_audit_log_id', ['model' => [$c]]);

        $this->addField('ts', ['type' => 'datetime']);

        $this->addField('model', ['type' => 'string']); // model class name
        $this->addField('model_id');                    // id of related model record

        $this->addField('action');
        $this->addField('time_taken', ['type' => 'float']);

        $this->addField('descr', [
            'caption' => 'Description',
            'type' => 'text',
        ]);

        $this->addField('user_info', [
            'type' => 'array',
            'serialize' => 'json',
        ]);                                              // JSON containing keys for browser etc
        $this->addField('request_diff', [
            'type' => 'array',
            'serialize' => 'json',
        ]); // requested changes
        $this->addField('reactive_diff', [
            'type' => 'array',
            'serialize' => 'json',
        ]); // reactive diff
        $this->addField('is_reverted', [
            'type' => 'boolean',
            'default' => false,
        ]);
        $this->hasOne('revert_audit_log_id', ['model' => [$c]]);

        $this->setOrder($this->order_field, 'desc');
    }

    /**
     * Loads most recent audit record.
     *
     * @return $this
     */
    public function loadLast()
    {
        return $this->setOrder('id', 'desc')->tryLoadAny();
    }

    /**
     * Returns user remote address.
     */
    public function getUserInfo(): array
    {
        return isset($_SERVER['REMOTE_ADDR']) ? ['ip' => $_SERVER['REMOTE_ADDR']] : [];
    }

    /**
     * For a specified model record differences.
     *
     * @todo Currently there is limitation - you can't undo and undo
     */
    public function undo()
    {
        if (!$this->loaded()) {
            throw new Exception('Load specific AuditLog entry before executing undo()');
        }

        $this->atomic(function () {
            $modelfqcn = $this->get('model');
            $m = new $modelfqcn($this->persistence);

            $f = 'undo_' . $this->get('action');

            $m->auditController->custom_action = 'undo ' . $this->get('action');
            $m->auditController->custom_fields['revert_audit_log_id'] = $this->getId();

            $this->{$f}($m);

            $this->set('is_reverted', true);
            $this->save();
        });
    }

    /**
     * Rollback change in model data.
     */
    public function undo_update(Model $m)
    {
        $m->load($this->get('model_id'));

        foreach ($this->get('request_diff') as $field => [$old, $new]) {
            if (!$m->hasField($field)) {
                continue;
            }

            $f = $m->getField($field);

            if (is_string($new) && in_array($f->type, [
                'date',
                'time',
                'datetime',
                'object',
            ], true)) {
                $new = unserialize($new);
            }

            if (json_encode([$m->get($field)]) !== json_encode([$new])) {
                throw (new Exception('New value does not match current. Risky to undo'))
                    ->addMoreInfo('new', $new)
                    ->addMoreInfo('current', $m->get($field));
            }

            if (is_string($old) && in_array($f->type, [
                'date',
                'time',
                'datetime',
                'object',
            ], true)) {
                $old = unserialize($old);
            }

            $m->set($field, $old);
        }

        $m->save();
    }

    /**
     * No description.
     */
    public function undo_delete(Model $m)
    {
        foreach ($this->get('request_diff') as $field => [$old, $new]) {
            if (!$m->hasField($field)) {
                continue;
            }

            $f = $m->getField($field);

            if (is_string($old) && in_array($f->type, [
                'date',
                'time',
                'datetime',
                'object',
            ], true)) {
                $old = unserialize($old);
            }

            $m->set($field, $old);
        }

        $m->save();
    }

    /**
     * Deletes model record.
     */
    public function undo_create(Model $m)
    {
        $m->delete($this->get('model_id'));
    }
}
