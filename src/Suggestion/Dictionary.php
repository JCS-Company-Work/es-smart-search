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
    private array $manual_additions = [];

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

        // Fetch manual entries additions list from the WordPress options table.
        $raw_additions = get_option( 'esss_manual_additions', '' );
        $parsed_additions = array_map( 'trim', explode( ',', strtolower( $raw_additions ) ) );
        $this->manual_additions = array_filter( $parsed_additions );

    }

    /**
     * Register hooks to update manual additions and ignored terms
     */
    public static function register(): void {

        // Update dictionary on product save
        add_action( 'save_post_product', [ self::class, 'handle_product_save' ], 20, 3 );

        // Update manual additions and ignored in wp_options when they are created/updated and rebuild the dictionary
        add_action( 'add_option_esss_manual_additions',    [ self::class, 'handle_options_save' ], 20, 0 );
        add_action( 'update_option_esss_manual_additions', [ self::class, 'handle_options_save' ], 20, 0 );
        add_action( 'add_option_esss_ignored_terms',       [ self::class, 'handle_options_save' ], 20, 0 );
        add_action( 'update_option_esss_ignored_terms',    [ self::class, 'handle_options_save' ], 20, 0 );
    }

    /**
     * Rebuild wrapper for product saves.
     */
    public static function handle_product_save( int $post_id ): void {

        // If user cannot edit the post, bail early.
        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Instantiate the dictionary object.
        $dictionary = new self();

        // Rebuild the dictionary.
        $dictionary->rebuild();
    }

    /**
     * Rebuild wrapper for options panel updates.
     */
    public static function handle_options_save(): void {

        // If user cannot manage options, bail early.
        if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Instantiate the dictionary object.
        $dictionary = new self();

        // Rebuild the dictionary.
        $dictionary->rebuild();
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

                // Merge manual additions directly into the unique pool
        if ( ! empty( $this->manual_additions ) ) {
            foreach ( $this->manual_additions as $word ) {
                $clean_word = preg_replace( '/[^\w\s]/u', '', SearchNormalizer::normalise( $word ) );
                if ( strlen( $clean_word ) >= 3 ) {
                    $unique_words[] = $clean_word;
                }
            }
        }

        // If there are no unique words extracted, return false early.
        if ( empty( $unique_words ) ) return false;

        // Clean out duplicates first
        $unique_words = array_unique( $unique_words );

        // Move the blacklist check here to filter out words found in the automated DB rows
        if ( ! empty( $this->blacklist ) ) {
            $unique_words = array_filter( $unique_words, function( $word ) {
                return ! in_array( $word, $this->blacklist, true );
            });
        }

        // Deduplicate the array and clean up keys
        $final_dictionary = array_values( $unique_words );

        // Save to WordPress options table permanently
        return update_option( self::CACHE_KEY, $final_dictionary );
    }

    /**
     * Helper to split text strings down into valid index words using the pre-parsed memory blacklist array.
     */
    private function extract_clean_words( string $text, array &$unique_words ): void {
        $normalised = SearchNormalizer::normalise( $text );
        $words = array_filter( preg_split( '/\s+/', $normalised ) );

        foreach ( $words as $word ) {
            $clean_word = preg_replace( '/[^\w\s]/u', '', $word );

            // Removed the blacklist array check from here to allow global post-processing override
            if ( strlen( $clean_word ) < 3 ) {
                continue;
            }

            $unique_words[] = $clean_word;
        }
    }

}