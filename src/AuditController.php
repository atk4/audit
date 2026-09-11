<?php

declare(strict_types=1);

namespace Atk4\Audit;

use Atk4\Audit\Model\AuditLog;
use Atk4\Core\DiContainerTrait;
use Atk4\Core\Exception;
use Atk4\Core\Factory;
use Atk4\Core\InitializerTrait;
use Atk4\Core\TrackableTrait;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

class AuditController
{
    use DiContainerTrait;
    use InitializerTrait {
        init as _init;
    }
    use TrackableTrait;

    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';

    public const VALUE_REDACTED = '[REDACTED]';
    public const VALUE_UNSUPPORTED = '[UNSUPPORTED]';

    /**
     * Audit data model.
     * Pass this property in constructor seed to change it.
     *
     * @var array<mixed,mixed>|AuditLog
     */
    public $auditModel = [AuditLog::class];

    private AuditPolicy $defaultPolicy;

    /** @var array<string,AuditPolicy> */
    private array $policies = [];

    /** @var array<int,AuditPolicy> */
    private array $models = [];

    /** @var Stack audit log stack */
    private Stack $stack;

    /** @var Persistence Persistence to observe */
    private ?Persistence $persistence = null;

    /** @var int Observed persistence hook index */
    private ?int $persistenceHookIndex = null;

    /**
     * Creates audit controller object.
     *
     * @param array<string, mixed> $defaults
     */
    public function __construct(?Persistence $persistence = null, array $defaults = [])
    {
        $this->defaultPolicy = new AuditPolicy();

        $this->setDefaults($defaults);

        // create audit model object if it's not already there
        $this->auditModel = Factory::factory($this->auditModel);
        if (!$this->auditModel->issetPersistence() && $persistence !== null) {
            $this->auditModel->setPersistence($persistence);
        }

        $this->stack = new Stack();
    }

    protected function init(): void
    {
        $this->_init();
    }

    /**
     * @return $this
     */
    public function setDefaultPolicy(AuditPolicy $policy)
    {
        $this->defaultPolicy = $policy;

        return $this;
    }

    /**
     * @return $this
     */
    public function setModelPolicy(string $modelClass, AuditPolicy $policy)
    {
        $this->policies[$modelClass] = $policy;

        return $this;
    }

    protected function getPolicyForModel(Model $model): AuditPolicy
    {
        $model = $this->getBaseModel($model);

        return $this->policies[get_class($model)] ?? $this->defaultPolicy;
    }

    /**
     * Monitor persistence, so models added later are automatically monitored.
     */
    public function observePersistence(Persistence $persistence): void
    {
        if ($this->persistence !== null) {
            throw new Exception('Audit controller already observes persistence');
        }

        $this->persistence = $persistence;

        $this->persistenceHookIndex = $persistence->onHook(
            Persistence::HOOK_AFTER_ADD,
            function (Persistence $p, Model $m) {
                $this->addModel($m);
            }
        );
    }

    /**
     * Stop monitoring persistence.
     *
     * Note: Models which were already added to persistence will still be monitored.
     */
    public function stopObservingPersistence(): void
    {
        if ($this->persistence === null) {
            throw new Exception('Audit controller does not observe persistence');
        }

        $this->persistence->removeHook(Persistence::HOOK_AFTER_ADD, $this->persistenceHookIndex, true);
        $this->persistenceHookIndex = null;
        $this->persistence = null;
    }

    /**
     * Add multiple models to observe.
     *
     * Note: You can not set policies when using this method, so use setModelPolicy() to set up policies in advance.
     *
     * @param Model[] $models
     *
     * @return $this
     */
    public function addModels(array $models)
    {
        foreach ($models as $model) {
            $this->addModel($model);
        }

        return $this;
    }

    private function getBaseModel(Model $model): Model
    {
        return $model->getModel(true);
    }

