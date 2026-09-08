<?php

namespace EsSmartSearch\Tests\Integration;

use EsSmartSearch\Suggestion\Dictionary;
use WP_UnitTestCase;

final class DictionaryIntegrationTest extends WP_UnitTestCase {

    private const CACHE_KEY = 'es_smart_search_suggestion_vocabulary';

    protected function setUp(): void {
        parent::setUp();

        register_taxonomy( 'effect', 'batch' );
        update_option( 'woocommerce_notify_low_stock_amount', 2 );
    }

    protected function tearDown(): void {
        delete_option( self::CACHE_KEY );
        delete_option( 'esss_ignored_terms' );
        delete_option( 'esss_manual_additions' );
        unregister_taxonomy( 'effect' );

        parent::tearDown();
    }

    public function test_rebuilds_and_persists_terms_from_acf_post_meta(): void {
        $post_id = self::factory()->post->create( [
            'post_type'   => 'batch',
            'post_status' => 'publish',
            'post_title'  => 'Carrara Marble Batch',
        ] );

        add_post_meta( $post_id, '_stock', 5 );
        add_post_meta( $post_id, 'colour', 'Carrara Marble' );
        add_post_meta( $post_id, 'finish', 'Honed' );
        add_post_meta( $post_id, 'effect', [ 'Ivory White', 'Polished' ] );
        wp_set_post_terms( $post_id, [ 'marble' ], 'effect' );

        $dictionary = new Dictionary();

        self::assertTrue( $dictionary->rebuild() );
        self::assertEqualsCanonicalizing(
            [ 'carrara', 'marble', 'honed', 'ivory', 'white', 'polished' ],
            $dictionary->get_terms()
        );
    }
}