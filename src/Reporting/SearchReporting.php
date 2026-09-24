<?php 

namespace EsSmartSearch\Reporting;

class SearchReporting {

    public function register() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Activate the plugin and create the search reporting table.
     *
     * @return void
     */
    public static function activate() {
    
        // Create the search reporting table if it doesn't exist
        self::createReportingTable();

    }

    /**
     * Create search reporting table in database
     *
     * @return void
     */
    public static function createReportingTable() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'es_smart_search_events';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            visitor_id CHAR(36) NOT NULL,
            session_id CHAR(36) NOT NULL,
            query_raw VARCHAR(255) NOT NULL,
            query_normalised VARCHAR(255) NOT NULL,
            matching_batches INT UNSIGNED NOT NULL DEFAULT 0,
            displayed_parents INT UNSIGNED NOT NULL DEFAULT 0,
            has_results TINYINT(1) NOT NULL DEFAULT 0,
            top_matches_json LONGTEXT NULL,
            page_path VARCHAR(255) NOT NULL,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY query_normalised (query_normalised),
            KEY has_results (has_results),
            KEY visitor_id (visitor_id),
            KEY session_id (session_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function register_routes() {

        // Auth callback function to check API key supplied is valid
        $auth_callback = [ $this, 'check_api_key_permission' ];

        // Register REST API routes for search reporting
        register_rest_route(
            'es-smart-search/v1',
            '/report',
            [
                'methods' => 'POST',
                'callback' => [ $this, 'handle_report' ],
                'permission_callback' => '__return_true',
            ]
        );

        // Register REST API route for summary
        register_rest_route(
            'es-smart-search/v1',
            '/summary',
            [
                'methods' => 'GET',
                'callback' => [ $this, 'handle_summary' ],
                'permission_callback' => $auth_callback,
            ]
        );

        // Register REST API route for activity
        register_rest_route(
            'es-smart-search/v1',
            '/activity',
            [
                'methods' => 'GET',
                'callback' => [ $this, 'handle_activity' ],
                'permission_callback' => $auth_callback,
            ]
        );

        // Register REST API route for popular searches
        register_rest_route(
            'es-smart-search/v1',
            '/popular',
            [
                'methods' => 'GET',
                'callback' => [ $this, 'handle_popular' ],
                'permission_callback' => $auth_callback,
            ]
        );

        // Register REST API route for no-results searches
        register_rest_route(
            'es-smart-search/v1',
            '/no-results',
            [
                'methods' => 'GET',
                'callback' => [ $this, 'handle_no_results' ],
                'permission_callback' => $auth_callback,
            ]
        );
    }

    public function check_api_key_permission( \WP_REST_Request $request ) {

        // Extract token via custom HTTP header context
        $provided_key = $request->get_header( 'X-ES-Smart-Search-Key' );

        // Query param fallback if header is absent
        if ( empty( $provided_key ) ) {
            $provided_key = $request->get_param( 'api_key' );
        }

        $stored_key = $this->get_decrypted_api_key();

        // Halt execution if an API token hasn't been configured/saved yet
        if ( empty( $stored_key ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'API authentication is unconfigured on the server.', 'es-smart-search' ),
                [ 'status' => 500 ]
            );
        }

        // Timing-attack safe evaluation checking strings logic
        if ( ! hash_equals( $stored_key, (string) $provided_key ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'Invalid or missing API key.', 'es-smart-search' ),
                [ 'status' => 401 ]
            );
        }

