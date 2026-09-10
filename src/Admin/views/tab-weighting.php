<?php
/**
 * View template for the Weighting Tab fields matrix
 */
defined( 'ABSPATH' ) || exit;

$current_weights = get_option( 'esss_weight_text', [] );
if ( ! is_array( $current_weights ) ) {
    $current_weights = []; 
}
arsort( $current_weights ); 

$filter_weights = get_option( 'esss_weight_filters', [] );
if ( ! is_array( $filter_weights ) ) { 
    $filter_weights = []; 
}
arsort( $filter_weights );

$available_fields = $this->get_all_available_fields();
error_log( 'available_fields: ' . print_r( $available_fields, true ) );
$json_fields      = esc_attr( wp_json_encode( $available_fields ) );
?>

<tr>
    <th scope="row">Text Search Weighting</th>
    <td>
        <!-- Updated to use class and removed matrix ID -->
        <div class="esss-weight-manager-block" data-all-fields="<?php echo $json_fields; ?>" data-type="text">
            <p class="description" style="margin-bottom: 15px;">Add unique product fields and assign search weights (0-100). Fields already assigned cannot be selected twice.</p>
            
            <table class="wp-list-table widefat fixed striped" style="margin-bottom: 15px;">
                <thead>
                    <tr>
                        <th style="width: 60%;">Product Field Name</th>
                        <th style="width: 25%;">Weight Value (0-100)</th>
                        <th style="width: 15%;"></th>
                    </tr>
                </thead>
                <!-- Changed from ID to Class -->
                <tbody class="esss-weight-rows-container">
                    <?php foreach ( $current_weights as $field_key => $weight_val ) : 
                        if ( ! isset( $available_fields[ $field_key ] ) ) continue; 
                        ?>
                        <tr class="esss-weight-row">
                            <td>
                                <select name="esss_weight_text[keys][]" class="esss-field-selector" data-selected="<?php echo esc_attr( $field_key ); ?>" style="width: 100%;"></select>
                            </td>
                            <td>
                                <input type="number" name="esss_weight_text[values][]" value="<?php echo esc_attr( $weight_val ); ?>" class="small-text" min="0" max="100" style="width: 100%;">
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="button esss-remove-weight-row">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button type="button" class="button button-secondary esss-add-weight-row">+ Add Custom Field Row</button>
        </div>
    </td>
</tr>

<tr>
    <th scope="row">Active Filter Weighting</th>
    <td>
        <div class="esss-weight-manager-block" style="max-width: 600px;" data-all-fields="<?php echo $json_fields; ?>" data-type="filter">
            <p class="description" style="margin-bottom: 12px;">Configure weights for active filters.</p>
            
            <table class="wp-list-table widefat fixed striped" style="margin-bottom: 15px;">
                <thead>
                    <tr>
                        <th style="width: 60%;">Filter Group Name</th>
                        <th style="width: 25%;">Weight Value (0-100)</th>
                        <th style="width: 15%;"></th>
                    </tr>
                </thead>
                <tbody class="esss-weight-rows-container">
                    <?php foreach ( $filter_weights as $field_key => $weight_val ) : 
                        if ( ! isset( $available_fields[ $field_key ] ) ) continue; 
                        ?>
                        <tr class="esss-weight-row">
                            <td>
                                <select name="esss_weight_filters[keys][]" class="esss-field-selector" data-selected="<?php echo esc_attr( $field_key ); ?>" style="width: 100%;"></select>
                            </td>
                            <td>
                                <input type="number" name="esss_weight_filters[values][]" value="<?php echo esc_attr( $weight_val ); ?>" class="small-text" min="0" max="100" style="width: 100%;">
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="button esss-remove-weight-row">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <button type="button" class="button button-secondary esss-add-weight-row">+ Add Active Filter Row</button>
        </div>
    </td>
</tr>
