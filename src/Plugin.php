<?php

namespace EsSmartSearch;

use EsSmartSearch\Assets;
use EsSmartSearch\Search;

class Plugin {

    public function boot() {
        $this->register_assets();
        $this->register_search();
    }

    private function register_assets() {
        new Assets()->register();
    }

    private function register_search() {
        new Search()->register();
    }

}