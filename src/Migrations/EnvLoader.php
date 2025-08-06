<?php

namespace Wijoc\MIGrator\Migrations;

use Dotenv\Dotenv;

class EnvLoader
{
    public function __construct()
    {
        $root = dirname(__FILE__, 4); // Move up to project root
        if (file_exists($root . '/.env')) {
            $dotenv = Dotenv::createImmutable($root);
            $dotenv->safeLoad();  // Does not throw error if .env missing

            foreach ($_ENV as $key => $value) {
                putenv("{$key}={$value}");
            }
        }
    }
}
