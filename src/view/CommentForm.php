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
     * Initialization.
     */
    public function init()
    {
        parent::init();

        // form should be inline
        $this->layout->inline = true;

        // destroy save button
        // @todo remove these lines after https://github.com/atk4/ui/pull/631 merge
        $this->buttonSave->destroy();
        $this->buttonSave = null;

        // add field
        $field = $this->addField('new_comment', new \atk4\ui\FormField\Line(['caption'=>'Add Comment']));

        // submit button
        $button = $field->addAction(['icon'=>'comment']);
        $button->on('click', $this->js()->submit());

        $this->onSubmit(function($f) {
            $m = $f->owner->model;
            $c = $m->audit_log_controller;
            $c->customLog($m, 'comment', $f->model->get('new_comment'));

            return $f->owner->jsReload();
        });
    }
}
