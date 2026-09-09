<?php

namespace EsSmartSearch\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EsSmartSearch\Suggestion\Dictionary;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

final class DictionaryTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    #[DoesNotPerformAssertions]
    public function test_registers_dictionary_rebuild_hooks(): void {
        Functions\expect( 'add_action' )->once()->with( 'save_post_batch', [ Dictionary::class, 'handle_product_save' ], 20, 3 );
        Functions\expect( 'add_action' )->times( 4 )->withArgs( function ( $hook, $callback, $priority, $accepted_args ): bool {
            return in_array( $hook, [
                'add_option_esss_manual_additions',
                'update_option_esss_manual_additions',
                'add_option_esss_ignored_terms',
                'update_option_esss_ignored_terms',
            ], true )
                && [ Dictionary::class, 'handle_options_save' ] === $callback
                && 20 === $priority
                && 0 === $accepted_args;
        } );

        Dictionary::register();
    }
}