    /**
     * Add model to observe.
     *
     * @return $this
     */
    public function addModel(Model $model, ?AuditPolicy $policy = null)
    {
        $model = $this->getBaseModel($model);

        // if already added, then just ignore and do nothing
        $obj_id = spl_object_id($model);
        if (isset($this->models[$obj_id])) {
            return $this;
        }

        // avoid auditing audit model itself
        if ($this->auditModel instanceof AuditLog && $model instanceof $this->auditModel) {
            return $this;
        }

        // store model and policy
        $policy ??= $this->getPolicyForModel($model);

        if ($policy->getModelMode($model) === AuditPolicy::MODEL_IGNORE) {
            return $this;
        }

        // var_dump('Store policy: '.get_class($model).' '.$obj_id);
        $this->models[$obj_id] = $policy;

        // add model hooks
        $model->onHook(
            Model::HOOK_BEFORE_SAVE,
            \Closure::fromCallable([$this, 'beforeSave']),
            [],
            \PHP_INT_MIN // as soon as possible
        );
        $model->onHook(
            Model::HOOK_BEFORE_DELETE,
            \Closure::fromCallable([$this, 'beforeDelete']),
            [],
            \PHP_INT_MIN // as soon as possible
        );
        $model->onHook(
            Model::HOOK_AFTER_INSERT,
            \Closure::fromCallable([$this, 'afterInsert']),
            [],
            \PHP_INT_MAX // as late as possible
        );
        $model->onHook(
            Model::HOOK_AFTER_UPDATE,
            \Closure::fromCallable([$this, 'afterUpdate']),
            [],
            \PHP_INT_MAX // as late as possible
        );
        $model->onHook(
            Model::HOOK_AFTER_SAVE,
            \Closure::fromCallable([$this, 'afterSave']),
            [],
            \PHP_INT_MAX // as late as possible
        );
        $model->onHook(
            Model::HOOK_AFTER_DELETE,
            \Closure::fromCallable([$this, 'afterDelete']),
            [],
            \PHP_INT_MAX // as late as possible
        );

        // adds hasMany reference to audit records
        $self = $this;

        $model->hasMany('AuditLog', [
            'model' => static function (Persistence $p) use ($model, $self) {
                return (clone $self->auditModel)->addCondition('model', get_class($model));
            },
            'ourField' => $model->idField,
            'theirField' => 'model_id',
        ]);

        /*
        // adds custom log method in model
        if (!$model->hasMethod('auditLog')) {
            $model->addMethod('auditLog', \Closure::fromCallable([$this, 'customLog']));
        }
        */

        return $this;
    }

    /**
     * Create new audit log record and push change into audit log table (and audit log stack).
     *
     * @param array<string,mixed> $request_diff
     */
    private function push(Model $m, string $action, array $request_diff = []): AuditLog
    {
        $m->assertIsEntity();

        // var_dump(['push'=>get_class($m)]);

        /** @var AuditLog $a */
        $a = $this->auditModel->createEntity();

        // set audit record values
        $a->setMulti([
            'model' => get_class($m),
            'model_id' => $m->isLoaded() ? $m->getId() : null,
            'start_time_ms' => self::getMs(),
            'action' => $action,
            'request_diff' => $request_diff,
            'reactive_diff' => [],
            'user_info' => $this->getUserInfo(),
        ]);

        if (!$this->stack->isEmpty()) {
            // link to previous audit record
            $a->set('initiator_audit_log_id', $this->stack->top()->getId());
        }

        // save the initial action
        $a->save();

        // save audit record in stack
        $this->stack->push($a);

        return $a;
    }

    /**
     * Pull most recent AuditLog entity from audit log stack.
     */
    private function pull(): AuditLog
    {
        $a = $this->stack->pop();
        // var_dump(['pull'=>$a->get('model')]);

        // save time taken
        $a->set('duration_ms', self::getMs() - $a->get('start_time_ms'));

        return $a;
    }

    private static function getMs(): int
    {
        return (int) round(microtime(true) * 1_000);
    }

