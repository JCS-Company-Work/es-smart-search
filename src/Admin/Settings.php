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
        register_setting( 'es_smart_search_group_general', 'esss_max_distance_short', [ 'type' => 'integer', 'default' => 1 ] );
        register_setting( 'es_smart_search_group_general', 'esss_max_distance_long', [ 'type' => 'integer', 'default' => 2 ] );
        register_setting( 'es_smart_search_group_general', 'esss_suggestions_limit', [ 'type' => 'integer', 'default' => 1 ] );
        register_setting( 'es_smart_search_group_general', 'esss_use_popular_searches', [ 'type' => 'boolean', 'default' => true ] );
        
        // Register dictionary settings
        register_setting( 'es_smart_search_group_dictionary', 'esss_synonyms', [ 'type' => 'string', 'default' => '' ] );
        register_setting( 'es_smart_search_group_dictionary', 'esss_ignored_terms', [ 'type' => 'string', 'default' => '' ] );
        register_setting( 'es_smart_search_group_dictionary', 'esss_manual_additions', [ 'type' => 'string', 'default' => '' ] );

        // Register weighting settings
        register_setting( 'es_smart_search_group_weighting', 'esss_target_acf_fields', [ 'type' => 'array', 'default' => [] ] );
        register_setting( 'es_smart_search_group_weighting', 'esss_weight_text', [ 'type' => 'string', 'default' => '' ] );
        register_setting( 'es_smart_search_group_weighting', 'esss_weight_filters', [ 'type' => 'string', 'default' => '' ] );

    }

    /**
     * Render the settings form for the Smart Search plugin.
     */
    public function render_settings_form(): void {

        // Handle saving if settings are intercepted on options execution pipeline updates
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
            add_settings_error( 'esss_messages', 'esss_message', 'Settings Saved and Dictionary Rebuilt.', 'updated' );
        }
        
        // Fetch the active dictionary list to render for the admin team
        $dictionary_instance = new Dictionary();
        $cached_terms = $dictionary_instance->get_terms();
        sort( $cached_terms ); 

        // Determine the current active tab state selection (defaulting to 'general')
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        ?>
        <div class="wrap">
            <h1>Smart Search Suggestion Settings</h1>
            <?php settings_errors( 'esss_messages' ); ?>
            
            <!-- Native WordPress Tab Navigation Bar Tree -->
            <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <a href="?page=es-smart-search&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">General & Typos</a>
                <a href="?page=es-smart-search&tab=dictionary" class="nav-tab <?php echo $active_tab === 'dictionary' ? 'nav-tab-active' : ''; ?>">Dictionary Overrides</a>
                <a href="?page=es-smart-search&tab=weighting" class="nav-tab <?php echo $active_tab === 'weighting' ? 'nav-tab-active' : ''; ?>">Search Weighting</a>
            </nav>
            
            <div style="display: flex; gap: 40px; margin-top: 20px;">
                
                <!-- Left Column: Full Form Container Matrix -->
                <div style="flex: 2; max-width: 800px;">
                    <form method="post" action="options.php">
                        <?php
                        // Securely print fields matched ONLY to the current view group parameter
                        if ( $active_tab === 'general' ) {
                            settings_fields( 'es_smart_search_group_general' );
                        } elseif ( $active_tab === 'dictionary' ) {
                            settings_fields( 'es_smart_search_group_dictionary' );
                        } elseif ( $active_tab === 'weighting' ) {
                            settings_fields( 'es_smart_search_group_weighting' );
                        }
                        ?>
                        <table class="form-table">
                            
                            <?php if ( $active_tab === 'general' ) : ?>
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
                            <?php endif; ?>
                            
                            <?php if ( $active_tab === 'dictionary' ) : ?>
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
                                        <p class="description">Comma-separated terms to completely strip out of your suggestion dictionary.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php if ( $active_tab === 'weighting' ) : ?>
                                <tr class="esss-tab-row weighting">
                                    <th scope="row"><label for="esss_weight_text">Text search weighting</label></th>
                                    <td>
                                        <textarea name="esss_weight_text" id="esss_weight_text" rows="10" cols="50" class="large-text"><?php echo esc_textarea( get_option( 'esss_weight_text', '' ) ); ?></textarea>
                                        <p class="description">Text weighting scores based on matched field.</p>
                                    </td>
                                </tr>
                                <tr class="esss-tab-row weighting">
                                    <th scope="row"><label for="esss_weight_filters">Filter search weighting</label></th>
                                    <td>
                                        <textarea name="esss_weight_filters" id="esss_weight_filters" rows="10" cols="50" class="large-text"><?php echo esc_textarea( get_option( 'esss_weight_filters', '' ) ); ?></textarea>
                                        <p class="description">Filter weighting scores based on matched field.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>

                        <?php submit_button(); ?>
                    </form>
                </div>

                    <!-- Right Sidebar Column: Active Dictionary Vocabulary Viewer Wrapper (Only display on Dictionary Tab) -->
                    <?php if ( $active_tab === 'dictionary' ) : ?>
                        <div style="flex: 1; min-width: 300px; background: #ffffff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; align-self: flex-start;">
                            <h2>Active Dictionary (<?php echo count( $cached_terms ); ?> words)</h2>
                            <input type="text" id="esss-vocab-search" placeholder="Type to filter words..." style="width: 100%; margin-bottom: 15px; padding: 6px 10px;">
                            <div id="esss-vocab-list" style="max-height: 400px; overflow-y: auto; padding: 10px; background: #f6f7f7; border: 1px solid #dcdcde; display: flex; flex-wrap: wrap; gap: 6px;">
                                <?php foreach ( $cached_terms as $term ) : ?>
                                    <span class="esss-vocab-pill" style="display: inline-block; padding: 4px 10px; background: #fff; border: 1px solid #c3c4c7; border-radius: 12px; font-size: 12px;"><?php echo esc_html( $term ); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    <?php endif; ?>

                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const currentTab = document.getElementById('esss_active_tab').value;
                            const sidebar = document.getElementById('esss-vocab-sidebar');
                            const searchInput = document.getElementById('esss-vocab-search');

                            // Find all native rows currently rendered in your form table container
                            const allRows = Array.from(document.querySelectorAll('form table.form-table tr'));

                            // Map each row index position cleanly to your layout tabs to separate them
                            allRows.forEach(function(row, index) {
                                let belongsToTab = 'general';

                                if (index >= 0 && index <= 3) {
                                    belongsToTab = 'general';      // Rows: Short typos, Long typos, Suggestion limit, Fallback toggle
                                } else if (index >= 4 && index <= 6) {
                                    belongsToTab = 'dictionary';   // Rows: Synonyms text area, Manual additions, Blacklist removals
                                } else if (index >= 7 && index <= 9) {
                                    belongsToTab = 'weighting';    // Rows: ACF checkboxes, Title weight input, Fields weight input
                                }

                                // Apply strict structural display toggles to spread them across tabs cleanly
                                if (belongsToTab === currentTab) {
                                    row.style.display = 'table-row';
                                } else {
                                    row.style.display = 'none';
                                }
                            });

                            // Only display your active dictionary word viewer sidebar if the Overrides tab is selected
                            if (sidebar) {
                                sidebar.style.display = currentTab === 'dictionary' ? 'block' : 'none';
                            }

                            // Amends the options target action to redirect directly back into your active tab viewport on save
                            const form = document.querySelector('form[action="options.php"]');
                            if (form) {
                                form.action = form.action + '?tab=' + currentTab;
                            }

                            // Instant, live client-side dictionary filter logic
                            if (searchInput) {
                                searchInput.addEventListener('input', function(e) {
                                    const filterText = e.target.value.toLowerCase().trim();
                                    const pills = document.querySelectorAll('.esss-vocab-pill');

                                    pills.forEach(function(pill) {
                                        const text = pill.textContent.toLowerCase();
                                        pill.style.display = text.includes(filterText) ? 'inline-block' : 'none';
                                    });
                                });
                            }
                        });
                        </script>

                </div>
            </div>
        <?php
    }

}
