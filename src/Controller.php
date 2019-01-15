<?php

namespace atk4\audit;

class Controller
{
    use \atk4\core\InitializerTrait {
        init as _init;
    }
    use \atk4\core\TrackableTrait;
    use \atk4\core\DIContainerTrait;
    use \atk4\core\FactoryTrait;

    /**
     * Audit data model.
     * Pass this property in constructor seed to change it.
     *
     * @var string|model\AuditLog
     */
    public $audit_model = model\AuditLog::class;

    /** @var array audit log stack - most recent record is first */
    public $audit_log_stack = [];

    /** @var bool should we record time taken to make this change? */
    public $record_time_taken = true;

    /** @var float start time of audit log */
    public $start_mt;

    /** @var string name of custom action, for example "comment" */
    public $custom_action = null;

    /** @var array custom fields to add */
    public $custom_fields = [];

    /**
     * Constructor - set up properties and audit model.
     *
     * @param array|string|\atk4\data\Model $defaults Seed options or just audit model
     */
    public function __construct($defaults = [])
    {
        if (is_array($defaults)) {
            $this->setDefaults($defaults);
        } else {
            $this->audit_model = $defaults;
        }

        // create audit model object if it's not already there
        $this->audit_model = $this->factory($this->audit_model);
    }

    /**
     * Initialize - set up all necessary hooks etc.
     */
    public function init()
    {
        $this->_init();

        if (isset($this->owner) && $this->owner instanceof \atk4\data\Model) {
            $this->setUp($this->owner);
        }
    }

    /**
     * Will set up specified model to be logged.
     *
     * @param \atk4\data\Model $m
     */
    public function setUp(\atk4\data\Model $m)
    {
        // adds hooks
        $m->addHook('beforeSave,beforeDelete', $this, null, -100); // called as soon as possible
        $m->addHook('afterSave,afterDelete', $this, null, 100);    // called as late as possible

        // adds hasMany reference to audit records
        $m->addRef('AuditLog', function($m) {
            // get audit model
            $a = isset($m->audit_model) ? clone $m->audit_model : clone $this->audit_model;
            if (!$a->persistence) {
                $m->persistence->add($a);
            }

            // ignore records which have empty request_diff, reactive_diff and descr
            // such records are generated because they are pushed in beforeSave hook,
            // but if there was no actual changes in data model, then afterSave hook
            // is never called, so we can't delete them automatically :(
            // AND worst thing about this is that we can't add this condition (below)
            // because then in audit push() save() we can't reload record and all audit
            // system goes down :(
            // SO currently we will have these empty audit records and no way to get rid of them.
            // Related: https://github.com/atk4/audit/issues/17
            /*
            $a->addCondition([
                ['descr', 'not', null],
                ['request_diff', 'not', null],
                ['reactive_diff', 'not', null],
            ]);
            */

            // jail
            $a->addCondition('model', get_class($m));
            if ($m->loaded()) {
                $a->addCondition('model_id', $m->id);
            }

            return $a;
        });

        // adds custom log methods in model
        // log() method can clash with some debug logger, so we use two methods just in case
        if (!$m->hasMethod('log')) {
            $m->addMethod('log', [$this, 'customLog']);
        }
        if (!$m->hasMethod('auditLog')) {
            $m->addMethod('auditLog', [$this, 'customLog']);
        }

        // adds link to audit controller in model properties
        $m->auditController = $this;
    }

    /**
     * Push change into audit log (and audit log stack).
     *
     * @param \atk4\data\Model $m
     * @param string           $action
     *
     * @return model\AuditLog
     */
    public function push(\atk4\data\Model $m, $action)
    {
        // add audit model
        $a = $m->ref('AuditLog');

        // set audit record values
        $a['ts'] = new \DateTime();

        // sometimes we already have conditions set on model, but there are strange cases,
        // when they are not. That's why we needed following 2 lines :(
        // BUT hopefully don't need them anymore - let's see.
        //$a['model'] = get_class($m);
        //$a['model_id'] = $m->id;

        if ($this->custom_action) {
            $action = $this->custom_action;
            $this->custom_action = null;
        }
        $a['action'] = $action;

        if ($this->custom_fields) {
            $a->set($this->custom_fields);
            $this->custom_fields = [];
        }

        if ($this->audit_log_stack) {
            // link to previous audit record
            $a['initiator_audit_log_id'] = $this->audit_log_stack[0]->id;
        }

        // save the initial action
        $a->save();

        // memorize start time
        if ($this->record_time_taken) {
            $a->start_mt = (float)microtime();
        }

        //Imants: deprecated - use $m->auditController->audit_log_stack[0] instead
        // or $m->auditController->custom_action and custom_fields properties in your beforeSave hook
        //$m->audit_log = $a;

        // save audit record in beginning of stack
        array_unshift($this->audit_log_stack, $a);

        return $a;
    }

    /**
     * Pull most recent change from audit log stack.
     *
     * @param \atk4\data\Model $m
     *
     * @return model\AuditLog
     */
    public function pull(\atk4\data\Model $m)
    {
        $a = array_shift($this->audit_log_stack);

        if ($this->custom_action) {
            $a->set('action', $this->custom_action);
            $this->custom_action = null;
        }

        if ($this->custom_fields) {
            $a->set($this->custom_fields);
            $this->custom_fields = [];
        }

        // save time taken
        if ($this->record_time_taken) {
            $a['time_taken'] = (float)microtime() - $a->start_mt;
        }

        return $a;
    }

