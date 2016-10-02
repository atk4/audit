<?php

namespace atk4\data\audit\model;

class AuditLog extends \atk4\data\Model {
    public $table = 'audit_log';
    public $title_field = 'descr';

    public $model = null;

    function init()
    {
        parent::init();

        $this->hasOne('initiator_audit_log_id', new AuditLog());

        $this->addField('ts', ['type' => 'datetime']);

        $this->addField('model', ['type' => 'string']);
        $this->addField('model_id');

        $this->addField('action');

        $this->addField('descr');

        $this->addField('user_info'); // JSON containing keys for browser etc
        $this->addField('request_diff'); // requested changes
        $this->addField('reactive_diff'); // reactive diff

        $this->addField('is_reverted', ['type' => 'boolean']);
        $this->hasOne('revert_audit_log_id', new AuditLog());
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
    function getDiffs(\atk4\data\Model $m)
    {
        $diff = [];
        foreach ($m->dirty as $key => $original) {
            $diff[$key] = [$original, $m[$key]];
        }
        return $diff;
    }
}
