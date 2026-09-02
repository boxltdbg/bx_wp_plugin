<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Box Now Delivery Bulk Vouchers
 * Handles bulk generation of vouchers for multiple orders
 */

// Add bulk action to WooCommerce orders (legacy CPT)
add_filter('bulk_actions-edit-shop_order', 'boxnow_add_bulk_action');
add_filter('handle_bulk_actions-edit-shop_order', 'boxnow_handle_bulk_action', 10, 3);

// Add bulk action for HPOS (WooCommerce Orders screen)
add_filter('bulk_actions-woocommerce_page_wc-orders', 'boxnow_add_bulk_action');
add_filter('handle_bulk_actions-woocommerce_page_wc-orders', 'boxnow_handle_bulk_action', 10, 3);

/**
 * Add bulk action to the dropdown menu
 * Works for both legacy CPT (edit-shop_order) and HPOS (woocommerce_page_wc-orders)
 */
function boxnow_add_bulk_action($actions)
{
    // Check if user has required capability
    if (!current_user_can('edit_shop_orders') && !current_user_can('manage_woocommerce')) {
        return $actions;
    }
    
    $actions['boxnow_generate_bulk_vouchers'] = __('Generate BOX NOW bulk vouchers', 'boxnowbulgaria');
    $actions['boxnow_cancel_bulk_vouchers']   = __('Cancel BOX NOW vouchers', 'boxnowbulgaria');
    return $actions;
}

/**
 * Handle the bulk action
 * Works for both legacy CPT (post_ids) and HPOS (order IDs)
 */
function boxnow_handle_bulk_action($redirect_to, $action, $post_ids)
{
    if ($action === 'boxnow_cancel_bulk_vouchers') {
        if (!current_user_can('edit_shop_orders') && !current_user_can('manage_woocommerce')) {
            return $redirect_to;
        }
        if (!is_array($post_ids) || empty($post_ids)) {
            return $redirect_to;
        }

        $cancelled = 0;
        $failed    = 0;
        $skipped   = 0;

        foreach ($post_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order || !$order->has_shipping_method('box_now_delivery')) {
                continue;
            }

            $parcel_ids = $order->get_meta('_boxnow_parcel_ids');
            if (!is_array($parcel_ids)) {
                $parcel_ids = array();
            }
            $single = $order->get_meta('_boxnow_parcel_id');
            if (!empty($single) && !in_array($single, $parcel_ids, true)) {
                $parcel_ids[] = $single;
            }

            if (empty($parcel_ids)) {
                $skipped++;
                continue;
            }

            $remaining = $parcel_ids;
            foreach ($parcel_ids as $parcel_id) {
                $result = boxnow_send_cancellation_request($parcel_id);
                if ($result === 'success') {
                    $cancelled++;
                    if (($key = array_search($parcel_id, $remaining, true)) !== false) {
                        unset($remaining[$key]);
                    }
                } else {
                    $failed++;
                }
            }

            $order->update_meta_data('_boxnow_parcel_ids', array_values($remaining));
            if (empty($remaining)) {
                $order->delete_meta_data('_boxnow_parcel_id');
            }
            $order->save();
        }

        return add_query_arg(array(
            'boxnow_cancel_done' => 1,
            'boxnow_cancel_ok'   => $cancelled,
            'boxnow_cancel_fail' => $failed,
            'boxnow_cancel_skip' => $skipped,
        ), $redirect_to);
    }

    if ($action !== 'boxnow_generate_bulk_vouchers') {
        return $redirect_to;
    }
    
    // Ensure we have an array of IDs
    if (!is_array($post_ids) || empty($post_ids)) {
        return $redirect_to;
    }

    // Filter only orders with BOX NOW delivery
    $boxnow_orders = array();
    $processed_orders = 0;
    $errors = array();

    foreach ($post_ids as $order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            continue;
        }

        // Check if order has BOX NOW delivery method
        $shipping_methods = $order->get_shipping_methods();
        $has_boxnow = false;
        
        foreach ($shipping_methods as $shipping_method) {
            if ($shipping_method->get_method_id() === 'box_now_delivery') {
                $has_boxnow = true;
                break;
            }
        }

        if ($has_boxnow) {
            $boxnow_orders[] = $order;
        }
    }

    if (empty($boxnow_orders)) {
        $redirect_to = add_query_arg('boxnow_bulk_error', 'no_boxnow_orders', $redirect_to);
        return $redirect_to;
    }

    // Generate vouchers for each order
    $all_parcel_ids = array();
    $successful_orders = array();

    foreach ($boxnow_orders as $order) {
        try {
            $result = boxnow_generate_single_order_voucher($order);
            if ($result['success']) {
                $all_parcel_ids = array_merge($all_parcel_ids, $result['parcel_ids']);
                $successful_orders[] = $order->get_id();
                $processed_orders++;
            } else {
                $errors[] = sprintf(__('Order #%d: %s', 'boxnowbulgaria'), $order->get_id(), $result['error']);
            }
        } catch (Exception $e) {
            $errors[] = sprintf(__('Order #%d: %s', 'boxnowbulgaria'), $order->get_id(), $e->getMessage());
        }
    }

    // Generate combined PDF if we have parcel IDs
    if (!empty($all_parcel_ids)) {
        $pdf_result = boxnow_generate_combined_pdf($all_parcel_ids);
        
        if ($pdf_result['success']) {
            // Note: Order status is NOT automatically changed - let the shop owner manage it manually
            $redirect_to = add_query_arg(array(
                'boxnow_bulk_success' => $processed_orders,
                'boxnow_bulk_file' => rawurlencode(basename($pdf_result['file_path']))
            ), $redirect_to);
        } else {
            $redirect_to = add_query_arg('boxnow_bulk_error', 'pdf_generation_failed', $redirect_to);
        }
    } else {
        $redirect_to = add_query_arg('boxnow_bulk_error', 'no_vouchers_generated', $redirect_to);
    }

    // Add errors to redirect URL if any
    if (!empty($errors)) {
        $redirect_to = add_query_arg('boxnow_bulk_errors', urlencode(implode('; ', $errors)), $redirect_to);
    }

    return $redirect_to;
}

