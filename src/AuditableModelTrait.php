<?php

declare(strict_types=1);

namespace Atk4\Audit;

trait AuditableModelTrait
{
    /** @var bool Should we audit this model */
    public $no_audit = true;

    /** @var Controller Audit controller object */
    public $auditcontroller;
}
