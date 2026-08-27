<?php

namespace EsSmartSearch;

use EsSmartSearch\Assets;
use EsSmartSearch\Search;
use EsSmartSearch\SearchIndex;

class Plugin {

    /**
     * Boot the plugin by registering assets, search, and search index.
     *
     * @return void
     */
    public function boot() {
        $this->register_assets();
        $this->register_search();
        $this->register_search_index();
    }

    /**
     * Register the assets for the plugin.
     *
     * @return void
     */
    private function register_assets() {
        ( new Assets() )->register();
    }

    /**
     * Register the search functionality for the plugin.
     *
     * @return void
     */
    private function register_search() {
        ( new Search() )->register();
    }

    /**
     * Register the search index functionality for the plugin.
     *
     * @return void
     */
    private function register_search_index() {
        ( new SearchIndex() )->register();
    }

}