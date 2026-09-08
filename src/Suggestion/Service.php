<?php

namespace EsSmartSearch\Suggestion;

use EsSmartSearch\Indexing\SearchNormalizer;

class Service {

    // Define private properties to hold settings in memory
    private int $max_distance_short;
    private int $max_distance_long;

    /**
     * Pull settings from options table once on boot
     */
    public function __construct() {
        // Fetch values once and cache them in memory with safe fallbacks
        $this->max_distance_short = (int) get_option( 'esss_max_distance_short', 1 );
        $this->max_distance_long  = (int) get_option( 'esss_max_distance_long', 2 );
    }

    /**
     * Get search suggestions based on the query and cached dictionary.
     * Incorporates dynamic administrative synonym tracking blocks.
     *
     * @param string $query
     * @param array $cached_dictionary
     * @param integer $limit
     * @return array|null
     */
    public function get_suggestions( string $query, array $cached_dictionary, int $limit = 1 ) {

        // Normalise query text down to clean lowercase bounds
        $query = SearchNormalizer::normalise( $query );

        // Fetch the raw synonym mappings from the options table
        $raw_synonyms = get_option( 'esss_synonyms', '' );
        
        if ( ! empty( $raw_synonyms ) ) {

            // Array to hold synonym mappings
            $synonym_map = [];
            
            // Clean out carriage returns and split into separate lines
            $lines = array_filter( explode( "\n", str_replace( "\r", "", strtolower( $raw_synonyms ) ) ) );
            
            foreach ( $lines as $line ) {

                // Skip empty lines or lines that don't contain the '=>' delimiter
                if ( strpos( $line, '=>' ) !== false ) {

                    // Split the line into trigger and replacement parts
                    list( $trigger, $replacements ) = explode( '=>', $line, 2 );
                    $trigger = trim( $trigger );
                    
                    // Split comma-separated matches into clean array tokens
                    $matches = array_filter( array_map( 'trim', explode( ',', $replacements ) ) );
                    
                    if ( ! empty( $trigger ) && ! empty( $matches ) ) {
                        $synonym_map[$trigger] = $matches;
                    }
                }
            }

            // Check if the entire multi-word phrase (e.g. "off white") hits a synonym mapping
            if ( array_key_exists( $query, $synonym_map ) ) {
                $synonym_suggestions = array_slice( $synonym_map[$query], 0, $limit );
                return empty( $synonym_suggestions ) ? null : $synonym_suggestions;
            }
        }
        
        // Split the query into individual words for standard Levenshtein calculations
        $words = array_filter( preg_split( '/\s+/', $query ) );
        $slots = [];
        $has_corrections = false;

        foreach ( $words as $word ) {
            if ( in_array( $word, $cached_dictionary, true ) ) {
                $slots[] = [ $word ];
                continue;
            }

            $candidates = [];
            $max_allowed_distance = $this->max_allowed_distance( $word );

            foreach ( $cached_dictionary as $valid_term ) {
                if ( strlen( $valid_term ) < 3 ) {
                    continue;
                }

                $distance = levenshtein( $word, $valid_term );
                if ( $distance <= $max_allowed_distance ) {
                    $candidates[] = [
                        'word'     => $valid_term,
                        'distance' => $distance
                    ];
                }
            }

            usort( $candidates, function( $a, $b ) {
                return $a['distance'] <=> $b['distance'];
            });

            $slot_words = array_column( $candidates, 'word' );

            if ( ! empty( $slot_words ) ) {
                $slots[] = $slot_words;
                $has_corrections = true;
            } else {
                $slots[] = [ $word ];
            }
        }

        if ( ! $has_corrections ) {
            return null;
        }

        // Generate phrase variations without relying on recursive index parameters
        $phrases = $this->generate_phrase_combinations( $slots, $limit );
        
        $phrases = array_filter( $phrases, function( $phrase ) use ( $query ) {
            return $phrase !== $query;
        });

        $final_suggestions = array_slice( array_values( $phrases ), 0, $limit );

        if ( empty( $final_suggestions ) ) {
            return null;
        }

        return $final_suggestions;
    }


    /**
     * Generate all possible phrase combinations from the correction slots.
     *
     * This function recursively generates all possible combinations of words from the given slots,
     * starting from the specified index. Each slot contains candidate corrections for a word in the query.
     *
     * @param array $slots An array of arrays, where each inner array contains candidate corrections for a word.
     * @param integer $index The current index in the slots array to process.
     * @param integer $limit The maximum number of phrase combinations to generate.
     * @return array An array of generated phrase combinations.
     *
     * @param array $slots
     * @param integer $limit The maximum number of phrase combinations to generate.
     * @param integer $index
     * @return array
     */
    private function generate_phrase_combinations( array $slots, int $limit, int $index = 0 ): array {

        if ( $limit <= 0 ) {
            return [];
        }
        
        // If the current index exceeds the number of slots, return an array with an empty string as the base case.
        if ( $index >= count( $slots ) ) {
            return [''];
        }

        // Array to hold the generated phrase combinations.
        $results = [];

        // Recursively generate combinations for the remaining slots.
        $sub_combinations = $this->generate_phrase_combinations( $slots, $limit, $index + 1 );

        // Combine each word in the current slot with each of the sub-combinations.
        foreach ( $slots[ $index ] as $word ) {
            foreach ( $sub_combinations as $combination ) {
                $results[] = trim( $word . ' ' . $combination );

                if ( count( $results ) >= $limit ) {
                    return $results;
                }
            }
        }

        // Return the generated phrase combinations.
        return $results;
    }

    /**
     * Determine the max allowed distance based on the word length. 
     * Under 3 characters, no distance is allowed. For short words, 
     * use the short distance setting. For longer words, use the long distance setting.
     * Both settings are incremented by one to allow suggestions to take place
     * after fuzzy matching gives no results
     *
     * @param string $word
     * @return integer
     */
    private function max_allowed_distance( string $word ): int {

        // Get the length of the word to determine the max allowed distance.
        $word_length = strlen( $word );
    
        // Return word length value based on the defined distance settings.
        return match ( true ) {
            $word_length <= 3 => 0,
            $word_length <= 5 => $this->max_distance_short + 1,
            default           => $this->max_distance_long + 1,
        };
    }
}