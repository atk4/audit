<?php

declare(strict_types=1);

namespace atk4\audit\view;

use atk4\data\Field_SQL_Expression;
use atk4\data\Model;
use atk4\ui\Exception;
use atk4\ui\Template;
use Throwable;

/**
 * Lister view for audit log records.
 *
 * Usage:
 *  $m = new Model();
 *  $m->add(new \atk4\audit\Controller());
 *
 *  $l = $view->add(new \atk4\audit\view\Lister());
 *  $l->setModel($m->ref('AuditLog'));
 */
class Lister extends \atk4\ui\Lister
{
    public $ui = 'small feed';

    /** @see init() */
    public $defaultTemplate = null;

    /** @var Template Template chunk for one changed field */
    public $t_row_change;

    /** @var Model */
    private $linkedModel;

    /**
     * Initialization.
     */
    public function init(): void
    {
        // set up default template
        if (!$this->defaultTemplate) {
            $this->defaultTemplate = __DIR__ . '/../../template/audit-lister.html';
        }

        parent::init();
    }

    /**
     * From the current template will extract {change} into $this->t_row_change.
     *
     * @throws Exception
     */
    public function initChunks()
    {
        if ($this->template->hasTag('change')) {
            $this->t_row_change = $this->template->cloneRegion('change');
            $this->template->del('changes');
        }

        return parent::initChunks();
    }

    /**
     * Render individual row.
     *
     * Adds rendering of field value changes section.
     *
     * @throws \atk4\core\Exception
     */
    public function renderRow()
    {
        if ($this->model->hasRef('updated_by_user_id')) {
            $this->t_row->trySet('user', $this->model->ref('updated_by_user_id')->getTitle());
        }

        $diff = $this->model->get('request_diff') ?? [];

        if ($this->t_row->hasTag('changes') && count($diff) > 0) {
            $t_change = clone $this->t_row_change;
            $html     = '';
            foreach ($diff as $field => list($old_value, $new_value)) {
                if ($field === 'id') {
                    continue;
                }

                // if field is no more in the model schema
                if (!$this->linkedModel->hasField($field)) {
                    continue;
                }

                if ($this->isEmptyOrNull($old_value) && $this->isEmptyOrNull($new_value)) {
                    continue;
                }

                if ($this->linkedModel->getField($field) instanceof Field_SQL_Expression) {
                    continue;
                }

                $t_change->trySet('field', $this->linkedModel->getField($field)->getCaption());

                $t_change->trySet('old_value', $this->normalizeValue($field, $old_value), false);
                $t_change->trySet('new_value', $this->normalizeValue($field, $new_value), false);
                $html .= $t_change->render();
            }
            $this->t_row->setHTML('changes', $html);
        } else {
            $this->t_row->del('changes');
        }

        return parent::renderRow();
    }

    public function isEmptyOrNull($val)
    {
        return empty(!is_string($val) ? $val : trim($val));
    }

    /**
     * @param $field
     * @param $value
     *
     * @throws \atk4\core\Exception
     * @throws \atk4\data\Exception
     *
     * @return mixed
     */
    public function normalizeValue($field, $value)
    {
        if (empty($value)) {
            return ' --- ';
        }

        if ($this->linkedModel->hasRef($field)) {
            $refModel = clone $this->linkedModel->refModel($field);
            $refModel->tryLoad((int)$value);
            return $refModel->getTitle();
        }

        try {
            if (isset($value['date'])) {
                $value = new \DateTime($value['date']);
                return $value->format($this->app->ui_persistence->datetime_format);
            }
        } catch (Throwable $e) {
        }

        return $value;
    }

    public function setModel(Model $m)
    {
        parent::setModel($m);

        $class             = $this->model->get('model');
        $this->linkedModel = new $class($this->app->db);

        // this conditions can be added here not in AuditLog Model
        // i hope, here are harmless - to hide empty rows
        /*        $this->model->addCondition([
                    ['descr', 'not', null],
                    ['request_diff', 'not', null],
                    ['reactive_diff', 'not', null],
                ]);
        */
        return $this->model;
    }
}
