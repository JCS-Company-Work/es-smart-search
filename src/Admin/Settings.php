<?php

namespace EsSmartSearch\Admin;

class Settings {

    public static function boot(): void {
        $instance = new self();
        add_action( 'admin_menu', [ $instance, 'add_menu_page' ] );
        add_action( 'admin_init', [ $instance, 'register_settings' ] );
    }

    public function add_menu_page(): void {
        add_options_page(
            'Smart Search Suggestions',
            'Smart Search',
            'manage_options',
            'es-smart-search',
            [ $this, 'render_settings_form' ]
        );
    }

    public function register_settings(): void {
        register_setting( 'es_smart_search_group', 'esss_max_distance_short', [ 'type' => 'integer', 'default' => 1 ] );
        register_setting( 'es_smart_search_group', 'esss_max_distance_long', [ 'type' => 'integer', 'default' => 2 ] );
        register_setting( 'es_smart_search_group', 'esss_ignored_terms', [ 'type' => 'string', 'default' => '' ] );
    }

    public function render_settings_form(): void {
        ?>
        <div class="wrap">
            <h1>Smart Search Suggestion Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'es_smart_search_group' );
                do_settings_sections( 'es_smart_search_group' );
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="esss_max_distance_short">Max Typos (Words ≤ 5 letters)</label></th>
                        <td>
                            <input type="number" name="esss_max_distance_short" id="esss_max_distance_short" value="<?php echo esc_attr( get_option( 'esss_max_distance_short', 1 ) ); ?>" min="0" max="3" class="small-text">
                            <p class="description">Recommended: 1. Controls words like "blue" or "grey".</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="esss_max_distance_long">Max Typos (Words ≥ 6 letters)</label></th>
                        <td>
                            <input type="number" name="esss_max_distance_long" id="esss_max_distance_long" value="<?php echo esc_attr( get_option( 'esss_max_distance_long', 2 ) ); ?>" min="0" max="4" class="small-text">
                            <p class="description">Recommended: 2. Controls long words like "marble" or "concrete".</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="esss_ignored_terms">Blacklisted Dictionary Words</label></th>
                        <td>
                            <textarea name="esss_ignored_terms" id="esss_ignored_terms" rows="4" cols="50" class="large-text"><?php echo esc_textarea( get_option( 'esss_ignored_terms', '' ) ); ?></textarea>
                            <p class="description">Comma-separated words to completely strip out of your suggestion dictionary (e.g. sample, test, temp).</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}