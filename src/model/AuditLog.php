<?php

namespace atk4\audit\model;

class AuditLog extends \atk4\data\Model {
    public $table = 'audit_log';
    public $title_field = 'descr';

    public $model = null;

    public $start_mt;

    public $controller = null;

    function init()
    {
        parent::init();

        $this->hasOne('initiator_audit_log_id', new AuditLog());

        $this->addField('ts', ['type' => 'datetime']);

        $this->addField('model', ['type' => 'string']);
        $this->addField('model_id');

        $this->addField('action');
        $this->addField('time_taken', ['type' => 'float']);

        $this->addField('descr');

        $this->addField('user_info', ['type' => 'struct']); // JSON containing keys for browser etc
        $this->addField('request_diff', ['type' => 'struct']); // requested changes
        $this->addField('reactive_diff', ['type' => 'struct']); // reactive diff

        $this->addField('is_reverted', ['type' => 'boolean']);
        $this->hasOne('revert_audit_log_id', new AuditLog());
    }

    function loadLast()
    {
        return $this->setOrder('id desc')->tryLoadAny();
    }

    function getUserInfo()
    {
        return [
            'ip' => $_SERVER['REMOTE_ADDR']
        ];
    }



    /**
     * For a specified model record diffs
     */

    function undo()
    {
        if (!$this->loaded()) {
            throw new \atk4\core\Exception('Load specific AuditLog entry before executing undo()');
        }

        $this->atomic(function() {
            $m = new $this['model']($this->persistence);
            $m->load($this['model_id']);

            foreach ($this['request_diff'] as $field => list($old, $new)) {
                if ($m[$field] !== $new) {
                    throw new \atk4\core\Exception([
                        'New value does not match current. Risky to undo',
                        'new' => $new, 'current' => $m[$field]
                    ]);
                }

                $m[$field] = $old;
            }

            $m->audit_log_controller->custom_action = 'undo '.$this['action'];
            $m->audit_log_controller->custom_fields['revert_audit_log_id'] = $this->id;

            $m->save();

            $this['is_reverted'] = true;
            $this->save();
        });
    }
}
