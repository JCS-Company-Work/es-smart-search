<?php

namespace EsSmartSearch\Indexing;

use EsSmartSearch\Indexing\SearchNormalizer;

class SearchMatcher {

    // Define the weights for each field group and the groups that allow fuzzy matching
    private array $weights = [
        'product_code' => 100,
        'size'         => 90,
        'usage'        => 80,
        'colour'       => 70,
        'effect'       => 65,
        'category'     => 60,
        'finish'       => 55,
        'title'        => 50,
        'factory'      => 35,
    ];

    // Define the groups that allow fuzzy matching
    private array $fuzzy_groups = [ 'title', 'colour', 'effect', 'category', 'factory' ];

    // Define a list of common terms to ignore during scoring
    private array $ignored_terms = [ 'tile', 'tiles', 'porcelain', 'product', 'products' ];

    /**
     * Get the weights for the active filter matches.
     *
     * @param array<string, mixed> $filters The active filters.
     * @return array<string, int> The weights for the matched filters.
     */
    public function get_filter_match_weights( $filters ) {

        // Define the default weights for each filter group.
        $weights = [
            'colour'      => 70,
            'effect'      => 65,
            'category'    => 60,
            'finish'      => 55,
            'size'        => 90,
            'dimensions'  => 90,
            'usage'       => 80,
            'thickness'   => 55,
            'slip_rating' => 55,
            'discount'    => 40,
            'quantity'    => 40,
        ];

        // Initialize an array to store the matched filter weights.
        $matched = [];

        // Loop over the active filters and collect their corresponding weights.
        foreach ( $filters as $group => $values ) {
            $group = 'categories' === $group ? 'category' : $group;
            if ( isset( $weights[ $group ] ) ) {
                $matched[ $group ] = $weights[ $group ];
            }
        }

        // Return matches for the active filters.
        return $matched;
    }

    /**
     * Extract the active filter values for scoring.
     *
     * @param array<string, mixed> $filters The active filters.
     * @return array<string, string> The extracted filter values for scoring.
     */
    public function get_filter_match_values( $filters ) {

        // Init matched array for storing filter values.
        $matched = [];

        // Loop over the active filters and collect their corresponding values for scoring.
        foreach ( $filters as $group => $values ) {
            $group = 'categories' === $group ? 'category' : $group;
            $values = is_array( $values ) ? $values : [ $values ];
            $matched[ $group ] = implode( ', ', array_map( 'sanitize_text_field', $values ) ) . ' (filter)';
        }

        return $matched;
    }

