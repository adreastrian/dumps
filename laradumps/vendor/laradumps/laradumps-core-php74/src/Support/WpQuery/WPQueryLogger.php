<?php

namespace LaraDumps\LaraDumpsCore\Support\WpQuery;

use LaraDumps\LaraDumpsCore\LaraDumps;
use LaraDumps\LaraDumpsCore\Payloads\QueriesPayload;

/**
 * WordPress Database Query Logger for LaraDumps
 */
class WPQueryLogger
{
    private static $loaded = false;
    private static $instance = null;
    private $query_count = 0;
    private $total_time = 0;
    private $queries = [];

    private $num_queries = 0;

    private $total_qs = 0;

    private $wpdb = null;

    private $data = [];

    public static function init()
    {

        if(!self::is_non_asset_request()){
            return;
        }

        if (self::$loaded) {
            return self::$instance;
        }

        self::$loaded = true;
        self::$instance = new static();
        return self::$instance;
    }

    private static function is_non_asset_request(): bool {
        // Get the path without query strings
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Common asset file extensions
        $asset_extensions = [
            'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp',
            'ico', 'woff', 'woff2', 'ttf', 'eot', 'map', 'json'
        ];

        // Improved pattern: allow an optional trailing slash after the extension
        return !preg_match('/\.(' . implode('|', $asset_extensions) . ')(\/)?$/i', $path);
    }


    private static function  isAssetRequest() {
        // Get the path without query strings
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Common asset extensions
        $asset_extensions = [
            'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp',
            'ico', 'woff', 'woff2', 'ttf', 'eot', 'map', 'json'
        ];

        // If path ends with an asset extension → treat as asset
        if (preg_match('/\.(' . implode('|', $asset_extensions) . ')$/i', $path)) {
            return true;
        }

        return false;

        // AJAX request
        if (wp_doing_ajax()) {
            return 'ajax';
        }

        // REST API request
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return 'rest';
        }

        // Cron request
        if (wp_doing_cron()) {
            return 'cron';
        }

        // Admin area (but not AJAX)
        if (is_admin()) {
            return 'admin';
        }

