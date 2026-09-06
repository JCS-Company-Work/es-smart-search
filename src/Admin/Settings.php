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

        // Register weighting settings
        register_setting( 'es_smart_search_global_group', 'esss_target_acf_fields', [ 'type' => 'array', 'default' => [] ] );
        register_setting( 'es_smart_search_global_group', 'esss_weight_filters', [ 'type' => 'string', 'default' => '' ] );
        
        // Register text weighting settings
        register_setting( 'es_smart_search_global_group', 'esss_weight_text', [
            'type'              => 'array',
            'default'           => [],
            'sanitize_callback' => [ $this, 'sanitize_weight_matrix' ]
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
                        <?php $this->render_general_tab(); ?>
                    </table>
                </div>

                <!-- Tab Content 2: Side-by-Side Flex Layout using external classes -->
                <div id="esss-tab-dictionary" class="esss-tab-pane" style="display: none;">
                        <div class="esss-form-column">
                            <table class="form-table">
                                <?php $this->render_dictionary_tab(); ?>
                            </table>
                        </div>
                        <div class="esss-sidebar-column">
                            <?php $this->render_dictionary_sidebar(); ?>
                        </div>
                </div>

                <!-- Tab Content 3 -->
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

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.nav-tab');
            const panes = document.querySelectorAll('.esss-tab-pane');

            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    tabs.forEach(t => t.classList.remove('nav-tab-active'));
                    this.classList.add('nav-tab-active');

                    panes.forEach(p => p.style.display = 'none');
                    const targetId = this.getAttribute('href');
                    document.querySelector(targetId).style.display = targetId === '#esss-tab-dictionary' ? 'flex' : 'block';
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render general tab settings fields
     *
     * @return void
     */
    private function render_general_tab() {
                                    
        ?>
            <tr class="esss-tab-row general">
                <th scope="row"><label for="esss_max_distance_short">Max Typos (Words ≤ 5 letters)</label></th>
                <td>
                    <input type="number" name="esss_max_distance_short" id="esss_max_distance_short" value="<?php echo esc_attr( get_option( 'esss_max_distance_short', 1 ) ); ?>" min="0" max="3" class="small-text">
                    <p class="description">Recommended value: 1. Controls short keywords like "blue" or "grey".</p>
                </td>
            </tr>
            <tr class="esss-tab-row general">
                <th scope="row"><label for="esss_max_distance_long">Max Typos (Words ≥ 6 letters)</label></th>
                <td>
                    <input type="number" name="esss_max_distance_long" id="esss_max_distance_long" value="<?php echo esc_attr( get_option( 'esss_max_distance_long', 2 ) ); ?>" min="0" max="4" class="small-text">
                    <p class="description">Recommended value: 2. Controls long keywords like "marble" or "concrete".</p>
                </td>
            </tr>
            <tr class="esss-tab-row general">
                <th scope="row"><label for="esss_suggestions_limit">Maximum Suggestions Limit</label></th>
                <td>
                    <input type="number" name="esss_suggestions_limit" id="esss_suggestions_limit" value="<?php echo esc_attr( get_option( 'esss_suggestions_limit', 1 ) ); ?>" min="1" max="5" class="small-text">
                    <p class="description">Controls how many phrase combinations to generate when a search fails.</p>
                </td>
            </tr>
            <tr class="esss-tab-row general">
                <th scope="row">Fallback When No Results</th>
                <td>
                    <fieldset>
                        <label for="esss_use_popular_searches">
                            <input type="checkbox" name="esss_use_popular_searches" id="esss_use_popular_searches" value="1" <?php checked( 1, get_option( 'esss_use_popular_searches', 1 ) ); ?>>
                            Use dynamic popular searches when no results or spelling suggestions are found
                        </label>
                        <p class="description">If unticked, the system will display standard usage links (Floor, Wall, Bathroom and Kitchen tiles) instead.</p>
                    </fieldset>
                </td>
            </tr>
            <tr class="esss-tab-row general">
                <th scope="row">Target ACF Index Fields</th>
                <td>
                    <?php
                    $all_available_fields = [ 'tile_colour', 'tile_finish', 'tile_effect', 'tile_size_friendly', 'factory_name' ];
                    $saved_fields = (array) get_option( 'esss_target_acf_fields', $all_available_fields );
                    foreach ( $all_available_fields as $field_slug ) : ?>
                        <label style="display: block; margin-bottom: 6px;">
                            <input type="checkbox" name="esss_target_acf_fields[]" value="<?php echo esc_attr( $field_slug ); ?>" <?php checked( in_array( $field_slug, $saved_fields, true ) ); ?>>
                            <code><?php echo esc_html( $field_slug ); ?></code>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php

    }

    /**
     * Render dictionary tab settings fields
     *
     * @return void
     */
    private function render_dictionary_tab() {

        ?>
            <tr class="esss-tab-row dictionary">
                <th scope="row"><label for="esss_synonyms">Synonym Mappings</label></th>
                <td>
                    <textarea name="esss_synonyms" id="esss_synonyms" rows="6" cols="50" class="large-text" placeholder="off white => white, cream&#10;dark grey => grey"><?php echo esc_textarea( get_option( 'esss_synonyms', "off white => white, cream\ndark grey => grey" ) ); ?></textarea>
                    <p class="description">Format: <code>phrase => match1, match2</code> (One mapping rule phrase expression entry per line).</p>
                </td>
            </tr>
            <tr class="esss-tab-row dictionary">
                <th scope="row"><label for="esss_manual_additions">Manually Add Words</label></th>
                <td>
                    <textarea name="esss_manual_additions" id="esss_manual_additions" rows="3" cols="50" class="large-text"><?php echo esc_textarea( get_option( 'esss_manual_additions', '' ) ); ?></textarea>
                    <p class="description">Comma-separated terms to force-inject into the dictionary index.</p>
                </td>
            </tr>
            <tr class="esss-tab-row dictionary">
                <th scope="row"><label for="esss_ignored_terms">Manually Remove Words (Blacklist)</label></th>
                <td>
                    <textarea name="esss_ignored_terms" id="esss_ignored_terms" rows="3" cols="50" class="large-text"><?php echo esc_textarea( get_option( 'esss_ignored_terms', '' ) ); ?></textarea>
                    <p class="description">Comma-separated terms to completely strip out of the suggestion dictionary.</p>
                </td>
            </tr>
        <?php
    }

    private function render_dictionary_sidebar(): void {
        $dictionary_instance = new Dictionary();
        $cached_terms = $dictionary_instance->get_terms();
        sort( $cached_terms );
        ?>
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
     * Helper to fetch only text-searchable ACF fields assigned to the batch CPT
     */
    private function get_all_available_fields(): array {
        $fields = [
            'category' => 'Category',
            'effect' => 'Effect',
            'title' => 'Post Title',
            'usage' => 'Usage',
        ];

        $target_cpt = 'batch'; 

        // Define the ONLY field types that can be part of full-text search matching
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
     * 
     *
     * @param [type] $input
     * @return array
     */
    public function sanitize_weight_matrix( $input ): array {
        // Check if the form arrays are present
        if ( is_array( $input ) && isset( $input['keys'] ) && isset( $input['values'] ) ) {
            $rebuilt_matrix = [];
            
            foreach ( $input['keys'] as $index => $key_slug ) {
                $clean_key = sanitize_key( trim( $key_slug ) );
                
                // Skip rows where no field option was selected
                if ( empty( $clean_key ) ) {
                    continue;
                }
                
                // Capture value, cast to integer, and clamp tightly between 0 and 100
                $raw_weight = isset( $input['values'][ $index ] ) ? absint( $input['values'][ $index ] ) : 50;
                $clamped_weight = max( 0, min( 100, $raw_weight ) );
                
                $rebuilt_matrix[ $clean_key ] = $clamped_weight;
            }
            
            arsort( $rebuilt_matrix ); // Keep heaviest entries at the top
            return $rebuilt_matrix;
        }
        
        // Fallback safeguard
        return is_array( $input ) ? $input : [];
    }


}
