<?php

namespace {
    if ( ! function_exists( 'sanitize_key' ) ) {
        function sanitize_key( $value ): string {
            return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) );
        }
    }

    if ( ! function_exists( 'absint' ) ) {
        function absint( $value ): int {
            return abs( (int) $value );
        }
    }
}

namespace EsSmartSearch\Tests {

    use EsSmartSearch\Admin\Settings;
    use PHPUnit\Framework\TestCase;

    final class SettingsTest extends TestCase {

    public function test_sanitizes_dynamic_weight_rows_into_a_sorted_map(): void {
        $weights = ( new Settings() )->sanitize_weights( [
            'keys'   => [ ' title ', 'colour', 'ignored field' ],
            'values' => [ '40', '120', '25' ],
        ] );

        self::assertSame(
            [ 'colour' => 100, 'title' => 40, 'ignoredfield' => 25 ],
            $weights
        );
    }

    public function test_missing_weight_values_use_the_existing_row_fallback(): void {
        self::assertSame(
            [ 'title' => 50 ],
            ( new Settings() )->sanitize_weights( [
                'keys'   => [ 'title' ],
                'values' => [],
            ] )
        );
    }

    public function test_non_array_weight_input_is_safe(): void {
        self::assertSame( [], ( new Settings() )->sanitize_weights( 'invalid' ) );
    }
    }
}
