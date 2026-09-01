<?php

namespace EsSmartSearch;

use EsSmartSearch\Assets;
use EsSmartSearch\Search;
use EsSmartSearch\SearchIndex;
use EsSmartSearch\SearchReporting;

class Plugin {

    /**
     * Boot the plugin by registering assets, search, and search index.
     *
     * @return void
     */
    public function boot() {
        ( new Assets() )->register();
        ( new Search() )->register();
        ( new SearchIndex() )->register();
        ( new SearchReporting() )->register();
    }
}