/**
 * Generate voucher for a single order (BULK VERSION - creates new vouchers OR uses existing)
 */
function boxnow_generate_single_order_voucher($order)
{
    $order_id = $order->get_id();
    
    // Check if voucher already exists - return existing parcel IDs
    $existing_parcel_ids = $order->get_meta('_boxnow_parcel_ids', true);
    if (!empty($existing_parcel_ids)) {
        return array(
            'success' => true,
            'parcel_ids' => $existing_parcel_ids,
            'message' => __('Voucher already exists', 'boxnowbulgaria')
        );
    }

    // No voucher exists - CREATE NEW ONE
    // Prepare data for the order. This throws for oversized/overweight orders,
    // so it must be inside a try — an uncaught throw here would abort the entire
    // bulk action for every remaining order.
    try {
        $prep_data = boxnow_prepare_data($order);
    } catch (Exception $e) {
        return array(
            'success' => false,
            'error' => $e->getMessage()
        );
    }

    $locker_id = isset($prep_data['locker_id']) ? $prep_data['locker_id'] : '';

    if (!$locker_id) {
        return array(
            'success' => false,
            'error' => __('Missing locker ID', 'boxnowbulgaria')
        );
    }

    // Locker validity is enforced by the delivery-requests API; no pre-flight check needed.
    // Generate voucher using the main delivery request function
    // Default to large compartment size (3) for bulk
    $compartment_size = 3;
    $voucher_quantity = 1;
    
    try {
        $delivery_request_response = boxnow_send_delivery_request($prep_data, $order_id, $voucher_quantity, $compartment_size);
        $response_body = json_decode($delivery_request_response, true);

        if (isset($response_body['id']) && isset($response_body['parcels'])) {
            // Vouchers created successfully
            $parcel_ids = array();
            
            foreach ($response_body['parcels'] as $parcel) {
                $parcel_ids[] = $parcel['id'];
                // Not autoloaded — see the same call in box-now-delivery.php.
                update_option('_boxnow_parcel_order_id_' . $parcel['id'], $order_id, false);
            }

            $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
            $order->update_meta_data('_boxnow_vouchers_created', 1);
            $order->update_meta_data('_voucher_created', 'yes');
            $order->save();

            return array(
                'success' => true,
                'parcel_ids' => $parcel_ids
            );
        } else {
            // Use shared helper function to parse API error (same as single voucher creation)
            $error_message = boxnow_parse_api_error($response_body);
            
            return array(
                'success' => false,
                'error' => $error_message
            );
        }
    } catch (Exception $e) {
        return array(
            'success' => false,
            'error' => $e->getMessage()
        );
    }
}