    /**
     * Calculates and returns array of all changed fields and their values.
     *
     * @param \atk4\data\Model $m
     *
     * @return array
     */
    public function getDiffs(\atk4\data\Model $m)
    {
        $diff = [];
        foreach ($m->dirty as $key => $original) {

            $f = $m->hasElement($key);

            // don't log fields if no_audit=true is set
            if ($f && isset($f->no_audit) && $f->no_audit) {
                continue;
            }

            // don't log DSQL expressions because they can be recursive and we can't store them
            if ($original instanceof \atk4\dsql\Expression || $m[$key] instanceof \atk4\dsql\Expression) {
                continue;
            }

            // key = [old value, new value]
            $diff[$key] = [$original, $m[$key]];
        }

        return $diff;
    }

    /**
     * Executes before model record is saved.
     *
     * @param \atk4\data\Model $m
     */
    public function beforeSave(\atk4\data\Model $m)
    {
        $action = $m->loaded() ? 'update' : 'create';
        $a = $this->push($m, $action);

        $a['request_diff'] = $this->getDiffs($m);

        if (!$a['descr'] && $m->loaded()) {
            $this->setDescr($a, $m, $action);
        }
    }

    /**
     * Executes after model record is saved.
     *
     * @param \atk4\data\Model $m
     */
    public function afterSave(\atk4\data\Model $m)
    {
        // pull from audit stack
        $a = $this->pull($m);

        if ($a['model_id'] === null) {
            // new record
            $a['reactive_diff'] = $m->get();
            $a['model_id'] = $m->id;

            // fill missing description for new record
            $action = 'save';
            if (!$a['descr'] && $m->loaded()) {
                $this->setDescr($a, $m, $action);
            }

        } else {

            // updated record
            $d = $this->getDiffs($m);
            foreach ($d as $f => list($f0, $f1)) {
                if (
                    isset($a['request_diff'][$f][1])
                    && $a['request_diff'][$f][1] === $f1
                ) {
                    unset($d[$f]);
                }
            }
            $a['reactive_diff'] = $d;

            if ($a['reactive_diff']) {
                $a['descr'] .= ' (resulted in '.$this->getDescr($a['reactive_diff'], $m).')';
            }
        }

        if ($a['request_diff'] || $a['reactive_diff']) {
            // there was changes - let's update audit record
            $a->save();
        } else {
            // no changes at all - let's delete pushed record, we actually didn't need it
            $a->delete();
        }
    }

    /**
     * Set description.
     *
     * @param model\AuditLog   $a      Audit model
     * @param \atk4\data\Model $m      Data model
     * @param string           $action Action taken
     */
    public function setDescr(\atk4\data\Model $a, \atk4\data\Model $m, string $action)
    {
        if ($a->hasMethod('getDescr')) {
            $a['descr'] = $a->getDescr();
        } else {
            // could use $m->getTitle() here, but we don't want to see IDs in log descriptions
            if ($m->hasElement($m->title_field)) {
                $a['descr'] = $action.' '.$m[$m->title_field].': '.$this->getDescr($a['request_diff'], $m);
            } else {
                $a['descr'] = $action.': '.$this->getDescr($a['request_diff'], $m);
            }
        }
    }

    /**
     * Executes before model record is deleted.
     *
     * @param \atk4\data\Model $m
     */
    public function beforeDelete(\atk4\data\Model $m)
    {
        $a = $this->push($m, 'delete');
        if ($m->only_fields) {
            $id = $m->id;
            $m = $m->newInstance()->load($id); // we need all fields
        }
        $a['request_diff'] = array_map(function($v){return [$v, null];}, $m->get());
        $a['descr'] = 'delete id='.$m->id;
        if ($m->title_field && $m->hasElement($m->title_field)) {
            $a['descr'] .= ' ('.$m[$m->title_field].')';
        }
    }

    /**
     * Executes after model record is deleted.
     *
     * @param \atk4\data\Model $m
     */
    public function afterDelete(\atk4\data\Model $m)
    {
        $this->pull($m)->save();
    }

    /**
     * Credit to mpen: http://stackoverflow.com/a/27368848/204819
     *
     * @param mixed $var
     *
     * @return bool
     */
    protected function canBeString($var) {
        return $var === null || is_scalar($var) || is_callable([$var, '__toString']);
    }

    /**
     * Return string with key=value info.
     *
     * @param array            $diff
     * @param \atk4\data\Model $m
     *
     * @return string
     *
     * @throws Exception
     */
    public function getDescr($diff, \atk4\data\Model $m)
    {
        if (!$diff) {
            return 'no changes';
        }

        $t = [];
        foreach ($diff as $key=>list($from, $to)) {
            $from = $m->persistence->typecastSaveField($m->getElement($key), $from);
            $to = $m->persistence->typecastSaveField($m->getElement($key), $to);

            if (!$this->canBeString($from) || ! $this->canBeString($to)) {
                throw new \atk4\core\Exception([
                    'Unable to typecast value for storing',
                    'field' => $key,
                    'from' => $from,
                    'to' => $to,
                ]);
            }

            $t[] = $key.'='.$to;
        }

        return join(', ', $t);
    }

    /**
     * Create custom log record.
     *
     * @param \atk4\data\Model $m
     * @param string           $action
     * @param string           $descr
     * @param array            $fields
     */
    public function customLog(\atk4\data\Model $m, $action, $descr = null, $fields = [])
    {
        $a = $this->push($m, $action);

        if ($descr === null) {
            if ($m->hasElement($m->title_field)) {
                $descr = $action.' '.$m[$m->title_field].': ';
            } else {
                $descr = $action;
            }
        }
        $a['descr'] = $descr;

        if ($fields) {
            $a->set($fields);
        }

        $this->pull($m)->save();
    }
}
