<?php

// A very basic file that sets up Agile Data to be used in some demonstrations
use atk4\audit\model\AuditLog;

try {
    if (file_exists('include/db.php')) {
        include 'include/db.php';
    } else {
        $db = new \atk4\data\Persistence\SQL('mysql:dbname=atk4;host=localhost', 'root', 'root');
    }
} catch (\PDOException $e) {
    throw new \atk4\ui\Exception([
        'This demo requires access to the database. See "demos/include/database.php"',
    ], null, $e);
}

$app->db = $db;


// Define some data models
if (!class_exists('Country')) {
    class Country extends \atk4\data\Model
    {
        public $table = 'country';

        public function init(): void
        {
            parent::init();

            $this->addField('name', ['actual' => 'nicename', 'required' => true, 'type' => 'string']);
            $this->addField('sys_name', ['actual' => 'name', 'system' => true]);

            $this->addField('iso', ['caption' => 'ISO', 'required' => true, 'type' => 'string']);
            $this->addField('iso3', ['caption' => 'ISO3', 'required' => true, 'type' => 'string']);
            $this->addField('numcode', ['caption' => 'ISO Numeric Code', 'type' => 'number', 'required' => true]);
            $this->addField('phonecode', ['caption' => 'Phone Prefix', 'type' => 'number', 'required' => true]);

            $this->onHook(\atk4\data\Model::HOOK_BEFORE_SAVE, function ($m) {
                if (!$m->get('sys_name')) {
                    $m->set('sys_name', strtoupper($m->get('name')));
                }
            });

            $this->addAction('undo', [
                'fields' => false,
                'scope' => \atk4\data\UserAction\Generic::SINGLE_RECORD,
                'callback' => 'undo',
                'ui' => [
                    'icon' => 'undo',
                    'button' => [null, 'icon' => 'undo'],
                    'execButton' => [\atk4\ui\Button::class, 'undo', 'blue']
                ],
            ]);
        }

        public function undo()
        {

            /** @var AuditLog $audit */
            $audit = $this->ref('AuditLog');
            $audit->loadLast();
            $audit->undo();
        }
    }
}
