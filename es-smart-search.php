<?php
/*
Plugin Name: ES Smart Search Prototype
Description: Prototype server-backed live search for Emporio Surfaces.
Version: 0.1.0
Author: Emporio Surfaces
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ESSS_VERSION', '0.1.2' );
define( 'ESSS_PATH', plugin_dir_path( __FILE__ ) );
define( 'ESSS_URL', plugin_dir_url( __FILE__ ) );
define( 'ESSS_INDEX_TRANSIENT', 'esss_search_index_v3' );

// Composer autoload
if ( file_exists( ESSS_PATH . 'vendor/autoload.php' ) ) {
    require_once ESSS_PATH . 'vendor/autoload.php';
}

// Initilise the plugin
use EsSmartSearch\Plugin;

( new Plugin() )->boot();