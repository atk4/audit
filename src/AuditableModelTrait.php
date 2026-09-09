<?php

declare(strict_types=1);

namespace Atk4\Audit;

trait AuditableModelTrait
{
    /** @var bool Should we audit this model? */
    public $noAudit = false;

    /** @var array<mixed,mixed>|AuditPolicy Optional audit policy definition */
    public $auditPolicy;
}
