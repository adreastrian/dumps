<?php

namespace LaraDumps\LaraDumpsCore\Actions;

use PHPUnit\Exception;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Config
{
    /** @var array|null */
    private static $cachedContent = null;

    /** @var string */
    private static $configFilePath;

    private static function init(): void
    {
        if (!isset(self::$configFilePath)) {
            self::$configFilePath = appBasePath() . 'laradumps.yaml';
        }
    }

    public static function setConfigFilePath(string $path): void
    {
        self::$configFilePath = $path.'/laradumps.yaml';
    }

    private static function loadConfig(): array
    {
        self::init();

        if (self::$cachedContent === null) {
            try {
                self::$cachedContent = file_exists(self::$configFilePath)
                    ? (array) Yaml::parseFile(self::$configFilePath)
                    : [];
            } catch (Exception $exception) {
                self::$cachedContent = [];
            }
        }

        return self::$cachedContent;
    }

    private static function saveConfig(array $content): void
    {
        self::init();
        self::$cachedContent = $content;
        $yamlContent         = Yaml::dump($content);
        file_put_contents(self::$configFilePath, $yamlContent);
    }

    public static function publish(string $pwd, string $filepath): bool
    {
        try {
            /** @var array $fileContent */
            $fileContent                        = Yaml::parseFile($filepath);
            $fileContent['app']['project_path'] = $pwd;

            self::saveConfig($fileContent);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function get(string $key, $default = null)
    {
        $content = self::loadConfig();

        $enabledInTesting = $content['observers']['enabled_in_testing'] ?? false;

        $keys    = explode('.', $key);
        $current = $content;

        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                if (runningInTest() && !$enabledInTesting) {
                    return false;
                }

                return $default;
            }
            $current = $current[$key];
        }

        if (runningInTest() && !$enabledInTesting) {
            return false;
        }

        return $current;
    }

    public static function set(string $key, $value): void
    {
        $content      = self::loadConfig();
        $keys         = explode('.', $key);
        $currentArray = &$content;

        foreach ($keys as $key) {
            if (!isset($currentArray[$key])) {
                $currentArray[$key] = [];
            }
            $currentArray = &$currentArray[$key];
        }

        $currentArray = $value;

        self::saveConfig($content);
    }

    public static function exists(): bool
    {
        return file_exists(self::$configFilePath);
    }
}
