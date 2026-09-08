<?php

namespace EsSmartSearch\Admin;

use EsSmartSearch\Suggestion\Dictionary;

class Settings {

    public static function boot(): void {
        $instance = new self();
        add_action( 'admin_menu', [ $instance, 'add_menu_page' ] );
        add_action( 'admin_init', [ $instance, 'register_settings' ] );
    }

    public function add_menu_page(): void {
        add_submenu_page(
            'options-general.php',
            'Smart Search Suggestions',
            'Smart Search',
            'manage_options',
            'es-smart-search',
            [ $this, 'render_settings_form' ]
        );
    }

    public function register_settings(): void {

        // Register general settings
        register_setting( 'es_smart_search_global_group', 'esss_max_distance_short', [ 'type' => 'integer', 'default' => 1 ] );
        register_setting( 'es_smart_search_global_group', 'esss_max_distance_long', [ 'type' => 'integer', 'default' => 2 ] );
        register_setting( 'es_smart_search_global_group', 'esss_suggestions_limit', [ 'type' => 'integer', 'default' => 1 ] );
        register_setting( 'es_smart_search_global_group', 'esss_use_popular_searches', [ 'type' => 'boolean', 'default' => true ] );
        
        // Register dictionary settings
        register_setting( 'es_smart_search_global_group', 'esss_synonyms', [ 'type' => 'string', 'default' => '' ] );
        register_setting( 'es_smart_search_global_group', 'esss_ignored_terms', [ 'type' => 'string', 'default' => '' ] );
        register_setting( 'es_smart_search_global_group', 'esss_manual_additions', [ 'type' => 'string', 'default' => '' ] );
        
        // Register text weighting settings
        register_setting( 'es_smart_search_global_group', 'esss_weight_text', [
            'type'              => 'array',
            'default'           => [],
            'sanitize_callback' => [ $this, 'sanitize_weights' ]
        ] );

        // Register filter weighting settings
        register_setting( 'es_smart_search_global_group', 'esss_weight_filters', [
            'type'              => 'array',
            'default'           => [],
            'sanitize_callback' => [ $this, 'sanitize_weights' ]
        ] );

    }

    /**
     * Render the settings form for the Smart Search plugin.
     */
    public function render_settings_form(): void {
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
            add_settings_error( 'esss_messages', 'esss_message', 'Settings Saved and Dictionary Rebuilt.', 'updated' );
        }

        // Path to the views directory
        $views_dir = plugin_dir_path( __FILE__ ) . 'views/';

        ?>
        <div class="wrap">
            <h1>Smart Search Suggestion Settings</h1>
            <?php settings_errors( 'esss_messages' ); ?>
            
