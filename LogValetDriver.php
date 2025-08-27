<?php

namespace Valet\Drivers\Custom;

/**
 * Simple Log Driver extending Laravel's existing driver
 * Save as: LogValetDriver.php in ~/.config/herd/valet/Drivers/
 */

use LaraDumps\LaraDumpsCore\LaraDumps;
use LaraDumps\LaraDumpsCore\Actions\Config;
use Valet\Drivers\Specific\WordPressValetDriver;

class LogValetDriver extends WordPressValetDriver
{
    /**
     * Determine if the driver serves the request.
     */
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
        $willServe = file_exists($sitePath . '/wp-config.php') || file_exists($sitePath . '/wp-config-sample.php');

        if (!$willServe) {
            return false;
        }

        require_once __DIR__ . '/laradumps/vendor/autoload.php';

        Config::setConfigFilePath($sitePath);

        if (!Config::exists()) {
            copy(__DIR__ . '/laradumps/vendor/laradumps/laradumps-core-php74/laradumps.yaml', $sitePath . '/laradumps.yaml');
            
            Config::set('app.project_path', $sitePath);
            Config::set('observers.mail', true);
            Config::set('observers.queries', true);
        }

        (new LaraDumps)->enableErrorLog();

        return true;
    }
}
