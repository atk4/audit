<?php

declare(strict_types=1);

namespace Atk4\Audit\View\Table\Column;

use Atk4\Audit\AuditController;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Ui\Table;

/**
 * Implements Column helper for grid.
 */
class Action extends Table\Column
{
    /** @var array<AuditController::ACTION_*, string> Describe color of each status tag. */
    public array $actions = [
        AuditController::ACTION_CREATE => 'green',
        AuditController::ACTION_UPDATE => 'yellow',
        AuditController::ACTION_DELETE => 'red',
        AuditController::ACTION_LOG => 'gray',
    ];

    /**
     * Pass argument with possible actions like this:.
     *
     *  ['action' => 'color', ...]
     *
     * @param array<AuditController::ACTION_*, string> $actions
     */
    public function __construct(array $actions = [])
    {
        parent::__construct();

        if ($actions !== []) {
            $this->actions = $actions;
        }
    }

    #[\Override]
    public function getHtmlTags(Model $row, ?Field $field): array
    {
        $action = $field->get($row);
        $color = $this->actions[$action] ?? 'gray';

        return [
            $field->shortName => $this->getApp()->getTag('span', ['class' => 'ui label ' . $color], $action),
        ];
    }
}
