<?php

declare(strict_types=1);

namespace Atk4\Audit\View;

use Atk4\Audit\AuditController;
use Atk4\Data\Model;
use Atk4\Ui\Js\JsExpression;
use Atk4\Ui\View;
use Atk4\Ui\View\EntityTrait;

class AuditDetail extends View
{
    use EntityTrait;

    public $defaultTemplate = __DIR__ . '/../../template/audit-detail.html';

    protected function renderView(): void
    {
        parent::renderView();
        if ($this->entity === null) {
            throw new \Exception('AuditDetail requires an entity');
        }

        /** @var Model $entity */
        $entity = $this->entity;
        /*
         * When navigating through Caused By / Causes, reload this view
         * with another audit record.
         *
         * Use the model behind the supplied entity instead of creating
         * a hardcoded AuditLog model. This keeps custom/extended audit
         * models working.
         */
        $requestedId = $this->getApp()->tryGetRequestQueryParam('audit_detail_id');
        if ($requestedId !== null && $requestedId !== '') {
            $entity = $entity->getModel()->load((int) $requestedId);
        }

        $auditId = $entity->get('id');
        $action = (string) $entity->get('action');
        $modelName = (string) $entity->get('model');
        $modelId = $entity->get('model_id');
        $startTime = $entity->get('start_time');
        $duration = $entity->get('duration');
        $description = $entity->get('descr');
        $requestDiff = $entity->get('request_diff');
        $reactiveDiff = $entity->get('reactive_diff');
        $summaryDiff = $entity->get('summary_diff');

        $userId = $entity->get('user_id');
        $sessionInfo = $entity->get('session_info');
        $requestDiff = is_array($requestDiff) ? $requestDiff : [];
        $reactiveDiff = is_array($reactiveDiff) ? $reactiveDiff : [];
        $summaryDiff = is_array($summaryDiff) ? $summaryDiff : [];
        $sessionInfo = is_array($sessionInfo) ? $sessionInfo : [];
        /*
         * hasOne reference already returns the related loaded entity.
         * If the foreign key is NULL, don't create a link.
         */
        $causedBy = $entity->get('initiator_audit_log_id') === null
            ? null
            : $entity->ref('initiator_audit_log_id');

        /*
         * Use the hasMany reference defined by AuditLog.
         */
        $causes = $entity->ref('Causes');
        $causes->setOrder('id', 'asc');
        /*
         * Plain template values.
         */
        $this->template->set([
            'audit_id' => (string) $auditId,
            'model' => $modelName,
            'model_id' => (string) $modelId,
            'start_time' => $this->formatDateTime($startTime),
            'duration' => $this->formatDuration($duration),
            'description' => $description !== null && $description !== ''
                ? (string) $description
                : '—',
        ]);
        /*
         * HTML fragments.
         *
         * These must use dangerouslySetHtml(), otherwise the generated
         * <span>/<a> markup would be escaped and displayed as text.
         */
        $this->template->dangerouslySetHtml('action', $this->renderAction($action));
        $this->template->dangerouslySetHtml('user_id', $this->formatScalar($userId));
        $this->template->dangerouslySetHtml('caused_by', $this->renderAuditLink($causedBy));
        $this->template->dangerouslySetHtml('causes', $this->renderCauses($causes));
        /*
         * All three audit diffs use the same old -> new representation.
         */
        $this->template->dangerouslySetHtml(
            'request_diff',
            $this->renderDiffCard('Request Diff', 'arrow right', $requestDiff)
        );

        $this->template->dangerouslySetHtml(
            'reactive_diff',
            $this->renderDiffCard('Reactive Diff', 'refresh', $reactiveDiff)
        );
        $this->template->dangerouslySetHtml(
            'summary_diff',
            $this->renderDiffCard('Summary Diff', 'file alternate outline', $summaryDiff)
        );

        $this->template->dangerouslySetHtml('session_info', $this->renderSessionInfo($sessionInfo));
        /*
         * Toggle raw JSON.
         */
        $this->on(
            'click',
            '.atk4-audit-raw-toggle',
            new JsExpression(
                '$(this).closest([]).toggleClass([])',
                ['.atk4-audit-json-card', 'atk4-audit-raw-visible']
            )
        );
        /*
         * Copy raw JSON.
         */
        $this->on(
            'click',
            '.atk4-audit-copy',
            new JsExpression(
                'navigator.clipboard.writeText(document.getElementById($(this).attr([])).textContent)',
                ['data-copy-target']
            )
        );
        /*
         * Navigate to another audit record.
         *
         * This directly reloads this AuditDetail view. The clicked
         * record ID is read from data-audit-id.
         */
        $this->on(
            'click',
            '.atk4-audit-record-link',
            $this->jsReload([
                'audit_detail_id' => new JsExpression('$(this).attr([])', ['data-audit-id']),
            ])
        );
    }

    /**
     * Render a link to another audit entity.
     */
    private function renderAuditLink(?Model $entity): string
    {
        if ($entity === null) {
            return '<span class="atk4-audit-muted">—</span>';
        }

        $id = $entity->get('id');

        return sprintf(
            '<a href="#" class="atk4-audit-record-link" data-audit-id="%s">#%s</a>',
            $this->escape((string) $id),
            $this->escape((string) $id)
        );
    }

