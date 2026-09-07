<?php

/**
 * View template for the General Tab fields matrix
 */
defined( 'ABSPATH' ) || exit;

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