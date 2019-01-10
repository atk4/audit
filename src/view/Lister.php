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
 *  $l->setModel($m->ref('AuditLog'));
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
     * Render individual row.
     *
     * Adds rendering of field value changes section.
     */
    public function renderRow()
    {
        if ($this->t_row->hasTag('changes') && $diff = $this->model['request_diff']) {
            $t_change = clone $this->t_row_change;
            $html = '';
            foreach ($diff as $field => list($old_value, $new_value)) {
                $t_change->trySet('field', $field);
                $t_change->trySet('old_value', $old_value);
                $t_change->trySet('new_value', $new_value);
                $html .= $t_change->render();
            }
            $this->t_row->setHTML('changes', $html);
        } else {
            $this->t_row->del('changes');
        }

        return parent::renderRow();
    }
}
