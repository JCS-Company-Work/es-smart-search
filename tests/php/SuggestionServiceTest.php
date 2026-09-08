<?php

namespace EsSmartSearch\Indexing {
    if ( ! function_exists( __NAMESPACE__ . '\\remove_accents' ) ) {
        function remove_accents( string $value ): string {
            return $value;
        }
    }
}

namespace EsSmartSearch\Suggestion {
    if ( ! function_exists( __NAMESPACE__ . '\\get_option' ) ) {
        function get_option( string $key, $default = false ) {
            return $GLOBALS['esss_test_options'][ $key ] ?? $default;
        }
    }
}

namespace EsSmartSearch\Tests {

    use EsSmartSearch\Suggestion\Service;
    use PHPUnit\Framework\TestCase;

    final class SuggestionServiceTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['esss_test_options'] = [];
    }

    protected function tearDown(): void {
        unset( $GLOBALS['esss_test_options'] );
    }

    public function test_returns_a_synonym_match_for_a_full_phrase(): void {
        $GLOBALS['esss_test_options']['esss_synonyms'] = "off white => white, cream\ndark grey => grey";

        self::assertSame(
            [ 'white', 'cream' ],
            ( new Service() )->get_suggestions( 'OFF WHITE', [ 'white', 'cream' ], 2 )
        );
    }

    public function test_limits_synonym_suggestions(): void {
        $GLOBALS['esss_test_options']['esss_synonyms'] = 'marble => carrara, calacatta, travertine';

        self::assertSame(
            [ 'carrara', 'calacatta' ],
            ( new Service() )->get_suggestions( 'marble', [], 2 )
        );
    }

    public function test_returns_typo_suggestions_using_configured_distance(): void {
        $GLOBALS['esss_test_options'] = [
            'esss_max_distance_short' => 1,
            'esss_max_distance_long'  => 2,
            'esss_synonyms'           => '',
        ];

        self::assertSame(
            [ 'marble' ],
            ( new Service() )->get_suggestions( 'marbl', [ 'marble', 'granite' ], 1 )
        );
    }

    public function test_returns_null_when_no_correction_is_available(): void {
        self::assertNull( ( new Service() )->get_suggestions( 'quartz', [ 'marble', 'granite' ] ) );
    }
    }
}
