<?php

use App\Tests\TestDatabaseInitializer;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? '') === 'test') {
    TestDatabaseInitializer::initialize();
}

if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0000);
}
