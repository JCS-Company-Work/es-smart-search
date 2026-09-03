<?php

namespace EsSmartSearch\Tests\Integration;

use EsSmartSearch\Indexing\Suggestion\Dictionary;
use WP_UnitTestCase;

final class DictionaryIntegrationTest extends WP_UnitTestCase {

    private const CACHE_KEY = 'es_smart_search_suggestion_vocabulary';

    protected function tearDown(): void {
        delete_option( self::CACHE_KEY );

        parent::tearDown();
    }

    public function test_rebuilds_and_persists_terms_from_acf_post_meta(): void {
        $post_id = self::factory()->post->create();

        add_post_meta( $post_id, 'colour', 'Carrara Marble' );
        add_post_meta( $post_id, 'finish', 'Honed' );
        add_post_meta( $post_id, 'effect', [ 'Ivory White', 'Polished' ] );

        $dictionary = new Dictionary();

        self::assertTrue( $dictionary->rebuild() );
        self::assertEqualsCanonicalizing(
            [ 'carrara', 'marble', 'honed', 'ivory', 'white', 'polished' ],
            $dictionary->get_terms()
        );
    }
}