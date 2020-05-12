<?php

namespace atk4\audit\tests;

use atk4\data\Model;

class TestModel extends \atk4\data\Model
{
    public $table = 'test';

    public function init():void
    {
        parent::init();

        // all field types
        $this->addField('f_string', ['type' => 'string']);
        $this->addField('f_text', ['type' => 'text']);
        $this->addField('f_boolean', ['type' => 'boolean']);
        $this->addField('f_integer', ['type' => 'integer']);
        $this->addField('f_money', ['type' => 'money']);
        $this->addField('f_float', ['type' => 'float']);
        $this->addField('f_date', ['type' => 'date']);
        $this->addField('f_datetime', ['type' => 'datetime']);
        $this->addField('f_time', ['type' => 'time']);
        $this->addField('f_array', ['type' => 'array']);
        $this->addField('f_object', ['type' => 'object']);
        $this->addField('f_enum', ['enum' => ['M','F']]);

        // custom serialization
        $this->addField('f_ser_json', ['type' => 'array', 'serialize' => 'json']);
        $this->addField('f_ser_ser', ['type' => 'array', 'serialize' => 'serialize']);

        $this->add(new \atk4\audit\Controller());
    }
}

class MyObject
{
    public $foo;
    public function __construct($foo = null)
    {
        $this->foo = $foo;
    }
}

/**
 * Tests audit compatibility with all possible field types.
 */
class FieldTypeTest extends \atk4\schema\PhpunitTestCase
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

    public function testFieldTypes()
    {
        $q = [
            'test' => [
                [
                    // when setting up database you have to give already type-casted values
                    'f_string'      => 'abc',
                    'f_text'        => 'def',
                    'f_boolean'     => 0,
                    'f_integer'     => 123,
                    'f_money'       => 123.45,
                    'f_float'       => 123.45,
                    'f_date'        => (new \DateTime())->format('Y-m-d'),
                    'f_datetime'    => (new \DateTime())->format('Y-m-d H:i:s'),
                    'f_time'        => (new \DateTime())->format('H:i:s'),
                    'f_array'       => json_encode([123,'foo'=>'bar']),
                    'f_object'      => json_encode(new MyObject()),
                    'f_enum'        => 'M',
                    'f_ser_json'    => json_encode([789,'qwe'=>'asd']),
                    'f_ser_ser'     => serialize([789,'qwe'=>'asd']),
                ],
            ],
            'audit_log' => $this->audit_db,
        ];
        $this->setDB($q);

        // load record, change all fields and save
        // this should create audit log record with all field values
        $m = new TestModel($this->db);
        $m->load(1);
        $m->set([
                    'f_string'      => 'def',
                    'f_text'        => 'abc',
                    'f_boolean'     => true,
                    'f_integer'     => 456,
                    'f_money'       => 456.78,
                    'f_float'       => 456.78,
                    'f_date'        => (new \DateTime())->sub(new \DateInterval('P1D')),
                    'f_datetime'    => (new \DateTime())->sub(new \DateInterval('P1D')),
                    'f_time'        => (new \DateTime())->sub(new \DateInterval('P1D')),
                    'f_array'       => [456,'foo'=>'qwe'],
                    'f_object'      => new MyObject('bar'),
                    'f_enum'        => 'F',
                    'f_ser_json'    => [987,'qwe'=>'zxc'],
                    'f_ser_ser'     => [987,'qwe'=>'zxc'],
                ]);
        $m->save();

        $l = $m->ref('AuditLog')->loadLast();

        // validate that all fields are mentioned in change description
        $this->assertTrue(is_int(strpos($l['descr'], 'f_string=')));
        $this->assertTrue(is_int(strpos($l['descr'], 'f_text=')));
        $this->assertTrue(is_int(strpos($l['descr'], 'f_boolean=')));
        $this->assertTrue(is_int(strpos($l['descr'], 'f_integer=')));
        $this->assertTrue(is_int(strpos($l['descr'], 'f_money=')));
        $this->assertTrue(is_int(strpos($l['descr'], 'f_float=')));
        $this->assertTrue(is_int(strpos($l['descr'], 'f_date=')));
        $this->assertTrue(is_int(strpos($l['descr'], 'f_datetime=')));
        $this->assertTrue(is_int(strpos($l['descr'], 'f_time=')));
        $this->assertTrue(is_int(strpos($l['descr'], 'f_array=')));
        $this->assertTrue(is_int(strpos($l['descr'], 'f_object=')));
        $this->assertTrue(is_int(strpos($l['descr'], 'f_enum=')));
        $this->assertTrue(is_int(strpos($l['descr'], 'f_ser_json=')));
        $this->assertTrue(is_int(strpos($l['descr'], 'f_ser_ser=')));
    }
}
