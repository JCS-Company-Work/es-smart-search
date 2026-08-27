<?php

namespace EsSmartSearch\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EsSmartSearch\SearchIndex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

/**
 * Tests the hooks that keep the cached search index up to date.
 */
final class SearchIndexTest extends TestCase {

    /**
     * Start Brain Monkey and define the plugin transient before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        if ( ! defined( 'ESSS_INDEX_TRANSIENT' ) ) {
            define( 'ESSS_INDEX_TRANSIENT', 'esss_search_index_v3' );
        }

        Monkey\setUp();
    }

    /**
     * Stop Brain Monkey after each test and release its WordPress mocks.
     *
     * @return void
     */
    protected function tearDown(): void {
        Monkey\tearDown();

        parent::tearDown();
    }

    /**
     * Confirms creating a SearchIndex object does not register WordPress hooks.
     *
     * Hook registration is an explicit step performed by the plugin boot process.
     */
    #[DoesNotPerformAssertions]
    public function test_constructor_does_not_register_hooks(): void {
        // No hooks should be added until the plugin explicitly calls register().
        Functions\expect( 'add_action' )->never();

        // Constructing the index manager must not change WordPress state.
        new SearchIndex();
    }

    /**
     * Confirms register() connects every index-changing event to the correct callback.
     *
     * The accepted argument counts matter because WordPress passes different numbers
     * of arguments to metadata and taxonomy hooks.
     */
    #[DoesNotPerformAssertions]
    public function test_register_adds_index_invalidation_hooks(): void {
        // Create the class whose hook registrations are being tested.
        $search_index = new SearchIndex();

        // A saved batch post must clear the cached index.
        Functions\expect( 'add_action' )
            ->once()
            ->with( 'save_post_batch', [ $search_index, 'invalidate' ] );

        // ACF saves use a late priority so the index is cleared after ACF has saved its fields.
        Functions\expect( 'add_action' )
            ->once()
            ->with( 'acf/save_post', [ $search_index, 'invalidate_acf' ], 9999 );

        // Each metadata hook must use the same callback and accept all four WordPress arguments.
        Functions\expect( 'add_action' )
            ->times( 3 )
            ->withArgs( function ( $hook, $callback, $priority = 10, $accepted_args = 1 ) use ( $search_index ): bool {
                return in_array( $hook, [ 'updated_post_meta', 'added_post_meta', 'deleted_post_meta' ], true )
                    && [ $search_index, 'invalidate_meta' ] === $callback
                    && 10 === $priority
                    && 4 === $accepted_args;
            } );

        // Taxonomy changes must pass all six arguments to the invalidation callback.
        Functions\expect( 'add_action' )
            ->once()
            ->with( 'set_object_terms', [ $search_index, 'invalidate_terms' ], 10, 6 );

        // Stock changes must invalidate the index for the affected product.
        Functions\expect( 'add_action' )
            ->once()
            ->with( 'woocommerce_product_set_stock_status', [ $search_index, 'invalidate_product' ] );

        // Deleted posts must invalidate the index when they are batches.
        Functions\expect( 'add_action' )
            ->once()
            ->with( 'deleted_post', [ $search_index, 'invalidate_deleted' ] );

        // Register the hooks. Brain Monkey verifies these expectations during tearDown().
        $search_index->register();
    }

    /**
     * Confirms invalidate() removes the one transient containing the search index.
     */
    #[DoesNotPerformAssertions]
    public function test_invalidate_deletes_the_search_index_transient(): void {
        // The index is stored in one known transient, which must be deleted.
        Functions\expect( 'delete_transient' )
            ->once()
            ->with( \ESSS_INDEX_TRANSIENT );

        // Directly invalidate the index and let Brain Monkey verify the deletion.
        ( new SearchIndex() )->invalidate();
    }

    /**
     * Confirms every supported batch change removes the cached index.
     *
     * Each callback receives the same post type from WordPress. The provider supplies
     * the callback name and representative arguments for each hook.
     */
    #[DataProvider( 'batch_change_callbacks' )]
    #[DoesNotPerformAssertions]
    public function test_batch_changes_invalidate_the_index( string $method, array $arguments ): void {
        // Pretend WordPress is running a callback for a batch post.
        Functions\when( 'get_post_type' )->justReturn( 'batch' );

        // A batch change must remove the existing cached index.
        Functions\expect( 'delete_transient' )
            ->once()
            ->with( \ESSS_INDEX_TRANSIENT );

        // Call the callback supplied by the data provider.
        ( new SearchIndex() )->$method( ...$arguments );
    }

    /**
     * Confirms changes to other post types leave the batch index untouched.
     *
     * This protects the cache from being rebuilt for unrelated WordPress content.
     */
    #[DataProvider( 'batch_change_callbacks' )]
    #[DoesNotPerformAssertions]
    public function test_non_batch_changes_do_not_invalidate_the_index( string $method, array $arguments ): void {
        // Pretend WordPress is running a callback for an unrelated post type.
        Functions\when( 'get_post_type' )->justReturn( 'product' );

        // Unrelated content must not remove the batch search index.
        Functions\expect( 'delete_transient' )->never();

        // Call the same callback supplied by the data provider.
        ( new SearchIndex() )->$method( ...$arguments );
    }

    /**
     * Supplies each invalidation callback and its expected WordPress arguments.
     *
     * @return array<string, array{0: string, 1: array}>
     */
    public static function batch_change_callbacks(): array {
        return [
            'acf save' => [ 'invalidate_acf', [ 123 ] ],
            'metadata update' => [ 'invalidate_meta', [ 1, 123 ] ],
            'taxonomy update' => [ 'invalidate_terms', [ 123, [], [], 'category', false, [] ] ],
            'stock update' => [ 'invalidate_product', [ 123 ] ],
            'post deletion' => [ 'invalidate_deleted', [ 123 ] ],
        ];
    }
}