<?php

namespace EsSmartSearch\Indexing;

class SearchNormalizer {

    /**
     * Normalise a string for consistent search matching.
     *
     * @param string $value
     * @return string Normalized value with accents removed, lowercased, and special characters replaced.
     */
    public static function normalise( $value ) {
        $value = strtolower( remove_accents( (string) $value ) );
        $value = str_replace( [ '×', '-', '/', ',' ], ' ', $value );
        $value = preg_replace( '/(\d+)\s*(?:x|by)\s*(\d+)/', '$1x$2', $value );
        $value = preg_replace( '/\s+/', ' ', $value );

        return trim( $value );
    }

}