<?php

namespace atk4\audit;

class Controller {

    use \atk4\core\InitializerTrait {
        init as _init;
    }
    use \atk4\core\TrackableTrait;

    public $first_audit_log;
    public $audit_log_stack = [];

    public $record_time_taken = true;

    public $audit_model;

    public $custom_action = null;
    public $custom_fields = [];

    function __construct($m = null, $options = [])
    {
        $this->audit_model = $m ?: $m = new model\AuditLog();

        foreach($options as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Will set up specified model to be logged
     */
    function setUp(\atk4\data\Model $m)
    {
        $m->addHook('beforeUpdate,afterUpdate', $this);
        $m->addRef('AuditLog', function($m) {
            $a = clone $this->audit_model;
            $m->persistence->add($a);

            $a->addCondition('model', get_class($m));
            if ($m->loaded()) {
                $a->addCondition('model_id', $m->id);
            }

            return $a;
        });

        $m->audit_log_controller = $this;
    }

    function init()
    {
        $this->_init();

        $this->setUp($this->owner);
    }

    function push(\atk4\data\Model $m, $action)
    {
        if (isset($m->audit_model)) {
            $a = clone $m->audit_model;
        } else {
            $a = clone $this->audit_model;
        }
        $m->persistence->add($a);

        if ($this->custom_action) {
            $action = $this->custom_action;
            $this->custom_action = null;
        }

        $a['request_diff'] = $this->getDiffs($m);
        $a['ts'] = new \DateTime();
        $a['model'] = get_class($m);
        $a['model_id'] = $m->id;
        $a['action'] = $action;
        $a['descr'] = $action.' '.$this->getDescr($a['request_diff']);

        if ($this->custom_fields) {
            $a->set($this->custom_fields);
            $this->custom_fields = [];
        }

        if (!$this->first_audit_log) {
            $this->first_audit_log = $a;
        }

        if ($this->audit_log_stack) {
            $a['initiator_audit_log_id'] = $this->audit_log_stack[0]->id;
        }

        // save the initial action
        $a->save();

        $a->start_mt = microtime();

        array_unshift($this->audit_log_stack, $a);
    }

    function pull(\atk4\data\Model $m)
    {
        $a = array_shift($this->audit_log_stack);
        $a['reactive_diff'] = $this->getDiffs($m);
        if($a['reactive_diff'] === $a['request_diff']) {
            // Don't store reactive diff if it's identical to requested diff
            unset($a['reactive_diff']);
        } else {
            $x = $a['reactive_diff'];

            $a['descr'].= ' (resulted in '.$this->getDescr($a['reactive_diff']).')';
        }

        if ($this->record_time_taken) {
            $a['time_taken'] = microtime() - $a->start_mt;
        }

        $a->save();
    }

    function getDiffs(\atk4\data\Model $m)
    {
        $diff = [];
        foreach ($m->dirty as $key => $original) {
            $diff[$key] = [$original, $m[$key]];
        }
        return $diff;
    }

    function beforeUpdate(\atk4\data\Model $m)
    {
        $this->push($m, 'update');
    }


    function afterUpdate(\atk4\data\Model $m)
    {
        $this->pull($m);
    }

    function getDescr($diff)
    {
        $t = [];
        foreach ($diff as $key=>list($from, $to)) {
            $t[] = $key.'='.$to;
        }
        return join(', ', $t);
    }
}
