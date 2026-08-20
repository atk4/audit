<?php

declare(strict_types=1);

namespace Atk4\Audit\Model;

use Atk4\Audit\AuditableModelTrait;
use Atk4\Audit\Controller;
use Atk4\Core\Exception;
use Atk4\Data\Model;

class AuditLog extends Model
{
    use AuditableModelTrait;

    /** @var string Table name */
    public $table = 'audit_log';

    /** @var ?string Title field */
    public ?string $titleField = 'descr';

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
        if (!$this->isEntity() || !$this->isLoaded()) {
            throw new Exception('Load specific AuditLog entry before executing undo()');
        }

        $this->atomic(function () {
            $modelfqcn = $this->get('model');
            if (!is_string($modelfqcn) || !is_a($modelfqcn, Model::class, true)) {
                throw (new Exception('Invalid model class stored in audit log'))
                    ->addMoreInfo('model', $modelfqcn);
            }

            $m = new $modelfqcn($this->getPersistence());

            $f = 'undo_' . $this->get('action');
            if (!method_exists($this, $f)) {
                throw (new Exception('Unsupported audit action'))
                    ->addMoreInfo('action', $this->get('action'));
            }

            $controller = $m->auditController ?? $m->auditcontroller ?? null;
            if (!$controller instanceof Controller) {
                throw new Exception('Audited model does not have an audit controller');
            }

            $controller->custom_action = 'undo ' . $this->get('action');
            $controller->custom_fields['revert_audit_log_id'] = $this->getId();

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
        $entity = $m->load($this->get('model_id'));
        foreach ($this->get('request_diff') ?? [] as $field => [$old, $new]) {
            if (!$entity->hasField($field)) {
                continue;
            }

            $f = $entity->getField($field);
            $new = $this->decodeAuditValue($f, $new);
            if (!$f->compare($entity->get($field), $new)) {
                throw (new Exception('New value does not match current. Risky to undo'))
                    ->addMoreInfo('new', $new)
                    ->addMoreInfo('current', $entity->get($field));
            }

            $old = $this->decodeAuditValue($f, $old);
            $entity->set($field, $old);
        }

        $entity->save();
    }
    /**
     * No description.
     */
    public function undo_delete(Model $m)
    {
        $entity = $m->createEntity();
        foreach ($this->get('request_diff') ?? [] as $field => [$old, $new]) {
            if (!$entity->hasField($field)) {
                continue;
            }

            $f = $entity->getField($field);
            $old = $this->decodeAuditValue($f, $old);
            $entity->set($field, $old);
        }

        $entity->save();
    }

    /**
     * Deletes model record.
     */
    public function undo_create(Model $m)
    {
        $m->delete($this->get('model_id'));
    }

    protected function decodeAuditValue($field, $value)
    {
        if (!is_string($value)) {
            return $value;
        }

        if (!in_array($field->type, [
            'date',
            'time',
            'datetime',
            'object',
        ], true)) {
            return $value;
        }

        $decoded = @unserialize($value, ['allowed_classes' => true]);

        return $decoded === false && $value !== 'b:0;' ? $value : $decoded;
    }
}