        return true;
    }

    /**
     * Retrieve and decrypt the stored API key for reporting purposes.
     *
     * @return string Decrypted API key or empty string if not configured.
     */
    private function get_decrypted_api_key() {

        $encoded = get_option( 'smart_search_reporting_api_key', '' );
        if ( empty( $encoded ) ) return '';

        $secret_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'site_fallback_salt';
        $secret_iv  = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'site_fallback_iv';
        
        $key = hash( 'sha256', $secret_key );
        $iv  = substr( hash( 'sha256', $secret_iv ), 0, 16 );
        
        return openssl_decrypt( base64_decode( $encoded ), "AES-256-CBC", $key, 0, $iv );
    }

    /**
     * Persist search data to the database for reporting purposes.
     *
     * @param \WP_REST_Request $request
     * @return void
     */
    public function handle_report( \WP_REST_Request $request ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'es_smart_search_events';

        $data = [
            'created_at' => current_time( 'mysql' ),
            'visitor_id' => sanitize_text_field( $request->get_param( 'visitor_id' ) ),
            'session_id' => sanitize_text_field( $request->get_param( 'session_id' ) ),
            'query_raw' => sanitize_text_field( $request->get_param( 'query_raw' ) ),
            'query_normalised' => self::esss_normalise_query( sanitize_text_field( $request->get_param( 'query_normalised' ) ) ),
            'matching_batches' => intval( $request->get_param( 'matching_batches' ) ),
            'displayed_parents' => intval( $request->get_param( 'displayed_parents' ) ),
            'has_results' => intval( $request->get_param( 'has_results' ) ),
            'top_matches_json' => wp_json_encode( $request->get_param( 'top_matches_json' ) ),
            'page_path' => sanitize_text_field( $request->get_param( 'page_path' ) ),
        ];

        $wpdb->insert( $table_name, $data );

        return new \WP_REST_Response(
            [ 'success' => true ],
            200
        );
    }

    /**
     * Get a summary of search activity within a specified date range.
     *
     * @param \WP_REST_Request $request
     * @return void
     */
    public function handle_summary( \WP_REST_Request $request ) {

        global $wpdb;

        // Table name for search events
        $table_name = $wpdb->prefix . 'es_smart_search_events';

        // Get date range parameters from the request
        $from = sanitize_text_field( $request->get_param( 'from' ) );
        $to   = sanitize_text_field( $request->get_param( 'to' ) );

        // Build the WHERE clause based on the date range
        $where  = 'WHERE 1=1';
        
        // Parameters for the prepared statement
        $params = [];

        // Add date range conditions to the WHERE clause and parameters array
        if ( $from ) {
            $where .= ' AND created_at >= %s';
            $params[] = $from . ' 00:00:00';
        }

        // Add the 'to' date condition if provided
        if ( $to ) {
            $where .= ' AND created_at <= %s';
            $params[] = $to . ' 23:59:59';
        }

        // Construct the SQL query with the WHERE clause and parameters
        $sql = "
            SELECT
                COUNT(*) AS searches,
                COUNT(DISTINCT visitor_id) AS unique_visitors,
                COUNT(DISTINCT session_id) AS unique_sessions,
                SUM(has_results = 1) AS searches_with_results,
                SUM(has_results = 0) AS searches_without_results
            FROM $table_name
            $where
        ";

        // Prepare the SQL statement if there are parameters
        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params );
        }

        // Execute the query and get the result
        $result = $wpdb->get_row( $sql );

        // Extract the total number of searches from the result
        $searches = (int) $result->searches;

        // Extract the number of unique visitors from the result
        return new \WP_REST_Response(
            [
                'searches'              => $searches,
                'unique_visitors'       => (int) $result->unique_visitors,
                'unique_sessions'       => (int) $result->unique_sessions,
                'searches_with_results' => (int) $result->searches_with_results,
                'searches_without_results' => (int) $result->searches_without_results,
                'result_rate'           => $searches > 0
                    ? round( ( $result->searches_with_results / $searches ) * 100, 2 )
                    : 0,
            ],
            200
        );
    }

    /**
     * Handle activity requests and return search activity data.
     *
     * @param \WP_REST_Request $request
     * @return void
     */
    public function handle_activity( \WP_REST_Request $request ) {

        global $wpdb;

        // Get the table name for search events
        $table_name = $wpdb->prefix . 'es_smart_search_events';

        // Get the 'from' and 'to' date parameters from the request
        $from = sanitize_text_field( $request->get_param( 'from' ) );
        $to   = sanitize_text_field( $request->get_param( 'to' ) );

        // Initialize the WHERE clause and parameters array for the SQL query
        $where  = 'WHERE 1=1';
        $params = [];

        // Add conditions to the WHERE clause based on the 'from' and 'to' date parameters
        if ( $from ) {
            $where .= ' AND created_at >= %s';
            $params[] = $from . ' 00:00:00';
        }

        // Add the 'to' date condition to the WHERE clause if provided
        if ( $to ) {
            $where .= ' AND created_at <= %s';
            $params[] = $to . ' 23:59:59';
        }

        // Construct the SQL query to retrieve search activity data
        $sql = "
            SELECT
                DATE(created_at) AS date,
                COUNT(*) AS searches,
                COUNT(DISTINCT visitor_id) AS unique_visitors
            FROM $table_name
            $where
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ";

        // Prepare the SQL query with the parameters if any
        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params );
        }

        // Execute the SQL query and get the results
        $results = $wpdb->get_results( $sql );

        // Return the search activity data as a REST response
        return new \WP_REST_Response( $results, 200 );
    }

    /**
     * Handle popular search queries requests and return popular search data.
     *
     * @param \WP_REST_Request $request
     * @return void
     */
    public function handle_popular( \WP_REST_Request $request ) {

        global $wpdb;

        // Get the table name for search events
        $table_name = $wpdb->prefix . 'es_smart_search_events';

        // Get the 'from', 'to', and 'limit' parameters from the request
        $from  = sanitize_text_field( $request->get_param( 'from' ) );
        $to    = sanitize_text_field( $request->get_param( 'to' ) );
        $limit = absint( $request->get_param( 'limit' ) );

        // Set a default limit if not provided
        if ( ! $limit ) {
            $limit = 20;
        }

        // Initialize the WHERE clause and parameters array for the SQL query
        $where  = 'WHERE 1=1';
        $params = [];

        // Add the 'from' date condition to the WHERE clause if provided
        if ( $from ) {
            $where .= ' AND created_at >= %s';
            $params[] = $from . ' 00:00:00';
        }

        // Add the 'to' date condition to the WHERE clause if provided
        if ( $to ) {
            $where .= ' AND created_at <= %s';
            $params[] = $to . ' 23:59:59';
        }

        // Build the SQL query to get popular search queries within the specified date range and limit
        $sql = "
            SELECT
                query_normalised AS query,
                COUNT(*) AS searches,
                COUNT(DISTINCT visitor_id) AS unique_visitors
            FROM $table_name
            $where
            GROUP BY query_normalised
            ORDER BY searches DESC
            LIMIT %d
        ";

        // Add the limit parameter to the parameters array for the SQL query
        $params[] = $limit;

        // Prepare the SQL query with the parameters to prevent SQL injection
        $sql = $wpdb->prepare( $sql, $params );

        // Execute the SQL query and get the results
        $results = $wpdb->get_results( $sql );

        // Return the results as a WP_REST_Response
        return new \WP_REST_Response( $results, 200 );
    }

    /**
     * Handle no results search queries.
     *
     * @param \WP_REST_Request $request
     * @return void
     */
    public function handle_no_results( \WP_REST_Request $request ) {

        global $wpdb;

        // Get the table name for the search events
        $table_name = $wpdb->prefix . 'es_smart_search_events';

        // Get the 'from', 'to', and 'limit' parameters from the request
        $from  = sanitize_text_field( $request->get_param( 'from' ) );
        $to    = sanitize_text_field( $request->get_param( 'to' ) );
        $limit = absint( $request->get_param( 'limit' ) );

        // Set a default limit if none is provided
        if ( ! $limit ) {
            $limit = 20;
        }

        // Initialize the WHERE clause and parameters array for the SQL query
        $where  = 'WHERE has_results = 0';
        $params = [];

        // Add the 'from' date condition to the WHERE clause if provided
        if ( $from ) {
            $where .= ' AND created_at >= %s';
            $params[] = $from . ' 00:00:00';
        }

        // Add the 'to' date condition to the WHERE clause if provided
        if ( $to ) {
            $where .= ' AND created_at <= %s';
            $params[] = $to . ' 23:59:59';
        }

        // Construct the SQL query to get no results search queries
        $sql = "
            SELECT
                query_normalised AS query,
                COUNT(*) AS searches,
                COUNT(DISTINCT visitor_id) AS unique_visitors
            FROM $table_name
            $where
            GROUP BY query_normalised
            ORDER BY searches DESC
            LIMIT %d
        ";

        // Add the limit parameter to the parameters array for the SQL query
        $params[] = $limit;

        // Prepare the SQL query with the parameters to prevent SQL injection
        $sql = $wpdb->prepare( $sql, $params );

        // Execute the SQL query and get the results
        $results = $wpdb->get_results( $sql );

        // Return the results as a WP_REST_Response
        return new \WP_REST_Response( $results, 200 );
    }

    /**
     * Normalise search query
     *
     * @param string $raw_query The raw search query.
     * @return string The normalised search query.
     */
    private static function esss_normalise_query( $raw_query ) {

        // Convert to lowercase
        $clean = mb_strtolower( $raw_query, 'UTF-8' );
        
        // Strip punctuation and special characters, keeping only letters, numbers, spaces, and hyphens
        $clean = preg_replace( '/[^\w\s-]/u', '', $clean );
        
        // Collapse multiple spaces into a single space
        $clean = preg_replace( '/\s+/', ' ', $clean );
        
        // Trim leading and trailing spaces
        return trim( $clean );
    }

}