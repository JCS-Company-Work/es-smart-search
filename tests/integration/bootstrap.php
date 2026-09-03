<?php

if ( '1' !== getenv( 'ESSS_INTEGRATION_TESTS' ) ) {
    fwrite( STDERR, "Integration tests require ESSS_INTEGRATION_TESTS=1.\n" );
    exit( 1 );
}

$wp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( false === $wp_tests_dir || '' === $wp_tests_dir || ! is_file( $wp_tests_dir . '/includes/bootstrap.php' ) ) {
    fwrite( STDERR, "Integration tests require WP_TESTS_DIR to point to the WordPress test library.\n" );
    exit( 1 );
}

$test_database = getenv( 'ESSS_TEST_DB_NAME' );

if ( false === $test_database || '' === $test_database || 'local' === $test_database ) {
    fwrite( STDERR, "Integration tests require ESSS_TEST_DB_NAME to name a non-local disposable database.\n" );
    exit( 1 );
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once $wp_tests_dir . '/includes/bootstrap.php';