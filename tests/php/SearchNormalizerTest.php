<?php

namespace EsSmartSearch\Indexing;

if ( ! function_exists( __NAMESPACE__ . '\\remove_accents' ) ) {
    function remove_accents( string $value ): string {
        return strtr( $value, [ 'é' => 'e', 'É' => 'E' ] );
    }
}

namespace EsSmartSearch\Tests;

use EsSmartSearch\Indexing\SearchNormalizer;
use PHPUnit\Framework\TestCase;

final class SearchNormalizerTest extends TestCase {

    public function test_normalises_case_accents_spacing_and_separators(): void {
        self::assertSame( 'cafe marble white 600x1200', SearchNormalizer::normalise( "  CAFÉ   Marble × White 600 by 1200  " ) );
    }

    public function test_normalises_empty_values(): void {
        self::assertSame( '', SearchNormalizer::normalise( '' ) );
        self::assertSame( '', SearchNormalizer::normalise( null ) );
    }

    public function test_preserves_dimension_format_after_normalisation(): void {
        self::assertSame( '600x600', SearchNormalizer::normalise( '600 x 600' ) );
        self::assertSame( '600x600', SearchNormalizer::normalise( '600by600' ) );
    }
}
