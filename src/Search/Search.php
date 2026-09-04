<?php

namespace EsSmartSearch\Search;

use EsSmartSearch\Indexing\SearchIndex;
use EsSmartSearch\Indexing\SearchMatcher;
use EsSmartSearch\Suggestion\Dictionary;
use EsSmartSearch\Suggestion\Service;

class Search {

    public function __construct(
        // Inject SearchIndex dependency for searching the cached index.
        private SearchIndex $search_index,
        private SearchMatcher $search_matcher,
        private Dictionary $dictionary,
        private Service $service,
    ) {}

    /**
     * Register REST routes and redirect that maintains hash in url
     * instead of standard ?param= format
     *
     * @return void
     */
    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_action( 'template_redirect', [ $this, 'redirect_query_search' ] );
    }

    /**
     * Register REST route for the search functionality
     *
     * @return void
     */
    public function register_routes(): void {
        register_rest_route( 'es-smart-search/v1', '/search', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'search' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'q' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );
    }

    /**
     * Redirects search queries from the batches page to the search results page.
     *
     * @return void
     */
    public function redirect_query_search(): void {

        // Only redirect on the batches page with a search query.
        if ( ! is_page_template( 'batches.php' ) ) {
            return;
        }

        // Only redirect when the search query is present.
        if ( empty( $_GET['textsearch'] ) ) {
            return;
        }

        // Sanitize the search query and redirect to the search results page with the query as a URL parameter.
        $query = sanitize_text_field( wp_unslash( $_GET['textsearch'] ) );

        // Redirect to the search results page with the query as a URL parameter.
        wp_safe_redirect(
            home_url( '/collections/search-results/#textsearch=' . rawurlencode( $query ) . '&page=1' )
        );

        // Exit to prevent further execution after the redirect.
        exit;
    }

    /**
     * Search the cached batch index and return ranked matching IDs.
     *
     * @param \WP_REST_Request $request Query and filter parameters.
     * @return \WP_REST_Response
     */
    public function search( \WP_REST_Request $request ) {

        // Get the search query and filters from the request parameters.
        $query = trim( (string) $request->get_param( 'q' ) );
        
        // Decode the filters from JSON and ensure they are in array format.
        $filters = json_decode( (string) $request->get_param( 'filters' ), true );
        
        // Ensure filters are in array format.
        $filters = is_array( $filters ) ? $filters : [];

        // Fetch your admin blacklist terms to clean the query on-the-fly
        $raw_blacklist = get_option( 'esss_ignored_terms', '' );
        $blacklist = array_map( 'trim', explode( ',', strtolower( $raw_blacklist ) ) );
        $blacklist = array_filter( $blacklist ); // Strip out empty entries safely

        // Extract and isolate tracking keywords, immediately dropping any blacklisted filler terms
        $raw_query_words = array_filter( explode( ' ', strtolower( $query ) ) );
        $query_words = array_filter( $raw_query_words, function( $word ) use ( $blacklist ) {
            return ! in_array( $word, $blacklist, true );
        });
        
        // Determine the index source based on the query and filters.
        $index_source = 'empty';

        // If the user typed ONLY blacklisted words (e.g., "porcelain tiles"),
        // we empty the query completely so the system falls back to showing all items!
        if ( ! empty( $raw_query_words ) && empty( $query_words ) ) {
            $query = ''; 
        }

        // Get the searchable batches based on the determined index source.
        $searchable_batches = $this->search_index->get_searchable_batches( $index_source );
        
        // Set the response headers to indicate the index source and count.
        header( 'X-ESSS-Index-Source: ' . $index_source );
        
        // Set the response header to indicate the count of searchable batches.
        header( 'X-ESSS-Index-Count: ' . count( $searchable_batches ) );
        
        // Initialize the array to store matching batches.
        $matches = [];

        // Loop through the searchable batches and evaluate each batch against the query and filters.
        foreach ( $searchable_batches as $batch ) {

            // Skip batches that do not match the filters.
            if ( ! $this->search_matcher->matches_filters( $batch['fields'], $filters ) ) continue;

            // Enforce strict multi-word e-commerce 'AND' lock guard and ensure deep search with array_walk_recursive
            if ( ! empty( $query_words ) ) {
                // Collect all values recursively to handle nested ACF array fields safely
                $field_strings = [];
                array_walk_recursive( $batch['fields'], function( $value ) use ( &$field_strings ) {
                    if ( is_string( $value ) || is_numeric( $value ) ) {
                        $field_strings[] = $value;
                    }
                } );

                // Combine the post title and flattened text properties into a single searchable text block
                $searchable_string = strtolower( $batch['post_title'] . ' ' . implode( ' ', $field_strings ) );
                $matches_all_words = true;

                foreach ( $query_words as $word ) {
                    // If a single word (like "white") is missing from this product, exclude it immediately
                    if ( false === strpos( $searchable_string, $word ) ) {
                        $matches_all_words = false;
                        break;
                    }
                }

                // Drop the item safely if it fails the cross-field AND check
                if ( ! $matches_all_words ) {
                    continue;
                }
            }

            // Get the matched fields for the current batch based on the active filters.
            $matched_fields = $this->search_matcher->get_filter_match_weights( $filters );
            
            // Get the matched values for the current batch based on the active filters.
            $matched_values = $this->search_matcher->get_filter_match_values( $filters );
            
            // Calculate the relevance score for the current batch based on the query and matched fields.
            $score = $this->search_matcher->score_batch( $query, $batch, $matched_fields );
            
            // If the query is empty, assign a default score of 1.
            $score = '' === $query ? 1 : $score;

            // Only consider batches with a positive relevance score.
            if ( $score > 0 ) {
                $matches[] = [
                    'id'            => $batch['id'],
                    'score'         => $score,
                    'matched_fields' => $matched_fields,
                    'matched_values' => $matched_values,
                ];
            }
        }

        // Sort the matching batches by their relevance score in descending order.
        usort( $matches, function( $left, $right ) {
            return $right['score'] <=> $left['score'];
        } );

        // Initialize the suggestion variable as null
        $suggestion = null;

        // If no matches were found and the search string wasn't empty, check the vocabulary dictionary
        if ( empty( $matches ) && '' !== $query ) {
            $vocabulary = $this->dictionary->get_terms();

            if ( ! empty( $vocabulary ) ) {

                // Fetch suggestion limit value from the settings
                $suggestions_limit = get_option( 'esss_suggestions_limit', 1 ); 

                // Fetch the suggestion via your SearchMatcher using your preferred limit setting (e.g. 1)
                $suggestion = $this->service->get_suggestions( $query, $vocabulary, $suggestions_limit );
            }

            // If spelling service fails to offer a typo correction, evaluate our fallback settings
            if ( empty( $suggestion ) ) {
                $fallback_data = $this->prepare_fallback_data();
            }

        }

        // Return the search results as a REST response.
        return rest_ensure_response( [
            'query'   => $query,
            'matches' => array_map( 'intval', wp_list_pluck( $matches, 'id' ) ),
            'count'   => count( $matches ),
            'ranking' => $matches,
            'suggestion' => $suggestion,
            'fallback'   => $fallback_data ?? null,
        ] );
    }

    /**
     * Prepare no result fallback data based on admin setting
     *
     * @return array
     */
    private function prepare_fallback_data() {

        // Determine whether to use popular searches or static links based on the admin setting.
        $use_popular = (bool) get_option( 'esss_use_popular_searches', 1 );

        if ( $use_popular ) {
            // Get top popular searches text queries from custom table
            return [
                'type' => 'popular', 
                'terms' => $this->get_popular_reporting_terms( 4 ) 
            ];
        } else {
            // Populate standard usage landing links
            return [
                'type' => 'usage',
                'terms' => $this->fallback_usage_terms()
            ];
        }
    }

    /**
     * Get the most popular search terms from the smart search events table.
     *
     * @param integer $limit
     * @return array $popular_terms
     */
    private function get_popular_reporting_terms( $limit = 4 ) {

        global $wpdb;
        
        // Define the table name for the smart search events.
        $table_name = $wpdb->prefix . 'es_smart_search_events';

        // Check if the smart search events table exists before querying it.
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
            return [];
        }

        // Fetch 5 times the limit from database logs. This ensures that if the top 3 
        // items contain junk typos, we have enough valid buffer items beneath them to fill layout slots.
        $raw_popular_terms = $wpdb->get_col( $wpdb->prepare(
            "SELECT query_normalised 
             FROM $table_name 
             WHERE has_results = 1 AND query_normalised != ''
             GROUP BY query_normalised 
             ORDER BY COUNT(query_normalised) DESC 
             LIMIT %d",
            $limit * 5 
        ) );

        // If no popular terms were fetched from the database, return an empty array.
        if ( empty( $raw_popular_terms ) ) {
            return [];
        }

        // Fetch the dictionary to use as the vocabulary source of truth.
        $vocabulary = $this->dictionary->get_terms();
        
        // If the vocabulary cache is totally empty, fall back to usage terms to avoid a broken UI
        if ( empty( $vocabulary ) ) {
            return $this->fallback_usage_terms();
        }

        // Array to hold the verified popular terms after cross-referencing with the dictionary.
        $verified_terms = [];

        // Evaluate each logged keyword against the dictionary matrix
        foreach ( $raw_popular_terms as $phrase ) {

            // Split the phrase into individual words to check multi-word entries (e.g., "blue marble") safely
            $words = array_filter( explode( ' ', $phrase ) );
            $is_valid_phrase = true;

            foreach ( $words as $word ) {
                // If a single word in the logged phrase (like "agataxx") is completely missing 
                // from our dictionary, we invalidate the entire phrase.
                if ( ! in_array( $word, $vocabulary, true ) ) {
                    $is_valid_phrase = false;
                    break;
                }
            }

            // If the phrase passed the dictionary check, add it to the verified terms array.
            if ( $is_valid_phrase ) {
                $verified_terms[] = $phrase;
            }

            // Break out the loop early the exact millisecond we satisfy your allocation limit
            if ( count( $verified_terms ) >= $limit ) {
                break;
            }
        }

        return $verified_terms;
    
    }

    /**
     * Provides a fallback list of popular usage terms for the search functionality.
     *
     * @return array An array of associative arrays containing 'label' and 'url' keys.
     */
    public function fallback_usage_terms() {
        return [ 'floor', 'wall', 'wall & floor', 'outdoor' ];
    }
}