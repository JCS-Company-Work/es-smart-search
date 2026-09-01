<?php 

namespace EsSmartSearch;

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
        register_rest_route(
            'es-smart-search/v1',
            '/report',
            [
                'methods' => 'POST',
                'callback' => [ $this, 'handle_report' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

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