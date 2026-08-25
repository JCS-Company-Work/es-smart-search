<?php

namespace EsSmartSearch;

use WP_Query;

class Search {

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( 'emporio-search/v1', '/search', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'esss_search' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'q' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );
    }

    /**
     * Search the cached batch index and return ranked matching IDs.
     *
     * @param WP_REST_Request $request Query and filter parameters.
     * @return WP_REST_Response
     */
    public function esss_search( \WP_REST_Request $request ) {
        $query = trim( (string) $request->get_param( 'q' ) );
        $filters = json_decode( (string) $request->get_param( 'filters' ), true );
        $filters = is_array( $filters ) ? $filters : [];
        $index_source = 'empty';

        if ( '' === $query && empty( $filters ) ) {
            header( 'X-ESSS-Index-Source: empty' );
            return rest_ensure_response( [
                'query'   => '',
                'matches' => [],
                'count'   => 0,
            ] );
        }

        $searchable_batches = $this->esss_get_searchable_batches( $index_source );
        header( 'X-ESSS-Index-Source: ' . $index_source );
        header( 'X-ESSS-Index-Count: ' . count( $searchable_batches ) );
        $matches            = [];

        foreach ( $searchable_batches as $batch ) {
            if ( ! $this->esss_matches_filters( $batch['fields'], $filters ) ) {
                continue;
            }

            $matched_fields = $this->esss_get_filter_match_weights( $filters );
            $matched_values = $this->esss_get_filter_match_values( $filters );
            $score = $this->esss_score_batch( $query, $batch, $matched_fields );
            $score = '' === $query ? 1 : $score;

            if ( $score > 0 ) {
                $matches[] = [
                    'id'            => $batch['id'],
                    'score'         => $score,
                    'matched_fields' => $matched_fields,
                    'matched_values' => $matched_values,
                ];
            }
        }

        usort( $matches, function( $left, $right ) {
            return $right['score'] <=> $left['score'];
        } );

        return rest_ensure_response( [
            'query'   => $query,
            'matches' => array_map( 'intval', wp_list_pluck( $matches, 'id' ) ),
            'count'   => count( $matches ),
            'ranking' => $matches,
        ] );
    }

    /** @return array<string, int> Weights used to describe active exact filters. */
    private function esss_get_filter_match_weights( $filters ) {
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
        $matched = [];

        foreach ( $filters as $group => $values ) {
            $group = 'categories' === $group ? 'category' : $group;
            if ( isset( $weights[ $group ] ) ) {
                $matched[ $group ] = $weights[ $group ];
            }
        }

        return $matched;
    }

    /** @return array<string, string> Active filter values shown in diagnostics. */
    private function esss_get_filter_match_values( $filters ) {
        $matched = [];

        foreach ( $filters as $group => $values ) {
            $group = 'categories' === $group ? 'category' : $group;
            $values = is_array( $values ) ? $values : [ $values ];
            $matched[ $group ] = implode( ', ', array_map( 'sanitize_text_field', $values ) ) . ' (filter)';
        }

        return $matched;
    }

    /**
     * Return the cached index, rebuilding it when unavailable.
     *
     * @param string $index_source Set to cache, rebuilt or unknown.
     * @return array<int, array<string, mixed>>
     */
    private function esss_get_searchable_batches( &$index_source = 'unknown' ) {
        $cached = get_transient( ESSS_INDEX_TRANSIENT );

        if ( false !== $cached && is_array( $cached ) ) {
            $index_source = 'cache';
            return $cached;
        }

        $batches = $this->esss_build_searchable_batches();
        $index_source = 'rebuilt';
        $expiration = 30 * DAY_IN_SECONDS;

        set_transient( ESSS_INDEX_TRANSIENT, $batches, $expiration );

        return $batches;
    }

    /** @return array<int, array<string, mixed>> Structured records for searchable batches. */
    private function esss_build_searchable_batches() {
        $query = new WP_Query( [
            'post_type'      => 'batch',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_stock',
                    'value'   => 1,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'   => '_stock_status',
                    'value' => 'instock',
                ],
            ],
        ] );

        $batches = [];

        foreach ( $query->posts as $batch_id ) {
            $product = wc_get_product( $batch_id );
            $fields  = get_fields( $batch_id );
            $terms   = wp_get_post_terms( $batch_id, 'category', [ 'fields' => 'names' ] );
            $effects = wp_get_post_terms( $batch_id, 'effect', [ 'fields' => 'names' ] );

            if ( ! $product ) {
                continue;
            }

            $sqm = (float) $product->get_stock_quantity() * (float) ( $fields['sqm_per_carton'] ?? 0 );

            $values = [
                $product->get_name(),
                get_post_field( 'post_title', $batch_id ),
                $this->esss_flatten_values( $fields ),
                $this->esss_get_usage( $fields['finish'] ?? '' ),
                is_wp_error( $terms ) ? '' : implode( ' ', $terms ),
            ];

            $batches[] = [
                'id'     => (int) $batch_id,
                'text'   => $this->esss_normalise( implode( ' ', $values ) ),
                'fields' => [
                    'title'      => [ $this->esss_normalise( $product->get_name() ), $this->esss_normalise( get_post_field( 'post_title', $batch_id ) ) ],
                    'colour'    => [ $this->esss_normalise( $fields['colour'] ?? '' ) ],
                    'effect'    => array_values( array_filter( [ $this->esss_normalise( is_wp_error( $effects ) ? '' : ( $effects[0] ?? '' ) ) ] ) ),
                    'finish'    => [ $this->esss_normalise( $fields['finish'] ?? '' ) ],
                    'size'      => $this->esss_get_size_values( $fields['dimensions'] ?? '' ),
                    'category'  => array_map( [ $this, 'esss_normalise' ], is_wp_error( $terms ) ? [] : $terms ),
                    'usage'     => array_map( [ $this, 'esss_normalise' ], $this->esss_get_usage_values( $fields['finish'] ?? '' ) ),
                    'thickness' => [ $this->esss_normalise( $fields['thickness'] ?? '' ) ],
                    'slip_rating' => [ $this->esss_normalise( $fields['slip_rating'] ?? '' ) ],
                    'discount'  => [ $this->esss_normalise( $fields['discount_percentage'] ?? '' ) ],
                    'quantity'  => [ $sqm ],
                    'factory'   => [ $this->esss_normalise( $fields['factory_name'] ?? '' ) ],
                    'product_code' => [ $this->esss_normalise( $fields['product_code'] ?? '' ) ],
                    'quantity'  => [ $sqm ],
                    'factory'   => [ $this->esss_normalise( $fields['factory_name'] ?? '' ) ],
                    'product_code' => [ $this->esss_normalise( $fields['product_code'] ?? '' ) ],
                ],
            ];
        }

        return $batches;
    }

    /** @return string[] Canonical dimension forms, including centimetre shorthand. */
    private function esss_get_size_values( $dimensions ) {
        $size = $this->esss_normalise( $dimensions );
        $values = $size ? [ $size ] : [];

        if ( preg_match( '/^(\d{3})x(\d{3})$/', $size, $matches ) ) {
            $values[] = ( (int) $matches[1] / 10 ) . 'x' . ( (int) $matches[2] / 10 );
        }

        return array_values( array_unique( $values ) );
    }

    /** @return bool Whether a batch satisfies every active filter group. */
    private function esss_matches_filters( $fields, $filters ) {
        foreach ( $filters as $group => $values ) {
            $group = 'categories' === $group ? 'category' : $group;
            $group = 'dimensions' === $group ? 'size' : $group;
            $values = is_array( $values ) ? $values : [ $values ];
            $values = array_filter( array_map( [ $this, 'esss_normalise' ], $values ) );

            if ( empty( $values ) || empty( $fields[ $group ] ) ) {
                return false;
            }

            if ( 'quantity' === $group ) {
                $quantity = (float) $fields['quantity'][0];
                $matches_quantity = false;

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

    /** @return string Space-separated derived usage labels for the index text. */
    private function esss_get_usage( $finish ) {
        return implode( ' ', $this->esss_get_usage_values( $finish ) );
    }

    /** @return string[] Usage labels derived from the controlled finish rules. */
    private function esss_get_usage_values( $finish ) {
        $usage_by_finish = [
            'floor'        => [ 'natural', 'structured' ],
            'wall'         => [ 'natural', 'polished', 'honed' ],
            'wall & floor' => [ 'natural' ],
            'outdoor'      => [ 'grip' ],
        ];
        $finish         = strtolower( trim( (string) $finish ) );
        $usage          = [];

        foreach ( $usage_by_finish as $label => $finishes ) {
            if ( in_array( $finish, $finishes, true ) ) {
                $usage[] = $label;
            }
        }

        return $usage;
    }

    /** @return string Scalar and nested ACF values flattened for fallback text. */
    private function esss_flatten_values( $values ) {
        if ( ! is_array( $values ) ) {
            return (string) $values;
        }

        $flattened = [];

        foreach ( $values as $value ) {
            if ( is_array( $value ) ) {
                $flattened[] = $this->esss_flatten_values( $value );
            } elseif ( is_scalar( $value ) ) {
                $flattened[] = (string) $value;
            }
        }

        return implode( ' ', $flattened );
    }

    /** @return string Lowercase, accent-free search text with canonical spacing. */
    private function esss_normalise( $value ) {
        $value = strtolower( remove_accents( (string) $value ) );
        $value = str_replace( [ '×', '-', '/', ',' ], ' ', $value );
        $value = preg_replace( '/(\d+)\s*(?:x|by)\s*(\d+)/', '$1x$2', $value );
        $value = preg_replace( '/\s+/', ' ', $value );

        return trim( $value );
    }

    /** @return int Relevance score, or zero when a required term cannot match. */
    private function esss_score_batch( $query, $batch, &$matched_fields = [] ) {
        $query = $this->esss_normalise( $query );
        $words = array_filter( preg_split( '/\s+/', $query ) );
        $score = 0;
        $fields = $batch['fields'];
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
        $fuzzy_groups = [ 'title', 'colour', 'effect', 'category', 'factory' ];
        $ignored_terms = [ 'tile', 'tiles', 'porcelain', 'product', 'products' ];

        foreach ( $words as $word ) {
            if ( in_array( $word, $ignored_terms, true ) ) {
                continue;
            }

            $word_score = 0;

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

            $closest = PHP_INT_MAX;
            foreach ( $fuzzy_groups as $group ) {
                foreach ( $fields[ $group ] ?? [] as $value ) {
                    foreach ( preg_split( '/\s+/', $value ) as $candidate ) {
                        if ( strlen( $word ) < 4 || strlen( $candidate ) < 4 ) {
                            continue;
                        }

                        $closest = min( $closest, levenshtein( $word, $candidate ) );
                    }
                }
            }

            if ( $closest <= 2 ) {
                $score += 15;
                $matched_fields['fuzzy'] = 15;
            } else {
                return 0;
            }
        }

        return $score;
    }


}