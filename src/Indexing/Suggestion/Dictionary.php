<?php

namespace EsSmartSearch\Indexing\Suggestion;

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

    /**
     * Register the class with WordPress hooks.
     */
    public static function register(): void {
        $instance = new self();
        
        // Hooks directly into itself
        add_action( 'acf/save_post', [ $instance, 'handle_acf_save' ], 20, 1 );
    }

    /**
     * Action hook callback to intercept product saves.
     * @param int|string $post_id The ID of the post being saved.
     */
    public function handle_acf_save( $post_id ): void {
        if ( ! is_numeric( $post_id ) || 'product' !== get_post_type( $post_id ) ) {
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

            if ( strlen( $clean_word ) >= 4 ) {
                $unique_words[] = $clean_word;
            }
        }
    }
}