    /**
     * Determine if the given fields match the specified filters.
     *
     * @param array $fields The batch fields to check.
     * @param array $filters The active filter groups and their values.
     * @return bool True if the fields satisfy all filters, false otherwise.
     */
    public function matches_filters( $fields, $filters ) {
        // Iterate over each filter group and its values.
        foreach ( $filters as $group => $values ) {

            // Map 'categories' to 'category' for consistency.
            $group = 'categories' === $group ? 'category' : $group;
        
            // Map 'dimensions' to 'size' for consistency.
            $group = 'dimensions' === $group ? 'size' : $group;
        
            // Ensure the filter values are in array format.
            $values = is_array( $values ) ? $values : [ $values ];
        
            // Normalize the filter values with search index method.
            $values = array_filter( array_map( [ SearchNormalizer::class, 'normalise' ], $values ) );

            // If the filter values are empty or the corresponding fields are not present, return false.
            if ( empty( $values ) || empty( $fields[ $group ] ) ) return false;

            if ( 'quantity' === $group ) {

                // Extract the quantity from the fields.
                $quantity = (float) $fields['quantity'][0];
                
                // Initialize a flag to track if the quantity matches any of the specified bands.
                $matches_quantity = false;

                // Iterate over each specified quantity band and check for matches.
                foreach ( $values as $band ) {
                    if ( preg_match( '/^sqm-(\d+)-(\d+)$/', $band, $matches ) ) {
                        $matches_quantity = $matches_quantity || ( $quantity >= (float) $matches[1] && $quantity <= (float) $matches[2] );
                    } elseif ( preg_match( '/^sqm-(\d+)\+$/', $band, $matches ) ) {
                        $matches_quantity = $matches_quantity || $quantity >= (float) $matches[1];
                    }
                }

                if ( ! $matches_quantity ) {
                    return false;
                }

                continue;
            }

            if ( ! array_intersect( $values, $fields[ $group ] ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Score batch based on the query and the batch's field values.
     *
     * @param string $query
     * @param array $batch
     * @param array $matched_fields
     * @return integer
     */
    public function score_batch( string $query, array $batch, array &$matched_fields = [] ): int {
        
        // Normalise and split the query into individual words
        $query = SearchNormalizer::normalise( $query );
        $words = array_filter( preg_split( '/\s+/', $query ) );
        
        $score = 0;
        $fields = $batch['fields'];

        foreach ( $words as $word ) {
            // Skip common words like "tile" or "porcelain"
            if ( in_array( $word, $this->ignored_terms, true ) ) {
                continue;
            }

            // Try to find an exact match first
            $word_score = $this->calculate_exact_score( $word, $fields, $matched_fields );
            if ( $word_score > 0 ) {
                $score += $word_score;
                continue; // Exact match found! Skip to the next search word.
            }

            // Hard exclusion rules (e.g., strict category filtering words)
            if ( in_array( $word, [ 'floor', 'wall', 'outdoor' ], true ) || preg_match( '/^\d+x\d+$/', $word ) ) {
                return 0;
            }

            // Fallback to fuzzy matching since exact matching found nothing
            $score += $this->calculate_fuzzy_score( $word, $fields, $matched_fields );
        }

        return $score;
    }

    /**
     * Check all fields for an exact substring match of a single word.
     *
     * @param string $word           The single search word (e.g., "blue").
     * @param array  $fields         The batch fields (e.g., $batch['fields']).
     * @param array  $matched_fields Passed by reference to log what groups matched.
     * @return int                   The highest exact match score found, or 0.
     */
    private function calculate_exact_score( string $word, array $fields, array &$matched_fields ): int {
        $word_score = 0;

        foreach ( $this->weights as $group => $weight ) {
            // If this field group doesn't exist in the batch data, skip it
            if ( empty( $fields[ $group ] ) ) {
                continue;
            }

            foreach ( $fields[ $group ] as $value ) {
                // Check if the search word exists inside the field text
                if ( '' !== $value && false !== strpos( $value, $word ) ) {
                    // If a word matches multiple fields, we keep the highest scoring group weight
                    $word_score = max( $word_score, $weight );
                    $matched_fields[ $group ] = $weight;
                }
            }
        }

        return $word_score;
    }

    /**
    * Check permitted fuzzy groups for close spelling matches.
    *
    * @param string $word           The single search word (e.g., "blue").
    * @param array  $fields         The batch fields.
    * @param array  $matched_fields Passed by reference to log fuzzy matches.
    * @return int                   The penalized fuzzy match score, or 0.
    */
    private function calculate_fuzzy_score( string $word, array $fields, array &$matched_fields ): int {
        $closest = PHP_INT_MAX;
        $closest_group = null;
        
        // Enforce strict limits: 4-letter words get 1 typo max. Longer words get 2 typos max.
        $max_allowed_distance = strlen( $word ) <= 4 ? 1 : 2;

        // Loop over each field group that allows fuzzy match to find potential close matches.
        foreach ( $this->fuzzy_groups as $group ) {
            
            // Skip field if it doesn't exist in the batch data
            if ( empty( $fields[ $group ] ) ) continue;

            // Loop over each value in the current field group.
            foreach ( $fields[ $group ] as $value ) {
                
                // Skip empty values early to reduce unnecessary processing.
                if ( '' === $value ) continue;

                // Split the field text into individual candidate words
                foreach ( preg_split( '/\s+/', $value ) as $candidate ) {
                    
                    // Skip short words to prevent false positives
                    if ( strlen( $word ) < 4 || strlen( $candidate ) < 4 ) continue;

                    // Calculate the Levenshtein distance 
                    // (number of single-character edits needed to transform one word into the other)
                    $distance = levenshtein( $word, $candidate );

                    // Track the absolute closest match that falls under our strict limit
                    if ( $distance <= $max_allowed_distance && $distance < $closest ) {

                        // Update the closest match and its group.
                        $closest = $distance;
                        
                        // Record the group of the closest match.
                        $closest_group = $group;

                    }
                }
            }
        }

        // If we found a valid fuzzy match under our cap, apply a 30% penalty.
        if ( $closest_group !== null && $closest <= $max_allowed_distance ) {

            // Apply a 30% penalty to the weight of the closest fuzzy match because it is not an exact match.
            $fuzzy_weight = (int) ( $this->weights[ $closest_group ] * 0.7 );
            
            // Record the penalized fuzzy match in the matched fields array.
            $matched_fields[ $closest_group . '_fuzzy' ] = $fuzzy_weight;
            
            // Return the penalized fuzzy match score.
            return $fuzzy_weight;
        }

        return 0;
    }

}