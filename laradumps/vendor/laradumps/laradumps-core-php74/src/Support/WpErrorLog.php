<?php

namespace LaraDumps\LaraDumpsCore\Support;

use Exception;
use LaraDumps\LaraDumpsCore\LaraDumps;
use LaraDumps\LaraDumpsCore\Payloads\LogPayload;
use LaraDumps\LaraDumpsCore\Actions\Dumper;
use Spatie\Backtrace\Backtrace;

/**
 * WordPress Error Capture - Works before and after WP loads
 */
class WpErrorLog
{
    public $errors = [];
    private static $loaded;
    private static $instance;
    private $handling_exception = false;
    private $wp_hooks_registered = false;

    public static function init()
    {
        if (self::$loaded) {
            return self::$instance;
        }
        self::$loaded = true;
        self::$instance = new static();
        return self::$instance;
    }

    public function __construct()
    {
        $this->init_basic_error_capture();
        $this->schedule_wp_hooks();
    }

    private function init_basic_error_capture()
    {
        // Always set up basic PHP error/exception handlers
        $this->setup_php_handlers();
    }

    private function schedule_wp_hooks()
    {
        // If WordPress is already loaded, register hooks immediately
        if ($this->is_wordpress_loaded()) {
            $this->init_wp_error_capture();
        } else {
            // Otherwise, wait for WordPress to load
            // Try multiple approaches since we're not sure which will work

            // Approach 1: Use init hook if functions are available
            if (function_exists('add_action')) {
                add_action('init', [$this, 'init_wp_error_capture'], 1);
            } else {
                // Approach 2: Register a shutdown function to check periodically
                register_shutdown_function([$this, 'late_wp_init_check']);
            }
        }
    }

    public function late_wp_init_check()
    {
        // This runs at shutdown - check if WP loaded and we missed registering hooks
        if (!$this->wp_hooks_registered && $this->is_wordpress_loaded()) {
            $this->init_wp_error_capture();
        }
    }

    private function is_wordpress_loaded()
    {
        return function_exists('add_action') &&
            function_exists('wp_die') &&
            (defined('ABSPATH') || defined('WP_DEBUG'));
    }

    private function should_capture_wp_errors()
    {
        return $this->is_wordpress_loaded() &&
            defined('WP_DEBUG') &&
            WP_DEBUG;
    }

    public function init_wp_error_capture()
    {
        if ($this->wp_hooks_registered || !$this->should_capture_wp_errors()) {
            return;
        }

        $this->wp_hooks_registered = true;

        // Hook into WordPress error handling
        add_action('wp_php_error_message', [$this, 'capture_wp_error'], 10, 6);

        // Hook for uncaught exceptions (WP 5.2+)
        if (function_exists('wp_register_fatal_error_handler')) {
            add_filter('wp_php_error_message', [$this, 'capture_wp_fatal'], 10, 6);
        }

        // WordPress specific hooks for additional error capture
        add_action('doing_it_wrong_run', [$this, 'capture_doing_it_wrong'], 10, 3);
        add_action('deprecated_function_run', [$this, 'capture_deprecated'], 10, 3);
        add_action('deprecated_hook_run', [$this, 'capture_deprecated_hook'], 10, 4);
    }

    private function setup_php_handlers()
    {
        // Store reference to self for closures
        $self = $this;

        // Set up error handler that works with or without WordPress
        set_error_handler(function($severity, $message, $file, $line, $context = null) use ($self) {
            // Capture our error
            $self->log_error_to_laradumps($severity, $message, $file, $line);

            // Let WordPress/PHP handle it normally
            return false;
        });

        // Set up exception handler that works with or without WordPress
        set_exception_handler(function($exception) use ($self) {
            // Mark that we're handling an exception
            $self->handling_exception = true;

            // Capture our exception
            $self->log_exception_to_laradumps($exception);

            // Check if WordPress is available and has a handler
            if ($self->is_wordpress_loaded() && function_exists('wp_die')) {
                wp_die(
                    'Uncaught exception: ' . $exception->getMessage(),
                    'WordPress Error',
                    ['response' => 500]
                );
            } else {
                // Reset flag and rethrow for default PHP handling
                $self->handling_exception = false;
                throw $exception;
            }
        });

        // Shutdown function for fatal errors
        register_shutdown_function([$this, 'capture_fatal']);
    }

