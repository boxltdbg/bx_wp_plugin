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
    return $actions;
}

/**
 * Handle the bulk action
 * Works for both legacy CPT (post_ids) and HPOS (order IDs)
 */
function boxnow_handle_bulk_action($redirect_to, $action, $post_ids)
{
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
                'boxnow_pdf_url' => $pdf_result['pdf_url']
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
    // Prepare data for the order
    $prep_data = boxnow_prepare_data($order);
    $locker_id = isset($prep_data['locker_id']) ? $prep_data['locker_id'] : '';

    if (!$locker_id) {
        return array(
            'success' => false,
            'error' => __('Missing locker ID', 'boxnowbulgaria')
        );
    }

    // Validate locker availability
    if (!boxnow_verify_locker_availability($locker_id)) {
        return array(
            'success' => false,
            'error' => sprintf(
                __('Locker %s is not available in the BOX NOW network. Please open the order and select another locker from the order details.', 'boxnowbulgaria'), 
                $locker_id
            )
        );
    }

    // Generate voucher using the main delivery request function
    // Default to medium compartment size (2) for bulk
    $compartment_size = 2;
    $voucher_quantity = 1;
    
    try {
        $delivery_request_response = boxnow_send_delivery_request($prep_data, $order_id, $voucher_quantity, $compartment_size);
        $response_body = json_decode($delivery_request_response, true);

        if (isset($response_body['id']) && isset($response_body['parcels'])) {
            // Vouchers created successfully
            $parcel_ids = array();
            
            foreach ($response_body['parcels'] as $parcel) {
                $parcel_ids[] = $parcel['id'];
                update_option('_boxnow_parcel_order_id_' . $parcel['id'], $order_id);
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
    $access_token = boxnow_get_access_token();
    
    if (!$access_token) {
        return array(
            'success' => false,
            'error' => __('Error getting access token', 'boxnowbulgaria')
        );
    }

    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/boxnow-temp/';
    
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }

    $pdf_files = array();
    $successful_downloads = 0;

    // Download individual PDFs for each parcel
    foreach ($parcel_ids as $parcel_id) {
        $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/parcels/' . $parcel_id . '/label.pdf';
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'accept' => 'application/pdf'
            ),
            'timeout' => 30
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $pdf_content = wp_remote_retrieve_body($response);
            if (!empty($pdf_content)) {
                // Save individual PDF temporarily
                $temp_filename = 'boxnow-temp-' . $parcel_id . '-' . time() . '.pdf';
                $temp_file_path = $temp_dir . $temp_filename;
                file_put_contents($temp_file_path, $pdf_content);
                $pdf_files[] = $temp_file_path;
                $successful_downloads++;
            }
        }
    }

    if ($successful_downloads === 0) {
        return array(
            'success' => false,
            'error' => __('No valid PDF files found for download', 'boxnowbulgaria')
        );
    }

    // Try to use Ghostscript if available, otherwise use simple concatenation
    $combined_pdf_content = '';
    
    // Check if Ghostscript is available
    $ghostscript_path = '';
    $possible_paths = array('/usr/bin/gs', '/usr/local/bin/gs', 'gs', 'gswin64c.exe', 'gswin32c.exe');
    
    foreach ($possible_paths as $path) {
        if (is_executable($path) || shell_exec("which $path 2>/dev/null")) {
            $ghostscript_path = $path;
            break;
        }
    }
    
    if ($ghostscript_path && count($pdf_files) > 1) {
        // Use Ghostscript to combine PDFs
        $input_files = implode(' ', array_map('escapeshellarg', $pdf_files));
        $output_file = $temp_dir . 'combined_temp.pdf';
        
        $command = "$ghostscript_path -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=" . escapeshellarg($output_file) . " $input_files 2>/dev/null";
        
        $result = shell_exec($command);
        
        if (file_exists($output_file) && filesize($output_file) > 0) {
            $combined_pdf_content = file_get_contents($output_file);
            unlink($output_file);
        }
    }
    
    // If Ghostscript failed or not available, use simple concatenation
    if (empty($combined_pdf_content)) {
        // Simple approach: concatenate PDFs with proper structure
        $combined_pdf_content = '';
        
        // Start with first PDF
        if (!empty($pdf_files)) {
            $first_pdf = file_get_contents($pdf_files[0]);
            if ($first_pdf) {
                $combined_pdf_content = $first_pdf;
                
                // Append other PDFs (remove headers)
                for ($i = 1; $i < count($pdf_files); $i++) {
                    $pdf_content = file_get_contents($pdf_files[$i]);
                    if ($pdf_content) {
                        // Remove PDF header and footer
                        $pdf_content = preg_replace('/%PDF-[\d.]+/', '', $pdf_content);
                        $pdf_content = preg_replace('/%%EOF.*$/', '', $pdf_content);
                        $combined_pdf_content .= $pdf_content;
                    }
                }
            }
        }
    }
    
    // Clean up individual PDF files
    foreach ($pdf_files as $pdf_file) {
        if (file_exists($pdf_file)) {
            unlink($pdf_file);
        }
    }

    // Save combined PDF
    $filename = 'boxnow-bulk-vouchers-' . date('Y-m-d-H-i-s') . '.pdf';
    $file_path = $temp_dir . $filename;
    
    if (file_put_contents($file_path, $combined_pdf_content) === false) {
        return array(
            'success' => false,
            'error' => __('Error saving combined PDF file', 'boxnowbulgaria')
        );
    }

    $pdf_url = $upload_dir['baseurl'] . '/boxnow-temp/' . $filename;

    return array(
        'success' => true,
        'pdf_url' => $pdf_url,
        'file_path' => $file_path,
        'downloaded_count' => $successful_downloads
    );
}

// Add admin notices for bulk action results
add_action('admin_notices', 'boxnow_bulk_action_admin_notices');

function boxnow_bulk_action_admin_notices()
{
    if (isset($_GET['boxnow_bulk_success'])) {
        $count = intval($_GET['boxnow_bulk_success']);
        $pdf_url = isset($_GET['boxnow_pdf_url']) ? $_GET['boxnow_pdf_url'] : '';
        
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>' . sprintf(__('Successfully generated vouchers for %d orders.', 'boxnowbulgaria'), $count) . '</p>';
        
        if ($pdf_url) {
            echo '<p><a href="' . esc_url($pdf_url) . '" class="button button-primary" target="_blank">' . __('Download combined PDF', 'boxnowbulgaria') . '</a></p>';
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
        $files = glob($temp_dir . '*.pdf');
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
