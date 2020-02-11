<?php
namespace atk4\audit\view;

/**
 * History view for audit log records.
 *
 * Usage:
 *  $m = new Model();
 *  $m->add(new \atk4\audit\Controller());
 *
 *  $v = $view->add(new \atk4\audit\view\History(['enable_comments'=>true]));
 *  $v->setModel($m);
 */
class History extends \atk4\ui\View
{
    /** @see init() */
    public $defaultTemplate = null;

    /** @var string Lister class */
    public $lister_class = Lister::class;

    /** @var Lister */
    public $lister;

    /** @var string Form class */
    public $form_class = CommentForm::class;

    /** @var CommentForm */
    public $form;

    /** @var bool Enable comment form? */
    public $enable_comments = true;

    /**
     * Initialization.
     */
    public function init()
    {
        // set up default template
        if (!$this->defaultTemplate) {
            $this->defaultTemplate = __DIR__ . '/../../template/audit-history.html';
        }

        parent::init();

        // add form
        if ($this->enable_comments) {
            $this->form = $this->add(new $this->form_class());
        }

        // add lister
        $this->lister = $this->add(new $this->lister_class());
    }

    /**
     * Set audit model.
     *
     * @param \atk4\data\Model $m Data model (not audit data model)
     *
     * @return \atk4\data\Model Data model
     */
    public function setModel(\atk4\data\Model $m)
    {
        parent::setModel($m);

        if ($this->lister) {
            $this->lister->setModel($m->ref('AuditLog'));
        }

        return $m;
    }
}
