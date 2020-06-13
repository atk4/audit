<?php

namespace atk4\audit\tests;

use atk4\audit\model\AuditLog;
use atk4\ui\App;

/** @runTestsInSeparateProcesses */
class ViewTest extends \atk4\schema\PhpunitTestCase
{
    public function testDemo()
    {
        include dirname(__DIR__) . '/demos/demo.php';
    }

    public function testIndex()
    {
        include dirname(__DIR__) . '/demos/index.php';
    }

    public function testWizard()
    {
        include dirname(__DIR__) . '/demos/wizard.php';
    }
}
