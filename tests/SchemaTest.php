<?php

namespace atk4\ui\tests;

use atk4\data\Model;

class SchemaTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testFirstTest()
    {

        $q = [
            'user' => [
                ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Steve', 'surname' => 'Jobs'],
            ],
            'audit_log' => [
                '_' => [
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
                ]
            ],
        ];
        $this->setDB($q);

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addField('surname');

        $m->add(new \atk4\audit\Controller());

        $m->tryLoadAny();
        $m['name'] = 'QQ';
        $m->save();


        var_dump($this->getDB()['audit_log']);


    }

}
