<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Box Now Delivery Order Column Indicator
 * Adds visual indicator in the Order column for BOX NOW orders
 */

// (оставяме само новата колона, без инжекции в номера/прегледа)

// Добавяме отделна колона "BOX NOW" (по желание на клиента)
add_filter('manage_edit-shop_order_columns', 'boxnow_add_indicator_column');
add_filter('manage_woocommerce_page_wc-orders_columns', 'boxnow_add_indicator_column');
add_action('manage_shop_order_posts_custom_column', 'boxnow_render_indicator_column', 20, 2);
add_action('manage_woocommerce_page_wc-orders_custom_column', 'boxnow_render_indicator_column', 20, 2);

// Add voucher/parcel ID column
add_filter('manage_edit-shop_order_columns', 'boxnow_add_voucher_column', 20);
add_filter('manage_woocommerce_page_wc-orders_columns', 'boxnow_add_voucher_column', 20);
add_action('manage_shop_order_posts_custom_column', 'boxnow_render_voucher_column', 20, 2);
add_action('manage_woocommerce_page_wc-orders_custom_column', 'boxnow_render_voucher_column', 20, 2);

/**
 * Добавя колона за BOX NOW в списъка с поръчки.
 */
function boxnow_add_indicator_column($columns)
{
    // Създаваме нов масив за колоните
    $new_columns = [];
    
    // Добавяме всички колони до тази с чекбоксите
    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;
        
        // След колоната с чекбоксите (обикновено първата), добавяме нашата колона
        if ($key === 'cb') {
            $new_columns['boxnow_indicator'] = ''; // Празен заглавие
        }
    }

    return $new_columns;
}

/**
 * Добавя колона за товарителници между Status и Ship to.
 */
function boxnow_add_voucher_column($columns)
{
    $new_columns = [];
    
    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;
        
        // Add voucher column after order_status (before shipping_address)
        if ($key === 'order_status') {
            $new_columns['boxnow_voucher'] = __('Voucher', 'boxnowbulgaria');
        }
    }

    return $new_columns;
}

/**
 * Renders the voucher/parcel ID column content.
 * Parcel IDs are clickable and open the PDF label in a new tab.
 */
function boxnow_render_voucher_column($column, $order_or_id)
{
    if ($column !== 'boxnow_voucher') {
        return;
    }

    $order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order($order_or_id);
    if (!$order) {
        return;
    }

    // Only show for BOX NOW orders
    if (!boxnow_order_has_boxnow($order)) {
        echo '<span style="color: #999;">—</span>';
        return;
    }

    $parcel_ids = $order->get_meta('_boxnow_parcel_ids', true);
    if (!is_array($parcel_ids)) {
        $parcel_ids = array();
    }

    // Fall back to the legacy single-parcel meta, otherwise vouchers created by
    // the automatic "order completed" flow show as Pending forever.
    if (empty($parcel_ids)) {
        $single = $order->get_meta('_boxnow_parcel_id', true);
        if (!empty($single)) {
            $parcel_ids = array($single);
        }
    }

    if (empty($parcel_ids)) {
        // No voucher created yet
        echo '<span class="boxnow-voucher-status boxnow-voucher-pending" title="' . esc_attr__('No voucher created yet', 'boxnowbulgaria') . '">⏳ ' . esc_html__('Pending', 'boxnowbulgaria') . '</span>';
        return;
    }

    // Display parcel IDs as clickable links to open PDF
    $output = '<div class="boxnow-voucher-ids">';
    foreach ($parcel_ids as $parcel_id) {
        $pdf_url = wp_nonce_url(
            admin_url('admin-ajax.php?action=print_box_now_voucher&parcel_id=' . urlencode($parcel_id)),
            'box-now-delivery-nonce'
        );
        $output .= '<a href="' . esc_url($pdf_url) . '" target="_blank" rel="noopener noreferrer" class="boxnow-voucher-link" title="' . esc_attr__('Open voucher', 'boxnowbulgaria') . ': ' . esc_attr($parcel_id) . '">';
        $output .= '📦 ' . esc_html($parcel_id);
        $output .= '</a><br>';
    }
    $output .= '</div>';
    
    echo $output;
}

/**
 * Рендерира съдържанието на колоната BOX NOW.
 */
function boxnow_render_indicator_column($column, $order_or_id)
{
    if ($column !== 'boxnow_indicator') {
        return;
    }

    $order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order($order_or_id);
    if (!$order) {
        return;
    }

    if (!boxnow_order_has_boxnow($order)) {
        return;
    }

    echo '<span class="boxnow-order-indicator" title="' . esc_attr__('BOX NOW Delivery', 'boxnowbulgaria') . '"><span class="boxnow-badge-line">BOX</span><span class="boxnow-badge-line">NOW</span></span>';
}

/**
 * Get plugin logo url for indicators
 */
function boxnow_get_logo_url()
{
    return plugins_url('css/img/boxnow-logo.png', dirname(__FILE__) . '/../box-now-delivery.php');
}

/**
 * Check if order indicator is enabled via settings
 */
function boxnow_indicator_enabled()
{
    // По подразбиране е включено; ако липсва опцията или е 'on', показваме индикатора.
    $opt = get_option('boxnow_show_order_indicator', 'on');
    return $opt === 'on' || empty($opt);
}

