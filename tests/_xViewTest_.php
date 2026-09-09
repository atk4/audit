<?php

declare(strict_types=1);

namespace Atk4\Audit\Tests;

use Atk4\Data\Schema\TestCase;

/** @runTestsInSeparateProcesses */
class _xViewTest extends TestCase
{
    public function testDemo()
    {
        include dirname(__DIR__) . '/demos/demo.php';
        self::assertTrue(true); // fake assert
    }

    public function testIndex()
    {
        include dirname(__DIR__) . '/demos/index.php';
        self::assertTrue(true); // fake assert
    }

    public function testWizard()
    {
        include dirname(__DIR__) . '/demos/wizard.php';
        self::assertTrue(true); // fake assert
    }
}
