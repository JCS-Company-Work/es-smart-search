<?php

namespace EsSmartSearch\CLI;

use EsSmartSearch\Suggestion\Dictionary;
use EsSmartSearch\Suggestion\Service;

class SearchReportingBackfill {

    public static function run(): void {

        global $wpdb;

        $table_name = $wpdb->prefix . 'es_smart_search_events';

        $dictionary = new Dictionary();
        $service    = new Service( $dictionary );

        $terms = $dictionary->get_terms();

        if ( empty( $terms ) ) {
            \WP_CLI::error( 'Dictionary is empty.' );
        }

        \WP_CLI::success(
            'Dictionary loaded: ' . count( $terms ) . ' terms.'
        );

        // Load the existing blacklist.
        $raw_blacklist = get_option( 'esss_ignored_terms', '' );

        $blacklist = array_filter(
            array_map(
                'trim',
                explode( ',', strtolower( $raw_blacklist ) )
            )
        );

        // Load existing reporting rows.
        $rows = $wpdb->get_results(
            "SELECT id, query_normalised, has_results
            FROM {$table_name}"
        );

        \WP_CLI::success( 'Rows loaded: ' . count( $rows ) );

        foreach ( $rows as $row ) {

            $query = trim( strtolower( $row->query_normalised ) );

            // Skip empty searches.
            if ( $query === '' ) {
                \WP_CLI::log(
                    $row->id . ': empty query → skipped'
                );

                continue;
            }

            // Skip searches containing only blacklisted terms.
            $raw_query_words = array_filter(
                explode( ' ', $query )
            );

            $query_words = array_filter(
                $raw_query_words,
                function ( $word ) use ( $blacklist ) {
                    return ! in_array( $word, $blacklist, true );
                }
            );

            if ( ! empty( $raw_query_words ) && empty( $query_words ) ) {
                \WP_CLI::log(
                    $row->id . ': ' . $query
                    . ' → skipped (blacklisted terms only)'
                );

                continue;
            }

            // Determine the match type.
            $match_type      = '';
            $suggested_value = null;

            if ( in_array( $query, $terms, true ) ) {
                $match_type = 'direct';

            } elseif ( (int) $row->has_results === 1 ) {
                $match_type = 'fuzzy';

            } else {
                $suggestions = $service->get_suggestions(
                    $query,
                    $terms,
                    1
                );

                if ( ! empty( $suggestions ) ) {
                    $match_type      = 'suggestion';
                    $suggested_value = $suggestions[0];
                } else {
                    $match_type = 'no_results';
                }
            }

            // Save the classification and suggestion.
            $result = $wpdb->update(
                $table_name,
                [
                    'match_type' => $match_type,
                    'suggestion' => $suggested_value,
                ],
                [
                    'id' => (int) $row->id,
                ],
                [
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                ]
            );

            if ( $result === false ) {
                \WP_CLI::warning(
                    'Failed to update row ' . $row->id . ': ' . $wpdb->last_error
                );

                continue;
            }

            \WP_CLI::log(
                $row->id . ': ' . $query
                . ' → ' . $match_type
                . ( $suggested_value ? ' → ' . $suggested_value : '' )
            );
        }

        \WP_CLI::success( 'Backfill complete.' );
    }
}