        // Fallback → normal front-end page
        return 'page';
    }

    public function __construct()
    {

        $this->data = (object) [
            'rows' => [],
            'errors' => [],
            'expensive' => [],
            'total_qs' => 0,
            'total_time' => 0,
            'has_result' => false,
            'has_trace' => false,
            'has_main_query' => false,
        ];
        $this->init_query_logging();
    }

    private function captureQuery($query)
    {
        $queryString = $query;
        $trace = new WpBackTrace();
        global $wp_the_query, $wpdb;

        $this->queries[$wpdb->num_queries -1] = [
            'query'      => $queryString,
            'trace'      => $trace,
            'start_time' => microtime(true),

        ];

        if(str_contains($queryString,'order_items')){
//            ds([
//                'query' => $queryString,
//                'trace' => $trace,
//                //'file'       => $trace->get_caller()['file'] ?? '',
//                //'line'       => $trace->get_caller()['line'] ?? 0,
//                'filtered_stack' => $trace->get_filtered_trace(),
//            ]);
        }

        $this->num_queries++;
    }

    public function process_db_object()
    {


        global $wp_the_query, $wpdb;

        $this->wpdb = $wpdb;


        // With SAVEQUERIES defined as false, `wpdb::queries` is empty but `wpdb::num_queries` is not.
        if (empty($wpdb->queries)) {
            $this->total_qs += $wpdb->num_queries;
            return;
        }



        $types = array();
        $total_time = 0;
        $has_result = false;
        $has_trace = false;
        $i = 0;
        $request = trim($wp_the_query->request ?: '');

        if (method_exists($wpdb, 'remove_placeholder_escape')) {
            $request = $wpdb->remove_placeholder_escape($request);
        }

        /**
         * @phpstan-var array{
         *   0: string,
         *   1: float,
         *   2: string,
         *   trace?: QM_Backtrace,
         *   result?: int|bool|WP_Error,
         * }|array{
         *   query: string,
         *   elapsed: float,
         *   debug: string,
         * } $query
         */
        foreach ($wpdb->queries as $index => $query) {

            $queryObject = [];
            $myQuery = '';
            $trace = null;
            $start_time = 0;

            if(isset($this->queries[$index])){
                $queryObject = $this->queries[$index];
                $myQuery = $this->queries[$index]['query'];
                $trace = $this->queries[$index]['trace'];
                $start_time = $this->queries[$index]['start_time'];

            }

            $callers = array();

            if (isset($query['query'], $query['elapsed'], $query['debug'])) {
                // WordPress.com VIP.
                $sql = $query['query'];
                $ltime = $query['elapsed'];
                $stack = $query['debug'];
            } elseif (isset($query[0], $query[1], $query[2])) {
                // Standard WP.
                $sql = $query[0];
                $ltime = $query[1];
                $stack = $query[2];

                // Query Monitor db.php drop-in.
                $has_trace = $trace !== null;
                $has_result = isset($query['result']);
            } else {
                // ¯\_(ツ)_/¯
                continue;
            }

            // @TODO: decide what I want to do with this:
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (false !== strpos($stack, 'wp_admin_bar')) {
                continue;
            }

            $result = $query['result'] ?? null;
            $total_time += $ltime;

            if ($trace !== null) {
                $component = $trace->get_component();
                $caller = $trace->get_caller();
                $caller_name = $caller['display'] ?? 'Unknown';
                $caller = $caller['display'] ?? 'Unknown';

            } else {

                $trace = null;
                $component = null;
                $callers = array_reverse(explode(',', $stack));
                $callers = array_map('trim', $callers);
                $callers = WpBackTrace::get_filtered_stack($callers);
                $caller = reset($callers);
                $caller_name = $caller;

            }

            $sql = trim($sql);
            $type = Util::get_query_type($sql);

            //$this->log_type($type);
            //$this->log_caller($caller_name, $ltime, $type);
            //$this->maybe_log_dupe($sql, $i);


            $is_main_query = false;

            if (false !== strpos($stack, ' WP->main,')) {
                // Ignore comments that are appended to queries by some web hosts.
                $match_sql = preg_replace('#/\*.*?\*/\s*$#s', '', $sql);

                $is_main_query = ($request === $match_sql);
            }

            $row = compact('caller', 'caller_name', 'sql', 'ltime', 'result', 'type', 'component', 'trace', 'is_main_query');

            if ($component) {
                $row['component'] = $component;
                //$this->log_component($component, $ltime, $type);
            }
            if (!isset($trace)) {
                $row['stack'] = $callers;
            }else{

                $row['filtered_stack'] = $trace->get_filtered_trace();
            }

            // @TODO these should store a reference ($i) instead of the whole row
            if ($result instanceof WP_Error) {
                $this->data->errors[] = $row;
            }

            // @TODO these should store a reference ($i) instead of the whole row
            if (self::is_expensive($row)) {
                $this->data->expensive[] = $row;
                $row['is_expensive'] = true;
            }

            $row['start_time'] = $start_time;

            $this->data->rows[$i] = $row;
            $i++;

            $this->log_completed_query($row);
        }

        $has_main_query = wp_list_filter($this->data->rows, array(
            'is_main_query' => true,
        ));

        $this->data->total_qs = count($this->data->rows);
        $this->data->total_time = $total_time;
        $this->data->has_result = $has_result;
        $this->data->has_trace = $has_trace;
        //$this->data->has_main_query = !empty($has_main_query);
    }

    public static function is_expensive( array $row ): bool
    {
        return $row['ltime'] > 0.05;
    }


    private function init_query_logging()
    {
        // Only enable if WordPress debug is on and we're in a WordPress environment
        if (!defined('WP_DEBUG') || !WP_DEBUG || !function_exists('add_action')) {
            return;
        }

        // Enable WordPress query logging if not already enabled
        if (!defined('SAVEQUERIES')) {
            define('SAVEQUERIES', true);
        }

        add_filter('log_query_custom_data', function ($query_data, $query, $query_time, $query_callstack, $query_start) {
            $this->captureQuery($query);
        }, 99, 5);

        // Hook into WordPress query actions
        if (did_action('init')) {
            $this->setup_query_hooks();
        } else {
            add_action('init', [$this, 'setup_query_hooks'], 1);
        }

    }

    public function setup_query_hooks()
    {
        global $wpdb;

        if (!$wpdb) {
            return;
        }

        // Method 1: Hook into wpdb directly
        add_filter('posts_request', [$this, 'log_posts_query'], 10, 2);
        add_filter('posts_results', [$this, 'log_posts_query_end'], 10, 2);

        // Method 2: Use WordPress's query log if available
        add_action('wp_footer', [$this, 'send_queries_summary'], 999);
        add_action('admin_footer', [$this, 'send_queries_summary'], 999);
        add_action('shutdown', [$this, 'send_queries_summary'], 999);

        // Method 3: Hook into wpdb query method directly (more reliable)
        //$this->hook_wpdb_query();
    }

    /**
     * Hook directly into wpdb query method
     */
    private function hook_wpdb_query()
    {
        global $wpdb;

        // Store original query method
        if (!isset($wpdb->original_query_method)) {
            $wpdb->original_query_method = [$wpdb, 'query'];
        }

        // Override wpdb query method
        add_filter('query', function ($query) {
            //$this->capture_query_start($query);
            return $query;
        }, 1);

        // Capture after query execution using WordPress hooks
        add_action('wp_loaded', function () {
            if (function_exists('add_filter')) {
                //add_filter('posts_pre_query', [$this, 'capture_posts_query'], 10, 2);
            }
        });
    }

    public function capture_query_start($query)
    {
        $this->current_query_start = microtime(true);
        $this->current_query = $query;
        return $query;
    }


    private function log_completed_query($row)
    {
        $execution_time = $row['ltime'];
        $start_time = $row['start_time'];
        $query = $row['sql'];

        $request_info = $this->get_request_info();
        $stack = $row['filtered_stack'] ?? [];

        //ds()->toScreen('request_info')->write($request_info);
        $component = $row['component'] ?? null;

        if($component){
            $component = $component->name;
        }

        $queryLog = [
            'time'           => $execution_time,
            'database'       => $this->get_database_name(),
            'driver'         => 'mysql',
            'connectionName' => 'default',
            'query'          => new QueryExecuted($query),
            'uri'            => $request_info['uri'],
            'method'         => $request_info['method'],
            'origin'         => $component ?? 'unknown',
            'argv'           => $request_info['argv'],
            'explain_nodes'  => [
                'execution_time_ms' => $execution_time,
                'query_type'        => $this->get_query_type($query),
            ],
        ];

        if(!str_contains($query,'order_items')){
            //return;
        }

        $payload = new QueriesPayload($queryLog);

        if(isset($stack[0])){
            $payload->setFrame($stack[0]);
        }

        $dumper = new LaraDumps();
        $dumper->send($payload, false);

        $this->total_time += $execution_time;
        $this->query_count++;
    }

    public function log_posts_query($query, $wp_query)
    {
        $this->log_wordpress_query($query, 'posts_request');
        return $query;
    }

    public function log_posts_query_end($posts, $wp_query)
    {
        // This is called after the query, but we already logged it
        return $posts;
    }

    private function log_wordpress_query($query, $context = 'wordpress')
    {
        //$this->captureQuery($query);
    }

    private function get_query_type($query)
    {
        $query = trim(strtoupper($query));
        if (strpos($query, 'SELECT') === 0) return 'SELECT';
        if (strpos($query, 'INSERT') === 0) return 'INSERT';
        if (strpos($query, 'UPDATE') === 0) return 'UPDATE';
        if (strpos($query, 'DELETE') === 0) return 'DELETE';
        if (strpos($query, 'CREATE') === 0) return 'CREATE';
        if (strpos($query, 'DROP') === 0) return 'DROP';
        if (strpos($query, 'ALTER') === 0) return 'ALTER';
        return 'OTHER';
    }

    public function send_queries_summary()
    {
        $this->process_db_object();
    }



    private function get_request_info()
    {
        // Determine request context
        if (defined('WP_CLI') && WP_CLI) {
            return [
                'uri'    => 'wp-cli',
                'method' => 'CLI',
                'origin' => 'command-line',
                'argv'   => isset($_SERVER['argv']) ? implode(' ', $_SERVER['argv']) : 'wp-cli'
            ];
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return [
                'uri'    => 'wp-cron',
                'method' => 'CRON',
                'origin' => 'wordpress-cron',
                'argv'   => 'cron-job'
            ];
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return [
                'uri'    => $_SERVER['REQUEST_URI'] ?? '/wp-admin/admin-ajax.php',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'POST',
                'origin' => 'ajax',
                'argv'   => $_POST['action'] ?? 'unknown-ajax'
            ];
        }

        // Regular web request
        return [
            'uri'    => $_SERVER['REQUEST_URI'] ?? '/',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'origin' => $_SERVER['HTTP_REFERER'] ?? $_SERVER['HTTP_HOST'] ?? 'direct',
            'argv'   => !empty($_GET) ? http_build_query($_GET) : (!empty($_POST) ? 'POST-data' : 'none')
        ];
    }

    private function get_database_name()
    {
        global $wpdb;
        return defined('DB_NAME') ? DB_NAME : ($wpdb ? $wpdb->dbname : 'wordpress');
    }

    private function parse_backtrace($backtrace)
    {
        $frames = [];

        foreach ($backtrace as $trace) {
            if (!isset($trace['file'])) continue;

            // Skip our own files and WordPress core files for cleaner traces
            if (strpos($trace['file'], __FILE__) !== false) continue;
            if (strpos($trace['file'], '/wp-includes/') !== false) continue;
            if (strpos($trace['file'], '/wp-admin/') !== false) continue;

            $frames[] = [
                'file'     => $trace['file'],
                'line'     => $trace['line'] ?? 0,
                'function' => ($trace['class'] ?? '') . ($trace['type'] ?? '') . ($trace['function'] ?? ''),
                'class'    => $trace['class'] ?? null,
                'method'   => $trace['function'] ?? null,
            ];

            // Limit to reasonable number of frames
            if (count($frames) >= 5) break;
        }

        return $frames;
    }
}

// Usage:
// WPQueryLogger::init();