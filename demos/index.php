<?php

declare(strict_types=1);

namespace Atk4\Audit\Demos;

use Atk4\Ui\Button;
use Atk4\Ui\Header;
use Atk4\Ui\Text;
use Atk4\Ui\View;

/** @var App $app */
require_once __DIR__ . '/init-app.php';

Header::addTo($app, ['Welcome to Audit Add-on demo app']);

// Setup db by using migration
$v = View::addTo($app, ['ui' => 'segment']);
Button::addTo($v, ['Setup demo SQLite database', 'icon' => 'cogs'])->link(['admin-setup']);

// Addon description
// Text::addTo(View::addTo($app, ['ui' => 'segment']))
//    ->addParagraph('ATK UI implements a high-level User Interface for Web App - such as Admin System. One of the most common things for the Admin system is a log-in screen.')
//    ->addParagraph('Although you can implement log-in form easily, this add-on does everything for you.');
