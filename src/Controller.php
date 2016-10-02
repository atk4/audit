<?php

namespace atk4\data\audit;

class Controller {
    public $first_audit_log;
    public $audit_log_stack = [];

    public $audit_model;

    function __construct($m = null, $options = [])
    {
        $this->audit_model = $m ?: $m = new audit\model\AuditLog();

        foreach($options as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Link 
     */
    function hookUp(\atk4\data\Model $m)
    {
        // 
    }

    function push(\atk4\data\Model $m)
    {
        if (isset($m->audit_model)) {
            $m = clone $m->audit_model;
        } else {
            $m = clone $this->audit_model;
        }

        if (!$this->first_audit_log) {
            $this->first_audit_log = clone $model;
        }
    }

    function beforeInsert(\atk4\data\Model $m)
    {
    }


    function afterInsert(\atk4\data\Model $m)
    {
    }
}
