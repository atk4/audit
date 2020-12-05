<?php

declare(strict_types=1);

namespace Atk4\Audit\View;

use Atk4\Data\Model;
use Atk4\Ui\View;

/**
 * History view for audit log records.
 *
 * Usage:
 *  $m = new Model();
 *  $m->add(new \Atk4\Audit\Controller());
 *
 *  $v = $view->add(new \Atk4\Audit\View\History(['enable_comments'=>true]));
 *  $v->setModel($m);
 */
class History extends View
{
    /** @see init() */
    public $defaultTemplate;

    /** @var array Lister class seed */
    public $listerClass = [Lister::class];

    /** @var Lister */
    public $lister;

    /** @var array Form class seed */
    public $formClass = [CommentForm::class];

    /** @var CommentForm */
    public $form;

    /** @var bool Enable comment form? */
    public $enable_comments = true;

    /**
     * Initialization.
     */
    protected function init(): void
    {
        // set up default template
        if (!$this->defaultTemplate) {
            $this->defaultTemplate = __DIR__ . '/../../template/audit-history.html';
        }

        parent::init();
    }

    /**
     * Set audit model.
     *
     * @param Model $m Data model (not audit data model)
     *
     * @return Model Data model
     */
    public function setModel(Model $m)
    {
        parent::setModel($m);

        // add form
        if ($this->enable_comments) {
            $this->form = $this->add($this->formClass);
        }

        // add lister
        $this->lister = $this->add($this->listerClass);
        $this->lister->setModel($m->ref('AuditLog'));

        return $m;
    }
}
