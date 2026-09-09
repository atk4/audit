<?php

declare(strict_types=1);

namespace Atk4\Audit\Tests;

use Atk4\Audit\AuditController;
use Atk4\Data\Schema\TestCase as Atk4TestCase;

/**
 * Tests basic create, update and delete operations.
 */
abstract class TestCase extends Atk4TestCase
{
    /** @var AuditController */
    protected $audit;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->audit = new AuditController($this->db);
        $this->createMigrator($this->audit->auditModel)->create();

        // force ascending order to ease testing
        $this->audit->auditModel->setOrder($this->audit->auditModel->idField);
    }
}
