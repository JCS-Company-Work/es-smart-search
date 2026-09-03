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
        // Register options explicitly with standard text strings validation attributes
        register_setting( 'es_smart_search_group', 'esss_max_distance_short', [ 'type' => 'integer', 'default' => 1 ] );
        register_setting( 'es_smart_search_group', 'esss_max_distance_long', [ 'type' => 'integer', 'default' => 2 ] );
        register_setting( 'es_smart_search_group', 'esss_ignored_terms', [ 'type' => 'string', 'default' => '' ] );
        register_setting( 'es_smart_search_group', 'esss_manual_additions', [ 'type' => 'string', 'default' => '' ] );

        // Register the suggestions limit setting
        register_setting( 'es_smart_search_group', 'esss_suggestions_limit', [ 'type' => 'integer', 'default' => 1 ] );

    }

    public function render_settings_form(): void {
        // Handle saving if settings are intercepted on options execution pipeline updates
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
            add_settings_error( 'esss_messages', 'esss_message', 'Settings Saved and Dictionary Rebuilt.', 'updated' );
        }
        
        // Fetch the active dictionary list to render for the admin team
        $dictionary_instance = new Dictionary();
        $cached_terms = $dictionary_instance->get_terms();
        sort( $cached_terms ); 
        ?>
        <div class="wrap">
            <h1>Smart Search Suggestion Settings</h1>
            <?php settings_errors( 'esss_messages' ); ?>
            
            <div style="display: flex; gap: 40px; margin-top: 20px;">
                
                <!-- Left Column: Form Container -->
                <div style="flex: 2; max-width: 800px;">
                    <form method="post" action="options.php">
                        <?php
                        // Output nonce, action, and option page hidden fields natively
                        settings_fields( 'es_smart_search_group' );
                        ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="esss_max_distance_short">Max Typos (Words ≤ 5 letters)</label></th>
                                <td>
                                    <input type="number" name="esss_max_distance_short" id="esss_max_distance_short" value="<?php echo esc_attr( get_option( 'esss_max_distance_short', 1 ) ); ?>" min="0" max="3" class="small-text">
                                    <p class="description">Recommended value: 1. Controls short keywords like "blue" or "grey".</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="esss_max_distance_long">Max Typos (Words ≥ 6 letters)</label></th>
                                <td>
                                    <input type="number" name="esss_max_distance_long" id="esss_max_distance_long" value="<?php echo esc_attr( get_option( 'esss_max_distance_long', 2 ) ); ?>" min="0" max="4" class="small-text">
                                    <p class="description">Recommended value: 2. Controls long keywords like "marble" or "concrete".</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="esss_suggestions_limit">Maximum Suggestions Limit</label></th>
                                <td>
                                    <input type="number" name="esss_suggestions_limit" id="esss_suggestions_limit" value="<?php echo esc_attr( get_option( 'esss_suggestions_limit', 1 ) ); ?>" min="1" max="5" class="small-text">
                                    <p class="description">Controls how many phrase combinations to generate when a search fails.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="esss_manual_additions">Manually Add Words</label></th>
                                <td>
                                    <textarea name="esss_manual_additions" id="esss_manual_additions" rows="3" cols="50" class="large-text"><?php echo esc_textarea( get_option( 'esss_manual_additions', '' ) ); ?></textarea>
                                    <p class="description">Comma-separated terms to force-inject into the dictionary index.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="esss_ignored_terms">Manually Remove Words (Blacklist)</label></th>
                                <td>
                                    <textarea name="esss_ignored_terms" id="esss_ignored_terms" rows="3" cols="50" class="large-text"><?php echo esc_textarea( get_option( 'esss_ignored_terms', '' ) ); ?></textarea>
                                    <p class="description">Comma-separated terms to completely strip out of your suggestion dictionary.</p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(); ?>
                    </form>
                </div>

                <!-- Right Column: Active Dictionary Vocabulary Viewer -->
                <div style="flex: 1; min-width: 330px; background: #ffffff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); align-self: flex-start;">
                    <h2 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; font-size: 1.3em;">
                        Active Dictionary Viewer 
                        <span style="font-size: 0.65em; color: #64748b; font-weight: normal; float: right; margin-top: 6px;">
                            (<?php echo count( $cached_terms ); ?> total words)
                        </span>
                    </h2>
                    <p>These are the terms that are currently included in your suggestion dictionary.</p>
                    
                    <input type="text" id="esss-vocab-search" placeholder="Type to filter words..." style="width: 100%; margin-bottom: 15px; padding: 6px 10px;">
                    
                    <div id="esss-vocab-list" style="max-height: 450px; overflow-y: auto; padding: 10px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 3px; display: flex; flex-wrap: wrap; gap: 6px;">
                        <?php if ( ! empty( $cached_terms ) ) : ?>
                            <?php foreach ( $cached_terms as $term ) : ?>
                                <span class="esss-vocab-pill" style="display: inline-block; padding: 4px 10px; background: #fff; border: 1px solid #c3c4c7; border-radius: 12px; font-size: 12px; color: #2c3338; font-weight: 500;">
                                    <?php echo esc_html( $term ); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p style="color: #64748b; font-style: italic; margin: 0;">Dictionary is currently empty. Save a product or trigger a rebuild to compile words.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('esss-vocab-search');
            const pills = document.querySelectorAll('.esss-vocab-pill');

            if (!searchInput) return;

            searchInput.addEventListener('input', function(e) {
                const filterText = e.target.value.toLowerCase().trim();

                pills.forEach(function(pill) {
                    const text = pill.textContent.toLowerCase();
                    if (text.includes(filterText)) {
                        pill.style.display = 'inline-block';
                    } else {
                        pill.style.display = 'none';
                    }
                });
            });
        });
        </script>
        <?php
    }
}
