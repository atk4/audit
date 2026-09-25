<?php

declare(strict_types=1);

namespace Atk4\Audit\View;

use Atk4\Data\Model;
use Atk4\Ui\Grid;
use Atk4\Ui\Modal;
use Atk4\Ui\View;

class AuditGrid extends Grid
{
    #[\Override]
    public function setModel(Model $model, ?array $fields = null): void
    {
        parent::setModel($model, $fields);

        $this->addDetailsView();
    }

    /**
     * Method adds Details view in audit grid.
     */
    public function addDetailsView(): void
    {
        $self = $this;
        $this->addModalAction(
            ['icon' => 'binoculars'],
            ['title' => null],
            static function (View $p, int $id) use ($self) {
                $modal = $p->getOwner();

                if ($modal instanceof Modal) {
                    $modal->addClass('atk4-audit-modal');
                    $modal->addScrolling();
                }

                $entity = $self->model->load($id);

                $detail = AuditDetail::addTo($p);
                $detail->setEntity($entity);
            }
        );

        array_unshift($this->table->columns, array_pop($this->table->columns));
    }
}
