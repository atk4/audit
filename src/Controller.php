<?php

namespace atk4\audit;

class Controller
{
    use \atk4\core\InitializerTrait {
        init as _init;
    }
    use \atk4\core\TrackableTrait;
    use \atk4\core\DIContainerTrait;

    /** @var array audit log stack - most recent record is first */
    public $audit_log_stack = [];

    /** @var bool should we record time taken to make this change? */
    public $record_time_taken = true;

    /** @var model\AuditLog audit log data model */
    public $audit_model;

    /** @var float start time of audit log */
    public $start_mt;

    /** @var string name of custom action, for example "comment" */
    public $custom_action = null;

    /** @var array custom fields to add */
    public $custom_fields = [];

    /**
     * Constructor.
     *
     * @param \atk4\data\Model $a        Audit data model
     * @param array            $defaults Seed options
     */
    public function __construct($a = null, $defaults = [])
    {
        $this->audit_model = $a ?: $a = new model\AuditLog();

        $this->setDefaults($defaults);
    }

    /**
     * Initialize - set up all necessary hooks etc.
     */
    public function init()
    {
        $this->_init();

        $this->setUp($this->owner);
    }

    /**
     * Will set up specified model to be logged.
     *
     * @param \atk4\data\Model $m
     */
    public function setUp(\atk4\data\Model $m)
    {
        // adds hooks
        $m->addHook('beforeSave,beforeDelete', $this, null, -100);
        $m->addHook('afterSave,afterDelete', $this, null, 100);

        // adds hasMany reference to audit records
        $m->addRef('AuditLog', [$this, 'getAuditModel']);

        // adds custom log methods in owner model
        // log() method can clash with some debug logger, so we use two methods just in case
        if (!$m->hasMethod('log')) {
            $m->addMethod('log', [$this, 'customLog']);
        }
        if (!$m->hasMethod('auditLog')) {
            $m->addMethod('auditLog', [$this, 'customLog']);
        }

        // adds audit controller in owner model property
        $m->audit_log_controller = $this;
    }

    /**
     * Returns jailed audit model to use.
     *
     * @param \atk4\data\Model|null $m
     *
     * @return \atk4\data\Model
     */
    public function getAuditModel(\atk4\data\Model $m = null)
    {
        if ($m === null) {
            $m = $this->owner;
        }

        // clone model
        $a = isset($m->audit_model) ? $m->audit_model : $this->audit_model;
        if (!$a->persistence) {
            $m->persistence->add($a);
        }

        // jail
        $a->addCondition('model', get_class($m));
        if ($m->loaded()) {
            $a->addCondition('model_id', $m->id);
        }

        return $a;
    }

    /**
     * Push change into audit log (and audit log stack).
     *
     * @param \atk4\data\Model $m
     * @param string           $action
     *
     * @return \atk4\data\Model
     */
    public function push(\atk4\data\Model $m, $action)
    {
        // add audit model
        $a = $m->ref('AuditLog');

        // set audit record values
        $a['ts'] = new \DateTime();

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
        $a->start_mt = (float)microtime();

        //Imants: deprecated - use $m->audit_log_controller->audit_log_stack[0] instead
        // or $m->audit_log_controller->custom_action and custom_fields properties in your beforeSave hook
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
     * @return \atk4\data\Model
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
        $action = 'save';
        $a = $this->pull($m);

        if ($a['model_id'] === null) {
            // new record
            $a['reactive_diff'] = $m->get();
            $a['model_id'] = $m->id;

            // fill missing description for new record
            if (!$a['descr'] && $m->loaded()) {
                $this->setDescr($a, $m, $action);
            }

        } else {

            // updated record
            $d = $this->getDiffs($m);
            foreach ($d as $f => list($f0, $f1)) {
                if (
                    isset($d[$f], $a['request_diff'][$f][1])
                    && $a['request_diff'][$f][1] === $f1
                ) {
                    unset($d[$f]);
                }
            }
            $a['reactive_diff'] = $d;

            if ($a['reactive_diff']) {
                $x = $a['reactive_diff'];

                $a['descr'] .= ' (resulted in '.$this->getDescr($a['reactive_diff'], $m).')';
            }
        }

        $a->save();
    }

    /**
     * Set description.
     *
     * @param \atk4\data\Model $a      Audit model
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
        $a['request_diff'] = $m->get();
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
