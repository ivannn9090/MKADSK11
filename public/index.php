<?php

use CodeIgniter\Boot;
use Config\Paths;

/*
 *---------------------------------------------------------------
 * POLYFILL: intl Locale class (for environments without php-intl)
 *---------------------------------------------------------------
 */
if (! extension_loaded('intl') && ! class_exists('Locale', false)) {
    class Locale
    {
        public static function getDefault(): string
        {
            return 'en';
        }

        public static function setDefault(string $locale): bool
        {
            return true;
        }

        public static function acceptFromHttp(string $header): string|false
        {
            return 'en';
        }

        public static function lookup(
            array $langtag,
            string $locale,
            bool $canonicalize = false,
            ?string $default = null
        ): ?string {
            return $default;
        }

        public static function parseLocale(string $locale): array
        {
            return ['language' => 'en'];
        }

        public static function composeLocale(array $subtags): string
        {
            return $subtags['language'] ?? 'en';
        }

        public static function filterMatches(string $langtag, string $locale, bool $canonicalize = false): bool
        {
            return true;
        }
    }
}

/*
 *---------------------------------------------------------------
 * CHECK PHP VERSION
 *---------------------------------------------------------------
 */

$minPhpVersion = '8.2'; // If you update this, don't forget to update `spark`.
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION,
    );

    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;

    exit(1);
}

/*
 *---------------------------------------------------------------
 * SET THE CURRENT DIRECTORY
 *---------------------------------------------------------------
 */

// Path to the front controller (this file)
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Ensure the current directory is pointing to the front controller's directory
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 * This process sets up the path constants, loads and registers
 * our autoloader, along with Composer's, loads our constants
 * and fires up an environment-specific bootstrapping.
 */

// LOAD OUR PATHS CONFIG FILE
// This is the line that might need to be changed, depending on your folder structure.
require FCPATH . '../app/Config/Paths.php';
// ^^^ Change this line if you move your application folder

$paths = new Paths();

// LOAD THE FRAMEWORK BOOTSTRAP FILE
require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));
