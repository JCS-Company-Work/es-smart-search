<?php

namespace EsSmartSearch\Indexing;

if ( ! function_exists( __NAMESPACE__ . '\\remove_accents' ) ) {
    function remove_accents( string $value ): string {
        return $value;
    }
}

namespace EsSmartSearch\Tests;

use EsSmartSearch\Indexing\Suggestion\Service;
use PHPUnit\Framework\TestCase;

final class SuggestionServiceTest extends TestCase {

    public function test_suggestions_preserve_order_and_honour_the_limit(): void {
        $service = new Service();
        $dictionary = [ 'cat', 'bat', 'dog', 'dig' ];

        self::assertSame( 'cat dog', $service->get_suggestions( 'catt dogg', $dictionary ) );
        self::assertSame(
            [ 'cat dog', 'cat dig' ],
            $service->get_suggestions( 'catt dogg', $dictionary, 2 )
        );
    }
}