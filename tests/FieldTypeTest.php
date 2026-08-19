<?php

declare(strict_types=1);

namespace Atk4\Audit\Tests;

use Atk4\Audit\AuditableModelTrait;
use Atk4\Audit\Controller;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class TestModel extends Model
{
    use AuditableModelTrait;

    public $table = 'test';

    public $title_field = 'f_string';

    protected function init(): void
    {
        parent::init();

        // all field types
        $this->getIdField()->type = 'integer';
        $this->addField('f_string', ['type' => 'string']);
        $this->addField('f_text', ['type' => 'text']);
        $this->addField('f_boolean', ['type' => 'boolean']);
        $this->addField('f_integer', ['type' => 'integer']);
        $this->addField('f_money', ['type' => 'atk4_money']);
        $this->addField('f_float', ['type' => 'float']);
        $this->addField('f_date', ['type' => 'date']);
        $this->addField('f_datetime', ['type' => 'datetime']);
        $this->addField('f_time', ['type' => 'time']);
        $this->addField('f_array', ['type' => 'array']);
        $this->addField('f_object', ['type' => 'object', 'serialize' => 'serialize']);
        $this->addField('f_object_serialized', ['type' => 'object', 'serialize' => 'serialize']);
        $this->addField('f_enum', ['enum' => ['M', 'F']]);

        // custom serialization
        $this->addField('f_ser_json', ['type' => 'array', 'serialize' => 'json']);
        $this->addField('f_ser_ser', ['type' => 'array', 'serialize' => 'serialize']);

        // security test - never show in changes
        $this->addField('f_security_never_persist', ['never_persist' => true]);
        $this->addField('f_security_never_save', ['never_save' => true]);
        $this->addField('f_security_read_only', ['read_only' => true]);

        // check expression not stored
        $this->addExpression('f_expression', ['[f_float]*[f_money]', 'type' => 'atk4_money']);

        $this->add(new Controller());
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

class MyObjectSerializable
{
    public $foo;

    public function __construct($foo = null)
    {
        $this->foo = $foo;
    }

    public function __toString()
    {
        return 'foo is ' . $this->foo;
    }
}

/**
 * Tests audit compatibility with all possible field types.
 */
class FieldTypeTest extends TestCase
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

    public function testFieldTypes()
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
                    'f_array' => json_encode([123, 'foo' => 'bar']),
                    'f_object' => serialize(new MyObject()),
                    'f_object_serialized' => serialize(new MyObjectSerializable()),
                    'f_enum' => 'M',
                    'f_ser_json' => json_encode([789, 'qwe' => 'asd']),
                    'f_ser_ser' => serialize([789, 'qwe' => 'asd']),
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

        $l = $entity->ref('AuditLog')->loadLast();

        // validate that all fields are mentioned in change description
        self::assertTrue(is_int(strpos($l->get('descr'), 'f_string=')));
        self::assertTrue(is_int(strpos($l->get('descr'), 'f_text=')));
        self::assertTrue(is_int(strpos($l->get('descr'), 'f_boolean=')));
        self::assertTrue(is_int(strpos($l->get('descr'), 'f_integer=')));
        self::assertTrue(is_int(strpos($l->get('descr'), 'f_money=')));
        self::assertTrue(is_int(strpos($l->get('descr'), 'f_float=')));

        self::assertTrue(is_int(strpos($l->get('descr'), 'f_date=' . $entity->get('f_date')->format('Y-m-d'))));

        self::assertTrue(is_int(strpos($l->get('descr'), 'f_datetime=' . $entity->get('f_datetime')->format('Y-m-d H:i:s'))));

        self::assertTrue(is_int(strpos($l->get('descr'), 'f_time=' . $entity->get('f_time')->format('H:i:s'))));
        self::assertTrue(is_int(strpos($l->get('descr'), 'f_array=')));
        self::assertTrue(is_int(strpos($l->get('descr'), 'f_object=')));
        self::assertTrue(is_int(strpos($l->get('descr'), 'f_object_serialized=foo is foo')));
        self::assertTrue(is_int(strpos($l->get('descr'), 'f_enum=')));
        self::assertTrue(is_int(strpos($l->get('descr'), 'f_ser_json=')));
        self::assertTrue(is_int(strpos($l->get('descr'), 'f_ser_ser=')));

        self::assertFalse(strpos($l->get('descr'), 'f_security_never_persist='));
        self::assertFalse(strpos($l->get('descr'), 'f_security_never_save='));
        self::assertFalse(strpos($l->get('descr'), 'f_security_read_only='));

        self::assertSame($entity->get('f_expression'), round($entity->get('f_float') * $entity->get('f_money'), 4)); // need to cast and round because atk4_money type does that
        self::assertFalse(strpos($l->get('descr'), 'f_expression='));
    }
}
