<?php

namespace EsSmartSearch\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EsSmartSearch\Assets\Assets;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

final class AssetTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    #[DoesNotPerformAssertions]
    public function test_registers_front_end_asset_hooks(): void {
        $assets = new Assets();

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'wp_enqueue_scripts', [ $assets, 'enqueue_scripts' ] );
        Functions\expect( 'add_action' )
            ->once()
            ->with( 'admin_enqueue_scripts', [ $assets, 'enqueue_admin_assets' ] );
        Functions\expect( 'add_filter' )
            ->once()
            ->with( 'script_loader_tag', [ $assets, 'script_loader_tag' ], 10, 2 );

        $assets->register();
    }
}