/**
 * Generate combined PDF for multiple parcel IDs
 * Since BOX NOW doesn't have bulk PDF API, we'll create individual PDFs and combine them
 */
function boxnow_generate_combined_pdf($parcel_ids)
{
    if (empty($parcel_ids) || !is_array($parcel_ids)) {
        return array('success' => false, 'error' => __('No parcels to print', 'boxnowbulgaria'));
    }

    $access_token = boxnow_get_access_token();
    if (!$access_token) {
        return array('success' => false, 'error' => __('Error getting access token', 'boxnowbulgaria'));
    }

    // Ask the BOX NOW API for one already-combined PDF of all labels (no local
    // merging needed). /api/v1/labels:search returns the merged PDF for the given
    // parcel IDs.
    $endpoint = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/labels:search';
    $response = wp_remote_post($endpoint, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json',
            'accept'        => 'application/pdf',
        ),
        'body' => wp_json_encode(array(
            'parcelIds' => array_values(array_map('strval', $parcel_ids)),
            'paperSize' => 'A6',
        )),
        'timeout' => 60,
    ));

    if (is_wp_error($response)) {
        return array('success' => false, 'error' => $response->get_error_message());
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $pdf_content = wp_remote_retrieve_body($response);
    if ($code !== 200 || empty($pdf_content)) {
        return array(
            'success' => false,
            'error' => sprintf(__('BOX NOW API returned status %d when generating labels.', 'boxnowbulgaria'), $code),
        );
    }

    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/boxnow-temp/';
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }
    if (!file_exists($temp_dir . '.htaccess')) {
        @file_put_contents($temp_dir . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }

    $filename = 'boxnow-bulk-vouchers-' . date('Y-m-d-H-i-s') . '.pdf';
    $file_path = $temp_dir . $filename;
    if (file_put_contents($file_path, $pdf_content) === false) {
        return array('success' => false, 'error' => __('Error saving combined PDF file', 'boxnowbulgaria'));
    }

    return array(
        'success' => true,
        'pdf_url' => $upload_dir['baseurl'] . '/boxnow-temp/' . $filename,
        'file_path' => $file_path,
        'downloaded_count' => count($parcel_ids),
    );
}

add_action('wp_ajax_boxnow_download_bulk_file', 'boxnow_download_combined_file');

function boxnow_download_combined_file()
{
    if (!current_user_can('edit_shop_orders')) {
        wp_die(esc_html__('Unauthorized', 'boxnowbulgaria'), '', array('response' => 403));
    }

    $file = isset($_GET['file']) ? basename(sanitize_file_name(wp_unslash($_GET['file']))) : '';

    if ($file === '' || !isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'boxnow_download_' . $file)) {
        wp_die(esc_html__('Invalid or expired link.', 'boxnowbulgaria'), '', array('response' => 403));
    }

    $upload_dir = wp_upload_dir();
    $temp_dir   = trailingslashit($upload_dir['basedir']) . 'boxnow-temp/';
    $real_path  = realpath($temp_dir . $file);
    $real_base  = realpath($temp_dir);

    if (!$real_path || !$real_base || strpos($real_path, $real_base) !== 0 || !is_file($real_path)) {
        wp_die(esc_html__('File not found.', 'boxnowbulgaria'), '', array('response' => 404));
    }

    $ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
    nocache_headers();
    if ($ext === 'zip') {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $file . '"');
    } else {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $file . '"');
    }
    header('Content-Length: ' . filesize($real_path));
    readfile($real_path);
    exit;
}

// Add admin notices for bulk action results
add_action('admin_notices', 'boxnow_bulk_action_admin_notices');

