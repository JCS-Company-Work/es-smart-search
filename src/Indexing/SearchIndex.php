<?php

namespace EsSmartSearch\Indexing;

use WP_Query;

/**
 * Class responsible for managing the search index and invalidating it when necessary.
 * Invalidating is required when a batch is added, updated, or deleted, 
 * or when its metadata or taxonomy terms change.
 */
final class SearchIndex {

    /**
     * Register hooks that can make the cached search index stale.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'save_post_batch', [ $this, 'invalidate' ] );
        add_action( 'acf/save_post', [ $this, 'invalidate_acf' ], 9999 );
        add_action( 'updated_post_meta', [ $this, 'invalidate_meta' ], 10, 4 );
        add_action( 'added_post_meta', [ $this, 'invalidate_meta' ], 10, 4 );
        add_action( 'deleted_post_meta', [ $this, 'invalidate_meta' ], 10, 4 );
        add_action( 'set_object_terms', [ $this, 'invalidate_terms' ], 10, 6 );
        add_action( 'woocommerce_product_set_stock_status', [ $this, 'invalidate_product' ] );
        add_action( 'deleted_post', [ $this, 'invalidate_deleted' ] );
    }

    /**
     * Return the cached index, rebuilding it when unavailable.
     *
     * @param string $index_source Set to cache, rebuilt or unknown.
     * @return array<int, array<string, mixed>>
     */
    public function get_searchable_batches( &$index_source = 'unknown' ) {

        // Attempt to retrieve the cached searchable batches from the transient.
        $cached = get_transient( ESSS_INDEX_TRANSIENT );

        // Check if the cached data is available and valid.
        if ( false !== $cached && is_array( $cached ) ) {
            $index_source = 'cache';
            return $cached;
        }

        // Build the searchable batches as the cache is unavailable or invalid.
        $batches = $this->build_searchable_batches();
        $index_source = 'rebuilt';
        $expiration = 30 * DAY_IN_SECONDS;

        // Store the rebuilt searchable batches in the transient for future use.
        set_transient( ESSS_INDEX_TRANSIENT, $batches, $expiration );

        // Return the rebuilt searchable batches.
        return $batches;
    }

