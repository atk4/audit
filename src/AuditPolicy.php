<?php

declare(strict_types=1);

namespace Atk4\Audit;

use Atk4\Data\Field;
use Atk4\Data\Model;

class AuditPolicy
{
    const MODEL_AUDIT = 10;
    const MODEL_IGNORE = 11;

    const FIELD_AUDIT = 20;
    const FIELD_IGNORE = 21;
    const FIELD_REDACT = 22;

    /** @var array<string,bool> */
    protected $ignoredModels = [];

    /** @var array<string,bool> */
    protected $ignoredFields = [];

    /** @var array<string,bool> */
    protected $redactedFields = [];

    /**
     * Model will not be audited.
     *
     * @return $this
     */
    public function ignoreModel(string $modelClass)
    {
        $this->ignoredModels[$modelClass] = true;

        return $this;
    }

    /**
     * Return audit policy of model.
     *
     * @return self::MODEL_*
     */
    public function getModelMode(Model $model): int
    {
        return isset($this->ignoredModels[get_class($model)])
            ? self::MODEL_IGNORE
            : self::MODEL_AUDIT;
    }

    /**
     * Field will not be audited.
     *
     * @return $this
     */
    public function ignoreField(string $fieldName)
    {
        $this->ignoredFields[$fieldName] = true;

        return $this;
    }

    /**
     * Field value will not show up in audit log.
     *
     * @return $this
     */
    public function redactField(string $fieldName)
    {
        $this->redactedFields[$fieldName] = true;

        return $this;
    }

    /**
     * Return audit policy of field.
     *
     * @return self::FIELD_*
     */
    public function getFieldMode(Field $field): int
    {
        if ($field->neverPersist || $field->neverSave || $field->readOnly) {
            return self::FIELD_IGNORE;
        }

        $name = $field->shortName;

        if (isset($this->ignoredFields[$name])) {
            return self::FIELD_IGNORE;
        }

        if (isset($this->redactedFields[$name])) {
            return self::FIELD_REDACT;
        }

        return self::FIELD_AUDIT;
    }
}
