<?php

namespace EsSmartSearch\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EsSmartSearch\Indexing\SearchIndex;
use EsSmartSearch\Indexing\SearchMatcher;
use EsSmartSearch\Search\Search;
use EsSmartSearch\Suggestion\Dictionary;
use EsSmartSearch\Suggestion\Service;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

final class SearchTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    #[DoesNotPerformAssertions]
    public function test_registers_search_routes_and_redirect_hook(): void {
        $search = new Search(
            new SearchIndex(),
            new SearchMatcher(),
            new Dictionary(),
            new Service()
        );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'rest_api_init', [ $search, 'register_routes' ] );
        Functions\expect( 'add_action' )
            ->once()
            ->with( 'template_redirect', [ $search, 'redirect_query_search' ] );

        $search->register();
    }
}
