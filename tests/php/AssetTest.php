<?php

namespace EsSmartSearch\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EsSmartSearch\Assets\Assets;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

/**
 * Tests the WordPress hook registration owned by the Assets class.
 */
final class AssetTest extends TestCase {

    /**
     * Start Brain Monkey before each test so WordPress functions can be mocked.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

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
     * Confirms creating an Assets object does not register WordPress hooks.
     *
     * Hook registration is an explicit step performed by the plugin boot process.
     */
    #[DoesNotPerformAssertions]
    public function test_constructor_does_not_register_hooks(): void {
        // No hooks should be added until the plugin explicitly calls register().
        Functions\expect( 'add_action' )->never();

        // Constructing the asset manager must not change WordPress state.
        new Assets();
    }

    /**
        * Confirms register() connects the asset loader to WordPress's front-end hook.
        */
    #[DoesNotPerformAssertions]
    public function test_register_adds_the_enqueue_hook(): void {
        // Create the class whose front-end hook registration is being tested.
        $assets = new Assets();

        // The asset loader must run when WordPress prepares front-end scripts.
        Functions\expect( 'add_action' )
            ->once()
            ->with( 'wp_enqueue_scripts', [ $assets, 'enqueue_scripts' ] );

        // Register the hook and let Brain Monkey verify the expected callback.
        $assets->register();
    }
}