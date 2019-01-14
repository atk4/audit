<?php

// A very basic file that sets up Agile Data to be used in some demonstrations
try {
    if (file_exists('include/db.php')) {
        include 'include/db.php';
    } else {
        $db = new \atk4\data\Persistence_SQL('mysql:dbname=atk4;host=localhost', 'root', 'root');
    }
} catch (PDOException $e) {
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

        public function init()
        {
            parent::init();
            $this->addField('name', ['actual' => 'nicename', 'required' => true, 'type' => 'string']);
            $this->addField('sys_name', ['actual' => 'name', 'system' => true]);

            $this->addField('iso', ['caption' => 'ISO', 'required' => true, 'type' => 'string']);
            $this->addField('iso3', ['caption' => 'ISO3', 'required' => true, 'type' => 'string']);
            $this->addField('numcode', ['caption' => 'ISO Numeric Code', 'type' => 'number', 'required' => true]);
            $this->addField('phonecode', ['caption' => 'Phone Prefix', 'type' => 'number', 'required' => true]);

            $this->addHook('beforeSave', function ($m) {
                if (!$m['sys_name']) {
                    $m['sys_name'] = strtoupper($m['name']);
                }
            });
        }
    }
}
