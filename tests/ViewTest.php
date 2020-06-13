<?php

namespace atk4\audit\tests;

use atk4\audit\model\AuditLog;
use atk4\ui\App;

/** @runTestsInSeparateProcesses */
class ViewTest extends \atk4\schema\PhpunitTestCase
{
    public function testDemo()
    {
        include getcwd() . '/demos/demo.php';
    }

    public function testIndex()
    {
        include getcwd() . '/demos/index.php';
    }

    public function testWizard()
    {
        include getcwd() . '/demos/wizard.php';
    }
}
