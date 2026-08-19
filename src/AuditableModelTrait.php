<?php

declare(strict_types=1);

namespace Atk4\Audit;

class AuditableModelTrait
{
    /** @var bool Should we audit this model */
    public $no_audit = true;
}
