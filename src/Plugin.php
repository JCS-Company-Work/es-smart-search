<?php

namespace EsSmartSearch;

use EsSmartSearch\Assets;
use EsSmartSearch\Indexing\SearchIndex;
use EsSmartSearch\Indexing\SearchMatcher;
use EsSmartSearch\Search;
use EsSmartSearch\Reporting\SearchReporting;

class Plugin {

    /**
     * Boot the plugin by registering assets, search, and search index.
     *
     * @return void
     */
    public function boot() {

        // Create an Assets instance and register its hooks.
        ( new Assets() )->register();

        // Create a SearchIndex instance and register its hooks.
        $search_index = new SearchIndex();
        $search_index->register();

        // Create a SearchMatcher instance.
        $search_matcher = new SearchMatcher();

        // Create a Search instance with the SearchIndex and register its hooks.
        $search = new Search( $search_index, $search_matcher );
        $search->register();

        // Create a SearchReporting instance and register its hooks.
        ( new SearchReporting() )->register();
        
    }
}