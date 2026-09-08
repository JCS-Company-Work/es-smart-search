<?php

namespace EsSmartSearch\Indexing {
    if ( ! function_exists( __NAMESPACE__ . '\\get_option' ) ) {
        function get_option( string $key, $default = false ) {
            return $GLOBALS['esss_test_options'][ $key ] ?? $default;
        }
    }
}

namespace {
    if ( ! function_exists( 'sanitize_text_field' ) ) {
        function sanitize_text_field( $value ): string {
            return trim( strip_tags( (string) $value ) );
        }
    }

    if ( ! function_exists( 'sanitize_key' ) ) {
        function sanitize_key( $value ): string {
            return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) );
        }
    }
}

namespace EsSmartSearch\Tests {

    use EsSmartSearch\Indexing\SearchMatcher;
    use PHPUnit\Framework\TestCase;

    final class SearchMatcherTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['esss_test_options'] = [];
    }

    protected function tearDown(): void {
        unset( $GLOBALS['esss_test_options'] );
    }

    public function test_exact_matches_use_the_highest_matching_field_weight(): void {
        $GLOBALS['esss_test_options'] = [
            'esss_weight_text' => [ 'title' => 50, 'colour' => 70 ],
            'esss_weight_filters' => [],
        ];

        $matched_fields = [];
        $score = ( new SearchMatcher() )->score_batch(
            'marble',
            [ 'fields' => [ 'title' => [ 'marble white' ], 'colour' => [ 'marble' ] ] ],
            $matched_fields
        );

        self::assertSame( 70, $score );
        self::assertSame( [ 'colour' => 70, 'title' => 50 ], $matched_fields );
    }

    public function test_common_terms_are_ignored(): void {
        $matcher = new SearchMatcher();

        self::assertSame(
            50,
            $matcher->score_batch( 'tile marble', [ 'fields' => [ 'title' => [ 'tile marble' ] ] ] )
        );
    }

    public function test_fuzzy_matching_is_limited_to_permitted_fields(): void {
        $matcher = new SearchMatcher();
        $matched_fields = [];

        $score = $matcher->score_batch(
            'whte',
            [
                'fields' => [
                    'colour'      => [ 'white' ],
                    'product_code' => [ 'code-123' ],
                ],
            ],
            $matched_fields
        );

        self::assertSame( 49, $score );
        self::assertSame( [ 'colour_fuzzy' => 49 ], $matched_fields );
    }

    public function test_strict_exclusions_return_no_score_without_an_exact_match(): void {
        $matcher = new SearchMatcher();

        self::assertSame( 0, $matcher->score_batch( 'floor', [ 'fields' => [ 'title' => [ 'marble' ] ] ] ) );
        self::assertSame( 0, $matcher->score_batch( '60x60', [ 'fields' => [ 'title' => [ 'marble' ] ] ] ) );
    }

    public function test_all_filters_must_match_and_aliases_are_supported(): void {
        $matcher = new SearchMatcher();
        $fields = [
            'category' => [ 'marble' ],
            'size'     => [ '60x60' ],
        ];

        self::assertTrue( $matcher->matches_filters( $fields, [ 'categories' => [ 'marble' ], 'dimensions' => [ '60 x 60' ] ] ) );
        self::assertFalse( $matcher->matches_filters( $fields, [ 'categories' => [ 'marble' ], 'finish' => [ 'honed' ] ] ) );
    }

    public function test_quantity_filters_support_ranges_and_open_ended_bands(): void {
        $matcher = new SearchMatcher();
        $fields = [ 'quantity' => [ 25.0 ] ];

        self::assertTrue( $matcher->matches_filters( $fields, [ 'quantity' => [ 'sqm-10-25' ] ] ) );
        self::assertTrue( $matcher->matches_filters( $fields, [ 'quantity' => [ 'sqm-25+' ] ] ) );
        self::assertFalse( $matcher->matches_filters( $fields, [ 'quantity' => [ 'sqm-26+' ] ] ) );
    }

    public function test_filter_weights_and_values_are_exposed_for_matching_filters(): void {
        $GLOBALS['esss_test_options']['esss_weight_filters'] = [ 'category' => 88 ];
        $matcher = new SearchMatcher();

        self::assertSame( [ 'category' => 88 ], $matcher->get_filter_match_weights( [ 'categories' => [ 'marble' ] ] ) );
        self::assertSame( [ 'category' => 'marble (filter)' ], $matcher->get_filter_match_values( [ 'categories' => [ 'marble' ] ] ) );
    }
    }
}
