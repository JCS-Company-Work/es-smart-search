<?php

namespace EsSmartSearch\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EsSmartSearch\Indexing\SearchIndex;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

final class SearchIndexTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        if ( ! defined( 'ESSS_INDEX_TRANSIENT' ) ) {
            define( 'ESSS_INDEX_TRANSIENT', 'esss_search_index_v3' );
        }
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    #[DoesNotPerformAssertions]
    public function test_registers_index_invalidation_hooks(): void {
        $index = new SearchIndex();

        Functions\expect( 'add_action' )->once()->with( 'save_post_batch', [ $index, 'invalidate' ] );
        Functions\expect( 'add_action' )->once()->with( 'acf/save_post', [ $index, 'invalidate_acf' ], 9999 );
        Functions\expect( 'add_action' )->times( 3 )->withArgs( function ( $hook, $callback, $priority, $accepted_args ) use ( $index ): bool {
            return in_array( $hook, [ 'updated_post_meta', 'added_post_meta', 'deleted_post_meta' ], true )
                && [ $index, 'invalidate_meta' ] === $callback
                && 10 === $priority
                && 4 === $accepted_args;
        } );
        Functions\expect( 'add_action' )->once()->with( 'set_object_terms', [ $index, 'invalidate_terms' ], 10, 6 );
        Functions\expect( 'add_action' )->once()->with( 'woocommerce_product_set_stock_status', [ $index, 'invalidate_product' ] );
        Functions\expect( 'add_action' )->once()->with( 'deleted_post', [ $index, 'invalidate_deleted' ] );

        $index->register();
    }

    #[DoesNotPerformAssertions]
    public function test_invalidate_deletes_the_search_transient(): void {
        Functions\expect( 'delete_transient' )
            ->once()
            ->with( ESSS_INDEX_TRANSIENT );

        ( new SearchIndex() )->invalidate();
    }
}
