<?php

namespace atk4\audit\view;

/**
 * Lister view for audit log records.
 *
 * Usage:
 *  $m = new ModelWithAudit();
 *  $l = $view->add(new \atk4\audit\view\Lister());
 *  $l->setModel($m); // will use $m->audit_log_controller->getAuditModel()) instead
 */
class Lister extends \atk4\ui\Lister
{
    public $defaultTemplate = null;

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

        return parent::setModel($m->audit_log_controller->getAuditModel());
    }
}
