<?php

use LaraDumps\LaraDumpsCore\LaraDumps;

if (!function_exists('appBasePath')) {
    function appBasePath(): string
    {
        $pwd = (defined('LARAVEL_START') || isset($_SERVER['LARAVEL_OCTANE'])) && function_exists('app')
            ? app()->basePath() : getcwd();

        $basePath = rtrim($pwd, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        foreach (['public', 'pub', 'wp-admin'] as $dir) {
            if (substr($basePath, -strlen($dir . DIRECTORY_SEPARATOR)) === $dir . DIRECTORY_SEPARATOR) {
                $basePath = substr($basePath, 0, -strlen($dir . DIRECTORY_SEPARATOR));

                break;
            }
        }

        return $basePath;
    }
}

if (!function_exists('ds')) {
    /**
     * @param mixed ...$args
     * @return LaraDumps|LaravelLaraDumps
     */
    function ds(...$args)
    {
        $sendRequest = function ($args, LaraDumps $instance) {
            if ($args) {
                foreach ($args as $arg) {
                    $instance->write($arg);
                }
            }
        };


        $instance = new LaraDumps();

        $sendRequest($args, $instance);

        return $instance;
    }
}

if (!function_exists('phpinfo')) {
    function phpinfo(): LaraDumps
    {
        return ds()->phpinfo();
    }
}

if (!function_exists('dsd')) {
    /**
     * @param mixed ...$args
     */
    function dsd(...$args): void
    {
        $instance = new LaraDumps();

        foreach ($args as $arg) {
            $instance->write($arg);
        }

        die();
    }
}

if (!function_exists('dsq')) {
    /**
     * @param mixed ...$args
     */
    function dsq(...$args): void
    {
        $instance = new LaraDumps();

        if ($args) {
            foreach ($args as $arg) {
                $instance->write($arg, false);
            }
        }
    }
}

if (!function_exists('runningInTest')) {
    function runningInTest(): bool
    {
        if (PHP_SAPI != 'cli') {
            return false;
        }

        if (strpos($_SERVER['argv'][0], 'phpunit') !== false) {
            return true;
        }

        if (strpos($_SERVER['argv'][0], 'pest') !== false) {
            return true;
        }

        return false;
    }
}
