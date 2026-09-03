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
     *
     * This function attempts to provide alternative search phrases by comparing
     * the user's query against a cached dictionary of valid terms. It generates
     * combinations of corrected words and returns a limited number of suggestions.
     *
     * @param string $query
     * @param array $cached_dictionary
     * @param integer $limit
     * @return array|null
     */
    public function get_suggestions( string $query, array $cached_dictionary, int $limit = 1 ) {

        // Normalise user query
        $query = SearchNormalizer::normalise( $query );
    
        // Split the query into individual words
        $words = array_filter( preg_split( '/\s+/', $query ) );
        
        // Array to hold possible corrections for each term in the query
        $slots = [];

        // Prepare slots for each word's possible corrections
        $has_corrections = false;

        // Loop through each word in the query to find possible corrections
        foreach ( $words as $word ) {
        
            // Check if the word is already in the cached dictionary
            if ( in_array( $word, $cached_dictionary, true ) ) {
                $slots[] = [ $word ];
                continue;
            }

            // Array to hold candidate corrections for the current word
            $candidates = [];
            
            // Determine the maximum allowed Levenshtein distance based on word length
            $max_allowed_distance = $this->max_allowed_distance( $word );

            // Loop through each valid term in the cached dictionary to find close matches
            foreach ( $cached_dictionary as $valid_term ) {

                // Skip very short valid terms to avoid irrelevant corrections
                if ( strlen( $valid_term ) < 3 ) continue;

                // Calculate the Levenshtein distance between the current word and the valid term
                $distance = levenshtein( $word, $valid_term );
                
                // If the distance is within the allowed threshold, consider it a candidate
                if ( $distance <= $max_allowed_distance ) {
                    $candidates[] = [
                        'word'     => $valid_term,
                        'distance' => $distance
                    ];
                }
            }

            // Sort the candidate corrections by their Levenshtein distance (closest matches first)
            usort( $candidates, function( $a, $b ) {
                return $a['distance'] <=> $b['distance'];
            });

            // Extract the words from the sorted candidate corrections for the current slot
            $slot_words = array_column( $candidates, 'word' );

            // If there are candidate corrections, add them to the slots; otherwise, keep the original word
            if ( ! empty( $slot_words ) ) {
                $slots[] = $slot_words;
                $has_corrections = true;
            } else {
                $slots[] = [ $word ];
            }
        }

        // If no corrections were found, return null early
        if ( ! $has_corrections ) {
            return null;
        }

        // Generate only the number of phrase combinations the caller can receive.
        $phrases = $this->generate_phrase_combinations( $slots, $limit );
        
        // Filter out the original query from the generated phrases
        $phrases = array_filter( $phrases, function( $phrase ) use ( $query ) {
            return $phrase !== $query;
        });

        // Limit the number of final suggestions to the specified limit
        $final_suggestions = array_slice( array_values( $phrases ), 0, $limit );

        // If there are no final suggestions after limiting, return null
        if ( empty( $final_suggestions ) ) {
            return null;
        }

        return 1 === count($final_suggestions) ? [$final_suggestions[0]] : $final_suggestions;
    }

    /**
     * Generate all possible phrase combinations from the correction slots.
     *
     * This function recursively generates all possible combinations of words from the given slots,
     * starting from the specified index. Each slot contains candidate corrections for a word in the query.
     *
     * @param array $slots An array of arrays, where each inner array contains candidate corrections for a word.
     * @param integer $index The current index in the slots array to process.
     * @return array An array of generated phrase combinations.
     *
     * @param array $slots
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
            $word_length <= 5 => $this->max_distance_short,
            default           => $this->max_distance_long,
        };
    }
}