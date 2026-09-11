<?php

declare(strict_types=1);

namespace Atk4\Audit\Demo;

use Atk4\Ui\App;
use Atk4\Ui\Header;

require_once __DIR__ . '/include/init.php';

// @var App $app

Header::addTo($app, ['Welcome to Audit Add-on demo app']);
