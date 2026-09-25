<?php

declare(strict_types=1);

namespace Atk4\Audit\Demos;

use Atk4\Ui\Layout;

class App extends \Atk4\Ui\App
{
    public $title = 'Atk4 Audit Demo App';

    /** @var Layout\Admin */
    public $layout; // @phpstan-ignore-line

    public function init(): void
    {
        $this->initLayout([Layout\Admin::class]);

        // construct menu
        $this->layout->menuLeft->addItem(['Dashboard', 'icon' => 'info'], ['index']);
        $this->layout->menuLeft->addItem(['Setup demo database', 'icon' => 'cogs'], ['admin-setup']);

        $g = $this->layout->menuLeft->addGroup(['Data']);
        $g->addItem(['Clients', 'icon' => 'table'], ['page-clients']);
        $g->addItem(['Invoices', 'icon' => 'table'], ['page-invoices']);
        $g->addItem(['AuditLog', 'icon' => 'table'], ['page-audit']);
    }
}
