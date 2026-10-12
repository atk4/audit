<?php

declare(strict_types=1);

namespace Atk4\Audit\Tests;

use Atk4\Audit\AuditController;
use Atk4\Audit\AuditPolicy;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

class Event extends Model
{
    public $table = 'event';

    protected function init(): void
    {
        parent::init();

        /** @var Persistence\Sql $db */
        $db = $this->getPersistence();

        $this->addField('name');
        $this->addField('created_time', ['type' => 'datetime', 'default' => $db->exprNow()]);
        $this->addField('event_time', ['type' => 'datetime']);
    }
}

/**
 * Tests basic create, update and delete operations.
 */
class DataTypeTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // test models
        $this->createMigrator(new Event($this->db))->create();
    }

    public function testExpr(): void
    {
        // auditable Event model
        $events = new Event($this->db);
        $this->audit->addModel($events);

        /** @var Persistence\Sql $db */
        $db = $this->db;
        $now = $db->exprNow();

        // create
        $import_data = [
            [
                'name' => 'John',
                'event_time' => $now,
            ]
        ];
        $events->import($import_data);

        // update
        $event = $events->load(1);
        $event->set('name', 'Peter');
        $event->save([
            'name' => 'Peter',
            'event_time' => $now,
        ]);

        // delete
        $event->delete();

        // test audit log
        $data = $this->audit->auditModel->export(['id', 'model', 'model_id', 'action', 'request_diff', 'reactive_diff', 'descr']);
        // print_r($data);

        self::assertEquals(
            [
                'id' => 1,
                'model' => 'Event',
                'model_id' => 1,
                'action' => AuditController::ACTION_CREATE,
                'request_diff' => [
                    'name' => [null, 'John'],
                    'event_time' => [null, AuditController::VALUE_EXPRESSION],
                ],
                'reactive_diff' => [
                    'id' => [null, 1],
                    'created_time' => [null, AuditController::VALUE_EXPRESSION],
                ],
                'descr' => 'create #1 (John): name=John, event_time=' . AuditController::VALUE_EXPRESSION,
            ],
            $data[0]
        );
        self::assertEquals(
            [
                'id' => 2,
                'model' => 'Event',
                'model_id' => 1,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [
                    'name' => ['John', 'Peter'],
                    'event_time' => [
                        $data[1]['request_diff']['event_time'][0], // datetime value we can't predict
                        AuditController::VALUE_EXPRESSION
                    ],
                ],
                'reactive_diff' => [
                ],
                'descr' => 'update #1 (Peter): name=Peter, event_time=' . AuditController::VALUE_EXPRESSION,
            ],
            $data[1]
        );
        self::assertEquals(
            [
                'id' => 3,
                'model' => 'Event',
                'model_id' => 1,
                'action' => AuditController::ACTION_DELETE,
                'request_diff' => [],
                'reactive_diff' => [
                    'id' => [1, null],
                    'name' => ['Peter', null],
                    'created_time' => [
                        $data[2]['reactive_diff']['created_time'][0], // datetime value we can't predict
                        null
                    ],
                    'event_time' => [
                        $data[2]['reactive_diff']['event_time'][0], // datetime value we can't predict
                        null
                    ],
                ],
                'descr' => 'delete #1 (Peter)',
            ],
            $data[2]
        );
    }
}