    private function getModelAuditPolicy(Model $model): AuditPolicy
    {
        $model = $this->getBaseModel($model);

        // var_dump('Request policy: '.get_class($model).' '.spl_object_id($model));
        return $this->models[spl_object_id($model)] ?? $this->defaultPolicy;
    }

    /**
     * Returns array of user info.
     *
     * @return array<string,string>
     */
    protected function getUserInfo(): array
    {
        $info = [];

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $info['ip'] = $_SERVER['REMOTE_ADDR'];
        }

        return $info;
    }

    /**
     * Calculates and returns array of all changed fields and their values.
     *
     * @return array<string,list<mixed>>
     */
    private function getDirtyDiff(Model $m): array
    {
        $policy = $this->getModelAuditPolicy($m);

        $diff = [];
        foreach ($m->getDirtyRef() as $fieldName => $oldValue) {
            $f = $m->getField($fieldName);
            $newValue = $m->get($fieldName);

            $mode = $policy->getFieldMode($f);
            switch ($mode) {
                case AuditPolicy::FIELD_IGNORE:
                    continue 2;
                case AuditPolicy::FIELD_REDACT:
                    // use redacted representation
                    $oldValue = ($oldValue === null ? null : self::VALUE_REDACTED);
                    $newValue = ($newValue === null ? null : self::VALUE_REDACTED);

                    break;
                case AuditPolicy::FIELD_AUDIT:
                    // actual value
                    break;
            }

            // object need to be serialized before save in audit
            // if not it will pass in json_encode and became an array
            $oldValue = $this->encodeAuditValue($m, $fieldName, $oldValue);
            $newValue = $this->encodeAuditValue($m, $fieldName, $newValue);

            // fieldName = [old value, new value]
            $diff[$fieldName] = [$oldValue, $newValue];
        }

        return $diff;
    }

    /**
     * Remove records from reactive diff if they are already found in request diff.
     *
     * @param array<string,mixed>            $reactiveDiff
     * @param array<string,array<int,mixed>> $requestDiff
     *
     * @return array<string,mixed>
     */
    private function cleanupReactiveDiff(Model $m, array $reactiveDiff, array $requestDiff): array
    {
        $policy = $this->getModelAuditPolicy($m);

        $diff = [];
        foreach ($reactiveDiff as $fieldName => $newValue) {
            $f = $m->getField($fieldName);
            $newValue = $this->encodeAuditValue($m, $fieldName, $newValue);

            $mode = $policy->getFieldMode($f);
            switch ($mode) {
                case AuditPolicy::FIELD_IGNORE:
                    continue 2;
                case AuditPolicy::FIELD_REDACT:
                    // use redacted representation
                    $newValue = ($newValue === null ? null : self::VALUE_REDACTED);

                    break;
                case AuditPolicy::FIELD_AUDIT:
                    // actual value
                    break;
            }

            // if change was requested and value matches the one requested, then skip
            if (array_key_exists($fieldName, $requestDiff)) {
                $requested = $this->decodeAuditValue($m, $fieldName, $requestDiff[$fieldName][1]);
                $reactive = $this->decodeAuditValue($m, $fieldName, $newValue);

                if ($m->getField($fieldName)->compare($requested, $reactive)) {
                    continue;
                }
            }

            $diff[$fieldName] = $newValue;
        }

        return $diff;
    }

    /**
     * Encode value for saving.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function encodeAuditValue(Model $m, string $fieldName, $value)
    {
        return $value === null ? null : $m->getModel()->getPersistence()->typecastSaveField($m->getField($fieldName), $value);
    }

    /**
     * Decode value when loading.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function decodeAuditValue(Model $m, string $fieldName, $value)
    {
        return $value === null ? null : $m->getModel()->getPersistence()->typecastLoadField($m->getField($fieldName), $value);
    }

    /**
     * Executes before model record is saved as soon as possible.
     */
    protected function beforeSave(Model $m, bool $is_update): void
    {
        $action = $is_update ? self::ACTION_UPDATE : self::ACTION_CREATE;
        $requestDiff = $this->getDirtyDiff($m);
        $a = $this->push($m, $action, $requestDiff);

        /*
        if (!$a->get('descr') && $is_update) {
            $this->setDescr($a, $m, $action);
        }
        */
    }

    /**
     * Executes after model record is inserted as late as possible.
     */
    protected function afterInsert(Model $m): void
    {
        // get (but don't pull) from audit stack
        $a = $this->stack->top();

        $requestDiff = $a->get('request_diff') ?? [];
        $reactiveDiff = $this->cleanupReactiveDiff($m, $m->get(), $requestDiff);

        // new record created
        $a->setMulti([
            'model_id' => $m->getId(),
            'reactive_diff' => $reactiveDiff,
        ]);

        /*
        // fill missing description for new record
        $action = 'save';
        if (!$a->get('descr') && $is_update) {
            $this->setDescr($a, $m, $action);
        }
        */

        $a->save();
    }

    /**
     * Executes after model record is updated as late as possible.
     * Note - keep in mind that after update hook is not called at all if data has not changed.
     *        So in such case it'll generate "empty" audit record which is kind of useless, but still correct behaviour.
     *
     * @param array<string,mixed> $changed_data
     */
    protected function afterUpdate(Model $m, array $changed_data): void
    {
        // get (but don't pull) from audit stack
        $a = $this->stack->top();

        $requestDiff = $a->get('request_diff') ?? [];
        $reactiveDiff = $this->cleanupReactiveDiff($m, $changed_data, $requestDiff);

        $a->set('reactive_diff', $reactiveDiff);

        /*
        if (count($d) > 0 && !$a->get('descr')) {
            $a->set('descr', '(resulted in ' . $this->getDescr($a->get('reactive_diff'), $m) . ')');
        }
        */

        $a->save();
    }

    /**
     * Executes after model record is saved as late as possible.
     *
     * If there were no data changes, then delete useless audit record
     */
    public function afterSave(Model $m, bool $is_update, bool $noChanges): void
    {
        $a = $this->pull();

        if ($is_update && $noChanges) {
            $a->delete();
        } else {
            $a->save();
        }
    }

    /**
     * Executes before model record is deleted as soon as possible.
     */
    public function beforeDelete(Model $m): void
    {
        // we need access to all fields
        $onlyFields = $m->getModel()->onlyFields;
        if ($onlyFields) {
            // $m = $m->getModel()->createEntity()->load($m->getId());
            $m = $m->getModel()->setOnlyFields(null)->load($m->getId());
        }

        $policy = $this->getModelAuditPolicy($m);

        $requestDiff = [];
        foreach ($m->getDataRef() as $fieldName => $value) {
            $f = $m->getField($fieldName);

            $mode = $policy->getFieldMode($f);
            switch ($mode) {
                case AuditPolicy::FIELD_IGNORE:
                    continue 2;
                case AuditPolicy::FIELD_REDACT:
                    // use redacted representation
                    $value = ($value === null ? null : self::VALUE_REDACTED);

                    break;
                case AuditPolicy::FIELD_AUDIT:
                    // actual value
                    break;
            }

            // object need to be serialized before save in audit
            // if not it will pass in json_encode and became an array
            $value = $this->encodeAuditValue($m, $fieldName, $value);

            // key = [old value, new value]
            $requestDiff[$fieldName] = [$value, null];
        }

        $a = $this->push($m, self::ACTION_DELETE, $requestDiff);

        /*
        $descr = 'delete id=' . $m->getId();

        if ($m->titleField && $m->hasField($m->titleField)) {
            $descr .= ' (' . $m->getTitle() . ')';
        }

        $a->set('descr', $descr);
        */

        // restore onlyFields
        $m->getModel()->setOnlyFields($onlyFields);
    }

    /**
     * Executes after model record is deleted as late as possible.
     */
    public function afterDelete(Model $m): void
    {
        $this->pull()->save();
    }
}
