<?php

namespace EsSmartSearch;

use EsSmartSearch\Assets;

class Plugin {

    public function boot() {
        $this->register_assets();
        //$this->register_rest_routes();
    }

    private function register_assets() {
        new Assets();
    }

}