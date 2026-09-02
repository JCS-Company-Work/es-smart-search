<?php

namespace EsSmartSearch\Indexing\Suggestion;

use EsSmartSearch\Indexing\SearchNormalizer;

class Service {

    /**
     * Compare a query against the vocabulary dictionary to find typos.
     */
    public function get_suggestions( string $query, array $cached_dictionary, int $limit = 1 ) {
        $query = SearchNormalizer::normalise( $query );
        $words = array_filter( preg_split( '/\s+/', $query ) );
        
        $slots = [];
        $has_corrections = false;

        foreach ( $words as $word ) {
            if ( in_array( $word, $cached_dictionary, true ) ) {
                $slots[] = [ $word ];
                continue;
            }

            $candidates = [];
            $max_allowed_distance = strlen( $word ) <= 4 ? 2 : 4;

            foreach ( $cached_dictionary as $valid_term ) {
                if ( strlen( $valid_term ) < 3 ) continue;

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
error_log( 'Slot words for "' . $word . '": ' . implode( ', ', $slot_words ) );
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

        $phrases = $this->generate_phrase_combinations( $slots );
        $phrases = array_filter( $phrases, function( $phrase ) use ( $query ) {
            return $phrase !== $query;
        });

        $final_suggestions = array_slice( array_values( $phrases ), 0, $limit );

        if ( empty( $final_suggestions ) ) {
            return null;
        }

        return 1 === $limit ? $final_suggestions : $final_suggestions;
    }

    private function generate_phrase_combinations( array $slots, int $index = 0 ): array {
        if ( $index >= count( $slots ) ) {
            return [''];
        }

        $results = [];
        $sub_combinations = $this->generate_phrase_combinations( $slots, $index + 1 );

        foreach ( $slots[ $index ] as $word ) {
            foreach ( $sub_combinations as $combination ) {
                $results[] = trim( $word . ' ' . $combination );
            }
        }

        return $results;
    }
}