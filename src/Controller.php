<?php

declare(strict_types=1);

namespace Atk4\Audit;

use Atk4\Audit\Model\AuditLog;
use Atk4\Core\DIContainerTrait;
use Atk4\Core\Exception;
use Atk4\Core\Factory;
use Atk4\Core\InitializerTrait;
use Atk4\Core\TrackableTrait;
use Atk4\Data\Model;

class Controller
{
    use InitializerTrait {
        init as _init;
    }
    use TrackableTrait;
    use DIContainerTrait;

    /**
     * Audit data model.
     * Pass this property in constructor seed to change it.
     *
     * @var string|AuditLog
     */
    public $audit_model = AuditLog::class;

    /** @var array audit log stack - most recent record is first */
    public $audit_log_stack = [];

    /** @var bool should we record time taken to make this change? */
    public $record_time_taken = true;

    /** @var float start time of audit log */
    public $start_mt;

    /** @var string name of custom action, for example "comment" */
    public $custom_action;

    /** @var array custom fields to add */
    public $custom_fields = [];

    /**
     * Constructor - set up properties and audit model.
     *
     * @param array|string|Model $defaults Seed options or just audit model
     */
    public function __construct($defaults = [])
    {
        $defaults = is_array($defaults) ? $defaults : ['audit_model' => $defaults];

        $this->setDefaults($defaults);

        // create audit model object if it's not already there
        $this->audit_model = Factory::factory([$this->audit_model]);
    }

    /**
     * Initialize - set up all necessary hooks etc.
     */
    protected function init(): void
    {
        $this->_init();

        if ($this->getOwner() instanceof Model) {
            $this->setUp($this->getOwner());
        }
    }

    /**
     * Will set up specified model to be logged.
     */
    public function setUp(Model $m)
    {
        // don't set up audit if model has $no_audit=true
        if (isset($m->no_audit) && $m->no_audit) {
            return;
        }

        // adds hooks
        $m->onHook(
            Model::HOOK_BEFORE_SAVE,
            \Closure::fromCallable([$this, 'beforeSave']),
            [],
            -100
        );
        $m->onHook(
            Model::HOOK_BEFORE_DELETE,
            \Closure::fromCallable([$this, 'beforeDelete']),
            [],
            -100
        ); // called as soon as possible

        $m->onHook(
            Model::HOOK_AFTER_SAVE,
            \Closure::fromCallable([$this, 'afterSave']),
            [],
            100
        );
        $m->onHook(
            Model::HOOK_AFTER_DELETE,
            \Closure::fromCallable([$this, 'afterDelete']),
            [],
            100
        ); // called as late as possible

        // adds hasMany reference to audit records
        $m->addRef('AuditLog', function ($m) {
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
                $a->addCondition('model_id', $m->getId());
            }

            return $a;
        });

        // adds custom log methods in model
        // log() method can clash with some debug logger, so we use two methods just in case
        if (!$m->hasMethod('log')) {
            $m->addMethod('log', \Closure::fromCallable([$this, 'customLog']));
        }

        if (!$m->hasMethod('auditLog')) {
            $m->addMethod('auditLog', \Closure::fromCallable([$this, 'customLog']));
        }

