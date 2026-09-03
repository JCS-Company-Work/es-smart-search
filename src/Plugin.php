<?php

namespace EsSmartSearch;

use EsSmartSearch\Admin\Settings;
use EsSmartSearch\Assets\Assets;
use EsSmartSearch\Search\Search;
use EsSmartSearch\Indexing\SearchIndex;
use EsSmartSearch\Indexing\SearchMatcher;
use EsSmartSearch\Suggestion\Dictionary;
use EsSmartSearch\Suggestion\Service;
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

        // Register the SearchSuggestionDictionary hooks.
        $dictionary = new Dictionary();
        $dictionary->register();

        $service = new Service();

        // Create a SearchIndex instance and register its hooks.
        $search_index = new SearchIndex();
        $search_index->register();

        // Create a SearchMatcher instance.
        $search_matcher = new SearchMatcher();

        // Create a Search instance with the SearchIndex and register its hooks.
        $search = new Search( $search_index, $search_matcher, $dictionary, $service );
        $search->register();

        // Create a SearchReporting instance and register its hooks.
        ( new SearchReporting() )->register();
        
        // Register the Settings hooks.
        Settings::boot();
    }
}