    /**
     * Render child audit records from the Causes hasMany reference.
     */
    private function renderCauses(Model $causes): string
    {
        $links = [];

        foreach ($causes as $cause) {
            $links[] = $this->renderAuditLink($cause);
        }

        if ($links === []) {
            return '<span class="atk4-audit-muted">—</span>';
        }

        return implode('<span class="atk4-audit-causes-separator">, </span>', $links);
    }

    /**
     * Render audit action as a Fomantic label.
     */
    private function renderAction(string $action): string
    {
        $class = 'basic';
        $icon = 'circle';

        switch ($action) {
            case AuditController::ACTION_CREATE:
                $class = 'green';
                $icon = 'plus';
                break;

            case AuditController::ACTION_UPDATE:
                $class = 'yellow';
                $icon = 'pencil';
                break;

            case AuditController::ACTION_DELETE:
                $class = 'red';
                $icon = 'trash';
                break;

            case AuditController::ACTION_LOG:
                $class = 'blue';
                $icon = 'info';
                break;
        }

        return sprintf(
            '<span class="ui small %s label"><i class="%s icon"></i>%s</span>',
            $class,
            $icon,
            $this->escape($action)
        );
    }

    /**
     * Render any audit diff.
     *
     * Expected format:
     *
     * [
     *     'field' => [oldValue, newValue],
     * ]
     *
     * @param array<string,mixed|array<0|1,mixed>> $diff
     */
    private function renderDiffCard(string $title, string $icon, array $diff): string
    {
        $rows = '';

        foreach ($diff as $field => $change) {
            $oldValue = null;
            $newValue = null;
            if (is_array($change)) {
                if (array_key_exists(0, $change)) {
                    $oldValue = $change[0];
                }

                if (array_key_exists(1, $change)) {
                    $newValue = $change[1];
                }
            } else {
                $newValue = $change;
            }
            $rows .= sprintf(
                '<div class="atk4-audit-diff-row">
                    <div class="atk4-audit-diff-field">%s</div>
                    <div class="atk4-audit-diff-old">%s</div>
                    <div class="atk4-audit-diff-arrow">→</div>
                    <div class="atk4-audit-diff-new">%s</div>
                </div>',
                $this->escape((string) $field),
                $this->formatValue($oldValue),
                $this->formatValue($newValue)
            );
        }
        if ($rows === '') {
            $rows = '<div class="atk4-audit-empty">No changes</div>';
        }

        $jsonId = $this->name . '_' . strtolower(str_replace(' ', '_', $title));
        $prettyJson = $this->prettyJson($diff);

        return sprintf(
            '<div class="ui segment atk4-audit-json-card">
                <div class="atk4-audit-json-header">
                    <div class="atk4-audit-json-title">
                        <i class="%s icon"></i>
                        %s
                    </div>
                    <button type="button" class="ui mini basic button atk4-audit-raw-toggle">
                        <i class="code icon"></i>
                        Raw JSON
                    </button>
                </div>

                <div class="atk4-audit-json-body">
                    <div class="atk4-audit-diff-list">
                        %s
                    </div>

                    <div class="atk4-audit-raw-json">
                        <div class="atk4-audit-raw-container">
                            <button
                                type="button"
                                class="ui mini basic button atk4-audit-copy"
                                data-copy-target="%s"
                            >
                                <i class="copy icon"></i>
                                Copy
                            </button>
                            <pre id="%s">%s</pre>
                        </div>
                    </div>
                </div>
            </div>',
            $this->escape($icon),
            $this->escape($title),
            $rows,
            $this->escape($jsonId),
            $this->escape($jsonId),
            $this->escape($prettyJson)
        );
    }

    /**
     * @param array<mixed,mixed> $sessionInfo
     */
    private function renderSessionInfo(array $sessionInfo): string
    {
        if ($sessionInfo === []) {
            return '<div class="atk4-audit-empty">No session information</div>';
        }

        $id = $this->name . '_session_info';

        return sprintf(
            '<div class="atk4-audit-raw-container">
                <button
                    type="button"
                    class="ui mini basic button atk4-audit-copy"
                    data-copy-target="%s"
                >
                    <i class="copy icon"></i>
                    Copy
                </button>
                <pre id="%s" class="atk4-audit-small-json">%s</pre>
            </div>',
            $this->escape($id),
            $this->escape($id),
            $this->escape($this->prettyJson($sessionInfo))
        );
    }

    /**
     * @param mixed $value
     */
    private function formatValue($value): string
    {
        if ($value === null) {
            return '<span class="atk4-audit-null">null</span>';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value) || is_object($value)) {
            return nl2br($this->escape($this->prettyJson($value)));
        }

        $encoded = json_encode(
            $value,
            \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
        );

        return $this->escape($encoded !== false ? $encoded : 'null');
    }

    /**
     * @param mixed $value
     */
    private function formatScalar($value): string
    {
        if ($value === null || $value === '') {
            return '<span class="atk4-audit-muted">—</span>';
        }

        return $this->escape((string) $value);
    }

    /**
     * @param mixed $value
     */
    private function formatDateTime($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s.v');
        }

        return (string) $value;
    }

    /**
     * @param mixed $value
     */
    private function formatDuration($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') . ' ms';
        }

        return (string) $value;
    }

    /**
     * @param mixed $value
     */
    private function prettyJson($value): string
    {
        $json = json_encode(
            $value,
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
        );

        return $json !== false ? $json : 'null';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