/**
 * Вмъква индикатор директно в номера на поръчката (работи и при HPOS).
 */
function boxnow_filter_admin_order_number($order_number, $order)
{
    if (!boxnow_indicator_enabled()) {
        return $order_number;
    }

    // Ако входният номер вече съдържа "BOX NOW" (напр. от друго място), пак добавяме бейджа.
    $number_has_boxnow = stripos(wp_strip_all_tags($order_number), 'box now') !== false;

    if (!$number_has_boxnow && !boxnow_order_has_boxnow($order)) {
        return $order_number;
    }

    // Показваме текстов бейдж вместо изображение, за да е стабилен.
    $badge = '<span class="boxnow-order-indicator" title="' . esc_attr__('BOX NOW Delivery', 'boxnowbulgaria') . '"><span class="boxnow-badge-line">BOX</span><span class="boxnow-badge-line">NOW</span></span> ';
    return $badge . $order_number;
}

/**
 * Проверява дали поръчката е BOX NOW.
 */
function boxnow_order_has_boxnow($order)
{
    if (!$order) {
        return false;
    }

    // 1) Ако има записан BOX NOW locker в мета, приемаме че е BOX NOW поръчка.
    $locker_id_meta = $order->get_meta('_boxnow_locker_id', true);
    if (!empty($locker_id_meta)) {
        return true;
    }

    // Check shipping methods
    foreach ($order->get_shipping_methods() as $shipping_method) {
        if ($shipping_method->get_method_id() === 'box_now_delivery') {
            return true;
        }
        if (stripos($shipping_method->get_name(), 'box now') !== false) {
            return true;
        }
    }

    // Fallback: shipping method title/meta
    $method_title = $order->get_shipping_method();
    if (!empty($method_title) && stripos($method_title, 'box now') !== false) {
        return true;
    }

    $meta_title = $order->get_meta('_shipping_method_title', true);
    if (!empty($meta_title) && stripos($meta_title, 'box now') !== false) {
        return true;
    }

    // Допълнителни мета за HPOS / legacy
    $locker_meta_json = $order->get_meta('box_now_selected_locker', true);
    if (!empty($locker_meta_json)) {
        return true;
    }

    // Fallback: order title/number contains BOX NOW (defensive: some Order classes lack get_formatted_order_number)
    $order_title = '';
    if (method_exists($order, 'get_formatted_order_number')) {
        $order_title = $order->get_formatted_order_number();
    } elseif (method_exists($order, 'get_order_number')) {
        $order_title = $order->get_order_number();
    } elseif (method_exists($order, 'get_id')) {
        $order_title = '#' . $order->get_id();
    }
    if (!empty($order_title) && stripos($order_title, 'box now') !== false) {
        return true;
    }

    return false;
}

// Add CSS styles for the indicator
add_action('admin_head', 'boxnow_order_column_styles');

function boxnow_order_column_styles()
{
    echo '<style>
        .boxnow-order-indicator {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1px;
            background-color: #44d62d;
            color: white;
            padding: 3px 5px;
            border-radius: 3px;
            font-size: 10px;
            line-height: 1.2;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .boxnow-badge-line {
            display: block;
            white-space: nowrap;
        }

        /* Стилове за класическия WooCommerce интерфейс */
        .widefat .column-boxnow_indicator,
        .wp-list-table .column-boxnow_indicator,
        /* Стилове за новия HPOS интерфейс */
        .woocommerce_page_wc-orders .wp-list-table .column-boxnow_indicator {
            width: 48px !important;
            min-width: 48px !important;
            max-width: 48px !important;
            text-align: center;
            padding: 0 4px !important;
        }

        /* Допълнителни стилове за по-добро подравняване */
        .wp-list-table .column-boxnow_indicator .boxnow-order-indicator {
            margin: 0 auto;
        }
        
        .boxnow-indicator.boxnow-voucher-created {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .boxnow-indicator.boxnow-voucher-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        /* Hover effect */
        .boxnow-indicator:hover {
            opacity: 0.8;
            cursor: help;
        }
        
        /* Voucher column styles */
        .widefat .column-boxnow_voucher,
        .wp-list-table .column-boxnow_voucher,
        .woocommerce_page_wc-orders .wp-list-table .column-boxnow_voucher {
            width: 140px !important;
            min-width: 120px !important;
        }
        
        .boxnow-voucher-ids {
            font-size: 12px;
            line-height: 1.6;
        }
        
        .boxnow-voucher-link {
            display: inline-block;
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            margin-bottom: 2px;
            white-space: nowrap;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .boxnow-voucher-link:hover {
            background-color: #4caf50;
            color: white;
            cursor: pointer;
            text-decoration: none;
        }
        
        .boxnow-voucher-link:focus {
            outline: 2px solid #4caf50;
            outline-offset: 1px;
        }
        
        .boxnow-voucher-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .boxnow-voucher-status.boxnow-voucher-pending {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>';

}

// Add custom CSS for better integration with WooCommerce admin
add_action('admin_enqueue_scripts', 'boxnow_order_column_scripts');

function boxnow_order_column_scripts($hook)
{
    if ($hook !== 'edit.php' || get_post_type() !== 'shop_order') {
        return;
    }
    
    // No column-specific inline styles needed
}
