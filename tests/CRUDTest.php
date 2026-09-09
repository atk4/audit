<?php

declare(strict_types=1);

namespace Atk4\Audit\Tests;

use Atk4\Audit\AuditableModelTrait;
use Atk4\Audit\AuditController;
use Atk4\Data\Model;

class User extends Model
{
    use AuditableModelTrait;

    public $table = 'user';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
        $this->addField('fullname');
        $this->addField('password');

        $this->onHook(Model::HOOK_BEFORE_SAVE, function($m){
            $m->set('fullname', trim($m->get('name').' '.$m->get('surname')));
        },[],-100);
    }
}

/**
 * Tests basic create, update and delete operations.
 */
class CrudTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // test models
        $this->createMigrator(new User($this->db))->create();
    }

    public function testCRUD()
    {
        // auditable User model
        $users = new User($this->db);
        $this->audit->addModel($users);

        // create 2 records
        $import_data = [
                [
                    'id' => 1, // manually set (request_diff)
                    'name' => 'Vinny',
                    'surname' => 'Shira',
                    'fullname' => 'Vinny Shira', // manually set (request_diff)
                    'password' => 'vinny123',
                ],
                [
                    //'id' => 2, // autoincrement (reactive_diff)
                    'name' => 'Zoe',
                    'surname' => 'Shatwell',
                    //'fullname' => 'Zoe Shatwell', // will be calculated (reactive_diff)
                    'password' => 'qwerty123',
                ],
            ];
        $users->import($import_data);

        // update name of 1 record
        $user = $users->load(1); // load Vinny
        $user->set('name', 'John');
        $user->save();

        // change nothing and save
        $user->save();

        // delete user #1
        $user->delete();

        // test audit log
        $data = $this->audit->auditModel->export(['id','model','model_id','action','request_diff','reactive_diff']);
        // print_r($data);

        self::assertEquals([
            // 2 import records
            [
                'id' => 1,
                'model' => 'Atk4\\Audit\\Tests\\User',
                'model_id' => 1,
                'action' => AuditController::ACTION_CREATE,
                'request_diff' => [
                    'id' => [null, 1],
                    'name' => [null, 'Vinny'],
                    'surname' => [null, 'Shira'],
                    'fullname' => [null, 'Vinny Shira'],
                    'password' => [null, 'vinny123'],
                ],
                'reactive_diff' => [
                ],
            ],
            [
                'id' => 2,
                'model' => 'Atk4\\Audit\\Tests\\User',
                'model_id' => 2,
                'action' => AuditController::ACTION_CREATE,
                'request_diff' => [
                    'name' => [null, 'Zoe'],
                    'surname' => [null, 'Shatwell'],
                    'password' => [null, 'qwerty123'],
                ],
                'reactive_diff' => [
                    'id' => 2,
                    'fullname' => 'Zoe Shatwell',
                ],
            ],
            // update name of #1 record
            [
                'id' => 3,
                'model' => 'Atk4\\Audit\\Tests\\User',
                'model_id' => 1,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [
                    'name' => ['Vinny', 'John'],
                ],
                'reactive_diff' => [
                    'fullname' => 'John Shira',
                ],
            ],
            // update nothing - such records are created and then removed from audit as they are almost useless, but we can't know that in advance
            /*
            [
                'id' => 4,
                'model' => 'Atk4\\Audit\\Tests\\User',
                'model_id' => 1,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [
                ],
                'reactive_diff' => [
                ],
            ],
            */
            // delete user #1
            [
                'id' => 5,
                'model' => 'Atk4\\Audit\\Tests\\User',
                'model_id' => 1,
                'action' => AuditController::ACTION_DELETE,
                'request_diff' => [
                    'id' => [1, null],
                    'name' => ['John', null],
                    'surname' => ['Shira', null],
                    'fullname' => ['John Shira', null],
                    'password' => ['vinny123', null],
                ],
                'reactive_diff' => [
                ],
            ],
        ], $data);
    }
}
