<?php

namespace EsSmartSearch;

class Assets {

    public function __construct() {
        $this->register();
    }

    /**
     * Register scripts to be enqueued
     *
     * @return void
     */
    public function register() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
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
        wp_enqueue_script('es-smart-search', ESSS_URL . 'assets/js/es-smart-search.js', [], ESSS_VERSION, true);
        wp_enqueue_style('es-smart-search', ESSS_URL . 'assets/css/es-smart-search.css', [], ESSS_VERSION);

        // Localize the script with the REST API endpoint and debug flag
        wp_localize_script( 'es-smart-search', 'ESSS', [
            'endpoint' => esc_url_raw( rest_url( 'emporio-search/v1/search' ) ),
            'debug'    => true,
        ] );

    }

}