<?php

namespace atk4\audit\tests;

use atk4\audit\model\AuditLog;

class CommonTest extends \atk4\schema\PhpunitTestCase
{
    protected $audit_db = ['_' => [
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
        'revert_audit_log_id' => 1
    ]];

    public function testUndo()
    {
        $q = [
            'test'      => [
                [
                    // when setting up database you have to give already type-casted values
                    'f_string'                 => 'abc',
                    'f_text'                   => 'def',
                    'f_boolean'                => 0,
                    'f_integer'                => 123,
                    'f_money'                  => 123.45,
                    'f_float'                  => 123.45,
                    'f_date'                   => (new \DateTime())->format('Y-m-d'),
                    'f_datetime'               => (new \DateTime())->format('Y-m-d H:i:s'),
                    'f_time'                   => (new \DateTime())->format('H:i:s'),
                    'f_array'                  => json_encode([
                        123,
                        'foo' => 'bar'
                    ]),
                    'f_object'                 => serialize(new MyObject()),
                    'f_object_serialized'      => serialize(new MyObjectSerializable()),
                    'f_enum'                   => 'M',
                    'f_ser_json'               => json_encode([
                        789,
                        'qwe' => 'asd'
                    ]),
                    'f_ser_ser'                => serialize([
                        789,
                        'qwe' => 'asd'
                    ]),
                    'f_security_never_persist' => 'never persist',
                    'f_security_never_save'    => 'never save',
                    'f_security_read_only'     => 'read only',
                ],
            ],
            'audit_log' => $this->audit_db,
        ];
        $this->setDB($q);

        // load record, change all fields and save
        // this should create audit log record with all field values
        $m = new TestModel($this->db);
        $m->load(1);

        $initial_state = $m->get();

        $m->set([
            'f_string'                 => 'def',
            'f_text'                   => 'abc',
            'f_boolean'                => true,
            'f_integer'                => 456,
            'f_money'                  => 456.78,
            'f_float'                  => 456.78,
            'f_date'                   => (new \DateTime())->sub(new \DateInterval('P1D')),
            'f_datetime'               => (new \DateTime())->sub(new \DateInterval('P1D')),
            'f_time'                   => (new \DateTime())->sub(new \DateInterval('P1D')),
            'f_array'                  => [456, 'foo' => 'qwe'],
            'f_object'                 => new MyObject('bar'),
            'f_object_serialized'      => new MyObjectSerializable('foo'),
            'f_enum'                   => 'F',
            'f_ser_json'               => [987, 'qwe' => 'zxc'],
            'f_ser_ser'                => [987, 'qwe' => 'zxc'],
            'f_security_never_persist' => 'change never persist',
            'f_security_never_save'    => 'change never save',
            //'f_security_read_only' => 'change read only', trigger error on change before
        ]);
        $m->save();

        $after_save = $m->get();

        /** @var AuditLog $audit */
        $audit = $m->ref('AuditLog');
        $audit->loadLast();
        $audit->undo();

        $after_undo = $m->load(1)->get();

        $this->assertNotEquals($initial_state, $after_save);
        $this->assertEquals($initial_state, $after_undo);
    }

    public function testUndoCreate()
    {
        $q = [
            'test'      => [
                [
                    // when setting up database you have to give already type-casted values
                    'f_string'                 => 'abc',
                    'f_text'                   => 'def',
                    'f_boolean'                => 0,
                    'f_integer'                => 123,
                    'f_money'                  => 123.45,
                    'f_float'                  => 123.45,
                    'f_date'                   => (new \DateTime())->format('Y-m-d'),
                    'f_datetime'               => (new \DateTime())->format('Y-m-d H:i:s'),
                    'f_time'                   => (new \DateTime())->format('H:i:s'),
                    'f_array'                  => json_encode([
                        123,
                        'foo' => 'bar'
                    ]),
                    'f_object'                 => serialize(new MyObject()),
                    'f_object_serialized'      => serialize(new MyObjectSerializable()),
                    'f_enum'                   => 'M',
                    'f_ser_json'               => json_encode([
                        789,
                        'qwe' => 'asd'
                    ]),
                    'f_ser_ser'                => serialize([
                        789,
                        'qwe' => 'asd'
                    ]),
                    'f_security_never_persist' => 'never persist',
                    'f_security_never_save'    => 'never save',
                    'f_security_read_only'     => 'read only',
                ],
            ],
            'audit_log' => $this->audit_db,
        ];
        $this->setDB($q);

        // load record, change all fields and save
        // this should create audit log record with all field values
        $m = new TestModel($this->db);
        $m->load(1);

        /** @var AuditLog $audit */
        $audit = $m->ref('AuditLog');
        $audit->undo_create($m);

        $m->tryLoad(1);
        $this->assertFalse($m->loaded());
    }

    public function testUndoDelete()
    {
        $q = [
            'test'      => [
                [
                    // when setting up database you have to give already type-casted values
                    'f_string'                 => 'abc',
                    'f_text'                   => 'def',
                    'f_boolean'                => 0,
                    'f_integer'                => 123,
                    'f_money'                  => 123.45,
                    'f_float'                  => 123.45,
                    'f_date'                   => (new \DateTime())->format('Y-m-d'),
                    'f_datetime'               => (new \DateTime())->format('Y-m-d H:i:s'),
                    'f_time'                   => (new \DateTime())->format('H:i:s'),
                    'f_array'                  => json_encode([
                        123,
                        'foo' => 'bar'
                    ]),
                    'f_object'                 => serialize(new MyObject()),
                    'f_object_serialized'      => serialize(new MyObjectSerializable()),
                    'f_enum'                   => 'M',
                    'f_ser_json'               => json_encode([
                        789,
                        'qwe' => 'asd'
                    ]),
                    'f_ser_ser'                => serialize([
                        789,
                        'qwe' => 'asd'
                    ])
                ],
            ],
            'audit_log' => $this->audit_db,
        ];
        $this->setDB($q);

        // load record, change all fields and save
        // this should create audit log record with all field values
        $m = new TestModel($this->db);
        $m->load(1);
        $m->save();
        $m->load(1);
        $before_delete_data = $m->get();
        $m->delete();

        $m->tryLoad(1);
        $this->assertFalse($m->loaded());

        $audit = $m->ref('AuditLog')->newInstance();
        $audit->addCondition('model', TestModel::class);
        $audit->addCondition('model_id', 1);
        $audit->tryLoadAny();
        $m = new TestModel($this->db);
        $audit->undo_delete($m);

        $m = new TestModel($this->db);
        $m->tryLoad(1);

        $this->assertTrue($m->loaded());

        $this->assertEquals($before_delete_data, $m->get());
    }
}