        // adds link to audit controller in model properties
        $m->auditController = $this;
    }

    /**
     * Push change into audit log (and audit log stack).
     */
    public function push(Model $m, string $action): AuditLog
    {
        /** @var AuditLog $a */
        $a = $m->ref('AuditLog');

        // set audit record values
        $a->set('ts', new \DateTime());

        // sometimes we already have conditions set on model, but there are strange cases,
        // when they are not. That's why we needed following 2 lines :(
        // BUT hopefully don't need them anymore - let's see.
        //$a->set('model', get_class($m));
        //$a->set('model_id', $m->getId());

        if ($this->custom_action) {
            $action = $this->custom_action;
            $this->custom_action = null;
        }

        $a->set('action', $action);

        if ($this->custom_fields) {
            $a->setMulti($this->custom_fields);
            $this->custom_fields = [];
        }

        if ($this->audit_log_stack) {
            // link to previous audit record
            $a->set('initiator_audit_log_id', $this->audit_log_stack[0]->getId());
        }

        // save the initial action
        $a->save();

        // memorize start time
        if ($this->record_time_taken) {
            $a->start_mt = (float) microtime();
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
     * @return AuditLog
     */
    public function pull()
    {
        $a = array_shift($this->audit_log_stack);

        if ($this->custom_action) {
            $a->set('action', $this->custom_action);
            $this->custom_action = null;
        }

        if ($this->custom_fields) {
            $a->setMulti($this->custom_fields);
            $this->custom_fields = [];
        }

        // save time taken
        if ($this->record_time_taken) {
            $a->set('time_taken', (float) microtime() - $a->start_mt);
        }

        return $a;
    }

    /**
     * Calculates and returns array of all changed fields and their values.
     */
    public function getDiffs(Model $m): array
    {
        $diff = [];
        foreach ($m->dirty as $key => $original) {
            if (!$this->isDiffFieldAuditable($m, $key)) {
                continue;
            }

            $value = $m->get($key);

            // object need to be serialized before save in audit
            // if not it will pass in json_encode and became an array
            $original = is_object($original) ? serialize($original) : $original;
            $value = is_object($value) ? serialize($value) : $value;

            // key = [old value, new value]
            $diff[$key] = [$original, $value];
        }

        return $diff;
    }

    /**
     * Executes before model record is saved.
     */
    public function beforeSave(Model $m, bool $is_update)
    {
        $action = $is_update ? 'update' : 'create';
        $a = $this->push($m, $action);

        $a->set('request_diff', $this->getDiffs($m));

        if (!$a->get('descr') && $is_update) {
            $this->setDescr($a, $m, $action);
        }
    }

    /**
     * Executes after model record is saved.
     */
    public function afterSave(Model $m, bool $is_update)
    {
        // pull from audit stack
        $a = $this->pull();

        if ($a->get('model_id') === null) {
            // new record
            $a->set('reactive_diff', $m->get());
            $a->set('model_id', $m->getId());

            // fill missing description for new record
            $action = 'save';
            if (empty($a->get('descr')) && $is_update) {
                $this->setDescr($a, $m, $action);
            }
        } else {
            // updated record
            $d = $this->getDiffs($m);
            foreach ($d as $f => [$f0, $f1]) {
                // if not set don't purge
                if (!isset($a->get('request_diff')[$f][1])) {
                    continue;
                }

                if (json_encode([$a->get('request_diff')[$f][1]]) === json_encode([$f1])) {
                    unset($d[$f]);
                }
            }

            $a->set('reactive_diff', $d);

            if (count($d) > 0 && empty($a->get('descr'))) {
                $a->set('descr', '(resulted in ' . $this->getDescr($a->get('reactive_diff'), $m) . ')');
            }
        }

        if (!empty($a->get('request_diff')) || !empty($a->get('reactive_diff'))) {
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
     * @param AuditLog $a      Audit model
     * @param Model    $m      Data model
     * @param string   $action Action taken
     */
    public function setDescr(AuditLog $a, Model $m, string $action)
    {
        if ($a->hasMethod('getDescr')) {
            $descr = $a->getDescr();
        } else {
            // could use $m->getTitle() here, but we don't want to see IDs in log descriptions
            if ($m->hasField($m->title_field)) {
                $descr = $action . ' ' . $m->getTitle() . ': ' . $this->getDescr($a->get('request_diff'), $m);
            } else {
                $descr = $action . ': ' . $this->getDescr($a->get('request_diff'), $m);
            }
        }

        $a->set('descr', $descr);
    }

    /**
     * Executes before model record is deleted.
     *
     * @param mixed $model_id
     */
    public function beforeDelete(Model $m, $model_id)
    {
        $a = $this->push($m, 'delete');
        if ($m->only_fields) {
            $m = $m->newInstance()->load($model_id); // we need all fields
        }

        $diff = [];
        foreach ($m->data as $key => $original) {
            if (!$this->isDiffFieldAuditable($m, $key)) {
                continue;
            }

            // object need to be serialized before save in audit
            // if not it will pass in json_encode and became an array
            if (is_object($original)) {
                $original = serialize($original);
            }

            // key = [old value, new value]
            $diff[$key] = [$original, null];
        }

        $a->set('request_diff', $diff);

        $descr = 'delete id=' . $model_id;

        if ($m->title_field && $m->hasField($m->title_field)) {
            $descr .= ' (' . $m->getTitle() . ')';
        }

        $a->set('descr', $descr);
    }

    /**
     * Executes after model record is deleted.
     *
     * @param mixed $model_id
     */
    public function afterDelete(Model $m, $model_id)
    {
        $this->pull()->save();
    }

    /**
     * Credit to open: http://stackoverflow.com/a/27368848/204819.
     *
     * @param mixed $var
     */
    protected function canBeString($var): bool
    {
        return $var === null || is_scalar($var) || is_callable([$var, '__toString']);
    }

    /**
     * Return string with key=value info.
     *
     * @param array $diff
     */
    public function getDescr($diff, Model $m): string
    {
        if (!$diff) {
            return 'no changes';
        }

        $t = [];
        foreach ($diff as $key => [$from, $to]) {
            $from = $this->getDescrFieldValue($m, $key, $from);
            $to = $this->getDescrFieldValue($m, $key, $to);

            if (!$this->canBeString($to)) {
                throw (new Exception('Unable to typecast value for storing'))
                    ->addMoreInfo('field', $key)
                    ->addMoreInfo('from', $from)
                    ->addMoreInfo('to', $to);
            }

            $t[] = $key . '=' . (string) $to;
        }

        return implode(', ', $t);
    }

    /**
     * Create custom log record.
     */
    public function customLog(Model $m, string $action, string $descr = null, array $fields = [])
    {
        $a = $this->push($m, $action);

        if ($descr === null) {
            if ($m->hasField($m->title_field)) {
                $descr = $action . ' ' . $m->get($m->title_field) . ': ';
            } else {
                $descr = $action;
            }
        }

        $a->set('descr', $descr);

        if ($fields) {
            $a->setMulti($fields);
        }

        $this->pull()->save();
    }

    /**
     * @param mixed $value
     */
    protected function getDescrFieldValue(Model $m, string $fieldname, $value): string
    {
        try {
            $field_must_be_object = in_array(
                $m->getField($fieldname)->type,
                [
                    'date',
                    'datetime',
                    'time',
                    'object',
                ],
                true
            );

            if (is_string($value) && $field_must_be_object) {
                $value = unserialize($value);

                if ($this->canBeString($value)) {
                    return (string) $value;
                }
            }

            // should use typecastSaveRow not typecastSaveField because we can have fields with serialize property set too
            // don't typecast value if it's empty anyway: https://github.com/atk4/data/issues/439
            $value = current($m->persistence->typecastSaveRow($m, [$fieldname => $value]));
        } catch (\Throwable $t) {
        }

        return (string) $value;
    }

    protected function isDiffFieldAuditable(Model $m, string $key): bool
    {
        if (!$m->hasField($key)) {
            return false;
        }

        $f = $m->getField($key);

        // don't log fields if no_audit=true is set
        if (isset($f->no_audit) && $f->no_audit) {
            return false;
        }

        // security fix : https://github.com/atk4/audit/pull/30
        if ($f->never_persist || $f->never_save || $f->read_only) {
            return false;
        }

        return true;
    }
}
