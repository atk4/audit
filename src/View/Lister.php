<?php

declare(strict_types=1);

namespace Atk4\Audit\View;

use Atk4\Data\Model;
use Atk4\Ui\Template;

/**
 * Lister view for audit log records.
 *
 * Usage:
 *  $m = new Model();
 *  $m->add(new \Atk4\Audit\Controller());
 *
 *  $l = $view->add(new \Atk4\Audit\View\Lister());
 *  $l->setModel($m->ref('AuditLog'));
 */
class Lister extends \Atk4\Ui\Lister
{
    public $ui = 'small feed';

    /** @see init() */
    public $defaultTemplate;
    /** @var Template Template chunk for one changed field */
    public $tRowChange;

    /** @var Model */
    protected $linkedModel;

    /**
     * Initialization.
     */
    protected function init(): void
    {
        // set up default template
        if (!$this->defaultTemplate) {
            $this->defaultTemplate = __DIR__ . '/../../template/audit-lister.html';
        }

        parent::init();
    }
    /**
     * From the current template will extract {change} into $this->tRowChange.
     */
    protected function initChunks(): void
    {
        if ($this->template->hasTag('change')) {
            $this->tRowChange = $this->template->cloneRegion('change');
            $this->template->del('change');
        }

        parent::initChunks();
    }
    /**
     * Render individual row.
     *
     * Adds rendering of field value changes section.
     */
    public function renderRow(): void
    {
        // Let UI Lister prepare the standard row values and links first.
        $this->renderTRow();

        $diff = $this->currentRow->get('request_diff') ?? [];
        if ($this->tRow->hasTag('changes') && count($diff) > 0 && $this->tRowChange !== null) {
            $t_change = clone $this->tRowChange;
            $html = '';
            foreach ($diff as $field => [$old_value, $new_value]) {
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

                if ($this->linkedModel->getField($field)->type === 'expression') {
                    continue;
                }
                $t_change->trySet('field', $this->linkedModel->getField($field)->getCaption());
                $t_change->trySet('old_value', $this->normalizeValue($field, $old_value), false);
                $t_change->trySet('new_value', $this->normalizeValue($field, $new_value), false);
                $html .= $t_change->render();
            }
            $this->tRow->setHTML('changes', $html);
        } else {
            $this->tRow->del('changes');
        }

        $html = $this->tRow->renderToHtml();
        if ($this->template->hasTag('rows')) {
            $this->template->dangerouslyAppendHtml('rows', $html);
        } else {
            $this->template->dangerouslyAppendHtml('_top', $html);
        }
    }

    public function isEmptyOrNull($val): bool
    {
        return empty(!is_string($val) ? $val : trim($val));
    }

    /**
     * @param string $field
     * @param mixed  $value
     *
     * @return mixed
     */
    public function normalizeValue($field, $value)
    {
        if (empty($value)) {
            return ' --- ';
        }
        if ($this->linkedModel->hasReference($field)) {
            $refModel = clone $this->linkedModel->ref($field);
            $refModel->tryLoad((int) $value);

            return $refModel->getTitle();
        }

        try {
            if (is_string($value) && in_array($this->linkedModel->getField($field)->type, ['date', 'datetime', 'time'], true)) {
                $value = @unserialize($value, ['allowed_classes' => true]);
                if ($value instanceof \DateTimeInterface) {
                    return $value->format($this->getApp()->uiPersistence->datetime_format);
                }
            }
        } catch (\Throwable $e) {
        }

        return $value;
    }

    public function setModel(Model $m): void
    {
        parent::setModel($m);

        $class = $this->model->get('model');
        if (!is_string($class) || !is_a($class, Model::class, true)) {
            throw new \InvalidArgumentException('Audit log contains an invalid model class');
        }

        $this->linkedModel = new $class($this->model->getPersistence());
        // this conditions can be added here not in AuditLog Model
        // i hope, here are harmless - to hide empty rows
        //        $this->model->addCondition([
        //            ['descr', 'not', null],
        //            ['request_diff', 'not', null],
        //            ['reactive_diff', 'not', null],
        //        ]);
    }
}
