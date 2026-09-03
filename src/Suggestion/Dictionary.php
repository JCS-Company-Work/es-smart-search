<?php

namespace EsSmartSearch\Suggestion;

use EsSmartSearch\Indexing\SearchNormalizer;

class Dictionary {

    /**
     * The option key used to store the flat cache.
     */
    private const CACHE_KEY = 'es_smart_search_suggestion_vocabulary';

    /**
     * Define the exact names/slugs of your target ACF custom fields.
     * Edit this array to match your ACF field names.
     */
    private array $target_acf_fields = [
        'colour',
        'finish',
        'effect',
        'category',
        'factory',
        'usage'
    ];

    // Array to hold terms that should be ignored when building the dictionary.
    private array $blacklist = [];

    /**
     * Fetch and process the blacklist strings exactly once on instantiation.
     */
    public function __construct() {

        // Fetch the raw blacklist string from the WordPress options table.
        $raw_blacklist   = get_option( 'esss_ignored_terms', '' );
        
        // Convert the raw blacklist string to lowercase and split it into individual terms.
        $parsed_terms    = array_map( 'trim', explode( ',', strtolower( $raw_blacklist ) ) );
        
        // Filter out any empty strings resulting from the split operation.
        $this->blacklist = array_filter( $parsed_terms ); 

    }

    /**
     * Register the class with WordPress hooks.
     */
    public static function boot(): void {
        $instance = new self();
        add_action( 'save_post_product', [ $instance, 'handle_product_save' ], 20, 3 );
    }

    /**
     * Handle the product save action and trigger a dictionary rebuild if necessary.
     *
     * @param int $post_id
     * @param \WP_Post $post
     * @param bool $update
     */
    public function handle_product_save( int $post_id, \WP_Post $post, bool $update ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $this->rebuild();
    }

    /**
     * Fetch the compiled dictionary array.
     *
     * @return array<int, string> Flat array of valid terms.
     */
    public function get_terms(): array {
        return get_option( self::CACHE_KEY, [] );
    }

    /**
     * Rebuild the dictionary terms cache by scanning ACF custom fields.
     *
     * @return bool True on successful update, false otherwise.
     */
    public function rebuild(): bool {

        global $wpdb;
        
        $unique_words = [];

        if ( empty( $this->target_acf_fields ) ) {
            return false;
        }

        // Prepare the SQL IN clause securely for our custom field keys
        $format = implode( ',', array_fill( 0, count( $this->target_acf_fields ), '%s' ) );
        
        // Direct SQL selection to quickly pluck values without loading heavy post objects
        $query = $wpdb->prepare(
            "SELECT DISTINCT meta_value 
             FROM {$wpdb->postmeta} 
             WHERE meta_key IN ($format) AND meta_value != ''",
            $this->target_acf_fields
        );

        $meta_values = $wpdb->get_col( $query );

        if ( empty( $meta_values ) ) {
            return false;
        }

        // Extract, normalise, and split raw field text into isolated words
        foreach ( $meta_values as $value ) {
            // Unserialize data if any ACF fields are stored as arrays (like multi-select check-boxes)
            if ( is_serialized( $value ) ) {
                $unserialized = maybe_unserialize( $value );
                if ( is_array( $unserialized ) ) {
                    foreach ( $unserialized as $sub_val ) {
                        $this->extract_clean_words( $sub_val, $unique_words );
                    }
                    continue;
                }
            }

            $this->extract_clean_words( $value, $unique_words );
        }

        // Include terms from the 'effects' taxonomy in the dictionary.
        $effect_terms = get_terms( [
            'taxonomy'   => 'effect', 
            'hide_empty' => true,
        ] );

        if ( ! is_wp_error( $effect_terms ) && ! empty( $effect_terms ) ) {
            foreach ( $effect_terms as $term ) {
                $this->extract_clean_words( $term->name, $unique_words );
            }
        }

        // If there are no unique words extracted, return false early.
        if ( empty( $unique_words ) ) return false;

        // Deduplicate the array and clean up keys
        $final_dictionary = array_values( array_unique( $unique_words ) );

        // Save to WordPress options table permanently
        return update_option( self::CACHE_KEY, $final_dictionary );
    }

    /**
     * Helper to split text strings down into valid index words.
     */
    private function extract_clean_words( string $text, array &$unique_words ): void {
        $normalised = SearchNormalizer::normalise( $text );
        $words = array_filter( preg_split( '/\s+/', $normalised ) );

        foreach ( $words as $word ) {
            // Strip punctuation and keep words longer than 3 characters
            $clean_word = preg_replace( '/[^\w\s]/u', '', $word );

            // If the word is 3 chars or less, or is in the blacklist, skip it.
            if ( strlen( $clean_word ) < 3 || in_array( $clean_word, $this->blacklist, true ) ) {
                continue;
            }

            // Add the clean word to the unique words array.
            $unique_words[] = $clean_word;
        }
    }
}