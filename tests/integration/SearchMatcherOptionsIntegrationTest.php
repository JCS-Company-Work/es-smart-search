<?php

namespace EsSmartSearch\Tests\Integration;

use EsSmartSearch\Indexing\SearchMatcher;
use WP_UnitTestCase;

final class SearchMatcherOptionsIntegrationTest extends WP_UnitTestCase {

    protected function tearDown(): void {
        delete_option( 'esss_weight_text' );
        delete_option( 'esss_weight_filters' );

        parent::tearDown();
    }

    public function test_uses_saved_size_weight_from_wp_options(): void {
        update_option( 'esss_weight_text', [ 'size' => 97 ] );
        update_option( 'esss_weight_filters', [ 'size' => 90 ] );

        $matcher = new SearchMatcher();
        $matched_fields = [];

        self::assertSame(
            97,
            $matcher->score_batch(
                '60x60',
                [ 'fields' => [ 'size' => [ '600x600', '60x60' ] ] ],
                $matched_fields
            )
        );
        self::assertSame( [ 'size' => 97 ], $matched_fields );
    }

    public function test_dimensions_filter_alias_matches_the_canonical_size_field(): void {
        $matcher = new SearchMatcher();

        self::assertTrue(
            $matcher->matches_filters(
                [ 'size' => [ '600x600', '60x60' ] ],
                [ 'dimensions' => [ '60 x 60' ] ]
            )
        );
    }
}
