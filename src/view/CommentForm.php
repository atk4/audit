<?php

declare(strict_types=1);

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
     * Init layout.
     */
    public function initLayout()
    {
        parent::initLayout();

        // form should be inline
        $this->layout->inline = true;

        // add field
        $field = $this->addControl('__new_comment', new \atk4\ui\Form\Control\Line(['caption' => 'Add Comment']));

        // submit button
        $button = $field->addAction(['icon' => 'comment']);
        $button->on('click', $this->js()->form('submit'));

        $this->onSubmit(function ($f) {
            // History->model = real data model
            $f->owner->model->auditLog('comment', $f->model->get('__new_comment'));

            return $f->owner->jsReload();
        });
    }
}
