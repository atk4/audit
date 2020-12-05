<?php

declare(strict_types=1);

namespace Atk4\Audit\View;

use Atk4\Ui\Form;
use Atk4\Ui\Form\Control;

/**
 * Comment form view for audit log records.
 *
 * Usage:
 *  see in History view source code
 */
class CommentForm extends Form
{
    public $ui = 'reply small form';

    /** We should not have save button in this form */
    public $buttonSave = false;

    protected function init(): void
    {
        parent::init();

        // form should be inline
        $this->layout->inline = true;

        // add field
        $field = $this->addControl('__new_comment', new Control\Line(['caption' => 'Add Comment']));

        // submit button
        $button = $field->addAction(['icon' => 'comment']);
        $button->on('click', $this->js()->form('submit'));

        $this->onSubmit(function ($f) {
            // History->model = real data model
            $f->getOwner()->model->auditLog('comment', $f->model->get('__new_comment'));

            return $f->getOwner()->jsReload();
        });
    }
}
