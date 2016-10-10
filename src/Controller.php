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

    function __construct($a = null, $options = [])
    {
        $this->audit_model = $a ?: $a = new model\AuditLog();

        foreach ($options as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Will set up specified model to be logged
     */
    function setUp(\atk4\data\Model $m)
    {
        $m->addHook('beforeSave,beforeDelete', $this, null, -100);
        $m->addHook('afterSave,afterDelete', $this, null, 100);
        $m->addRef('AuditLog', function($m) {
            $a = isset($m->audit_model) ? clone $m->audit_model : clone $this->audit_model;
            if (!$a->persistence) {
                $m->persistence->add($a);
            }

            $a->addCondition('model', get_class($m));
            if ($m->loaded()) {
                $a->addCondition('model_id', $m->id);
            }

            return $a;
        });

        if (!$m->hasMethod('log')) {
            $m->addMethod('log', [$this, 'customLog']);
        }

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

        $a['ts'] = new \DateTime();
        $a['model'] = get_class($m);
        $a['model_id'] = $m->id;
        $a['action'] = $action;

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
        $m->audit_log = $a;

        array_unshift($this->audit_log_stack, $a);
        return $a;
    }

    function pull(\atk4\data\Model $m)
    {
        $a = array_shift($this->audit_log_stack);

        unset($m->audit_log);

        if ($this->record_time_taken) {
            $a['time_taken'] = microtime() - $a->start_mt;
        }
        return $a;
    }

    function getDiffs(\atk4\data\Model $m)
    {
        $diff = [];
        foreach ($m->dirty as $key => $original) {

            $f = $m->hasElement($key);
            if($f && isset($f->no_audit) && $f->no_audit) {
                continue;
            }

            $diff[$key] = [$original, $m[$key]];
        }
        return $diff;
    }

    function customLog(\atk4\data\Model $m, $action, $descr = null, $fields = [])
    {
        $a = $this->push($m, $action);
        $a['descr'] = $descr ?: $action;

        if ($fields) {
            $a->set($fields);
        }

        $this->pull($m)->save();
    }

    function beforeSave(\atk4\data\Model $m)
    {
        if(!$m->loaded()) {
            $a = $this->push($m, $action = 'create');
        } else {
            $a = $this->push($m, $action = 'update');
        }
        $a['request_diff'] = $this->getDiffs($m);

        if(!$a['descr']) {
            $a['descr'] = $a->hasMethod('getDescr') ?
                $a->getDescr() : $action.' '.$this->getDescr($a['request_diff'], $m);
        }
    }

    function afterSave(\atk4\data\Model $m)
    {
        $a = $this->pull($m);

        if ($a['model_id'] === null) {
            // new record
            $a['reactive_diff'] = $m->get();
            $a['model_id'] = $m->id;
        } else {
            $a['reactive_diff'] = $this->getDiffs($m);
            if ($a['reactive_diff'] === $a['request_diff']) {
                // Don't store reactive diff if it's identical to requested diff
                unset($a['reactive_diff']);
            } else {
                $x = $a['reactive_diff'];

                $a['descr'].= ' (resulted in '.$this->getDescr($a['reactive_diff'], $m).')';
            }
        }
        
        $a->save();
    }

    function beforeDelete(\atk4\data\Model $m)
    {
        $a = $this->push($m, 'delete');
        if ($m->only_fields) {
            $id = $m->id;
            $m = $m->newInstance()->load($id); // we need all fields
        }
        $a['request_diff'] = $m->get();
        $a['descr'] = 'delete id='.$m->id;
        if ($m->title_field && $m->hasElement($m->title_field)) {
            $a['descr'] .= ' ('.$m[$m->title_field].')';
        }
    }

    function afterDelete(\atk4\data\Model $m)
    {
        $this->pull($m)->save();
    }

    function getDescr($diff, \atk4\data\Model $m)
    {
        if (!$diff) return 'no changes';
        $t = [];
        foreach ($diff as $key=>list($from, $to)) {
            $from = $m->persistence->typecastSaveField($m->getElement($key), $from);
            $to = $m->persistence->typecastSaveField($m->getElement($key), $to);


            $t[] = $key.'='.$to;
        }
        return join(', ', $t);
    }

}
