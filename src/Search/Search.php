<?php

namespace EsSmartSearch\Search;

use EsSmartSearch\Indexing\SearchIndex;
use EsSmartSearch\Indexing\SearchMatcher;
use EsSmartSearch\Indexing\Suggestion\Dictionary;
use EsSmartSearch\Indexing\Suggestion\Service;

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
        
        // Determine the index source based on the query and filters.
        $index_source = 'empty';

        // If the query is empty and there are no filters, return an empty result set.
        if ( '' === $query && empty( $filters ) ) {
        
            // retrun empty headers and response
            header( 'X-ESSS-Index-Source: empty' );
            return rest_ensure_response( [
                'query'   => '',
                'matches' => [],
                'count'   => 0,
            ] );
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
                // Fetch the suggestion via your SearchMatcher using your preferred limit setting (e.g. 1)
                $suggestion = $this->service->get_suggestions( $query, $vocabulary, 1 );
            }
        }

        // Return the search results as a REST response.
        return rest_ensure_response( [
            'query'   => $query,
            'matches' => array_map( 'intval', wp_list_pluck( $matches, 'id' ) ),
            'count'   => count( $matches ),
            'ranking' => $matches,
            'suggestion' => $suggestion,
        ] );
    }

}