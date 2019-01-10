<?php

namespace atk4\audit\view;

/**
 * Comment form view for audit log records.
 *
 * Usage:
 *  see in History view source code
 */
class CommentForm extends \atk4\ui\Form
{
    public $ui = 'reply small form';

    /** We should not have save button in this form */
    public $buttonSave = false;

    /**
     * Init layout
     */
    public function initLayout()
    {
        parent::initLayout();

        // form should be inline
        $this->layout->inline = true;

        // destroy save button
        // @todo remove these lines after https://github.com/atk4/ui/pull/631 merge
        $this->buttonSave->destroy();
        $this->buttonSave = null;

        // add field
        $field = $this->addField('__new_comment', new \atk4\ui\FormField\Line(['caption'=>'Add Comment']));

        // submit button
        $button = $field->addAction(['icon'=>'comment']);
        $button->on('click', $this->js()->form('submit'));

        $this->onSubmit(function($f) {


            // WHY OWNER MODEL IS NOT LOADED HERE  ?!??!?!?!
            // IT'S LOADED WHEN WE CALL SETMODEL(), BUT AT THIS POINT IT'S NO MORE LOADED.
            // WHY ???
            // IT SHOULD BE LOADED BECAUSE ONLY THEN AUDIT CONTROLLER WILL CORRECTLY LINK
            // NEW AUDIT RECORD WITH THIS MODEL AND MODEL_ID.
            $f->owner->model->auditLog('comment', $f->model->get('__new_comment'));

            return $f->owner->jsReload();
        });
    }

    /**
     * Just store data model in forms properties for using it in submit.
     */
    public function setModel($m_audit)
    {
        return $m_audit;
    }
}
