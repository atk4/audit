<?php

namespace atk4\audit\view;

/**
 * Lister view for audit log records.
 *
 * Usage:
 *  $m = new Model();
 *  $m->add(new \atk4\audit\Controller());
 *
 *  $l = $view->add(new \atk4\audit\view\Lister());
 *  $l->setModel($m); // IMPORTANT - here you set your model not audit model. It will be used automatically.
 */
class Lister extends \atk4\ui\Lister
{
    public $ui = 'small feed';

    /** @see init() */
    public $defaultTemplate = null;

    /** @var Template Template chunk for one changed field */
    public $t_row_change;

    /**
     * Initialization.
     */
    public function init()
    {
        // set up default template
        if (!$this->defaultTemplate) {
            $this->defaultTemplate = __DIR__ . '/../../template/audit-lister.html';
        }

        parent::init();
    }

    /**
     * From the current template will extract {change} into $this->t_row_change.
     */
    public function initChunks()
    {
        if ($this->template->hasTag('change')) {
            $this->t_row_change = $this->template->cloneRegion('change');
            $this->template->del('changes');
        }

        return parent:: initChunks();
    }

    /**
     * Set audit model for Lister view.
     *
     * @param \atk4\data\Model $m
     *
     * @return \atk4\data\Model Returns jailed audit model
     */
    public function setModel(\atk4\data\Model $m)
    {
        if (!isset($m->audit_log_controller)) {
            throw new \atk4\core\Exception(['Audit is not enabled for this model', 'model' => $m]);
        }

        $m = $m->audit_log_controller->getAuditModel();

        // change title field - code smells :(
        $m->title_field = 'action';

        return parent::setModel($m);
    }

    /**
     * Render individual row.
     *
     * Adds rendering of field value changes section.
     */
    public function renderRow()
    {
        if ($this->t_row->hasTag('changes')) {
            $t_change = clone $this->t_row_change;
            $html = '';
            foreach ($this->model['request_diff'] as $field => list($old_value, $new_value)) {
                $t_change->trySet('field', $field);
                $t_change->trySet('old_value', $old_value);
                $t_change->trySet('new_value', $new_value);
                $html .= $t_change->render();
            }
            $this->t_row->setHTML('changes', $html);
        }

        return parent::renderRow();
    }
}
