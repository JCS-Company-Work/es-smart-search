<?php

namespace EsSmartSearch\Indexing;

class SearchMatcher {

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
            $values = array_filter( array_map( [ 'EsSmartSearch\SearchNormalizer', 'normalise' ], $values ) );

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
     * Score the given batch based on weighting and batch values
     *
     * @param string $query
     * @param array $batch
     * @param array $matched_fields
     * @return int
     */
    public function score_batch( $query, $batch, &$matched_fields = [] ) {

        // Normalise the query for consistent matching
        $query = SearchNormalizer::normalise( $query );
    
        // Split the query into individual words
        $words = array_filter( preg_split( '/\s+/', $query ) );
    
        // Initialize the score for this batch
        $score = 0;
    
        // Extract the fields from the batch for scoring
        $fields = $batch['fields'];
    
        // Define the weights for each field group and the groups that allow fuzzy matching
        $weights = [
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
        $fuzzy_groups = [ 'title', 'colour', 'effect', 'category', 'factory' ];
        
        // Define a list of common terms to ignore during scoring
        $ignored_terms = [ 'tile', 'tiles', 'porcelain', 'product', 'products' ];

        foreach ( $words as $word ) {

            // Skip ignored terms to avoid inflating scores for common words
            if ( in_array( $word, $ignored_terms, true ) ) continue;

            // Initialize the score for this word within the batch
            $word_score = 0;

            // Iterate over each field group and its associated weight to calculate the word score
            foreach ( $weights as $group => $weight ) {
                foreach ( $fields[ $group ] ?? [] as $value ) {
                    if ( '' !== $value && false !== strpos( $value, $word ) ) {
                        $word_score = max( $word_score, $weight );
                        $matched_fields[ $group ] = $weight;
                    }
                }
            }

            if ( $word_score > 0 ) {
                $score += $word_score;
                continue;
            }

            if ( in_array( $word, [ 'floor', 'wall', 'outdoor' ], true ) || preg_match( '/^\d+x\d+$/', $word ) ) {
                return 0;
            }

            // Initialize the closest distance and matching group for fuzzy matching
            $closest = PHP_INT_MAX;

            $closest_group = null;

            // Loop over the fuzzy groups to find the closest match for the word
            foreach ( $fuzzy_groups as $group ) {

                // Skip empty groups to avoid unnecessary processing
                if ( empty( $fields[ $group ] ?? [] ) ) continue;

                // Loop over each value in the group to calculate the Levenshtein distance
                foreach ( $fields[ $group ] ?? [] as $value ) {

                    // Skip empty values to avoid unnecessary processing
                    if ( '' === $value ) continue;

                    foreach ( preg_split( '/\s+/', $value ) as $candidate ) {

                        // Skip short words to avoid false positives in fuzzy matching
                        if ( strlen( $word ) < 4 || strlen( $candidate ) < 4 ) continue;

                        // Calculate the distance between the search word and candidate
                        $distance = levenshtein( $word, $candidate );

                        // Store the closest match and the group it belongs to
                        if ( $distance < $closest ) {

                            $closest = $distance;

                            $closest_group = $group;

                        }

                    }

                }

            }

            // If a close match was found, award points based on the matching group; otherwise, return 0
            if ( $closest <= 2 && $closest_group ) {

                $fuzzy_score = (int) ( $weights[ $closest_group ] * 0.3 );

                $score += $fuzzy_score;

                $matched_fields[ $closest_group ] = $fuzzy_score;

            } else {

                return 0;

            }
        }

        return $score;
    }

}