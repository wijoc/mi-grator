<?php

namespace Wijoc\MIGrator\Migrations;

use Dotenv\Dotenv;

class EnvLoader
{
    public function __construct()
    {
        $root = dirname(__DIR__, 5);
        if (file_exists($root . '/.env')) {
            $dotenv = Dotenv::createImmutable($root);
            $dotenv->safeLoad();

            foreach ($_ENV as $key => $value) {
                putenv("{$key}={$value}");
            }
        }
    }
}
