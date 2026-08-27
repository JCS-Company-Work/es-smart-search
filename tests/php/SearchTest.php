<?php

namespace EsSmartSearch\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EsSmartSearch\Search;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

final class SearchTest extends TestCase {

    /**
     * Set up tests and add Brain Monkey.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        Monkey\setUp();
    }

    /**
     * Tear down tests and remove Brain Monkey.
     *
     * @return void
     */
    protected function tearDown(): void {
        Monkey\tearDown();

        parent::tearDown();
    }

    /**
     * Verifies construction has no WordPress hook side effects.
     */
    #[DoesNotPerformAssertions]
    public function test_constructor_does_not_register_hooks(): void {
        Functions\expect( 'add_action' )->never();

        new Search();
    }

    /**
     * Verifies the search registrar attaches its REST route callback.
     */
    #[DoesNotPerformAssertions]
    public function test_register_adds_the_rest_route_hook(): void {
        $search = new Search();

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'rest_api_init', [ $search, 'register_routes' ] );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'template_redirect', [ $search, 'redirect_query_search' ] );

        $search->register();
    }

}