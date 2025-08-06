<?php

namespace Wijoc\MIGrator\Migrations;

class EnvLoader
{
    public function __construct()
    {
        $this->loadEnvFile();
    }

    public function loadEnvFile(string $path = __DIR__ . '/.env'): void
    {
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;

                [$name, $value] = array_map('trim', explode('=', $line, 2));
                if (!isset($_ENV[$name]) && getenv($name) === false) {
                    putenv("$name=$value");
                    $_ENV[$name] = $value;
                }
            }
        }
    }
}
