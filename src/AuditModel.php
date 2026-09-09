<?php

declare(strict_types=1);

namespace Atk4\Audit;

use Atk4\Data\Field;
use Atk4\Data\Model;

/**
 * This is deliberately a small wrapper.
 * This gives you a place to put model-specific audit handling later without turning AuditController into a huge class.
 */
class AuditModel
{
    /** @var Model */
    public $model;

    /** @var AuditPolicy */
    public $policy;

    public function __construct(Model $model, AuditPolicy $policy)
    {
        $this->model = $model;
        $this->policy = $policy;
    }

    // is this the right place for this or better in AuditPolicy or AuditController ???
    /*
    public function shouldAuditField(Field $field): bool
    {
        return $this->policy->getFieldMode($field) !== AuditPolicy::FIELD_IGNORE;
    }
    */
}