    public function capture_wp_error($message, $error)
    {
        if (is_array($error) && isset($error['message'])) {
            $this->log_error_to_laradumps(
                E_ERROR,
                $error['message'],
                $error['file'] ?? '',
                $error['line'] ?? 0
            );
        }
        return $message; // Don't modify WP's message
    }

    public function capture_wp_fatal($message, $error)
    {
        if (is_array($error) && isset($error['type'])) {
            $this->errors[] = [
                'type' => 'wp_fatal',
                'message' => $error['message'] ?? 'Unknown error',
                'file' => $error['file'] ?? '',
                'line' => $error['line'] ?? 0,
                'time' => time()
            ];
        }
        return $message;
    }

    public function capture_doing_it_wrong($function, $message, $version)
    {
        $frames = new Backtrace();
        $frames = (new LaraDumps())->parseFrame($frames);

        $log = [
            'message' => "Doing it wrong: {$function} - {$message}",
            'level' => 'warning',
            'context' => Dumper::dump([
                'function' => $function,
                'version' => $version,
                'backtrace' => $frames
            ]),
        ];

        $payload = new LogPayload($log);
        (new LaraDumps())->send($payload);
    }

    public function capture_deprecated($function, $version, $replacement)
    {
        $log = [
            'message' => "Deprecated function: {$function} (since {$version})",
            'level' => 'notice',
            'context' => Dumper::dump([
                'function' => $function,
                'version' => $version,
                'replacement' => $replacement
            ]),
        ];

        $payload = new LogPayload($log);
        (new LaraDumps())->send($payload);
    }

    public function capture_deprecated_hook($hook, $version, $replacement, $message)
    {
        $log = [
            'message' => "Deprecated hook: {$hook} (since {$version}) - {$message}",
            'level' => 'notice',
            'context' => Dumper::dump([
                'hook' => $hook,
                'version' => $version,
                'replacement' => $replacement
            ]),
        ];

        $payload = new LogPayload($log);
        (new LaraDumps())->send($payload);
    }

    private function log_error_to_laradumps($severity, $message, $file, $line)
    {
        $data = [
            'type' => 'error',
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'time' => time()
        ];

        $this->errors[] = $data;

        $traces = array_filter(
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10), 
            fn($trace) => !str_contains(strtolower($trace['function']), 'laradumps')
        );

        try {
            $snippet = (new CodeSnippet())->getCodeSnippetFromTrace(
                $traces,
                $file,
                $line
            );
        } catch (Exception $e) {
            $snippet = null;
        }

        $log = [
            'message' => $message,
            'level' => $this->severity_to_level($severity),
            'context' => Dumper::dump([
                'severity' => $severity,
                'file' => $file,
                'line' => $line,
                'backtrace' => $traces
            ]),
        ];

        $payload = new LogPayload($log);

        if ($snippet) {
            $payload->setCodeSnippet($snippet);
        }
        (new LaraDumps())->send($payload);
    }

    private function log_exception_to_laradumps($exception)
    {
        $log = [
            'message' => $exception->getMessage(),
            'level' => 'error',
            'context' => Dumper::dump([
                'exception' => $exception,
                'class' => get_class($exception),
                'code' => $exception->getCode()
            ]),
        ];

        $payload = new LogPayload($log);

        try {
            $snippet = (new CodeSnippet())->fromException($exception);
            $payload->setCodeSnippet($snippet);
        } catch (Exception $e) {
            // Ignore if we can't get snippet
        }

        (new LaraDumps())->send($payload);
    }

    public function capture_fatal()
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->errors[] = [
                'type' => 'fatal',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'time' => time()
            ];

            $log = [
                'message' => $error['message'],
                'level' => 'critical',
                'context' => Dumper::dump([
                    'error_type' => $error['type'],
                    'file' => $error['file'],
                    'line' => $error['line']
                ]),
            ];

            $payload = new LogPayload($log);

            try {
                $snippet = (new CodeSnippet())->getCodeSnippetFromTrace(
                    [],
                    $error['file'],
                    $error['line']
                );
                $payload->setCodeSnippet($snippet);
            } catch (Exception $e) {
                // Ignore if we can't get snippet
            }
            (new LaraDumps())->send($payload);
        }
    }

    private function severity_to_level($severity)
    {
        switch ($severity) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return 'error';
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'warning';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'notice';
            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'info';
            default:
                return 'debug';
        }
    }

    public function get_errors()
    {
        return $this->errors;
    }

    public function clear_errors()
    {
        $this->errors = [];
    }
}