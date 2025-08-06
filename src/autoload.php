<?php

return new class
{
    public function __construct()
    {
        $this->load([
            __DIR__ . '/QueryBuilder.php',
            __DIR__ . '/migration/*.php',
            __DIR__ . '/command/*.php',
            __DIR__ . '/WPCLICommand.php',
        ]);
    }

    public function load($paths)
    {
        foreach ($paths as $path) {
            foreach (glob($path) as $file) {
                require_once $file;
            }
        }
    }
};
