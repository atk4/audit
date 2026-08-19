<?php

declare(strict_types=1);

namespace Atk4\Audit\Tests;

use Atk4\Audit\Model\AuditLog;
use Atk4\Data\Schema\TestCase;

class CommonTest extends TestCase
{
    protected $audit_db = [[
        'initiator_audit_log_id' => 1,
        'ts' => '',
        'model' => '',
        'model_id' => 1,
        'action' => '',
        'user_info' => '',
        'time_taken' => 1.1,
        'request_diff' => '',
        'reactive_diff' => '',
        'descr' => '',
        'is_reverted' => '',
        'revert_audit_log_id' => 1,
    ]];

    public function testUndo()
    {
        $q = [
            'test' => [
                [
                    // when setting up database you have to give already type-casted values
                    'f_string' => 'abc',
                    'f_text' => 'def',
                    'f_boolean' => 0,
                    'f_integer' => 123,
                    'f_money' => 123.45,
                    'f_float' => 123.45,
                    'f_date' => (new \DateTime())->format('Y-m-d'),
                    'f_datetime' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'f_time' => (new \DateTime())->format('H:i:s'),
                    'f_array' => json_encode([
                        123,
                        'foo' => 'bar',
                    ]),
                    'f_object' => serialize(new MyObject()),
                    'f_object_serialized' => serialize(new MyObjectSerializable()),
                    'f_enum' => 'M',
                    'f_ser_json' => json_encode([
                        789,
                        'qwe' => 'asd',
                    ]),
                    'f_ser_ser' => serialize([
                        789,
                        'qwe' => 'asd',
                    ]),
                    'f_security_never_persist' => 'never persist',
                    'f_security_never_save' => 'never save',
                    'f_security_read_only' => 'read only',
                ],
            ],
            'audit_log' => $this->audit_db,
        ];
        $this->setDb($q);

        // load record, change all fields and save
        // this should create audit log record with all field values
        $m = new TestModel($this->db);
        $entity = $m->load(1);

        $initial_state = $entity->get();

        $entity->setMulti([
            'f_string' => 'def',
            'f_text' => 'abc',
            'f_boolean' => true,
            'f_integer' => 456,
            'f_money' => 456.78,
            'f_float' => 456.78,
            'f_date' => (new \DateTime())->sub(new \DateInterval('P1D')),
            'f_datetime' => (new \DateTime())->sub(new \DateInterval('P1D')),
            'f_time' => (new \DateTime())->sub(new \DateInterval('P1D')),
            'f_array' => [456, 'foo' => 'qwe'],
            'f_object' => new MyObject('bar'),
            'f_object_serialized' => new MyObjectSerializable('foo'),
            'f_enum' => 'F',
            'f_ser_json' => [987, 'qwe' => 'zxc'],
            'f_ser_ser' => [987, 'qwe' => 'zxc'],
            'f_security_never_persist' => 'change never persist',
            'f_security_never_save' => 'change never save',
            // 'f_security_read_only' => 'change read only', trigger error on change before
        ]);
        $entity->save();

        $after_save = $entity->get();

        /** @var AuditLog $audit */
        $audit = $entity->ref('AuditLog');
        $audit->loadLast();
        $audit->undo();

        $after_undo = $m->load(1)->get();

        self::assertNotSame($initial_state, $after_save);
        // need to serialize because of DateTime objects
        self::assertSame(serialize($initial_state), serialize($after_undo));
    }

    public function testUndoCreate()
    {
        $q = [
            'test' => [
                [
                    // when setting up database you have to give already type-casted values
                    'f_string' => 'abc',
                    'f_text' => 'def',
                    'f_boolean' => 0,
                    'f_integer' => 123,
                    'f_money' => 123.45,
                    'f_float' => 123.45,
                    'f_date' => (new \DateTime())->format('Y-m-d'),
                    'f_datetime' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'f_time' => (new \DateTime())->format('H:i:s'),
                    'f_array' => json_encode([
                        123,
                        'foo' => 'bar',
                    ]),
                    'f_object' => serialize(new MyObject()),
                    'f_object_serialized' => serialize(new MyObjectSerializable()),
                    'f_enum' => 'M',
                    'f_ser_json' => json_encode([
                        789,
                        'qwe' => 'asd',
                    ]),
                    'f_ser_ser' => serialize([
                        789,
                        'qwe' => 'asd',
                    ]),
                    'f_security_never_persist' => 'never persist',
                    'f_security_never_save' => 'never save',
                    'f_security_read_only' => 'read only',
                ],
            ],
            'audit_log' => $this->audit_db,
        ];
        $this->setDb($q);

        // load record, change all fields and save
        // this should create audit log record with all field values
        $m = new TestModel($this->db);
        $entity = $m->load(1);

        /** @var AuditLog $audit */
        $audit = $entity->ref('AuditLog');
        $audit->undo_create($entity);

        $entity = $m->tryLoad(1);
        self::assertFalse($entity->loaded());
    }

    public function testUndoDelete()
    {
        $q = [
            'test' => [
                [
                    // when setting up database you have to give already type-casted values
                    'f_string' => 'abc',
                    'f_text' => 'def',
                    'f_boolean' => 0,
                    'f_integer' => 123,
                    'f_money' => 123.45,
                    'f_float' => 123.45,
                    'f_date' => (new \DateTime())->format('Y-m-d'),
                    'f_datetime' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'f_time' => (new \DateTime())->format('H:i:s'),
                    'f_array' => json_encode([
                        123,
                        'foo' => 'bar',
                    ]),
                    'f_object' => serialize(new MyObject()),
                    'f_object_serialized' => serialize(new MyObjectSerializable()),
                    'f_enum' => 'M',
                    'f_ser_json' => json_encode([
                        789,
                        'qwe' => 'asd',
                    ]),
                    'f_ser_ser' => serialize([
                        789,
                        'qwe' => 'asd',
                    ]),
                ],
            ],
            'audit_log' => $this->audit_db,
        ];
        $this->setDb($q);

        // load record, change all fields and save
        // this should create audit log record with all field values
        $m = new TestModel($this->db);

        $e1 = (clone $m)->load(1);
        $e1->save();

        $e2 = (clone $m)->load(1);
        $before_delete_data = $e2->get();
        $e2->delete();

        $e3 = (clone $m)->tryLoad(1);
        self::assertFalse($e3->loaded());

        $audit = $m->ref('AuditLog')->newInstance();
        $audit->addCondition('model', TestModel::class);
        $audit->addCondition('model_id', 1);
        $audit->tryLoadAny();

        $m2 = new TestModel($this->db);
        $audit->undo_delete($m2);

        $m3 = new TestModel($this->db);
        $e4 = $m5->tryLoad(1);
        self::assertTrue($e4->loaded());

        // need to serialize because of DateTime objects
        self::assertSame(json_encode($before_delete_data), json_encode($e4->get()));
    }
}