            <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <a href="#esss-tab-general" class="nav-tab nav-tab-active">General & Typos</a>
                <a href="#esss-tab-dictionary" class="nav-tab">Dictionary Overrides</a>
                <a href="#esss-tab-weighting" class="nav-tab">Search Weighting</a>
            </nav>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'es_smart_search_global_group' ); ?>

                <!-- Tab Content 1 -->
                <div id="esss-tab-general" class="esss-tab-pane">
                    <table class="form-table">
                        <?php include $views_dir . 'tab-general.php'; ?>
                    </table>
                </div>

                <div id="esss-tab-dictionary" class="esss-tab-pane" style="display: none;">
                    <?php include $views_dir . 'tab-dictionary.php'; ?>
                </div>

                <div id="esss-tab-weighting" class="esss-tab-pane" style="display: none;">
                    <table class="form-table">
                        <?php include $views_dir . 'tab-weighting.php'; ?>
                    </table>
                    
                </div>

                <div class="esss-submit-actions">
                    <?php submit_button('Save Settings'); ?>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Renders the sidebar for the dictionary tab, including a searchable list of active dictionary terms.
     *
     * @return void
     */
    private function render_dictionary_sidebar(): void {

        // Instantiate the dictionary class to fetch active terms.
        $dictionary_instance = new Dictionary();
        
        // Fetch the cached dictionary terms.
        $cached_terms = $dictionary_instance->get_terms();
        
        // Sort the cached terms alphabetically for easier browsing.
        sort( $cached_terms );

        ?>
        <!-- Sidebar for active dictionary terms -->
        <h2>Active Dictionary (<?php echo count( $cached_terms ); ?> words)</h2>
        <input type="text" id="esss-vocab-search" class="esss-vocab-search-input" placeholder="Type to filter words...">
        <div id="esss-vocab-list" class="esss-vocab-list-window">
            <?php foreach ( $cached_terms as $term ) : ?>
                <span class="esss-vocab-pill"><?php echo esc_html( $term ); ?></span>
            <?php endforeach; ?>
        </div>

        <script>
            document.getElementById('esss-vocab-search')?.addEventListener('input', function(e) {
                const filter = e.target.value.toLowerCase();
                document.querySelectorAll('.esss-vocab-pill').forEach(function(pill) {
                    pill.style.display = pill.textContent.toLowerCase().includes(filter) ? 'inline-block' : 'none';
                });
            });
        </script>
        <?php
    }


    /**
     * Build array of available fields for weighting (ACF and manually added)
     *
     * @return array
     */
    private function get_all_available_fields(): array {
        $fields = [
            'category' => 'Category',
            'effect' => 'Effect',
            'title' => 'Post Title',
            'usage' => 'Usage',
            'thickness' => 'Thickness',
            'quantity' => 'Quantity',
            'discount' => 'Discount',
            'dimensions' => 'Format',
        ];

        $target_cpt = 'batch'; 

        // Define the ONLY field types that can be part weighting dropdowns (can be overwritten above)
        $allowed_types = [
            'text',
            'textarea',
            'wysiwyg',
            'select',
            'radio'
        ];

        if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
            $matched_groups = acf_get_field_groups( [ 'post_type' => $target_cpt ] );

            if ( ! empty( $matched_groups ) && is_array( $matched_groups ) ) {
                foreach ( $matched_groups as $group ) {
                    $acf_fields = acf_get_fields( $group['key'] );
                    
                    if ( ! empty( $acf_fields ) && is_array( $acf_fields ) ) {
                        foreach ( $acf_fields as $field ) {
                            if ( empty( $field['name'] ) || empty( $field['type'] ) ) {
                                continue;
                            }

                            // Strict check: Only include fields explicitly on the allowlist
                            if ( ! in_array( $field['type'], $allowed_types, true ) ) {
                                continue;
                            }
                            
                            $fields[ $field['name'] ] = esc_html( $field['label'] );
                        }
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * Sanitize the weights input array, ensuring keys are valid and values are clamped between 0 and 100.
     *
     * @param array $input
     * @return array
     */
    public function sanitize_weights( $input ): array {

        // Check if the form arrays are present
        if ( is_array( $input ) && isset( $input['keys'] ) && isset( $input['values'] ) ) {

            // Array to hold the sanitized key-value pairs
            $rebuilt_matrix = [];
            
            foreach ( $input['keys'] as $index => $key_slug ) {
                $clean_key = sanitize_key( trim( $key_slug ) );
                
                // Skip rows where no field option was selected
                if ( empty( $clean_key ) ) continue;
                
                // Capture value, cast to integer, and clamp between 0 and 100
                $raw_weight = isset( $input['values'][ $index ] ) ? absint( $input['values'][ $index ] ) : 50;
                $clamped_weight = max( 0, min( 100, $raw_weight ) );
                
                $rebuilt_matrix[ $clean_key ] = $clamped_weight;
            }
            
            // Sort so highest weightings are at the top
            arsort( $rebuilt_matrix ); 
            return $rebuilt_matrix;
        }
        
        // Fallback safeguard
        return is_array( $input ) ? $input : [];
    }

}