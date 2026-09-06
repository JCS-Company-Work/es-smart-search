<?php

/**
 * View template for the Dictionary Tab fields matrix
 */
defined( 'ABSPATH' ) || exit;

?>
    <div class="esss-form-column">
        <table class="form-table">
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
        </table>
    </div>
    <div class="esss-sidebar-column">
        <?php $this->render_dictionary_sidebar(); ?>
    </div>