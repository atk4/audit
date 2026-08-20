<?php

declare(strict_types=1);

// A very basic file that sets up Agile Data to be used in some demonstrations

namespace Atk4\Audit\Demo;

use Atk4\Data\Persistence;
use Atk4\Ui\App;
use Atk4\Ui\Exception;

try {
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php'; // @phpstan-ignore requireOnce.fileNotFound
    } else {
        require_once __DIR__ . '/db.example.php';
    }
} catch (\PDOException $e) {
    throw (new Exception('This demo requires access to a database. See "demos/database.php"'))
        ->addMoreInfo('PDO error', $e->getMessage());
}

/** @var App $app */
/** @var Persistence $db */

$app->db = $db;

// Define some data models
if (!class_exists('Country')) {
    require_once 'Country.php';
}