    /**
     * Build the array of searchable batches with their fields and normalized text.
     *
     * @return array $batches The array of searchable batches with their fields and normalized text.
     */
    private function build_searchable_batches() {

        // Query all published batches with stock greater than zero and in stock status.
        $query = new WP_Query( [
            'post_type'      => 'batch',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_stock',
                    'value'   => 1,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'   => '_stock_status',
                    'value' => 'instock',
                ],
            ],
        ] );

        // Array to hold the searchable batch data.
        $batches = [];

        // Loop through the queried batch IDs and build the searchable batch data.
        foreach ( $query->posts as $batch_id ) {
            $product = wc_get_product( $batch_id );
            $fields  = get_fields( $batch_id );
            $terms   = wp_get_post_terms( $batch_id, 'category', [ 'fields' => 'names' ] );
            $effects = wp_get_post_terms( $batch_id, 'effect', [ 'fields' => 'names' ] );

            if ( ! $product ) {
                continue;
            }

            $sqm = (float) $product->get_stock_quantity() * (float) ( $fields['sqm_per_carton'] ?? 0 );

            $values = [
                $product->get_name(),
                get_post_field( 'post_title', $batch_id ),
                $this->flatten_values( $fields ),
                $this->get_usage( $fields['finish'] ?? '' ),
                is_wp_error( $terms ) ? '' : implode( ' ', $terms ),
            ];

            // Add the searchable batch data to the array, including normalized text and relevant fields.
            $batches[] = [
                'id'     => (int) $batch_id,
                'text'   => SearchNormalizer::normalise( implode( ' ', $values ) ),
                'fields' => [
                    'title'      => [ SearchNormalizer::normalise( $product->get_name() ), SearchNormalizer::normalise( get_post_field( 'post_title', $batch_id ) ) ],
                    'colour'    => [ SearchNormalizer::normalise( $fields['colour'] ?? '' ) ],
                    'effect'    => array_values( array_filter( [ SearchNormalizer::normalise( is_wp_error( $effects ) ? '' : ( $effects[0] ?? '' ) ) ] ) ),
                    'finish'    => [ SearchNormalizer::normalise( $fields['finish'] ?? '' ) ],
                    'size'      => $this->get_size_values( $fields['dimensions'] ?? '' ),
                    'category'  => array_map( [ 'EsSmartSearch\SearchNormalizer', 'normalise' ], is_wp_error( $terms ) ? [] : $terms ),
                    'usage'     => array_map( [ 'EsSmartSearch\SearchNormalizer', 'normalise' ], $this->get_usage_values( $fields['finish'] ?? '' ) ),
                    'thickness' => [ SearchNormalizer::normalise( $fields['thickness'] ?? '' ) ],
                    'slip_rating' => [ SearchNormalizer::normalise( $fields['slip_rating'] ?? '' ) ],
                    'discount'  => [ SearchNormalizer::normalise( $fields['discount_percentage'] ?? '' ) ],
                    'quantity'  => [ $sqm ],
                    'factory'   => [ SearchNormalizer::normalise( $fields['factory_name'] ?? '' ) ],
                    'product_code' => [ SearchNormalizer::normalise( $fields['product_code'] ?? '' ) ],
                ],
            ];
        }

        return $batches;
    }

    /**
     * Get the normalized size values for the given dimensions.
     *
     * @param array $dimensions
     * @return array The array of normalized size values.
     */
    private function get_size_values( $dimensions ) {

        // Normalize the dimensions and prepare the size values array.
        $size = SearchNormalizer::normalise( $dimensions );
        $values = $size ? [ $size ] : [];

        // Check if the size matches the pattern for three-digit dimensions and convert to a scaled format.
        if ( preg_match( '/^(\d{3})x(\d{3})$/', $size, $matches ) ) {
            $values[] = ( (int) $matches[1] / 10 ) . 'x' . ( (int) $matches[2] / 10 );
        }

        // Return the unique normalized size values.
        return array_values( array_unique( $values ) );
    }

    /**
     * Get the space-separated usage labels for the given finish.
     *
     * @param string $finish
     * @return string Space-separated usage labels derived from the controlled finish rules.
     */
    private function get_usage( $finish ) {
        return implode( ' ', $this->get_usage_values( $finish ) );
    }

    /**
     * Get the usage labels for the given finish as an array.
     *
     * @param string $finish
     * @return array Array of usage labels derived from the controlled finish rules.
     */
    private function get_usage_values( $finish ) {
        $usage_by_finish = [
            'floor'        => [ 'natural', 'structured' ],
            'wall'         => [ 'natural', 'polished', 'honed' ],
            'wall & floor' => [ 'natural' ],
            'outdoor'      => [ 'grip' ],
        ];
        $finish         = strtolower( trim( (string) $finish ) );
        $usage          = [];

        foreach ( $usage_by_finish as $label => $finishes ) {
            if ( in_array( $finish, $finishes, true ) ) {
                $usage[] = $label;
            }
        }

        return $usage;
    }


    /**
     * Flatten scalar and nested ACF values into a single space-separated string.
     *
     * @param mixed $values Scalar or nested array of values to flatten.
     * @return string Space-separated flattened values.
     */
    private function flatten_values( $values ) {
        if ( ! is_array( $values ) ) {
            return (string) $values;
        }

        $flattened = [];

        foreach ( $values as $value ) {
            if ( is_array( $value ) ) {
                $flattened[] = $this->flatten_values( $value );
            } elseif ( is_scalar( $value ) ) {
                $flattened[] = (string) $value;
            }
        }

        return implode( ' ', $flattened );
    }

    /**
     * Delete the cached search index.
     *
     * @return void
     */
    public function invalidate(): void {
        delete_transient( ESSS_INDEX_TRANSIENT );
    }

    /**
     * Invalidate the cached index when ACF fields are saved.
     *
     * @param int|string $post_id Post whose ACF fields were saved.
     * @return void
     */
    public function invalidate_acf( $post_id ): void {
        if ( 'batch' === get_post_type( $post_id ) ) {
            $this->invalidate();
        }
    }

    /**
        * Invalidate the cached index when post metadata changes.
     *
        * @param int $meta_id Metadata record ID.
        * @param int $object_id Post whose metadata changed.
        * @return void
     */
        public function invalidate_meta( $meta_id, $object_id ): void {
        if ( 'batch' === get_post_type( $object_id ) ) {
            $this->invalidate();
        }
    }

    /**
     * Invalidate the cached index when post taxonomy terms change.
     *
     * @param int $object_id Post whose taxonomy relationships changed.
     * @param array $terms Term IDs or term data supplied by WordPress.
     * @param array $tt_ids Term taxonomy IDs supplied by WordPress.
     * @param string $taxonomy Taxonomy whose relationships changed.
     * @param bool $append Whether terms were appended instead of replaced.
     * @param array $old_tt_ids Previously assigned term taxonomy IDs.
     * @return void
     */
    public function invalidate_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ): void {
        if ( 'batch' === get_post_type( $object_id ) ) {
            $this->invalidate();
        }
    }

    /**
     * Invalidate the cached index when a product stock status changes.
     *
     * @param int $product_id Product whose stock status changed.
     * @return void
     */
    public function invalidate_product( $product_id ): void {
        if ( 'batch' === get_post_type( $product_id ) ) {
            $this->invalidate();
        }
    }

    /**
     * Invalidate the cached index when a post is deleted.
     *
     * @param int $post_id Deleted post ID.
     * @return void
     */
    public function invalidate_deleted( $post_id ): void {
        if ( 'batch' === get_post_type( $post_id ) ) {
            $this->invalidate();
        }
    }
}