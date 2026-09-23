<?php

/**
 * View template for the Dictionary Tab fields matrix
 */
defined( 'ABSPATH' ) || exit;
$placeholder = get_option( 'smart_search_reporting_api_key', '' );
?>
    <div class="esss-form-column">
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Reporting API Key</th>
                <td>
                    <div style="display: flex; gap: 8px; align-items: center; max-width: 500px;">
                        <input type="password" 
                                id="smart_search_api_field"
                                name="smart_search_reporting_api_key" 
                                value="<?php echo esc_attr( $placeholder ); ?>" 
                                class="regular-text" 
                                autocomplete="off" />
                        <button type="button" id="smart_search_btn_generate" class="button button-secondary">Generate</button>
                        <button type="button" id="smart_search_btn_copy" class="button button-secondary">Copy</button>
                    </div>
                    <p id="smart_search_status_msg" class="description">API key to allow access to reporting service.</p>
                </td>
            </tr>
        </table>
    </div>