function boxnow_bulk_action_admin_notices()
{
    if (isset($_GET['boxnow_bulk_success'])) {
        $count = intval($_GET['boxnow_bulk_success']);
        $bulk_file = isset($_GET['boxnow_bulk_file']) ? basename(sanitize_file_name(wp_unslash($_GET['boxnow_bulk_file']))) : '';

        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>' . sprintf(__('Successfully generated vouchers for %d orders.', 'boxnowbulgaria'), $count) . '</p>';

        if ($bulk_file) {
            $download_url = wp_nonce_url(
                admin_url('admin-ajax.php?action=boxnow_download_bulk_file&file=' . rawurlencode($bulk_file)),
                'boxnow_download_' . $bulk_file
            );
            $is_zip = (strtolower(pathinfo($bulk_file, PATHINFO_EXTENSION)) === 'zip');
            $label = $is_zip ? __('Download voucher labels (ZIP)', 'boxnowbulgaria') : __('Download combined PDF', 'boxnowbulgaria');
            echo '<p><a href="' . esc_url($download_url) . '" class="button button-primary" target="_blank">' . esc_html($label) . '</a></p>';
        }
        echo '</div>';
    }

    if (isset($_GET['boxnow_bulk_error'])) {
        $error_code = $_GET['boxnow_bulk_error'];
        $error_message = '';
        
        switch ($error_code) {
            case 'no_boxnow_orders':
                $error_message = __('No orders with BOX NOW delivery among selected.', 'boxnowbulgaria');
                break;
            case 'pdf_generation_failed':
                $error_message = __('Error generating PDF file.', 'boxnowbulgaria');
                break;
            case 'no_vouchers_generated':
                $error_message = __('No vouchers were generated.', 'boxnowbulgaria');
                break;
            default:
                $error_message = __('An error occurred during processing.', 'boxnowbulgaria');
        }
        
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>' . $error_message . '</p>';
        echo '</div>';
    }

    if (isset($_GET['boxnow_bulk_errors'])) {
        $errors = urldecode($_GET['boxnow_bulk_errors']);
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . __('Partial errors:', 'boxnowbulgaria') . '</strong></p>';
        echo '<p>' . esc_html($errors) . '</p>';
        echo '</div>';
    }
}

// Clean up temporary files (run daily)
add_action('wp_scheduled_delete', 'boxnow_cleanup_temp_files');

function boxnow_cleanup_temp_files()
{
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/boxnow-temp/';
    
    if (is_dir($temp_dir)) {
        $files = glob($temp_dir . '*.{pdf,zip}', GLOB_BRACE);
        if (!is_array($files)) {
            $files = array();
        }
        $cutoff_time = time() - (24 * 60 * 60); // 24 hours ago

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
}

// Schedule cleanup if not already scheduled
if (!wp_next_scheduled('wp_scheduled_delete')) {
    wp_schedule_event(time(), 'daily', 'wp_scheduled_delete');
}

add_action('admin_footer', 'boxnow_bulk_cancel_inline_js');

function boxnow_bulk_cancel_inline_js()
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || !in_array($screen->id, array('edit-shop_order', 'woocommerce_page_wc-orders'), true)) {
        return;
    }

    $confirm_msg = __('Cancel BOX NOW vouchers for the selected orders? This requests cancellation from BOX NOW and cannot be undone.', 'boxnowbulgaria');

    $result_msg = '';
    if (isset($_GET['boxnow_cancel_done'])) {
        $ok   = isset($_GET['boxnow_cancel_ok']) ? intval($_GET['boxnow_cancel_ok']) : 0;
        $fail = isset($_GET['boxnow_cancel_fail']) ? intval($_GET['boxnow_cancel_fail']) : 0;
        if ($fail > 0) {
            $result_msg = sprintf(
                /* translators: 1: cancelled count, 2: failed count */
                __('BOX NOW: %1$d voucher(s) cancelled, %2$d could not be cancelled. Please check those orders and try again.', 'boxnowbulgaria'),
                $ok,
                $fail
            );
        } else {
            $result_msg = sprintf(
                /* translators: %d: cancelled count */
                __('BOX NOW: %d voucher(s) cancelled successfully.', 'boxnowbulgaria'),
                $ok
            );
        }
    }
    ?>
    <script>
      (function ($) {
        var confirmMsg = <?php echo wp_json_encode($confirm_msg); ?>;
        $(document).on('click', '#doaction, #doaction2', function (e) {
          var sel = this.id === 'doaction2' ? 'select[name="action2"]' : 'select[name="action"]';
          if ($(sel).val() === 'boxnow_cancel_bulk_vouchers') {
            if (!window.confirm(confirmMsg)) {
              e.preventDefault();
              e.stopImmediatePropagation();
            }
          }
        });
        <?php if ($result_msg !== '') : ?>
        window.alert(<?php echo wp_json_encode($result_msg); ?>);
        <?php endif; ?>
      })(jQuery);
    </script>
    <?php
}
