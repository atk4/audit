<?php

declare(strict_types=1);

namespace Atk4\Audit\Tests;

use Atk4\Audit\AuditController;
use Atk4\Audit\AuditPolicy;
use Atk4\Data\Model;

class User extends Model
{
    public $table = 'user';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
        $this->addField('fullname');
        $this->addField('password');

        $this->onHook(Model::HOOK_BEFORE_SAVE, static function ($m) {
            $m->set('fullname', trim($m->get('name') . ' ' . $m->get('surname')));
        }, [], -100);
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
                // 'id' => 2, // autoincrement (reactive_diff)
                'name' => 'Zoe',
                'surname' => 'Shatwell',
                // 'fullname' => 'Zoe Shatwell', // will be calculated (reactive_diff)
                'password' => 'qwerty123',
            ],
            [
                'name' => 'Peter',
                'surname' => 'Pen',
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

        // update user #3
        $users->load(3)->save(['surname' => 'Pencil']);

        // test audit log
        $data = $this->audit->auditModel->export(['id', 'model', 'model_id', 'action', 'request_diff', 'reactive_diff']);
        // print_r($data);

        self::assertSame([
            // 3 import records
            [
                'id' => 1,
                'model' => 'Atk4\Audit\Tests\User',
                'model_id' => 1,
                'action' => AuditController::ACTION_CREATE,
                'request_diff' => [
                    'id' => [null, 1],
                    'name' => [null, 'Vinny'],
                    'surname' => [null, 'Shira'],
                    'fullname' => [null, 'Vinny Shira'],
                    'password' => [null, 'vinny123'],
                ],
                'reactive_diff' => [],
            ],
            [
                'id' => 2,
                'model' => 'Atk4\Audit\Tests\User',
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
            [
                'id' => 3,
                'model' => 'Atk4\Audit\Tests\User',
                'model_id' => 3,
                'action' => AuditController::ACTION_CREATE,
                'request_diff' => [
                    'name' => [null, 'Peter'],
                    'surname' => [null, 'Pen'],
                ],
                'reactive_diff' => [
                    'id' => 3,
                    'fullname' => 'Peter Pen',
                    'password' => null,
                ],
            ],
            // update name of #1 record
            [
                'id' => 4,
                'model' => 'Atk4\Audit\Tests\User',
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
                'id' => 5,
                'model' => 'Atk4\Audit\Tests\User',
                'model_id' => 1,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [],
                'reactive_diff' => [],
            ],
            */
            // delete user #1
            [
                'id' => 6,
                'model' => 'Atk4\Audit\Tests\User',
                'model_id' => 1,
                'action' => AuditController::ACTION_DELETE,
                'request_diff' => [
                    'id' => [1, null],
                    'name' => ['John', null],
                    'surname' => ['Shira', null],
                    'fullname' => ['John Shira', null],
                    'password' => ['vinny123', null],
                ],
                'reactive_diff' => [],
            ],
            // update name of #1 record
            [
                'id' => 7,
                'model' => 'Atk4\Audit\Tests\User',
                'model_id' => 3,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [
                    'surname' => ['Pen', 'Pencil'],
                ],
                'reactive_diff' => [
                    'fullname' => 'Peter Pencil',
                ],
            ],
        ], $data);

        // test reference traversal
        $users = new User($this->db);
        $this->audit->addModel($users);

        // all audit records except user #1 records because such user is already deleted
        // audit records are still in database, but can't be accessed by traversing from user model
        self::assertSame([
            2, 3, 7,
        ], array_keys($users->ref('AuditLog')->export(['id'], 'id')));

        // only user #2 records
        $user = $users->load(2);
        self::assertSame([
            2,
        ], array_keys($user->ref('AuditLog')->export(['id'], 'id')));

        // only user #3 records
        $user = $users->load(3);
        self::assertSame([
            3, 7,
        ], array_keys($user->ref('AuditLog')->export(['id'], 'id')));
    }

    public function testPolicy()
    {
        // auditable User model
        $users = new User($this->db);
        $policy = (new AuditPolicy())
            ->ignoreField('id')
            ->ignoreField('fullname')
            ->redactField('password')
        ;
        $this->audit->addModel($users, $policy);

        // create 2 records
        $import_data = [
            [
                'name' => 'John',
                'surname' => 'Doe',
                'password' => 'john123',
            ],
            [
                'name' => 'Peter',
                'surname' => 'Pen',
                'fullname' => 'Peter Pen',
            ],
        ];
        $users->import($import_data);

        // change password of user #1
        $users->load(1)->save(['password' => 'newpass']);

        // test audit log
        $data = $this->audit->auditModel->export(['id', 'action', 'request_diff', 'reactive_diff']);
        // print_r($data);

        self::assertSame([
            // 2 import records
            [
                'id' => 1,
                'action' => AuditController::ACTION_CREATE,
                'request_diff' => [
                    'name' => [null, 'John'],
                    'surname' => [null, 'Doe'],
                    'password' => [null, AuditController::VALUE_REDACTED],
                ],
                'reactive_diff' => [
                    // 'id' => 1, // ignored
                    // 'fullname' => 'John Doe', // ignored
                    // 'password' => 'john123', // redacted and not changed
                ],
            ],
            [
                'id' => 2,
                'action' => AuditController::ACTION_CREATE,
                'request_diff' => [
                    'name' => [null, 'Peter'],
                    'surname' => [null, 'Pen'],
                    // 'fullname' => [null, 'Peter Pen'], // ignored
                ],
                'reactive_diff' => [
                    // 'id' => 2, // ignored
                    // 'fullname' => 'Peter Pen', // ignored
                    // 'password' => null, // redacted fields can't be reactive
                ],
            ],
            [
                'id' => 3,
                'action' => AuditController::ACTION_UPDATE,
                'request_diff' => [
                    'password' => [AuditController::VALUE_REDACTED, AuditController::VALUE_REDACTED],
                ],
                'reactive_diff' => [],
            ],
        ], $data);
    }
}
