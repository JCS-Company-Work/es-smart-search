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

        // Localize the script with the REST API endpoint and debug flag
        wp_localize_script( 'es-smart-search', 'ESSS', [
            'endpoint' => esc_url_raw( rest_url( 'emporio-search/v1/search' ) ),
            'debug'    => true,
        ] );

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