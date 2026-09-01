<?php

namespace EsSmartSearch;

class Assets {

    /**
     * Register scripts to be enqueued
     *
     * @return void
     */
    public function register() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_filter( 'script_loader_tag', [ $this, 'script_loader_tag' ], 10, 2 );
    }

    /**
     * Enqueue scripts and styles for the front-end.
     *
     * @return void
     */
    public function enqueue_scripts() {

        // Only enqueue on category pages and specific templates
        if (! is_category() && ! is_page_template( 'grouped-products.php' ) && ! is_page_template( 'batches.php' )) {
            return;
        }

        // Enqueue the JavaScript and CSS files
        wp_enqueue_script('es-smart-search', ESSS_URL . 'assets/js/SmartSearch.js', [], ESSS_VERSION, true);
        wp_enqueue_style('es-smart-search', ESSS_URL . 'assets/css/es-smart-search.css', [], ESSS_VERSION);

        // 1. Tell LiteSpeed to track this nonce dynamically behind the cache layer
        if ( has_action( 'litespeed_nonce' ) ) {
            do_action( 'litespeed_nonce', 'esss_reporting_nonce' );
        }

        // 2. Build the unified payload data object
        $esss_options = [
            'endpoint'          => esc_url_raw( rest_url( 'es-smart-search/v1/search' ) ),
            'reportingEndpoint' => esc_url_raw( rest_url( 'es-smart-search/v1/report' ) ),
            'nonce'             => wp_create_nonce( 'wp_rest' ), // Handled via LiteSpeed ESI hole-punching
            'debug'             => true,
        ];

        // 3. Convert the PHP array to a clean, safe JavaScript object block
        $inline_js = 'window.ESSS = ' . wp_json_encode( $esss_options ) . ';';

        // 4. Inject it cleanly right before the script loads
        wp_add_inline_script( 'es-smart-search', $inline_js, 'before' );

        // Localize the script with the REST API endpoint and debug flag
        // wp_localize_script( 'es-smart-search', 'ESSS', [
        //     'endpoint' => esc_url_raw( rest_url( 'emporio-search/v1/search' ) ),
        //     'debug'    => true,
        // ] );

    }

    /**
     * Convert scripts to ES6 modules
     *
     * @param string $tag The HTML script tag.
     * @param string $handle The script handle.
     * @return string Modified script tag.
     */
    public function script_loader_tag( $tag, $handle ) {

        if ( 'es-smart-search' !== $handle ) {
            return $tag;
        }

        return str_replace(
            '<script ',
            '<script type="module" ',
            $tag
        );
    }

}