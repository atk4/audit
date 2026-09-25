<?php

declare(strict_types=1);

namespace Atk4\Audit\Demos;

use Atk4\Audit\Demos\Model\Client;
use Atk4\Audit\Demos\Model\Invoice;
use Atk4\Audit\Demos\Model\InvoiceLine;
use Atk4\Audit\Model\AuditLog;
use Atk4\Ui\Button;
use Atk4\Ui\Console;
use Atk4\Ui\Header;
use Atk4\Ui\Js\JsBlock;
use Atk4\Ui\Message;
use Atk4\Ui\View;

/** @var App $app */
require_once __DIR__ . '/init-app.php';

Header::addTo($app, ['Setup demo database']);

$v = View::addTo($app, ['ui' => 'segment']);
Message::addTo($v, ['type' => 'warning'])->set('Be aware that running this migration will also reset all demo data you may have');

// setup migrator console
$c1 = MigratorConsole::addTo($v, ['event' => false]);

// after migration import data
$c1->onHook(MigratorConsole::HOOK_AFTER_MIGRATION, static function (Console $c): void {
    $c->notice('Populating data...');

    $clients = new Client($c->getApp()->db);
    foreach ($clients as $m) {
        $m->delete();
    }
    $clients->import([
        ['name' => 'John Doe', 'vat_number' => 'GB1234567890', 'balance' => 1234.56, 'active' => true],
        ['name' => 'Pokemon', 'vat_number' => 'LV-13141516', 'balance' => 100.65, 'active' => false],
    ]);
    $c->debug('  Import clients.. OK');

    // Import invoices
    $invoices = new Invoice($c->getApp()->db);
    foreach ($invoices as $m) {
        $m->delete();
    }
    $invoices->import([
        ['ref' => '123', 'doc_date' => new \DateTime('2026-08-15'), 'client_id' => 2, 'total' => 100.00],
        ['ref' => '456', 'doc_date' => new \DateTime('2026-09-10'), 'client_id' => 1, 'total' => 75.50],
        ['ref' => '789', 'doc_date' => new \DateTime('2026-09-13'), 'client_id' => 1, 'total' => 39.99],
    ]);
    $c->debug('  Import invoices.. OK');

    // Import invoice lines
    $lines = new InvoiceLine($c->getApp()->db);
    foreach ($lines as $m) {
        $m->delete();
    }
    $lines->import([
        ['invoice_id' => 1, 'item' => 'Laptop', 'price' => 100, 'qty' => 1],
        ['invoice_id' => 2, 'item' => 'Monitor', 'price' => 50, 'qty' => 1],
        ['invoice_id' => 2, 'item' => 'Keyboard', 'price' => 10, 'qty' => 2],
        ['invoice_id' => 2, 'item' => 'Mouse', 'price' => 5.50, 'qty' => 1],
        ['invoice_id' => 3, 'item' => 'Pizza', 'price' => 30.99, 'qty' => 1],
        ['invoice_id' => 3, 'item' => 'Cola', 'price' => 4.50, 'qty' => 2],
    ]);
    $c->debug('  Import invoice lines.. OK');

    // Add some custom log messages
    $clients->loadBy('name', 'Pokemon')->auditLog('Custom message for Pokemon', ['foo' => 'bar', 'something' => 'anything']); // @phpstan-ignore method.notFound
    $invoices->loadBy('ref', '456')->auditLog('Invoice #456 is paid', ['paid_by' => 'some guy']); // @phpstan-ignore method.notFound
    $c->debug('  Import custom messages.. OK');

    $c->notice('Data imported');
});

$c1->migrateModels([
    [AuditLog::class],
    [Client::class],
    [Invoice::class],
    [InvoiceLine::class],
]);

// button to execute migration
$b = Button::addTo($v, ['Run migration', 'icon' => 'check']);
$b->on('click', static function () use ($c1, $b) {
    return new JsBlock([
        $c1->jsExecute(),
        $b->js()->hide(),
    ]);
});
