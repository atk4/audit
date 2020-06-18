<?php

declare(strict_types=1);

namespace atk4\audit\tests;

use atk4\audit\model\AuditLog;
use atk4\ui\App;

/** @runTestsInSeparateProcesses */
class ViewTest extends \atk4\schema\PhpunitTestCase
{
    public function testDemo()
    {
        include dirname(__DIR__) . '/demos/demo.php';
        $this->assertTrue(true); // fake assert
    }

    public function testIndex()
    {
        include dirname(__DIR__) . '/demos/index.php';
        $this->assertTrue(true); // fake assert
    }

    public function testWizard()
    {
        include dirname(__DIR__) . '/demos/wizard.php';
        $this->assertTrue(true); // fake assert
    }
}
