<?php

/**
 * View template for the Weighting Tab fields matrix
 */
defined( 'ABSPATH' ) || exit;

    $current_weights = get_option( 'esss_weight_text', [] );
    if ( ! is_array( $current_weights ) ) {
        $current_weights = []; // Instantly drops the stale string, protecting arsort()
    }

    arsort( $current_weights ); // Automatically sort heaviest items first
    
    $available_fields = $this->get_all_available_fields();
    ?>
    <tr>
        <th scope="row">Text Search Weighting</th>
        <td>
            <!-- Pass our dynamic database schema down to the JavaScript runner cleanly -->
            <div id="esss-weight-matrix-wrapper" 
                style="max-width: 600px;" 
                data-all-fields="<?php echo esc_attr( wp_json_encode( $available_fields ) ); ?>">
                
                <p class="description" style="margin-bottom: 15px;">Add unique product fields and assign search weights (0-100). Fields already assigned cannot be selected twice.</p>
                
                <table class="wp-list-table widefat fixed striped" style="margin-bottom: 15px;">
                    <thead>
                        <tr>
                            <th style="width: 60%;">Product Field Name</th>
                            <th style="width: 25%;">Weight Value (0-100)</th>
                            <th style="width: 15%; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="esss-weight-rows-container">
                        <?php foreach ( $current_weights as $field_key => $weight_val ) : 
                            if ( ! isset( $available_fields[ $field_key ] ) ) {
                                continue; // Skip orphan database entries if they disappear from schema
                            }
                            ?>
                            <tr class="esss-weight-row">
                                <td>
                                    <!-- Populated dynamically and filtered via JS on load -->
                                    <select name="esss_weight_text[keys][]" class="esss-field-selector" data-selected="<?php echo esc_attr( $field_key ); ?>" style="width: 100%;"></select>
                                </td>
                                <td>
                                    <input type="number" name="esss_weight_text[values][]" value="<?php echo esc_attr( $weight_val ); ?>" class="small-text" min="0" max="100" style="width: 100%;">
                                </td>
                                <td style="text-align: center;">
                                    <button type="button" class="button esss-remove-weight-row" style="color: #b32d2e; border-color: #b32d2e;">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <button type="button" id="esss-add-weight-row" class="button button-secondary">+ Add Custom Field Row</button>
            </div>
        </td>
    </tr>
    <tr class="esss-tab-row weighting">
        <th scope="row"><label for="esss_weight_filters">Filter search weighting</label></th>
        <td>
            <textarea name="esss_weight_filters" id="esss_weight_filters" rows="10" cols="50" class="large-text"><?php echo esc_textarea( get_option( 'esss_weight_filters', '' ) ); ?></textarea>
            <p class="description">Filter weighting scores based on matched field.</p>
        </td>
    </tr>
