<?php

namespace EsSmartSearch\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EsSmartSearch\Assets;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

final class AssetTest extends TestCase {

    /**
     * Set up tests and add Brain Monkey
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        Monkey\setUp();
    }

    /**
     * Tear down tests and remove Brain Monkey
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

        new Assets();
    }

    /**
    * Verifies the asset registrar attaches its front-end enqueue callback.
    */
    #[DoesNotPerformAssertions]
    public function test_register_adds_the_enqueue_hook(): void {
        $assets = new Assets();

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'wp_enqueue_scripts', [ $assets, 'enqueue_scripts' ] );

        $assets->register();
    }
}