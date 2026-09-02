<?php
/*
Plugin Name: BOX NOW Delivery
Description: WordPress plugin by BOX NOW for integrating delivery to BOX NOW locker.
Author: boxnowbulgaria
Text Domain: boxnowbulgaria
Domain Path: /languages
Version: 3.0.0
*/

add_action('before_woocommerce_init', function () {
  if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
  }
});

function boxnow_log($message) {
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log(is_string($message) ? $message : print_r($message, true));
  }
}

// Load plugin text domain for translations
function boxnow_load_textdomain() {
  load_plugin_textdomain('boxnowbulgaria', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'boxnow_load_textdomain');

/**
 * Built-in translations fallback (no .mo required).
 * This makes the plugin work immediately when installed on sites without compiled translation files.
 */
function boxnow_load_builtin_translations() {
  static $loaded = false;
  if ($loaded) {
    return;
  }
  $loaded = true;

  $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
  if ($locale !== 'bg_BG') {
    return; // Non-BG locales use source strings (English).
  }

  $map_file = plugin_dir_path(__FILE__) . 'languages/boxnowbulgaria-bg_BG.php';
  if (!file_exists($map_file)) {
    return;
  }

  $map = include $map_file;
  if (!is_array($map) || empty($map)) {
    return;
  }

  $GLOBALS['boxnowbulgaria_builtin_i18n'] = $map;

  // Simple gettext fallback for our domain only.
  add_filter('gettext', function ($translation, $text, $domain) {
    if ($domain !== 'boxnowbulgaria') {
      return $translation;
    }
    $map = isset($GLOBALS['boxnowbulgaria_builtin_i18n']) && is_array($GLOBALS['boxnowbulgaria_builtin_i18n'])
      ? $GLOBALS['boxnowbulgaria_builtin_i18n']
      : [];

    return isset($map[$text]) ? $map[$text] : $translation;
  }, 1, 3);

  add_filter('gettext_with_context', function ($translation, $text, $context, $domain) {
    if ($domain !== 'boxnowbulgaria') {
      return $translation;
    }
    $map = isset($GLOBALS['boxnowbulgaria_builtin_i18n']) && is_array($GLOBALS['boxnowbulgaria_builtin_i18n'])
      ? $GLOBALS['boxnowbulgaria_builtin_i18n']
      : [];

    $key = $context . "\004" . $text;
    return isset($map[$key]) ? $map[$key] : $translation;
  }, 1, 4);
}
add_action('plugins_loaded', 'boxnow_load_builtin_translations', 0);

// API helpers: multi-country support, phone normalization, location URL builder
require_once plugin_dir_path(__FILE__) . 'includes/box-now-delivery-api-helpers.php';

// Parcel fit helpers: shared size/weight validation for checkout and voucher creation
require_once plugin_dir_path(__FILE__) . 'includes/box-now-delivery-fit.php';

// Cancel order API call file
require_once(plugin_dir_path(__FILE__) . 'includes/box-now-delivery-cancel-order.php');

// Include the box-now-delivery-print-order.php file
require_once plugin_dir_path(__FILE__) . 'includes/box-now-delivery-print-order.php';

// Include the box-now-delivery-bulk-vouchers.php file
require_once plugin_dir_path(__FILE__) . 'includes/box-now-delivery-bulk-vouchers.php';

// Include the box-now-delivery-order-column.php file
require_once plugin_dir_path(__FILE__) . 'includes/box-now-delivery-order-column.php';

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

  // Include custom shipping method file
  include(plugin_dir_path(__FILE__) . 'includes/box-now-delivery-shipping-method.php');

  // Include admin page functions
  include(plugin_dir_path(__FILE__) . 'includes/box-now-delivery-admin-page.php');

  /**
   * Enqueue scripts and styles for Box Now plugin.
   */
  function box_now_delivery_enqueue_scripts()
  {
    if (is_checkout()) {
      $button_color = esc_attr(get_option('boxnow_button_color', '#84C33F'));
      $button_text = esc_attr(get_option('boxnow_button_text', __('Select BOX NOW Locker', 'boxnowbulgaria')));
      $checkout_type = get_option('boxnow_checkout_type', 'classic'); // added setting

      $boxnow_js_path = plugin_dir_path(__FILE__) . 'js/box-now-delivery.js';
      $boxnow_js_ver  = file_exists($boxnow_js_path) ? filemtime($boxnow_js_path) : '3.0.0';
      wp_enqueue_script('box-now-delivery-js', plugin_dir_url(__FILE__) . 'js/box-now-delivery.js', array('jquery'), $boxnow_js_ver, true);
      $boxnow_css_path = plugin_dir_path(__FILE__) . 'css/box-now-delivery.css';
      $boxnow_css_ver  = file_exists($boxnow_css_path) ? filemtime($boxnow_css_path) : '3.0.0';
      wp_enqueue_style('box-now-delivery-css', plugins_url('/css/box-now-delivery.css', __FILE__), array(), $boxnow_css_ver);
      
      // Add custom CSS for locker details
      wp_add_inline_style('box-now-delivery-css', '
        .boxnow-locker-details {
          font-family: "Inter", "Segoe UI", Roboto, -apple-system, BlinkMacSystemFont, sans-serif;
          color: #111827;
          max-width: 100%;
          margin: 18px 0;
          padding: 18px;
          border: 1px solid #b7dfb3;
          border-radius: 12px;
          background: #ffffff;
          box-shadow: 0 6px 16px rgba(17, 24, 39, 0.04);
          line-height: 1.6;
        }

        .boxnow-locker-header {
          display: flex;
          align-items: flex-start;
          justify-content: space-between;
          gap: 10px;
          margin-bottom: 14px;
        }

        .boxnow-locker-title {
          font-size: 18px;
          font-weight: 700;
          letter-spacing: -0.01em;
          margin: 0;
        }

        .boxnow-locker-subtitle {
          font-size: 14px;
          color: #6b7280;
          margin-top: 4px;
        }

        .boxnow-locker-layout {
          display: flex;
          gap: 20px;
          align-items: flex-start;
          flex-wrap: wrap;
        }

        .boxnow-locker-image {
          flex: 0 0 220px;
          max-width: 260px;
        }

        .boxnow-locker-image img {
          width: 100%;
          height: 150px;
          object-fit: cover;
          border-radius: 10px;
          border: 1px solid #dfe3e8;
          box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
          cursor: zoom-in;
          transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .boxnow-locker-image img:hover {
          transform: translateY(-2px);
          box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
        }

        .boxnow-locker-meta {
          flex: 1;
          min-width: 240px;
          display: flex;
          flex-direction: column;
          gap: 12px;
        }

        .boxnow-row {
          display: flex;
          flex-direction: column;
          gap: 4px;
        }

        .boxnow-label {
          font-size: 11px;
          letter-spacing: 0.05em;
          text-transform: uppercase;
          color: #6b7280;
          font-weight: 700;
        }

        .boxnow-value {
          font-size: 15px;
          font-weight: 600;
          color: #1f2937;
        }

        .boxnow-text {
          margin: 0;
          color: #4b5563;
          font-size: 14px;
        }

        .boxnow-chip {
          display: inline-flex;
          align-items: center;
          gap: 6px;
          width: fit-content;
          padding: 7px 12px;
          border-radius: 999px;
          border: 1px solid #bbf7d0;
          background: #ecfdf3;
          color: #166534;
          font-weight: 700;
          font-size: 13px;
        }

        .boxnow-lightbox-backdrop {
          position: fixed;
          inset: 0;
          background: rgba(0, 0, 0, 0.68);
          display: none;
          align-items: center;
          justify-content: center;
          padding: 28px;
          z-index: 9999;
        }

        .boxnow-lightbox-backdrop.is-visible {
          display: flex;
        }

        .boxnow-lightbox-image {
          max-width: 92vw;
          max-height: 92vh;
          border-radius: 12px;
          box-shadow: 0 16px 48px rgba(0, 0, 0, 0.28);
          background: #ffffff;
        }

        .boxnow-lightbox-close {
          position: absolute;
          top: 18px;
          right: 18px;
          width: 38px;
          height: 38px;
          border-radius: 999px;
          border: none;
          background: rgba(0, 0, 0, 0.6);
          color: #ffffff;
          font-size: 18px;
          cursor: pointer;
          transition: background 0.15s ease;
        }

        .boxnow-lightbox-close:hover {
          background: rgba(0, 0, 0, 0.75);
        }
        
        @media (max-width: 768px) {
          .boxnow-locker-details {
            margin: 12px 0 !important;
            padding: 14px !important;
          }
          
          .boxnow-locker-layout {
            flex-direction: column;
            gap: 14px;
          }
          
          .boxnow-locker-image {
            width: 100%;
          }
          
          .boxnow-locker-image img {
            width: 100% !important;
            height: 180px !important;
          }
        }
        
        @media (max-width: 480px) {
          .boxnow-locker-details {
            margin: 10px 0 !important;
            padding: 12px !important;
          }
        }
      ');

      // Get the custom COD description for BoxNow - always get it from settings
      // (don't check if BoxNow is selected - JS will handle that)
      $custom_cod_description = '';
      $_cod_locale = get_locale();
      $_cod_is_bg  = (strpos($_cod_locale, 'bg') === 0);

      // Get from all shipping zones
        $zones = WC_Shipping_Zones::get_zones();
        foreach ($zones as $zone) {
          if (!empty($zone['shipping_methods'])) {
            foreach ($zone['shipping_methods'] as $method) {
              if ($method->id === 'box_now_delivery') {
                $enable_custom = $method->get_option('enable_custom_cod_description');
                if ('yes' === $enable_custom) {
                  $_desc_bg = $method->get_option('custom_cod_description');
                  $_desc_en = $method->get_option('custom_cod_description_en');
                  $custom_cod_description = (!$_cod_is_bg && !empty($_desc_en)) ? $_desc_en : $_desc_bg;
                  if (!empty($custom_cod_description)) {
                    break 2;
                  }
                }
            }
          }
        }
      }

      // Also check "Rest of the world" zone (ID 0)
      if (empty($custom_cod_description)) {
        $default_zone = new WC_Shipping_Zone(0);
        foreach ($default_zone->get_shipping_methods() as $method) {
          if ($method->id === 'box_now_delivery') {
            $enable_custom = $method->get_option('enable_custom_cod_description');
            if ('yes' === $enable_custom) {
              $_desc_bg = $method->get_option('custom_cod_description');
              $_desc_en = $method->get_option('custom_cod_description_en');
              $custom_cod_description = (!$_cod_is_bg && !empty($_desc_en)) ? $_desc_en : $_desc_bg;
              if (!empty($custom_cod_description)) {
                break;
              }
            }
          }
        }
      }

      $allowed_countries      = function_exists('boxnow_get_allowed_countries') ? boxnow_get_allowed_countries() : array('bg');
      $country_codes_param    = implode(',', $allowed_countries);

      $_widget_locale   = strtolower(function_exists('determine_locale') ? determine_locale() : get_locale());
      $_widget_lang2    = substr($_widget_locale, 0, 2);
      $_widget_lang_map = array('bg' => 'bg', 'el' => 'gr', 'gr' => 'gr', 'hr' => 'hr', 'sl' => 'si');
      $widget_language  = isset($_widget_lang_map[$_widget_lang2]) ? $_widget_lang_map[$_widget_lang2] : 'en';

      wp_localize_script('box-now-delivery-js', 'boxNowDeliverySettings', array(
        'embeddedIframe' => esc_attr(get_option('embedded_iframe', '')),
        'displayMode' => esc_attr(get_option('box_now_display_mode', 'popup')),
        'buttonColor' => $button_color,
        'buttonText' => $button_text,
        'lockerNotSelectedMessage' => esc_js(get_option("boxnow_locker_not_selected_message", __('Please select a locker to continue!', 'boxnowbulgaria'))),
        'gps_option' => get_option('boxnow_gps_tracking', 'on'),
        'ajax_url' => admin_url('admin-ajax.php'),
        'checkoutType' => $checkout_type, // pass checkout type to JS
        'customCodDescription' => $custom_cod_description, // COD description for block checkout
        'widgetBaseUrl' => function_exists('boxnow_get_widget_base_url') ? esc_url(boxnow_get_widget_base_url()) : 'https://widget-v5.boxnow.bg',
        'countryCodes' => esc_attr($country_codes_param),
        'widgetLanguage' => esc_attr($widget_language),
        'lockerNonce' => wp_create_nonce('boxnow_set_locker'),
        'partnerId' => esc_attr(get_option('boxnow_partner_id', '')),
        'i18n' => array(
          'selectedLocker' => esc_html__('Selected locker', 'boxnowbulgaria'),
          'lockerName' => esc_html__('Locker Name:', 'boxnowbulgaria'),
          'lockerAddress' => esc_html__('Locker Address:', 'boxnowbulgaria'),
          'selectLockerAlert' => esc_html__('Please select a locker to continue!', 'boxnowbulgaria'),
        ),
      ));
    }
  }
  add_action('wp_enqueue_scripts', 'box_now_delivery_enqueue_scripts');

  /**
   * Display locker details on thank you page after checkout
   */
  add_action('woocommerce_thankyou', 'boxnow_display_locker_details_thankyou', 10, 1);
  
  function boxnow_display_locker_details_thankyou($order_id) {
    $order = wc_get_order($order_id);
    
    if (!$order) {
      return;
    }
    
    // Check if this order has BOX NOW delivery
    $shipping_methods = $order->get_shipping_methods();
    $has_boxnow = false;
    
    foreach ($shipping_methods as $shipping_method) {
      if ($shipping_method->get_method_id() === 'box_now_delivery') {
        $has_boxnow = true;
        break;
      }
    }
    
    if (!$has_boxnow) {
      return;
    }
    
    $locker_id = $order->get_meta('_boxnow_locker_id');
    
    if (!empty($locker_id)) {
      // Get locker details from API
      $locker_details = boxnow_get_locker_details($locker_id);
      
      // Debug: Log if API fails
      if (!$locker_details) {
        boxnow_log("BOX NOW: Failed to get locker details for ID: " . $locker_id);
      }
      
      $has_image = !empty($locker_details['image']);
      $locker_city = $locker_details['city'] ?? '';
      $full_address = trim(($locker_details['address'] ?? '') . (!empty($locker_city) ? ', ' . $locker_city : ''));

      echo '<div class="boxnow-locker-details">';
      echo '<div class="boxnow-locker-header">';
      echo '<div>';
      echo '<p class="boxnow-locker-title">' . esc_html__('BOX NOW Locker', 'boxnowbulgaria') . '</p>';
      echo '<p class="boxnow-locker-subtitle">' . esc_html__('Delivery Details', 'boxnowbulgaria') . '</p>';
      echo '</div>';
      echo '</div>';
      
      if ($locker_details && !empty($locker_details['address'])) {
        echo '<div class="boxnow-locker-layout">';
        
        if ($has_image) {
          echo '<div class="boxnow-locker-image">';
          echo '<img src="' . esc_url($locker_details['image']) . '" alt="BOX NOW Locker" loading="lazy" decoding="async" data-full="' . esc_url($locker_details['image']) . '">';
          echo '</div>';
        }
        
        echo '<div class="boxnow-locker-meta">';
        echo '<div class="boxnow-row">';
        echo '<span class="boxnow-label">' . esc_html__('Address', 'boxnowbulgaria') . '</span>';
        echo '<span class="boxnow-value">' . esc_html($full_address ?: ($locker_details['address'] ?? __('Address not available', 'boxnowbulgaria'))) . '</span>';
        echo '</div>';
        
        echo '<div class="boxnow-row">';
        echo '<span class="boxnow-label">' . esc_html__('Locker ID', 'boxnowbulgaria') . '</span>';
        echo '<span class="boxnow-value">' . esc_html($locker_id) . '</span>';
        echo '</div>';
        
        if (!empty($locker_details['description'])) {
          echo '<div class="boxnow-row">';
          echo '<span class="boxnow-label">' . esc_html__('Note', 'boxnowbulgaria') . '</span>';
          echo '<p class="boxnow-text">' . esc_html($locker_details['description']) . '</p>';
          echo '</div>';
        }
        
        echo '<span class="boxnow-chip">' . esc_html__('Delivery to selected locker', 'boxnowbulgaria') . '</span>';
        echo '</div>';
        echo '</div>';
      } else {
        echo '<div class="boxnow-locker-layout">';
        if ($has_image) {
          echo '<div class="boxnow-locker-image">';
          echo '<img src="' . esc_url($locker_details['image']) . '" alt="BOX NOW Locker" loading="lazy" decoding="async" data-full="' . esc_url($locker_details['image']) . '">';
          echo '</div>';
        }
        echo '<div class="boxnow-locker-meta">';
        echo '<div class="boxnow-row">';
        echo '<span class="boxnow-label">' . esc_html__('Locker ID', 'boxnowbulgaria') . '</span>';
        echo '<span class="boxnow-value">' . esc_html($locker_id) . '</span>';
        echo '</div>';
        echo '<p class="boxnow-text">' . esc_html__('Details will be loaded when the order is processed.', 'boxnowbulgaria') . '</p>';
        echo '<span class="boxnow-chip">' . esc_html__('Delivery to selected locker', 'boxnowbulgaria') . '</span>';
        echo '</div>';
        echo '</div>';
      }
      
      if ($has_image) {
        echo '<div class="boxnow-lightbox-backdrop" id="boxnow-locker-lightbox" aria-hidden="true">';
        echo '<button type="button" class="boxnow-lightbox-close" aria-label="' . esc_attr__('Close view', 'boxnowbulgaria') . '">×</button>';
        echo '<img class="boxnow-lightbox-image" src="' . esc_url($locker_details['image']) . '" alt="BOX NOW Locker">';
        echo '</div>';
        echo '<script>
          (function() {
            const img = document.querySelector(".boxnow-locker-image img");
            const lightbox = document.getElementById("boxnow-locker-lightbox");
            if (!img || !lightbox) return;
            const lightboxImg = lightbox.querySelector("img");
            const closeBtn = lightbox.querySelector(".boxnow-lightbox-close");
            const open = function() {
              lightboxImg.src = img.dataset.full || img.src;
              lightbox.classList.add("is-visible");
            };
            const close = function() {
              lightbox.classList.remove("is-visible");
            };
            img.addEventListener("click", open);
            closeBtn && closeBtn.addEventListener("click", close);
            lightbox.addEventListener("click", function(evt) {
              if (evt.target === lightbox) {
                close();
              }
            });
            document.addEventListener("keyup", function(evt) {
              if (evt.key === "Escape") {
                close();
              }
            });
          })();
        </script>';
      }

      echo '</div>';
      
      // Clear localStorage after order completion
      echo '<script>
        (function() {
          // Clear BOX NOW locker data from localStorage after successful order
          try {
            localStorage.removeItem("box_now_selected_locker");
            console.log("BOX NOW: Cleared localStorage after order completion");
          } catch (e) {
            console.warn("BOX NOW: Could not clear localStorage", e);
          }
        })();
      </script>';
    }
  }

  /**
   * Add locker details to WooCommerce emails.
   * Previously limited to admin new_order; now also shown in key customer emails.
   */
  add_action('woocommerce_email_order_details', 'boxnow_add_locker_details_to_email', 10, 4);
  
  function boxnow_add_locker_details_to_email($order, $sent_to_admin, $plain_text, $email) {
    // Allow in selected email types (admin new_order + customer status emails)
    $allowed_email_ids = [
      'new_order',                  // admin
      'customer_processing_order',  // customer
      'customer_completed_order',   // customer
    ];
  
    if (!in_array($email->id, $allowed_email_ids, true)) {
      return;
    }
    
    // Check if this order has BOX NOW delivery
    $shipping_methods = $order->get_shipping_methods();
    $has_boxnow = false;
    
    foreach ($shipping_methods as $shipping_method) {
      if ($shipping_method->get_method_id() === 'box_now_delivery') {
        $has_boxnow = true;
        break;
      }
    }
    
    if (!$has_boxnow) {
      return;
    }
    
    $locker_id = $order->get_meta('_boxnow_locker_id');
    
    if (!empty($locker_id)) {
      // Get locker details from API
      $locker_details = boxnow_get_locker_details($locker_id);
      $locker_city = $locker_details['city'] ?? '';
      $locker_name = $locker_details['name'] ?? '';
      $locker_description = $locker_details['description'] ?? '';
      $locker_address = $locker_details['address'] ?? '';

      // Fallback към данните, съхранени от checkout (както се виждат там)
      $locker_meta_json = $order->get_meta('_box_now_selected_locker', true);
      if (!empty($locker_meta_json)) {
        $locker_meta = json_decode($locker_meta_json, true);
        if (empty($locker_name) && !empty($locker_meta['boxnowLockerName'])) {
          $locker_name = $locker_meta['boxnowLockerName'];
        }
        if (empty($locker_address) && !empty($locker_meta['boxnowLockerAddressLine1'])) {
          $locker_address = $locker_meta['boxnowLockerAddressLine1'];
        }
        if (empty($locker_city) && !empty($locker_meta['boxnowLockerPostalCode'])) {
          $locker_city = $locker_meta['boxnowLockerPostalCode'];
        }
      }

      $full_address_email = trim($locker_address . (!empty($locker_city) ? ', ' . $locker_city : ''));
      $boxnow_logo_url = plugin_dir_url(__FILE__) . 'css/img/boxnow-logo.png';
      
      if ($plain_text) {
        echo "\n" . str_repeat('-', 50) . "\n";
        echo __("BOX NOW LOCKER", 'boxnowbulgaria') . "\n";
        echo str_repeat('-', 50) . "\n";
        echo __("Locker ID:", 'boxnowbulgaria') . " " . $locker_id . "\n";
        if (!empty($locker_name)) {
          echo __("Name:", 'boxnowbulgaria') . " " . $locker_name . "\n";
        }
        
        if (!empty($full_address_email) || !empty($locker_details['address'])) {
          $addr_text = $full_address_email ?: $locker_details['address'];
          echo __("Address:", 'boxnowbulgaria') . " " . $addr_text . "\n";
        }
        
        if (!empty($locker_description)) {
          echo __("Note:", 'boxnowbulgaria') . " " . $locker_description . "\n";
        }
        
        echo __("Your order will be delivered to your selected BOX NOW locker.", 'boxnowbulgaria') . "\n";
        echo str_repeat('-', 50) . "\n";
      } else {
        $accent = '#84C33F';
        echo '<div style="margin: 10px 0 18px 0; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; background: #ffffff; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; border-left: 4px solid ' . esc_attr($accent) . ';">';
        echo '<div style="display:inline-flex; align-items:center; gap:8px; margin-bottom: 10px; padding:6px 10px; border-radius:999px; background:#ecfdf3; border:1px solid #bbf7d0; color:#166534; font-weight:700; font-size:14px; line-height:1.3;">';
        echo '<span>' . esc_html__('Delivery to BOX NOW locker', 'boxnowbulgaria') . '</span>';
        echo '</div>';

        if ($locker_details || !empty($locker_address) || !empty($locker_name)) {
          $img_width  = 220;
          $img_height = 140;

          // Email-safe таблица вместо flex, за еднакъв вид в различни клиенти
          echo '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; border-collapse:collapse; margin-top:6px;">';
          echo '<tr>';

          if (!empty($locker_details['image'])) {
            echo '<td style="width:' . $img_width . 'px; padding:0 12px 0 0; vertical-align:top;">';
            echo '<div style="width:' . $img_width . 'px; height:' . $img_height . 'px;">';
            echo '<img src="' . esc_url($locker_details['image']) . '" alt="BOX NOW Locker" style="width:' . $img_width . 'px; height:' . $img_height . 'px; object-fit: cover; border-radius: 6px; border: 1px solid #d1d5db; display:block;">';
            echo '</div>';
            echo '</td>';
          }

          echo '<td style="vertical-align:top;">';
          echo '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; border-collapse:collapse;">';

          if (!empty($locker_name)) {
            echo '<tr>';
            echo '<td style="font-size:12px; letter-spacing:0.04em; text-transform:uppercase; color:#6b7280; font-weight:700; padding:0 0 6px 0; white-space:nowrap;">' . esc_html__('Locker Name:', 'boxnowbulgaria') . '</td>';
            echo '<td style="font-size:14px; color:#111827; font-weight:700; padding:0 0 6px 8px;">' . esc_html($locker_name) . '</td>';
            echo '</tr>';
          }

          echo '<tr>';
          echo '<td style="font-size:12px; letter-spacing:0.04em; text-transform:uppercase; color:#6b7280; font-weight:700; padding:0 0 6px 0; white-space:nowrap;">' . esc_html__('Address:', 'boxnowbulgaria') . '</td>';
          echo '<td style="font-size:14px; color:#111827; font-weight:700; padding:0 0 6px 8px;">' . esc_html($full_address_email ?: ($locker_details['address'] ?? __('Address not available', 'boxnowbulgaria'))) . '</td>';
          echo '</tr>';

          echo '<tr>';
          echo '<td style="font-size:12px; letter-spacing:0.04em; text-transform:uppercase; color:#6b7280; font-weight:700; padding:0 0 6px 0; white-space:nowrap;">' . esc_html__('Locker ID:', 'boxnowbulgaria') . '</td>';
          echo '<td style="font-size:14px; color:#111827; font-weight:700; padding:0 0 6px 8px;">' . esc_html($locker_id) . '</td>';
          echo '</tr>';

          if (!empty($locker_description)) {
            echo '<tr>';
            echo '<td style="font-size:12px; letter-spacing:0.04em; text-transform:uppercase; color:#6b7280; font-weight:700; padding:4px 0 0 0; vertical-align:top;">' . esc_html__('Note', 'boxnowbulgaria') . '</td>';
            echo '<td style="font-size:13px; color:#374151; line-height:1.5; padding:4px 0 0 8px;">' . esc_html($locker_description) . '</td>';
            echo '</tr>';
          }

          echo '</table>';
          echo '</td>';

          echo '</tr>';
          echo '</table>';
        } else {
          echo '<div style="display: flex; flex-direction: column; gap: 10px;">';
          echo '<div style="display:flex; align-items:center; gap:10px;">';
          echo '<span style="font-size:12px; letter-spacing:0.04em; text-transform:uppercase; color:#6b7280; font-weight:700; white-space:nowrap;">' . esc_html__('Locker ID:', 'boxnowbulgaria') . '</span>';
          echo '<span style="font-size:14px; color:#111827; font-weight:700;">' . esc_html($locker_id) . '</span>';
          echo '</div>';
          echo '<span style="font-size:13px; color:#374151;">' . esc_html__('Details will be available upon delivery.', 'boxnowbulgaria') . '</span>';
          echo '</div>';
        }

        echo '</div>';
      }
    }
  }

  /**
   * Calculate distance between two coordinates using Haversine Formula
   * 
   * @param float $lat1 Latitude of first point
   * @param float $lon1 Longitude of first point
   * @param float $lat2 Latitude of second point
   * @param float $lon2 Longitude of second point
   * @return float Distance in kilometers
   */
  function boxnow_calculate_distance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // Earth radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earth_radius * $c;
    
    return $distance;
  }

  /**
   * Filter lockers by distance from a reference point using Haversine Formula
   * Returns only lockers within 1km radius, sorted by distance (closest first)
   * 
   * @param string $reference_locker_id ID of the reference locker (will be excluded from results)
   * @param float $reference_lat Latitude of reference point
   * @param float $reference_lng Longitude of reference point
   * @param array $lockers_data Array of locker objects from JSON API
   * @param float $max_distance_km Maximum distance in kilometers (default: 1.0)
   * @return array Filtered and sorted array of lockers with distance information
   */
  function boxnow_filter_lockers_by_distance($reference_locker_id, $reference_lat, $reference_lng, $lockers_data, $max_distance_km = 1.0) {
    $filtered_lockers = array();
    $skipped_no_coords = 0;
    $skipped_invalid_coords = 0;
    $skipped_too_far = 0;
    $skipped_reference = 0;
    $processed_count = 0;
    
    // Convert reference coordinates to float using parseFloat equivalent
    $ref_lat = is_numeric($reference_lat) ? floatval($reference_lat) : 0;
    $ref_lng = is_numeric($reference_lng) ? floatval($reference_lng) : 0;
    
    boxnow_log('BOX NOW FILTER: Starting filter for reference locker ' . $reference_locker_id . ' at coordinates: ' . $ref_lat . ', ' . $ref_lng);
    boxnow_log('BOX NOW FILTER: Total lockers to process: ' . count($lockers_data));
    boxnow_log('BOX NOW FILTER: Max distance: ' . $max_distance_km . ' km');
    
    // Validate reference coordinates
    if ($ref_lat == 0 || $ref_lng == 0 || abs($ref_lat) > 90 || abs($ref_lng) > 180) {
      boxnow_log('BOX NOW FILTER: Invalid reference coordinates: lat=' . $ref_lat . ', lng=' . $ref_lng);
      return array();
    }
    
    foreach ($lockers_data as $locker) {
      $processed_count++;
      
      // 1. EXCLUDE CURRENT: Filter out the locker if its id matches the reference ID
      $locker_id = isset($locker['id']) ? (string)$locker['id'] : '';
      if ($locker_id === (string)$reference_locker_id) {
        $skipped_reference++;
        continue;
      }
      
      // 2. COORDINATE MAPPING: Check both 'lat'/'lng' and 'latitude'/'longitude'
      $locker_lat_raw = isset($locker['lat']) ? $locker['lat'] : (isset($locker['latitude']) ? $locker['latitude'] : 0);
      $locker_lng_raw = isset($locker['lng']) ? $locker['lng'] : (isset($locker['longitude']) ? $locker['longitude'] : 0);
      
      // 3. CONVERSION & VALIDATION: Convert to float immediately and skip if missing or zero
      $locker_lat = is_numeric($locker_lat_raw) ? floatval($locker_lat_raw) : 0;
      $locker_lng = is_numeric($locker_lng_raw) ? floatval($locker_lng_raw) : 0;
      
      if ($locker_lat == 0 || $locker_lng == 0) {
        $skipped_no_coords++;
        continue;
      }
      
      // 4. MATH: Calculate distance using Haversine Formula (Earth Radius 6371)
      $distance = boxnow_calculate_distance($ref_lat, $ref_lng, $locker_lat, $locker_lng);
      
      // DEBUGGING: error_log instead of console.log for PHP
      // boxnow_log("Checking Locker " . $locker_id . ": Distance is " . $distance . " km");
      
      // 5. STRICT RADIUS: Only include lockers where distance > 0 AND distance <= 1.0 km
      if ($distance > 0 && $distance <= $max_distance_km) {
        boxnow_log('BOX NOW FILTER: INCLUDED locker ' . (isset($locker['id']) ? $locker['id'] : 'unknown') . ' - distance: ' . round($distance, 3) . ' km');
        
        // Get city for display only (NOT for filtering)
        $city = '';
        if (isset($locker['municipality']) && !empty($locker['municipality'])) {
          $city = trim($locker['municipality']);
        } elseif (isset($locker['municipalityName']) && !empty($locker['municipalityName'])) {
          $city = trim($locker['municipalityName']);
        } elseif (isset($locker['city']) && !empty($locker['city'])) {
          $city = trim($locker['city']);
        } elseif (isset($locker['addressLine2']) && !empty($locker['addressLine2'])) {
          $city = trim($locker['addressLine2']);
        }
        
        $filtered_lockers[] = array(
          'id' => isset($locker['id']) ? $locker['id'] : '',
          'name' => isset($locker['name']) ? $locker['name'] : '',
          'address' => isset($locker['addressLine1']) ? $locker['addressLine1'] : '',
          'city' => $city, // For display only, NOT used for filtering
          'latitude' => $locker_lat,
          'longitude' => $locker_lng,
          'distance' => round($distance, 3), // Distance in kilometers, rounded to 3 decimal places
          'image' => isset($locker['image']) ? $locker['image'] : '',
          'description' => isset($locker['description']) ? $locker['description'] : '',
        );
      } else {
        $skipped_too_far++;
        if ($processed_count <= 5) { // Log first 5 for debugging
          boxnow_log('BOX NOW FILTER: Skipped locker ' . (isset($locker['id']) ? $locker['id'] : 'unknown') . ' - distance: ' . round($distance, 3) . ' km (too far)');
        }
      }
    }
    
    boxnow_log('BOX NOW FILTER: Processed ' . $processed_count . ' lockers');
    boxnow_log('BOX NOW FILTER: Skipped ' . $skipped_reference . ' reference locker(s)');
    boxnow_log('BOX NOW FILTER: Skipped ' . $skipped_no_coords . ' lockers without coordinates');
    boxnow_log('BOX NOW FILTER: Skipped ' . $skipped_invalid_coords . ' lockers with invalid coordinates');
    boxnow_log('BOX NOW FILTER: Skipped ' . $skipped_too_far . ' lockers too far (>' . $max_distance_km . ' km)');
    boxnow_log('BOX NOW FILTER: Found ' . count($filtered_lockers) . ' lockers within ' . $max_distance_km . ' km');
    
    // Sort by distance (ascending) - closest first
    usort($filtered_lockers, function($a, $b) {
      $dist_a = isset($a['distance']) ? $a['distance'] : 999999;
      $dist_b = isset($b['distance']) ? $b['distance'] : 999999;
      if ($dist_a < $dist_b) return -1;
      if ($dist_a > $dist_b) return 1;
      return 0;
    });
    
    return $filtered_lockers;
  }

  /**
   * Find nearby lockers using Haversine Formula
   * 
   * @param string $locker_id Current locker ID (can be empty if using order address)
   * @param int $limit Number of nearby lockers to return
   * @param object|null $order Optional order object to use delivery address if locker not found
   * @return array Array of nearby lockers with distance
   */
  function boxnow_find_nearby_lockers($locker_id, $limit = 5, $order = null) {
    $current_lat = null;
    $current_lng = null;

    // Fetch lockers from all allowed region APIs (multi-country support).
    $data = function_exists('boxnow_fetch_all_lockers_data')
      ? boxnow_fetch_all_lockers_data()
      : array('data' => array());

    if (empty($data['data'])) {
      boxnow_log('BOX NOW: Failed to get lockers from location API or invalid response format.');
      return array();
    }

    // 1. PRIORITIZE API LOOKUP: Find the coordinates of the locker causing the error ($locker_id)
    // This ensures that if you switch from Sofia to Burgas, the radius moves to Burgas.
    if (!empty($locker_id)) {
      foreach ($data['data'] as $locker) {
        if (!empty($locker['id']) && (string)$locker['id'] === (string)$locker_id) {
          $locker_lat_raw = isset($locker['lat']) ? $locker['lat'] : (isset($locker['latitude']) ? $locker['latitude'] : 0);
          $locker_lng_raw = isset($locker['lng']) ? $locker['lng'] : (isset($locker['longitude']) ? $locker['longitude'] : 0);
          
          if ($locker_lat_raw != 0 && $locker_lng_raw != 0) {
            $current_lat = floatval($locker_lat_raw);
            $current_lng = floatval($locker_lng_raw);
            boxnow_log('BOX NOW: Found reference locker ' . $locker_id . ' in API. Using its coordinates: ' . $current_lat . ', ' . $current_lng);
            break;
          }
        }
      }
    }

    // 2. FALLBACK: Only if not in API, use saved order meta (might be stale!)
    if (($current_lat === null || $current_lng === null) && $order !== null) {
      $saved_lat = $order->get_meta('_boxnow_locker_latitude');
      $saved_lng = $order->get_meta('_boxnow_locker_longitude');
      if (!empty($saved_lat) && !empty($saved_lng)) {
        $current_lat = floatval($saved_lat);
        $current_lng = floatval($saved_lng);
        boxnow_log('BOX NOW: Reference locker not in API. Falling back to order meta: ' . $current_lat . ', ' . $current_lng);
      }
    }

    if ($current_lat === null || $current_lng === null) {
      boxnow_log('BOX NOW: No reference coordinates available for locker ' . $locker_id . '. Returning empty array.');
      return array();
    }
    
    // Use the distance-only filter function with the verified coordinates
    $filtered_lockers = boxnow_filter_lockers_by_distance($locker_id, $current_lat, $current_lng, $data['data'], 1.0);
    
    if (count($filtered_lockers) > 0) {
      return array_slice($filtered_lockers, 0, $limit);
    }

    return array();
  }

  /**
   * Get locker details from BOX NOW API
   */
  function boxnow_get_locker_details($locker_id) {
    if (empty($locker_id)) {
      return false;
    }

    // Try to get from cache first
    // bump cache key to include city data
    $cache_key = 'boxnow_locker_v3_' . $locker_id;
    $cached_details = get_transient($cache_key);
    
    if ($cached_details !== false) {
      return $cached_details;
    }

    // Find the locker across all allowed region APIs.
    $locker_data = function_exists('boxnow_fetch_locker_by_id')
      ? boxnow_fetch_locker_by_id($locker_id)
      : false;

    if (!$locker_data) {
      return false;
    }
    
    // Prepare locker details from location API data
    $locker_details = array(
      'id' => $locker_data['id'] ?? $locker_id,
      'address' => $locker_data['addressLine1'] ?? '',
      'city' => $locker_data['municipality']
        ?? $locker_data['municipalityName']
        ?? $locker_data['city']
        ?? $locker_data['cityName']
        ?? $locker_data['addressLine2']
        ?? $locker_data['region']
        ?? '',
      'country' => $locker_data['_locker_country'] ?? strtolower($locker_data['country'] ?? ''),
      'description' => $locker_data['description'] ?? '',
      'image' => $locker_data['image'] ?? '',
      'location' => array(
        'lat' => isset($locker_data['lat']) ? $locker_data['lat'] : (isset($locker_data['latitude']) ? $locker_data['latitude'] : ''),
        'lng' => isset($locker_data['lng']) ? $locker_data['lng'] : (isset($locker_data['longitude']) ? $locker_data['longitude'] : ''),
      ),
      'status' => $locker_data['status'] ?? '',
      'capacity' => $locker_data['capacity'] ?? '',
    );

    // Cache for 1 hour
    set_transient($cache_key, $locker_details, HOUR_IN_SECONDS);

    // Debug: Log successful retrieval
    boxnow_log("BOX NOW: Successfully retrieved locker details for ID: " . $locker_id . " - Address: " . $locker_details['address']);

    return $locker_details;
  }

  // Get the selected checkout type from settings
  $checkout_type = get_option('boxnow_checkout_type', 'classic');

  /*
   * CLASSIC CHECKOUT CODE BLOCK (Only executes if checkout_type = 'classic')
   */
  if ($checkout_type === 'classic') {
    // AJAX handler for classic checkout
    add_action('wp_ajax_set_boxnow_locker_id', 'set_boxnow_locker_id');
    add_action('wp_ajax_nopriv_set_boxnow_locker_id', 'set_boxnow_locker_id');

    function set_boxnow_locker_id()
    {
      check_ajax_referer('boxnow_set_locker', 'nonce');

      if (!isset($_POST['locker_id'])) {
        wp_send_json_error('No locker ID provided.');
      }

      $locker_id      = sanitize_text_field($_POST['locker_id']);
      $locker_country = isset($_POST['locker_country']) ? strtolower(sanitize_key($_POST['locker_country'])) : '';

      if (function_exists('WC')) {
        WC()->session->set('_boxnow_locker_id', $locker_id);
        WC()->session->set('_boxnow_locker_country_widget', $locker_country);
        wp_send_json_success();
      } else {
        wp_send_json_error('WooCommerce session not available.');
      }
    }

    // Add a custom field to retrieve the Locker ID from the checkout page
    add_filter('woocommerce_checkout_fields', 'bndp_box_now_delivery_custom_override_checkout_fields');

    /**
     * Add custom field for Locker ID on checkout.
     *
     * @param array $fields Fields on the checkout.
     * @return array $fields Modified fields.
     */
    function bndp_box_now_delivery_custom_override_checkout_fields($fields)
    {
      $fields['billing']['_boxnow_locker_id'] = array(
        'label' => __('BOX NOW Locker ID', 'boxnowbulgaria'),
        'placeholder' => _x('BOX NOW Locker ID', 'placeholder', 'boxnowbulgaria'),
        'required' => false,
        'class' => array('boxnow-form-row-hidden', 'boxnow-locker-id-field'),
        'clear' => true
      );
      return $fields;
    }

    /**
     * Hide the locker ID field on the checkout page.
     */
    function bndp_hide_box_now_delivery_locker_id_field()
    {
      if (is_checkout()) {
?>
        <script>
          jQuery(document).ready(function($) {
            $('.boxnow-locker-id-field').hide();
          });
        </script>
    <?php
      }
    }
    add_action('wp_footer', 'bndp_hide_box_now_delivery_locker_id_field');

    /**
     * Update the order meta with field value for classic checkout.
     */
    function bndp_box_now_delivery_checkout_field_update_order_meta($order)
    {
      // Only save BOX NOW locker meta when the customer actually chose BOX NOW delivery.
      $chosen_methods = isset($_POST['shipping_method']) ? (array) $_POST['shipping_method'] : array();
      $is_boxnow = false;
      foreach ($chosen_methods as $method) {
        if (strpos(sanitize_text_field($method), 'box_now_delivery') !== false) {
          $is_boxnow = true;
          break;
        }
      }

      if (!$is_boxnow) {
        // Different delivery method chosen — do not attach locker meta to this order.
        $order->save();
        return;
      }

      if (!empty($_POST['_boxnow_locker_id'])) {
        $locker_id = sanitize_text_field($_POST['_boxnow_locker_id']);
        $order->update_meta_data('_boxnow_locker_id', $locker_id);

        // Save locker country — prefer what the widget sent (stored in session via AJAX before form submit).
        $widget_country = function_exists('WC') ? WC()->session->get('_boxnow_locker_country_widget') : '';
        if (!empty($widget_country)) {
          $order->update_meta_data('_boxnow_locker_country', sanitize_text_field($widget_country));
        }

        // Save locker coordinates and city for future use.
        $locker_details = boxnow_get_locker_details($locker_id);
        if ($locker_details) {
          if (empty($widget_country) && !empty($locker_details['country'])) {
            $order->update_meta_data('_boxnow_locker_country', sanitize_text_field($locker_details['country']));
          }
          if (!empty($locker_details['location']['lat']) && !empty($locker_details['location']['lng'])) {
            $order->update_meta_data('_boxnow_locker_latitude', floatval($locker_details['location']['lat']));
            $order->update_meta_data('_boxnow_locker_longitude', floatval($locker_details['location']['lng']));
          }
          if (!empty($locker_details['city'])) {
            $order->update_meta_data('_boxnow_locker_city', trim($locker_details['city']));
          }
        }
      }

      // Запази пълния JSON от checkout, за да има ime/adres, kakto se vijdat tam
      if (!empty($_POST['box_now_selected_locker'])) {
        $order->update_meta_data('_box_now_selected_locker', wp_kses_post($_POST['box_now_selected_locker']));
      }
      if (!$order->meta_exists('_selected_warehouse')) {
        $order->add_meta_data('_selected_warehouse', explode(',', str_replace(' ', '', get_option('boxnow_warehouse_id', '')))[0]);
      }
      $order->save();
    }
    add_action('woocommerce_checkout_create_order', 'bndp_box_now_delivery_checkout_field_update_order_meta');
  }

  /*
   * BLOCK-BASED CHECKOUT CODE BLOCK (Only executes if checkout_type = 'block')
   */
  if ($checkout_type === 'block') {
    add_action('wp_ajax_set_boxnow_locker_id', 'set_boxnow_locker_id');
    add_action('wp_ajax_nopriv_set_boxnow_locker_id', 'set_boxnow_locker_id');

    function set_boxnow_locker_id()
    {
      check_ajax_referer('boxnow_set_locker', 'nonce');

      if (!isset($_POST['locker_id'])) {
        wp_send_json_error('No locker ID provided.');
      }

      $locker_id      = sanitize_text_field($_POST['locker_id']);
      $locker_country = isset($_POST['locker_country']) ? strtolower(sanitize_key($_POST['locker_country'])) : '';

      if (function_exists('WC')) {
        WC()->session->set('_boxnow_locker_id', $locker_id);
        WC()->session->set('_boxnow_locker_country_widget', $locker_country);
        wp_send_json_success();
      } else {
        wp_send_json_error('WooCommerce session not available.');
      }
    }

    //Validate locker before order complete
    add_action('wp_ajax_validate_boxnow_locker_id', 'validate_boxnow_locker_id');
    add_action('wp_ajax_nopriv_validate_boxnow_locker_id', 'validate_boxnow_locker_id');
    
    // Get locker details via AJAX
    add_action('wp_ajax_get_boxnow_locker_details', 'get_boxnow_locker_details');
    add_action('wp_ajax_nopriv_get_boxnow_locker_details', 'get_boxnow_locker_details');

    function get_boxnow_locker_details() {
      if (!isset($_POST['locker_id'])) {
        wp_send_json_error('No locker ID provided.');
      }

      $locker_id = sanitize_text_field($_POST['locker_id']);
      $locker_details = boxnow_get_locker_details($locker_id);
      
      if ($locker_details) {
        wp_send_json_success($locker_details);
      } else {
        wp_send_json_error('Failed to get locker details.');
      }
    }

    function validate_boxnow_locker_id()
    {
      $locker_id = WC()->session->get('boxnow_locker_id');
      if (!empty($locker_id)) {
        wp_send_json_success(['locker_id' => $locker_id]);
      } else {
        wp_send_json_error(['locker_id' => null]);
      }
    }

    add_action('woocommerce_cart_emptied', 'clear_boxnow_session_data');
    add_action('woocommerce_before_cart', 'clear_boxnow_session_data');
    add_action('woocommerce_checkout_init', 'clear_boxnow_session_data');

    function clear_boxnow_session_data()
    {
      if (!is_admin() && function_exists('WC') && WC()->session) {
        WC()->session->set('boxnow_locker_id', null);
      }
    }

    /**
     * Update the order meta for block-based checkout after the order is created.
     */
    function bndp_box_now_delivery_checkout_field_update_order_meta_block($order)
    {
      if (isset($_POST['_boxnow_locker_id']) && !empty($_POST['_boxnow_locker_id'])) {
        $locker_id = sanitize_text_field($_POST['_boxnow_locker_id']);
        $order->update_meta_data('_boxnow_locker_id', $locker_id);
        
        // Save locker coordinates and city for future use
        $locker_details = boxnow_get_locker_details($locker_id);
        if ($locker_details) {
          if (!empty($locker_details['location']['lat']) && !empty($locker_details['location']['lng'])) {
            $order->update_meta_data('_boxnow_locker_latitude', floatval($locker_details['location']['lat']));
            $order->update_meta_data('_boxnow_locker_longitude', floatval($locker_details['location']['lng']));
          }
          if (!empty($locker_details['city'])) {
            $order->update_meta_data('_boxnow_locker_city', trim($locker_details['city']));
          }
        }
      }

    // Запази JSON от checkout за block checkout
    if (!empty($_POST['box_now_selected_locker'])) {
      $order->update_meta_data('_box_now_selected_locker', wp_kses_post($_POST['box_now_selected_locker']));
    }

      if (!$order->meta_exists('_selected_warehouse')) {
        $order->add_meta_data('_selected_warehouse', explode(',', str_replace(' ', '', get_option('boxnow_warehouse_id', '')))[0]);
      }

      $order->save();
    }
    add_action('woocommerce_store_api_checkout_update_order_meta', 'bndp_box_now_delivery_checkout_field_update_order_meta_block');

    // Save the locker_id from session into the order meta once the order is updated from request
    function save_boxnow_locker_id_from_session($order)
    {
      // Only save BOX NOW locker meta when the customer actually chose BOX NOW delivery.
      $is_boxnow = false;
      foreach ($order->get_shipping_methods() as $shipping_item) {
        if (strpos($shipping_item->get_method_id(), 'box_now_delivery') !== false) {
          $is_boxnow = true;
          break;
        }
      }

      if (!$is_boxnow) {
        // Different delivery method chosen — clear stale session data and skip meta.
        WC()->session->set('_boxnow_locker_id', null);
        WC()->session->set('_boxnow_locker_country_widget', null);
        $order->save();
        return;
      }

      $locker_id = WC()->session->get('_boxnow_locker_id');
      if (!empty($locker_id)) {
        $order->update_meta_data('_boxnow_locker_id', sanitize_text_field($locker_id));

        // Prefer country provided directly by the widget (stored in session by AJAX handler).
        $widget_country = WC()->session->get('_boxnow_locker_country_widget');
        if (!empty($widget_country)) {
          $order->update_meta_data('_boxnow_locker_country', sanitize_text_field($widget_country));
        }

        // Fetch locker details for coordinates and city (country falls back to API if widget didn't send it).
        $locker_details = boxnow_get_locker_details($locker_id);
        if ($locker_details) {
          if (empty($widget_country) && !empty($locker_details['country'])) {
            $order->update_meta_data('_boxnow_locker_country', sanitize_text_field($locker_details['country']));
          }
          if (!empty($locker_details['location']['lat']) && !empty($locker_details['location']['lng'])) {
            $order->update_meta_data('_boxnow_locker_latitude', floatval($locker_details['location']['lat']));
            $order->update_meta_data('_boxnow_locker_longitude', floatval($locker_details['location']['lng']));
          }
          if (!empty($locker_details['city'])) {
            $order->update_meta_data('_boxnow_locker_city', trim($locker_details['city']));
          }
        }
      }
      if (!$order->meta_exists('_selected_warehouse')) {
        $warehouse_ids = explode(',', str_replace(' ', '', get_option('boxnow_warehouse_id', '')));
        $first_warehouse = isset($warehouse_ids[0]) ? $warehouse_ids[0] : '';
        $order->update_meta_data('_selected_warehouse', $first_warehouse);
      }
      $order->save();
    }
    add_action('woocommerce_store_api_checkout_update_order_from_request', 'save_boxnow_locker_id_from_session', 10, 1);
  }

   // Force override with JavaScript to include the locker ID in the POST data during checkout
   function force_boxnow_locker_id_submission()
   {
     if (is_checkout()) {
     ?>
       <script>
         jQuery(document).ready(function($) {
           $('form.checkout').on('checkout_place_order', function() {
             var selectedMethod = $('input[type="radio"][name="shipping_method[0]"]:checked').val();
             var isBoxNow = selectedMethod === 'box_now_delivery';
             var lockerData = null;
             try {
               lockerData = JSON.parse(localStorage.getItem('box_now_selected_locker'));
             } catch (e) {
               lockerData = null;
             }
             if (isBoxNow && lockerData && lockerData.boxnowLockerId) {
               $('<input>').attr({
                 type: 'hidden',
                 name: '_boxnow_locker_id',
                 value: lockerData.boxnowLockerId
               }).appendTo('form.checkout');
             } else if (!isBoxNow) {
               // Customer is checking out with a different method — discard stale locker data
               localStorage.removeItem('box_now_selected_locker');
             }
           });
         });
       </script>
       <?php
     }
   }
   add_action('wp_footer', 'force_boxnow_locker_id_submission');

  /* Display field value on the order edit page for both checkout types */
  // Works for both legacy CPT and HPOS
  add_action('woocommerce_admin_order_data_after_billing_address', 'bndp_box_now_delivery_checkout_field_display_admin_order_meta', 10, 1);

  /**
   * Display custom checkout field in the order edit page.
   *
   * @param WC_Order $order WooCommerce Order.
   */
  function bndp_box_now_delivery_checkout_field_display_admin_order_meta($order)
{
    // Ensure we have a valid order object (defensive check - v2.1.4 didn't have this but it's safe)
    if (!$order || !is_a($order, 'WC_Order')) {
        return;
    }
    
    // 1) Check if the order used Box Now shipping (restored v2.1.4 behavior)
    $shipping_methods = $order->get_shipping_methods();
    $box_now_used = false;
    foreach ($shipping_methods as $shipping_method) {
        if ($shipping_method->get_method_id() === 'box_now_delivery') {
            $box_now_used = true;
            break;
        }
    }
    if (!$box_now_used) {
        return;
    }

    // Existing metadata
    $locker_id    = $order->get_meta('_boxnow_locker_id');
    $warehouse_id = $order->get_meta('_selected_warehouse');

    // (A) If _boxnow_real_address is missing but we have a locker => fetch it NOW
    $real_boxnow_address = $order->get_meta('_boxnow_real_address');
    if (empty($real_boxnow_address) && !empty($locker_id)) {
        $fetched = boxnow_get_locker_address_line1($locker_id);
        if (!empty($fetched)) {
            $order->update_meta_data('_boxnow_real_address', $fetched);
            $order->save();
            $real_boxnow_address = $fetched;
        }
    }

    // (B) Now do your existing warehouse check
    $warehouse_ids_str = get_option('boxnow_warehouse_id', '');
    $warehouse_ids     = explode(',', str_replace(' ', '', $warehouse_ids_str));
    if (!empty($warehouse_ids)) {
      $current_wh = $order->get_meta('_selected_warehouse');
      if (empty($current_wh) || !in_array($current_wh, $warehouse_ids)) {
        $order->update_meta_data('_selected_warehouse', $warehouse_ids[0]);
        $order->save();
      }
    }

    // (C) Retrieve token and warehouse data (unchanged logic)
    $api_url   = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/auth-sessions';
    $auth_args = array(
      'method'  => 'POST',
      'headers' => array('Content-Type' => 'application/json'),
      'body'    => json_encode(array(
        'grant_type'    => 'client_credentials',
        'client_id'     => get_option('boxnow_client_id', ''),
        'client_secret' => get_option('boxnow_client_secret', '')
      ))
    );
    $response = wp_remote_post($api_url, $auth_args);
    $json     = json_decode(wp_remote_retrieve_body($response), true);

    // Safely get access_token
    $access_token = '';
    if (is_array($json) && !empty($json['access_token'])) {
      $access_token = $json['access_token'];
    }

    $origins_url   = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/origins';
    $origins_args  = array(
      'method'  => 'GET',
      'headers' => array(
        'Authorization' => 'Bearer ' . $access_token,
        'Content-Type'  => 'application/json'
      )
    );
    $warehouses_json = wp_remote_get($origins_url, $origins_args);
    $warehouses_list = json_decode(wp_remote_retrieve_body($warehouses_json), true);

    // Build an array of warehouse_id => warehouse_name
    $warehouse_names = array();
    if (!empty($warehouses_list['data']) && is_array($warehouses_list['data'])) {
      foreach ($warehouses_list['data'] as $wh) {
        $warehouse_names[$wh['id']] = $wh['name'];
      }
    }

    // (D) Display HTML for Box Now in admin
    ?>
    <div class="boxnow_data_column">
      <h4>
        <?php echo esc_html__('BOX NOW Delivery', 'boxnowbulgaria'); ?>
        <a href="#" class="edit_address"><?php echo esc_html__('Edit', 'boxnowbulgaria'); ?></a>
      </h4>

      <!-- "View" mode (before clicking "Edit") -->
      <div class="address">
        <?php
        // Номер на автомат
        echo '<p><strong>' . esc_html__('Locker ID: ', 'boxnowbulgaria') . '</strong><span id="display_boxnow_locker_id">' . esc_html($locker_id) . '</span></p>';

        // Адрес на автомат
        echo '<p id="display_boxnow_real_address_wrapper" style="' . (empty($real_boxnow_address) ? 'display:none;' : '') . '"><strong>' . esc_html__('Locker Address: ', 'boxnowbulgaria') . '</strong><span id="display_boxnow_real_address">' . esc_html($real_boxnow_address) . '</span></p>';

        // Номер на склад
        echo '<p><strong>' . esc_html__('Warehouse ID: ', 'boxnowbulgaria') . '</strong><span id="display_selected_warehouse">' . esc_html($warehouse_id);
        if (!empty($warehouse_names[$warehouse_id])) {
          echo ' - ' . esc_html($warehouse_names[$warehouse_id]);
        }
        echo '</span></p>';
        ?>
      </div>

      <!-- "Edit" mode (after clicking "Edit") -->
      <div class="edit_address">
        <p class="form-field form-field-wide _boxnow_locker_id" style="margin: 0 !important;">
          <label for="_boxnow_locker_id" style="display: block; margin-bottom: 5px; margin-top: 5px">
            <?php esc_html_e('Locker ID:', 'boxnowbulgaria'); ?>
          </label>
        <span style="display: flex; align-items: center; gap: 10px;">
          <?php 
            $saved_lat = $order->get_meta('_boxnow_locker_latitude');
            $saved_lng = $order->get_meta('_boxnow_locker_longitude');
            $saved_addr = $order->get_meta('_boxnow_real_address');
          ?>
          <input type="text"
            class="short"
            name="_boxnow_locker_id"
            id="_boxnow_locker_id"
            value="<?php echo esc_attr($locker_id); ?>"
            data-saved-id="<?php echo esc_attr($locker_id); ?>"
            data-saved-lat="<?php echo esc_attr($saved_lat); ?>"
            data-saved-lng="<?php echo esc_attr($saved_lng); ?>"
            data-saved-addr="<?php echo esc_attr($saved_addr); ?>" />
          <button type="button" class="button" id="admin-boxnow-open-popup">
            <?php esc_html_e('Change Address', 'boxnowbulgaria'); ?>
          </button>
        </span>
        </p>

        <?php $real_boxnow_address = $order->get_meta('_boxnow_real_address'); ?>
        <p class="form-field form-field-wide _boxnow_real_address" style="margin: 0 !important;">
          <label for="_boxnow_real_address" style="display: block; margin-bottom: 5px; margin-top: 5px">
            <?php esc_html_e('Locker Address:', 'boxnowbulgaria'); ?>
          </label>
          <input type="text"
            class="short"
            name="_boxnow_real_address"
            id="_boxnow_real_address"
            value="<?php echo esc_attr($real_boxnow_address); ?>"
            readonly="readonly" />
        </p>

        <?php
        $warehouse_ids   = explode(',', str_replace(' ', '', get_option('boxnow_warehouse_id', '')));
        $warehouses_show = array();
        foreach ($warehouse_ids as $id) {
          if (!empty($warehouse_names[$id])) {
            $warehouses_show[$id] = $id . ' - ' . esc_html($warehouse_names[$id]);
          } else {
            $warehouses_show[$id] = $id; // fallback if not found
          }
        }
        $warehouse_options = array_combine($warehouse_ids, $warehouses_show);
        woocommerce_wp_select(array(
          'id'            => '_selected_warehouse',
          'label'         => esc_html__('Warehouse ID: ', 'boxnowbulgaria'),
          'wrapper_class' => '_selected_warehouse',
          'options'       => $warehouse_options
        ));
        ?>

        <style>
          #boxnow-admin-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6) !important;
            z-index: 999999;
            display: none;
          }

          #boxnow-admin-popup-iframe {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            border: 0;
            border-radius: 0;
            background: transparent;
            z-index: 1000000;
            display: none;
          }
        </style>

        <script type="text/javascript">
          (function($) {

            $('#admin-boxnow-open-popup').on('click', function(e) {
              e.preventDefault();
              openBoxNowAdminPopup();
            });

            function openBoxNowAdminPopup() {
              if ($('#boxnow-admin-overlay').length === 0) {
                $('body').append('<div id="boxnow-admin-overlay"></div>');
              }
              if ($('#boxnow-admin-popup-iframe').length === 0) {
                $('body').append('<iframe id="boxnow-admin-popup-iframe" allow="geolocation"></iframe>');
              }
              var widgetBase = '<?php echo esc_js(function_exists('boxnow_get_widget_base_url') ? boxnow_get_widget_base_url() : 'https://widget-v5.boxnow.bg'); ?>'.replace(/\/$/, '');
              var countryCodes = '<?php echo esc_js(function_exists('boxnow_get_allowed_countries') ? implode(',', boxnow_get_allowed_countries()) : 'bg'); ?>'.replace(/\s/g, '');
              var partnerId = '<?php echo esc_js(get_option('boxnow_partner_id', '')); ?>';
              var ccList = countryCodes.split(',').filter(Boolean);
              var langMap = { bg: 'bg', gr: 'gr', hr: 'hr', si: 'si', cy: 'gr' };
              var language = ccList.length > 1 ? 'en' : (langMap[(ccList[0] || 'bg').toLowerCase()] || 'en');
              var src = widgetBase + '/popup.html?gps=yes&autoselect=no&countryCode=' + encodeURIComponent(countryCodes) +
                '&language=' + encodeURIComponent(language) +
                (partnerId ? '&partnerId=' + encodeURIComponent(partnerId) : '');
              $('#boxnow-admin-popup-iframe').attr('src', src).show();
              $('#boxnow-admin-overlay').show();
            }
            // Expose for other admin UI triggers (e.g. alternative lockers modal)
            window.openBoxNowAdminPopup = openBoxNowAdminPopup;

            function closeBoxNowAdminPopup() {
              $('#boxnow-admin-popup-iframe').attr('src', '').hide();
              $('#boxnow-admin-overlay').hide();
            }
            $(document).on('click', '#boxnow-admin-overlay', function() {
              closeBoxNowAdminPopup();
            });
            window.addEventListener('message', function(event) {
              var dataObj = event.data;
              if (typeof dataObj === 'string') {
                try {
                  dataObj = JSON.parse(dataObj);
                } catch (e) {}
              }
              if (dataObj === 'closeIframe' || dataObj.boxnowClose !== undefined) {
                closeBoxNowAdminPopup();
                return;
              }
              if (dataObj && dataObj.boxnowLockerId) {
                $('#_boxnow_locker_id').val(dataObj.boxnowLockerId);

                // Update localStorage with NEW locker data for nearby calculation consistency
                localStorage.setItem("box_now_selected_locker", JSON.stringify(dataObj));

                // Notify other scripts that the locker has changed
                $('#_boxnow_locker_id').trigger('change');

                // Persist new locker on the order if we are on an order screen with voucher JS loaded
                var orderIdField = document.getElementById('box_now_order_id');
                var orderId = orderIdField ? orderIdField.value : null;
                if (orderId && typeof myAjax !== 'undefined' && myAjax.ajaxurl && myAjax.nonce) {
                  $.post(
                    myAjax.ajaxurl,
                    {
                      action: 'boxnow_update_order_locker',
                      order_id: orderId,
                      locker_id: dataObj.boxnowLockerId,
                      security: myAjax.nonce
                    },
                    function(response) {
                      if (!response || !response.success) {
                        console.error('BOX NOW: Failed to save locker from map.', response && response.data);
                      }
                    },
                    'json'
                  );
                }

                closeBoxNowAdminPopup();
                // Re-fetch address for new ID
                fetchAndShowBoxNowAddress(dataObj.boxnowLockerId);
              }
            }, false);

            // (2) LIVE-UPDATE when admin changes locker ID manually
            var fetchTimeout = null;
            $('#_boxnow_locker_id').on('change keyup blur', function() {
              var typedLockerId = $(this).val().trim();
              
              // Immediate partial update: Clear stale metadata if ID changes
              if (typedLockerId.length >= 4) {
                  var currentData = localStorage.getItem("box_now_selected_locker");
                  try {
                      var data = currentData ? JSON.parse(currentData) : {};
                      if (data.boxnowLockerId !== typedLockerId) {
                          // ID changed! Wipe everything else to avoid stale data
                          var newData = {
                              boxnowLockerId: typedLockerId,
                              boxnowLockerLat: "",
                              boxnowLockerLng: "",
                              boxnowLockerName: "Loading...",
                              boxnowLockerAddressLine1: "",
                              boxnowLockerAddressLine2: "",
                              boxnowLockerPostalCode: ""
                          };
                          localStorage.setItem("box_now_selected_locker", JSON.stringify(newData));
                      }
                  } catch(e) {}
              }

              // Full data fetch with debounce
              if (fetchTimeout) clearTimeout(fetchTimeout);
              if (typedLockerId.length >= 4) {
                fetchTimeout = setTimeout(function() {
                  fetchAndShowBoxNowAddress(typedLockerId);
                }, 400);
              }
            });

            // (2.1) Force update when the Order is being saved/updated
            $('#post').on('submit', function() {
                var currentId = $('#_boxnow_locker_id').val();
                if (currentId && currentId.length >= 4) {
                    var currentData = localStorage.getItem("box_now_selected_locker");
                    try {
                        var data = currentData ? JSON.parse(currentData) : {};
                        data.boxnowLockerId = currentId;
                        localStorage.setItem("box_now_selected_locker", JSON.stringify(data));
                    } catch(e) {}
                }
            });

            // Update display warehouse when changed in edit mode
            $('#_selected_warehouse').on('change', function() {
              var text = $(this).find('option:selected').text();
              $('#display_selected_warehouse').text(text);
            });

            // (3) A function that fetches location data from the BoxNow URL,
            //     finds the matching ID, and updates _boxnow_real_address.
            function fetchAndShowBoxNowAddress(lockerId) {
              if (!lockerId) {
                $('#_boxnow_real_address').val('');
                $('#display_boxnow_real_address').text('');
                $('#display_boxnow_real_address_wrapper').hide();
                localStorage.removeItem("box_now_selected_locker");
                return;
              }
              
              // Update display locker ID immediately
              $('#display_boxnow_locker_id').text(lockerId);

              // Fetch locker details via server-side AJAX (supports all countries, not BG only).
              $.post(ajaxurl, {
                action: 'boxnow_get_locker_info',
                locker_id: lockerId,
                nonce: '<?php echo wp_create_nonce('boxnow_get_locker_info'); ?>'
              })
                .done(function(response) {
                  if (response && response.success && response.data) {
                    updateLockerUIAndStorage(response.data);
                  } else {
                    handleLockerNotFound(lockerId);
                  }
                })
                .fail(function(xhr, status, error) {
                  console.error('BOX NOW: Locker info request failed:', status, error);
                  $('#display_boxnow_real_address').text('API Error');
                });

              function updateLockerUIAndStorage(locker) {
                var addr = locker.addressLine1 || locker.address || '';
                $('#_boxnow_real_address').val(addr);
                
                var lockerData = {
                  boxnowLockerId: String(locker.id),
                  boxnowLockerLat: String(locker.lat || locker.latitude || ""),
                  boxnowLockerLng: String(locker.lng || locker.longitude || ""),
                  boxnowLockerName: locker.name || "",
                  boxnowLockerAddressLine1: addr,
                  boxnowLockerAddressLine2: locker.city || locker.municipality || "",
                  boxnowLockerPostalCode: locker.postalCode || locker.zip || ""
                };
                localStorage.setItem("box_now_selected_locker", JSON.stringify(lockerData));
                console.log('BOX NOW: LocalStorage updated for ID ' + lockerId, lockerData);
                
                $('#display_boxnow_real_address').text(addr);
                $('#display_boxnow_real_address_wrapper').show();

                if (typeof window.updateNearbyLockers === 'function') {
                  window.updateNearbyLockers();
                }
              }

              function handleLockerNotFound(id) {
                console.warn('BOX NOW: Locker ID ' + id + ' not found in location API.');
                
                // Check if we have SAVED data in the DOM for this specific ID
                var $input = $('#_boxnow_locker_id');
                var savedId = String($input.attr('data-saved-id') || "").trim().toUpperCase();
                var searchId = String(id).trim().toUpperCase();
                
                if (savedId === searchId || savedId === "BG" + searchId || "BG" + savedId === searchId) {
                    var savedLat = $input.attr('data-saved-lat');
                    var savedLng = $input.attr('data-saved-lng');
                    if (savedLat && savedLng) {
                        console.log('BOX NOW: Locker ' + id + ' not in API, but found saved coordinates in order meta. Using them.');
                        var savedLocker = {
                            id: id,
                            lat: savedLat,
                            lng: savedLng,
                            name: "Locker " + id + " (Off-map/Full)",
                            addressLine1: $input.attr('data-saved-addr') || "Address from Order Meta",
                            city: "",
                            postalCode: ""
                        };
                        updateLockerUIAndStorage(savedLocker);
                        return;
                    }
                }

                $('#display_boxnow_real_address').text('Locker not found in location API');
                
                // Still update ID in localStorage so the order can be saved, but clear stale metadata
                var lockerData = {
                    boxnowLockerId: String(id),
                    boxnowLockerLat: "",
                    boxnowLockerLng: "",
                    boxnowLockerName: "Unknown Locker (Off-map)",
                    boxnowLockerAddressLine1: "Not found in API",
                    boxnowLockerAddressLine2: "",
                    boxnowLockerPostalCode: ""
                };
                localStorage.setItem("box_now_selected_locker", JSON.stringify(lockerData));
              }
            }

            // Sync localStorage on page load to match the current order's locker
            $(document).ready(function() {
              var currentLockerId = $('#_boxnow_locker_id').val();
              if (currentLockerId) {
                fetchAndShowBoxNowAddress(currentLockerId);
              }
            });

            // Expose helper for other scripts (e.g., alternative lockers modal) to refresh UI without reload.
            window.fetchAndShowBoxNowAddress = fetchAndShowBoxNowAddress;
          })(jQuery);
        </script>
      </div>
    </div>
    <?php
  }


  /**
   * Fetch the locker addressLine1 from the BoxNow public location API.
   *
   * @param string $locker_id The ID of the locker (e.g. "BG10001").
   * @return string The "addressLine1", or an empty string if not found or error.
   */
  function boxnow_get_locker_address_line1($locker_id)
  {
    if (!function_exists('boxnow_fetch_locker_by_id')) {
      return '';
    }
    $locker = boxnow_fetch_locker_by_id($locker_id);
    return (!empty($locker['addressLine1'])) ? $locker['addressLine1'] : '';
  }

  /**
   * AJAX handler for fetching locker details in the admin order edit UI.
   * Replaces the old client-side $.get(apms_bg-BG.json) call with a server-side
   * multi-country lookup via boxnow_fetch_locker_by_id().
   */
  add_action('wp_ajax_boxnow_get_locker_info', 'boxnow_get_locker_info_ajax');
  function boxnow_get_locker_info_ajax()
  {
    check_ajax_referer('boxnow_get_locker_info', 'nonce');
    if (!current_user_can('edit_shop_orders')) {
      wp_send_json_error('Unauthorized');
    }
    $locker_id = isset($_POST['locker_id']) ? sanitize_text_field($_POST['locker_id']) : '';
    if (empty($locker_id)) {
      wp_send_json_error('No locker ID provided');
    }
    if (!function_exists('boxnow_fetch_locker_by_id')) {
      wp_send_json_error('Helper not available');
    }
    $locker = boxnow_fetch_locker_by_id($locker_id);
    if (!$locker) {
      wp_send_json_error('Locker not found');
    }
    wp_send_json_success($locker);
  }

  /**
   * Save custom checkout fields in the admin + store real locker address in _boxnow_real_address.
   */
  function bndp_box_now_delivery_save_checkout_field_admin_order_meta($post_id)
  {
    $order = wc_get_order($post_id);

    // Ensure we have an order and the required POST data
    if (!$order || !isset($_POST['_boxnow_locker_id']) || !isset($_POST['_selected_warehouse'])) {
      return;
    }

    // Save locker ID + warehouse
    $locker_id = sanitize_text_field($_POST['_boxnow_locker_id']);
    $old_locker_id = $order->get_meta('_boxnow_locker_id');
    $order->update_meta_data('_boxnow_locker_id', $locker_id);

    $selected_warehouse = sanitize_text_field($_POST['_selected_warehouse']);
    $order->update_meta_data('_selected_warehouse', $selected_warehouse);

    // Fetch the real address and coordinates
    $real_address_line1 = isset($_POST['_boxnow_real_address']) ? sanitize_text_field($_POST['_boxnow_real_address']) : '';
    
    // If ID changed or address is empty, we MUST fetch from API
    if ($locker_id !== $old_locker_id || empty($real_address_line1)) {
      $api_address = boxnow_get_locker_address_line1($locker_id);
      if (!empty($api_address)) {
        $real_address_line1 = $api_address;
      }
    }
    
    if (!empty($locker_id)) {
      // If ID is same as before, check if we already have coordinates
      $existing_lat = $order->get_meta('_boxnow_locker_latitude');
      $existing_lng = $order->get_meta('_boxnow_locker_longitude');

      // Also save coordinates for finding nearby lockers.
      // Use multi-country helper instead of the hardcoded Bulgaria-only endpoint.
      $found_in_api = false;
      if (function_exists('boxnow_fetch_locker_by_id')) {
        $locker_raw = boxnow_fetch_locker_by_id($locker_id);
        if ($locker_raw) {
          $locker_lat_str = isset($locker_raw['lat']) ? $locker_raw['lat'] : (isset($locker_raw['latitude']) ? $locker_raw['latitude'] : '');
          $locker_lng_str = isset($locker_raw['lng']) ? $locker_raw['lng'] : (isset($locker_raw['longitude']) ? $locker_raw['longitude'] : '');
          if (!empty($locker_lat_str) && !empty($locker_lng_str)) {
            $locker_lat = is_numeric($locker_lat_str) ? floatval($locker_lat_str) : 0;
            $locker_lng = is_numeric($locker_lng_str) ? floatval($locker_lng_str) : 0;
            if ($locker_lat != 0 && $locker_lng != 0) {
              $order->update_meta_data('_boxnow_locker_latitude', $locker_lat);
              $order->update_meta_data('_boxnow_locker_longitude', $locker_lng);
              $found_in_api = true;
              boxnow_log('BOX NOW: Saved coordinates to order meta: ' . $locker_lat . ', ' . $locker_lng);
            }
          }
        }
      }
      
      // Fallback 1: Try boxnow_get_locker_details if not found in API
      if (!$found_in_api) {
        $locker_details = boxnow_get_locker_details($locker_id);
        if ($locker_details) {
          if (!empty($locker_details['location']['lat']) && !empty($locker_details['location']['lng'])) {
            $order->update_meta_data('_boxnow_locker_latitude', floatval($locker_details['location']['lat']));
            $order->update_meta_data('_boxnow_locker_longitude', floatval($locker_details['location']['lng']));
            $found_in_api = true;
            boxnow_log('BOX NOW: Saved coordinates to order meta from boxnow_get_locker_details: ' . $locker_details['location']['lat'] . ', ' . $locker_details['location']['lng']);
          }
        }
      }

      // Fallback 2: If ID is same as before, DO NOT clear the coordinates if API failed
      if (!$found_in_api && $locker_id === $old_locker_id && !empty($existing_lat)) {
          boxnow_log('BOX NOW: Locker ' . $locker_id . ' not in API, but keeping existing order coordinates.');
      }
    }

    // Store it in a new meta key
    $order->update_meta_data('_boxnow_real_address', $real_address_line1);
    $order->save();
  }
  add_action('woocommerce_process_shop_order_meta', 'bndp_box_now_delivery_save_checkout_field_admin_order_meta');

  /**
   * Save extra details when processing the shop order.
   */
  add_action('woocommerce_order_status_changed', 'boxnow_save_extra_details', 10, 4);

  function boxnow_save_extra_details($order_id, $old_status, $new_status, $order)
  {
    // Log old status, new status, locker ID, and warehouse ID before status change
    boxnow_log('Order ID: ' . $order_id . ', Previous status: ' . $old_status . ', New status: ' . $new_status);
    boxnow_log('Before status change, Locker ID: ' . $order->get_meta('_boxnow_locker_id'));
    boxnow_log('Before status change, Warehouse ID: ' . $order->get_meta('_selected_warehouse'));

    // Check if locker id and warehouse ID are present
    $locker_id = $order->get_meta('_boxnow_locker_id');
    if (isset($locker_id) && $locker_id !== '') {
      boxnow_log("Locker ID: " . $locker_id);
    } else {
      boxnow_log("Error: Locker ID field is empty.");
    }

    $warehouse_id = $order->get_meta('_selected_warehouse');
    if (isset($warehouse_id) && $warehouse_id !== '') {
      boxnow_log("Warehouse ID: " . $warehouse_id);
    } else {
      boxnow_log("Warehouse ID is empty when trying to save.");
    }

    // Refresh the order data after changes
    $order = wc_get_order($order_id);

    // Log locker ID and warehouse ID after status change
    boxnow_log('After status change, Locker ID: ' . $order->get_meta('_boxnow_locker_id'));
    boxnow_log('After status change, Warehouse ID: ' . $order->get_meta('_selected_warehouse'));
  }

  /**
   * Change Cash on delivery title to custom
   */
  add_filter('woocommerce_gateway_title', 'bndp_change_cod_title_for_box_now_delivery', 20, 2);

  function bndp_change_cod_title_for_box_now_delivery($title, $payment_id)
  {
    if (!is_admin() && $payment_id === 'cod') {
      if (function_exists('WC') && WC()->session) {
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        $box_now_delivery_method = 'box_now_delivery';

        if (is_array($chosen_shipping_methods) && in_array($box_now_delivery_method, $chosen_shipping_methods)) {
          $title = __('Cash on Delivery (COD)', 'boxnowbulgaria');
        }
      }
    }

    return $title;
  }

  /*
  * Send information to BOX NOW api and for sending an email to the customer with the voucher
  */
  add_action('woocommerce_order_status_completed', 'boxnow_order_completed');

  /**
   * Fires when an order is marked "completed" in WooCommerce,
   * then attempts to create a BoxNow shipping voucher via API.
   *
   * @param int      $order_id   The WooCommerce order ID.
   * @param string   $old_status Previous order status (e.g. 'processing').
   * @param string   $new_status New order status (e.g. 'completed').
   * @param WC_Order $order      The Order object.
   */
  function boxnow_order_completed($order_id)
  {
    boxnow_log("DEBUG: boxnow_order_completed() called for order_id = $order_id");

    if (get_transient('_manual_status_change')) {
      delete_transient('_manual_status_change');
      boxnow_log("DEBUG: _manual_status_change was set. Bailing out.");
      return;
    }

    if (get_option('boxnow_voucher_option') !== 'email') {
      boxnow_log("DEBUG: boxnow_voucher_option is not 'email'. Bailing out.");
      return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
      boxnow_log("DEBUG: No order found for #$order_id. Stopping.");
      return;
    }

    if (!$order->has_shipping_method('box_now_delivery')) {
      boxnow_log("DEBUG: Order #$order_id doesn't have box_now_delivery shipping method. Stopping.");
      return;
    }

    // If voucher is already created, skip (check both meta keys)
    if ($order->get_meta('_voucher_created')) {
      boxnow_log("DEBUG: _voucher_created is already set for #$order_id. Stopping.");
      return;
    }
    
    // Also check if vouchers were created from admin panel
    $vouchers_created = $order->get_meta('_boxnow_vouchers_created', true);
    if ($vouchers_created) {
      boxnow_log("DEBUG: _boxnow_vouchers_created is already set for #$order_id. Stopping.");
      return;
    }

    // Prepare data. boxnow_prepare_data() throws when the parcel is too big or
    // too heavy — catching it here keeps marking an order "completed" from
    // fatalling; the shop owner gets an order note instead.
    try {
      $prep_data = boxnow_prepare_data($order);
    } catch (Exception $e) {
      boxnow_log('BOX NOW: cannot auto-create voucher for order #' . $order_id . ' — ' . $e->getMessage());
      $order->add_order_note(
        __('BOX NOW voucher was not created automatically:', 'boxnowbulgaria') . ' ' . $e->getMessage()
      );
      return;
    }

    $locker_id = isset($prep_data['locker_id']) ? $prep_data['locker_id'] : '';

    boxnow_log("DEBUG: locker_id from prep_data = $locker_id");

    // If no locker ID, record it and bail. Do NOT wp_die() here: this runs on the
    // woocommerce_order_status_completed hook, so dying aborts the whole status
    // change (and any bulk action it was part of) with a blank page.
    if (!$locker_id) {
      boxnow_log("DEBUG: No locker ID for order #$order_id — skipping voucher creation.");
      $order->add_order_note(__('No BOX NOW locker ID was found in this order, so no voucher was created.', 'boxnowbulgaria'));
      return;
    }

    // Proceed directly — locker validity is enforced by the delivery-requests API.
    // API errors are caught and logged in boxnow_order_completed_delivery_request().
    boxnow_log("DEBUG: Proceeding with voucher creation for locker $locker_id...");

    $response = boxnow_order_completed_delivery_request($prep_data, $order_id, 1);
    $response_data = json_decode($response, true);

    if (isset($response_data['parcels'][0]['id'])) {
      $order->update_meta_data('_boxnow_parcel_id', $response_data['parcels'][0]['id']);
      $order->update_meta_data('_voucher_created', 'yes');
      $order->save();
      boxnow_log("DEBUG: Voucher created successfully for order #$order_id");
    } else {
      boxnow_log("DEBUG: Request to create voucher failed. Response: " . print_r($response_data, true));
    }
  }

  /**
   * Reference sent to BOX NOW as "orderNumber".
   *
   * This used to be strval(mt_rand()), which made it impossible to match a BOX
   * NOW parcel back to a WooCommerce order. Use the real order number, with a
   * short suffix on repeat requests so BOX NOW never sees a duplicate.
   *
   * @param int $order_id
   * @return string
   */
  function boxnow_build_order_reference($order_id)
  {
    $order  = wc_get_order($order_id);
    $number = $order ? (string) $order->get_order_number() : (string) $order_id;

    if ($number === '') {
      $number = (string) $order_id;
    }

    // Count how many delivery requests this order has already produced.
    $attempt = $order ? (int) $order->get_meta('_boxnow_request_count') : 0;
    if ($order) {
      $order->update_meta_data('_boxnow_request_count', $attempt + 1);
      $order->save();
    }

    $reference = $attempt > 0 ? $number . '-' . ($attempt + 1) : $number;

    /**
     * Filter the reference sent to BOX NOW as orderNumber.
     *
     * @param string $reference
     * @param int    $order_id
     */
    return (string) apply_filters('boxnow_order_reference', $reference, $order_id);
  }

  // This is the delivery request only for the boxnow_order_completed function
  function boxnow_order_completed_delivery_request($prep_data, $order_id, $num_vouchers)
  {
    $access_token = boxnow_get_access_token();

    if (empty($access_token)) {
      boxnow_log('BOX NOW: Aborting auto delivery request for order #' . $order_id . ' — authentication failed (no token).');
      return json_encode(array('error' => 'authentication_failed', 'message' => 'Authentication failed. Please check BOX NOW API credentials in the plugin settings.'));
    }

    $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/delivery-requests';
    $randStr = boxnow_build_order_reference($order_id);
    $payment_method = $prep_data['payment_method'];
    $send_voucher_via_email = get_option('boxnow_voucher_option', 'email') === 'email';
    $send_voucher_via_button = get_option('boxnow_voucher_option', 'email') === 'button';

    $items = [];
    for ($i = 0; $i < $num_vouchers; $i++) {
      $item_data = [
        "value" => $prep_data['product_price'],
        "weight" => $prep_data['weight']
      ];

      if (isset($prep_data['compartment_sizes'])) {
        $item_data["compartmentSize"] = $prep_data['compartment_sizes'][0];
      }

      $items[] = $item_data;
    }

    $data = [
      "notifyOnAccepted" => $send_voucher_via_button ? get_option('boxnow_voucher_button', '') : '',
      "orderNumber" => $randStr,
      "invoiceValue" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
      "paymentMode" => $payment_method === 'cod' ? "cod" : "prepaid",
      "amountToBeCollected" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
      "allowReturn" => true,
      "origin" => [
        "contactName" => get_option('boxnow_sender_name', ''),
        "contactNumber" => get_option('boxnow_sender_phone', ''),
        "contactEmail" => get_option('boxnow_sender_email', ''),
        "locationId" => $prep_data['selected_warehouse'],
      ],
      "destination" => [
        "contactNumber" => $prep_data['phone'],
        "contactEmail" => $prep_data['email'],
        "contactName" => $prep_data['first_name'] . ' ' . $prep_data['last_name'],
        "locationId" => $prep_data['locker_id'],
      ],
      "items" => $items
    ];
  

    $response = wp_remote_post($api_url, [
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode($data),
    ]);

    $order = wc_get_order($order_id);

    if (is_wp_error($response)) {
      boxnow_log('BOX NOW Auto Voucher: WP connection error for order #' . $order_id . ' — ' . $response->get_error_message());
      return json_encode(array('error' => $response->get_error_message()));
    }

    $http_code        = wp_remote_retrieve_response_code($response);
    $response_body_raw = wp_remote_retrieve_body($response);
    $response_body    = json_decode($response_body_raw, true);

    boxnow_log('BOX NOW Auto Voucher: order #' . $order_id . ' — HTTP ' . $http_code . ', Response: ' . $response_body_raw);

    if ($order && isset($response_body['id']) && !empty($response_body['parcels']) && is_array($response_body['parcels'])) {
      $parcel_ids = [];
      foreach ($response_body['parcels'] as $parcel) {
        if (empty($parcel['id'])) {
          continue;
        }
        $parcel_ids[] = $parcel['id'];
        // Needed by the "print voucher" endpoint to resolve parcel -> order.
        // Without it, automatically created labels cannot be printed.
        update_option('_boxnow_parcel_order_id_' . $parcel['id'], $order_id, false);
      }
      $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
      $order->save();
    }

    return $response_body_raw;
  }

  /**
   * Determine the smallest BOX NOW compartment a single set of dimensions fits into.
   *
   * Orientation aware: all axis-aligned rotations are tried, plus tilted
   * (diagonal) placement inside a compartment face — so a long flat item is not
   * rejected just because its longest side is above the compartment length.
   *
   * @param array $dimensions length/width/height in cm.
   * @return int 1 = small, 2 = medium, 3 = large.
   * @throws Exception When the item does not fit any compartment.
   */
  function boxnow_get_compartment_size($dimensions)
  {
    $item = array(
      'length' => isset($dimensions['length']) && is_numeric($dimensions['length']) ? (float) $dimensions['length'] : 0.0,
      'width'  => isset($dimensions['width'])  && is_numeric($dimensions['width'])  ? (float) $dimensions['width']  : 0.0,
      'height' => isset($dimensions['height']) && is_numeric($dimensions['height']) ? (float) $dimensions['height'] : 0.0,
    );

    // No dimensions recorded — fall back to medium, as before.
    if (max($item['length'], $item['width'], $item['height']) <= 0) {
      return 2;
    }

    foreach (boxnow_get_compartments() as $size => $box) {
      if (boxnow_item_fits_in_box($item, $box)) {
        return $size;
      }
    }

    throw new Exception(__('Invalid product dimensions - please ensure the product(s) fit in a BOX NOW locker!', 'boxnowbulgaria'));
  }

  function boxnow_prepare_data($order)
  {
    // Update possibly edited fields
    if (isset($_POST['_boxnow_locker_id']) && !empty($_POST['_boxnow_locker_id'])) {
      $order->update_meta_data('_boxnow_locker_id', wc_clean($_POST['_boxnow_locker_id']));
    }
    if (isset($_POST['_selected_warehouse']) && !empty($_POST['_selected_warehouse'])) {
      $order->update_meta_data('_selected_warehouse', wc_clean($_POST['_selected_warehouse']));
    }
    $order->save();

    $prep_data = $order->get_address('billing');
    foreach ($order->get_meta_data() as $data) {
      $meta_key = $data->key;
      $meta_value = $data->value;

      switch ($meta_key) {
        case get_option('boxnow-save-data-addressline1', ''):
          $prep_data['locker_addressline1'] = $meta_value;
          break;
        case get_option('boxnow-save-data-postalcode', ''):
          $prep_data['locker_postalcode'] = (int)$meta_value;
          break;
        case get_option('boxnow-save-data-addressline2', ''):
          $prep_data['locker_addressline2'] = $meta_value;
          break;
        case '_boxnow_locker_id':
          $prep_data['locker_id'] = $meta_value;
          break;
        case '_selected_warehouse':
          $prep_data['selected_warehouse'] = $meta_value;
          break;
      }
    }

    $prep_data['payment_method'] = $order->get_payment_method();
    $prep_data['order_total'] = $order->get_total();
    $prep_data['product_price'] = number_format(strval($order->get_subtotal()), 2, '.', '');

    // Validate the whole order against the largest BOX NOW compartment using the
    // same geometry the checkout uses, so an order that was allowed to check out
    // can never be rejected here.
    //
    // Heights are NOT stacked. Stacking (h1 + h2 + h3 …) rejects orders that fit
    // perfectly well — two 15 cm items do not need 30 cm of compartment height,
    // they sit next to each other. Instead we check the two conditions that
    // actually make a parcel impossible: an item that cannot be placed in the
    // compartment in any orientation (tilted/diagonal included), and a summed
    // volume larger than the compartment.
    $instance_id = boxnow_get_order_instance_id($order);
    $limits      = boxnow_get_shipping_limits($instance_id);
    $box         = array(
      'length' => $limits['length'],
      'width'  => $limits['width'],
      'height' => $limits['height'],
    );

    $order_items = boxnow_get_order_items($order);
    $fit         = boxnow_check_items_fit($order_items, $box, $limits['weight']);

    if (!$fit['fits']) {
      boxnow_log(sprintf(
        'BOX NOW: order #%d rejected — %s (volume %.1f/%.1f cm3, weight %.2f/%.2f kg%s)',
        $order->get_id(),
        $fit['reason'],
        $fit['total_volume'],
        $fit['box_volume'],
        $fit['total_weight'],
        $limits['weight'],
        $fit['oversized_item'] ? ', item: ' . $fit['oversized_item'] : ''
      ));

      if ($fit['reason'] === 'weight_exceeded') {
        throw new Exception(sprintf(
          /* translators: 1: order weight in kg, 2: configured limit in kg */
          __('Order weight (%1$s kg) exceeds the BOX NOW limit of %2$s kg.', 'boxnowbulgaria'),
          number_format($fit['total_weight'], 2),
          number_format($limits['weight'], 2)
        ));
      }

      throw new Exception(__('Invalid product dimensions - please ensure the product(s) fit in a BOX NOW locker!', 'boxnowbulgaria'));
    }

    // Large compartment by default — it always works for anything that passed the
    // check above, and the admin UI lets the shop pick a smaller one manually.
    // Shops that trust their product dimensions can opt into automatic sizing:
    //   add_filter('boxnow_compartment_size', function ($size, $smallest) { return $smallest ?: $size; }, 10, 2);
    $smallest_compartment = boxnow_get_compartment_for_items($order_items);
    $compartment_size     = (int) apply_filters('boxnow_compartment_size', 3, $smallest_compartment, $order);
    if ($compartment_size < 1 || $compartment_size > 3) {
      $compartment_size = 3;
    }
    $prep_data['compartment_sizes'] = [$compartment_size];

    // Fall back to the globally configured warehouse when the order has none.
    if (empty($prep_data['selected_warehouse'])) {
      $prep_data['selected_warehouse'] = get_option('boxnow_warehouse_id', '');
    }

    // Normalize customer phone — prefer locker country (stored from widget), fall back to billing country.
    $locker_country  = strtolower((string) $order->get_meta('_boxnow_locker_country'));
    $billing_country = strtolower((string) $order->get_billing_country());
    $phone_country   = !empty($locker_country) ? $locker_country : $billing_country;
    $prep_data['phone'] = boxnow_normalize_phone(isset($prep_data['phone']) ? $prep_data['phone'] : '', $phone_country);

    // Declared parcel weight: the order's net weight (already summed and unit
    // converted above) plus a packaging allowance.
    $packaging_weight    = (float) apply_filters('boxnow_packaging_weight', 1.00, $order);
    $prep_data['weight'] = round($fit['total_weight'] + $packaging_weight, 3);

    return $prep_data;
  }

  function boxnow_send_delivery_request($prep_data, $order_id, $num_vouchers, $compartment_sizes)
  {
    $access_token = boxnow_get_access_token();

    if (empty($access_token)) {
      boxnow_log('BOX NOW: Aborting delivery request for order #' . $order_id . ' — authentication failed (no token).');
      return json_encode(array('error' => 'authentication_failed', 'message' => 'Authentication failed. Please check BOX NOW API credentials in the plugin settings.'));
    }

    $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/delivery-requests';
    $randStr = boxnow_build_order_reference($order_id);
    $payment_method = $prep_data['payment_method'];
    $send_voucher_via_email = get_option('boxnow_voucher_option', 'email') === 'email';
    $send_voucher_via_button = get_option('boxnow_voucher_option', 'email') === 'button';

    $items = [];
    for ($i = 0; $i < $num_vouchers; $i++) {
      $items[] = [
        "value" => $prep_data['product_price'],
        "weight" => $prep_data['weight'],
        "compartmentSize" => $compartment_sizes
      ];
    }

    $data = [
      "notifyOnAccepted" => $send_voucher_via_button ? get_option('boxnow_voucher_button', '') : '',
      "orderNumber" => $randStr,
      "invoiceValue" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
      "paymentMode" => $payment_method === 'cod' ? "cod" : "prepaid",
      "amountToBeCollected" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
      "allowReturn" => true,
      "origin" => [
        "contactName" => get_option('boxnow_sender_name', ''),
        "contactNumber" => get_option('boxnow_sender_phone', ''),
        "contactEmail" => get_option('boxnow_sender_email', ''),
        "locationId" => $prep_data['selected_warehouse'],
      ],
      "destination" => [
        "contactNumber" => $prep_data['phone'],
        "contactEmail" => $prep_data['email'],
        "contactName" => $prep_data['first_name'] . ' ' . $prep_data['last_name'],
        "locationId" => $prep_data['locker_id'],
      ],
      "items" => $items
    ];

    $response = wp_remote_post($api_url, [
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode($data),
    ]);

    $order = wc_get_order($order_id);

    if (is_wp_error($response)) {
      // Return the error as JSON so the caller can parse it
      boxnow_log('BOX NOW: WP Error: ' . $response->get_error_message());
      return json_encode(array('error' => $response->get_error_message()));
    } else {
      $http_code = wp_remote_retrieve_response_code($response);
      $response_body_raw = wp_remote_retrieve_body($response);
      $response_body = json_decode($response_body_raw, true);
      
      // Log HTTP status and response for debugging
      boxnow_log('BOX NOW: HTTP Status: ' . $http_code . ', Response: ' . $response_body_raw);
      
      if ($order && isset($response_body['id']) && !empty($response_body['parcels']) && is_array($response_body['parcels'])) {
        $parcel_ids = [];
        foreach ($response_body['parcels'] as $parcel) {
          if (empty($parcel['id'])) {
            continue;
          }
          $parcel_ids[] = $parcel['id'];
          // Needed by the "print voucher" endpoint to resolve parcel -> order.
          update_option('_boxnow_parcel_order_id_' . $parcel['id'], $order_id, false);
        }
        $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
        $order->save();
      }
      // Always return the response body - let caller handle error parsing
      return $response_body_raw;
    }
  }

  function boxnow_get_access_token()
  {
    $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/auth-sessions';
    $client_id = get_option('boxnow_client_id', '');
    $client_secret = get_option('boxnow_client_secret', '');

    $response = wp_remote_post($api_url, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => $client_id,
        'client_secret' => $client_secret,
      ]),
    ]);

    if (is_wp_error($response)) {
      boxnow_log('BOX NOW Auth: WP connection error — ' . $response->get_error_message());
      return null;
    }

    $http_code  = wp_remote_retrieve_response_code($response);
    $body_raw   = wp_remote_retrieve_body($response);
    $json       = json_decode($body_raw, true);

    if (isset($json['access_token'])) {
      return $json['access_token'];
    }

    // Token missing — log HTTP status and full response so partners can diagnose the cause
    $api_error = '';
    if (is_array($json)) {
      $api_error = isset($json['message']) ? $json['message']
        : (isset($json['error']) ? $json['error']
        : (isset($json['error_description']) ? $json['error_description'] : $body_raw));
    } else {
      $api_error = $body_raw;
    }
    boxnow_log('BOX NOW Auth failed — HTTP ' . $http_code . ': ' . $api_error);
    return null;
  }

  // Print Vouchers section
  function box_now_delivery_vouchers_input($order)
  {
    // Ensure we have a valid order object (defensive check - v2.1.4 didn't have this but it's safe)
    if (!$order || !is_a($order, 'WC_Order')) {
        return;
    }
    
    // Get the order shipping method (restored v2.1.4 behavior)
    $shipping_methods = $order->get_shipping_methods();
    $box_now_used = false;

    foreach ($shipping_methods as $shipping_method) {
      if ($shipping_method->get_method_id() == 'box_now_delivery') {
        $box_now_used = true;
        break;
      }
    }

    // Only proceed if Box Now was used
    if ($box_now_used) {
      if (get_option('boxnow_voucher_option', 'email') === 'button') {
        $max_vouchers = 0;
        foreach ($order->get_items() as $item) {
          $max_vouchers += $item->get_quantity();
        }

        $parcel_ids = $order->get_meta('_boxnow_parcel_ids');
        $vouchers_created = $order->get_meta('_boxnow_vouchers_created');
        $button_disabled = $vouchers_created ? 'disabled' : '';

        $is_cod = ($order->get_payment_method() === 'cod');
        $allow_multiple = (get_option('boxnow_allow_multiple_labels', 'off') === 'on');
        $show_quantity_input = ($allow_multiple && !$is_cod);

        if (!empty($parcel_ids)) {
          echo '<input type="hidden" id="box_now_parcel_ids" value="' . esc_attr(json_encode($parcel_ids ?: [])) . '">';
        }

        echo '<input type="hidden" id="create_vouchers_enabled" value="true" />';
        echo '<input type="hidden" id="max_vouchers" value="' . esc_attr($max_vouchers) . '">';
        echo '<input type="hidden" id="boxnow_allow_multiple" value="' . ($allow_multiple ? 'yes' : 'no') . '" />';
        echo '<input type="hidden" id="boxnow_is_cod" value="' . ($is_cod ? 'yes' : 'no') . '" />';

        if ($parcel_ids) {
          $links_html = '';
          foreach ($parcel_ids as $parcel_id) {
            $links_html .= '<a href="#" data-parcel-id="' . $parcel_id . '" class="parcel-id-link box-now-link">&#128196; ' . $parcel_id . '</a> ';
            $links_html .= '<button class="cancel-voucher-btn" data-order-id="' . $order->get_id() . '" style="color: white; background-color: red; border-radius: 4px; margin: 4px 0; border: none; cursor: pointer; padding: 6px 12px; font-size: 13px;">&#9664; ' . esc_html__('Cancel generated voucher(s)', 'boxnowbulgaria') . '</button><br>';
          }
        } else {
          $links_html = '';
        }
    ?>
        <div class="box-now-vouchers">
          <h4><?php esc_html_e('Generate BOX NOW Voucher', 'boxnowbulgaria'); ?></h4>
          <?php if ($show_quantity_input) : ?>
          <p><?php esc_html_e('Voucher(s) for this order (Maximum possible vouchers:', 'boxnowbulgaria'); ?> <span style="font-weight: bold; color: red;"><?php echo esc_html($max_vouchers); ?></span>)</p>
          <?php endif; ?>
          <input type="hidden" id="box_now_order_id" value="<?php echo esc_attr($order->get_id()); ?>" />
          <?php if ($show_quantity_input) : ?>
          <input type="number" id="box_now_voucher_code" name="box_now_voucher_code" min="1" max="<?php echo esc_attr($max_vouchers); ?>" placeholder="<?php esc_attr_e('Enter number of vouchers', 'boxnowbulgaria'); ?>" style="width: 100%;" />
          <?php else : ?>
          <input type="hidden" id="box_now_voucher_code" name="box_now_voucher_code" value="1" />
          <?php if ($is_cod) : ?>
          <p style="font-size: 12px; color: #666; margin: 0 0 8px 0;"><?php esc_html_e('COD order: always 1 label to prevent duplicate payment requests.', 'boxnowbulgaria'); ?></p>
          <?php endif; ?>
          <?php endif; ?>

          <div class="box-now-compartment-size-buttons" style="margin-top: 10px;">
            <button type="button" id="box_now_create_voucher_small" class="button button-primary box-now-voucher-btn box-now-voucher-btn-small" data-compartment-size="small" <?php echo esc_attr($button_disabled); ?> style="display: block; margin-bottom: 10px;"><?php esc_html_e('Create Voucher (Small)', 'boxnowbulgaria'); ?></button>
            <button type="button" id="box_now_create_voucher_medium" class="button button-primary box-now-voucher-btn box-now-voucher-btn-medium" data-compartment-size="medium" <?php echo esc_attr($button_disabled); ?> style="display: block; margin-bottom: 10px;"><?php esc_html_e('Create Voucher (Medium)', 'boxnowbulgaria'); ?></button>
            <button type="button" id="box_now_create_voucher_large" class="button button-primary box-now-voucher-btn box-now-voucher-btn-large" data-compartment-size="large" <?php echo esc_attr($button_disabled); ?> style="display: block; margin-bottom: 10px;"><?php esc_html_e('Create Voucher (Large)', 'boxnowbulgaria'); ?></button>
          </div>
          <div id="box_now_voucher_link"><?php echo wp_kses_post($links_html); ?></div>
        </div>
      <?php
      }
    }
  }
  // Works for both legacy CPT and HPOS
  add_action('woocommerce_admin_order_data_after_shipping_address', 'box_now_delivery_vouchers_input', 10, 1);

  function box_now_delivery_vouchers_js()
  {
    // Bump version to refresh cached admin JS (alternative lockers dialog updates)
    // Restored v2.1.4 behavior: enqueue on all admin pages (simpler, works reliably)
    wp_enqueue_script('box-now-delivery-js', plugin_dir_url(__FILE__) . 'js/box-now-create-voucher.js', array('jquery'), '1.0.1', true);

    wp_localize_script('box-now-delivery-js', 'myAjax', array(
      'nonce' => wp_create_nonce('box-now-delivery-nonce'),
      'ajaxurl' => admin_url('admin-ajax.php'),
    ));
  }
  add_action('admin_enqueue_scripts', 'box_now_delivery_vouchers_js');

  function boxnow_cancel_voucher_ajax_handler()
  {
    if (!current_user_can('edit_shop_orders')) {
      wp_send_json_error(__('Unauthorized', 'boxnowbulgaria'), 403);
    }
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'box-now-delivery-nonce')) {
      wp_die('Invalid nonce');
    }

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $parcel_id = isset($_POST['parcel_id']) ? sanitize_text_field($_POST['parcel_id']) : '';

    if ($order_id > 0 && $parcel_id) {
      $order = wc_get_order($order_id);
      if (!$order) {
        wp_send_json_error(__('Invalid order number', 'boxnowbulgaria'));
        return;
      }

      $api_cancellation_result = boxnow_send_cancellation_request($parcel_id);
      if ($api_cancellation_result === 'success') {
        // Update parcel IDs meta (order status is NOT changed - let shop owner manage it manually)
        $parcel_ids = $order->get_meta('_boxnow_parcel_ids');
        if (!is_array($parcel_ids)) {
          $parcel_ids = array();
        }
        if (($key = array_search($parcel_id, $parcel_ids)) !== false) {
          unset($parcel_ids[$key]);
          $parcel_ids = array_values($parcel_ids);
          $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
        }

        // Keep the legacy single-parcel meta in sync so the "BOX NOW Canceled"
        // order status does not try to cancel an already-cancelled parcel.
        if ($order->get_meta('_boxnow_parcel_id') === $parcel_id) {
          $order->delete_meta_data('_boxnow_parcel_id');
        }

        // Allow new vouchers to be generated once every parcel is cancelled.
        if (empty($parcel_ids)) {
          $order->delete_meta_data('_boxnow_vouchers_created');
          $order->delete_meta_data('_voucher_created');
        }

        $order->save();
        delete_option('_boxnow_parcel_order_id_' . $parcel_id);

        wp_send_json_success($parcel_id);
      } else {
        wp_send_json_error(__('BOX NOW API error:', 'boxnowbulgaria') . ' ' . $api_cancellation_result);
      }
    } else {
      wp_send_json_error(__('Missing order or parcel ID.', 'boxnowbulgaria'));
    }
  }
  add_action('wp_ajax_cancel_voucher', 'boxnow_cancel_voucher_ajax_handler');

  /**
   * Parse BOX NOW API error response and return a user-friendly error message
   * This function is used by both single and bulk voucher creation
   * 
   * @param array|null $response_body The decoded JSON response from the API
   * @return string The formatted error message
   */
  function boxnow_parse_api_error($response_body)
  {
    // Default error message
    $error_message = __('Error generating voucher', 'boxnowbulgaria');

    // Handle string response (not yet decoded)
    if (is_string($response_body)) {
      $decoded = json_decode($response_body, true);
      if ($decoded !== null) {
        $response_body = $decoded;
      } else {
        boxnow_log('BOX NOW: Raw string API response: ' . $response_body);
        return $error_message . ' [API: ' . $response_body . ']';
      }
    }

    // Handle null or empty response
    if (empty($response_body) || !is_array($response_body)) {
      boxnow_log('BOX NOW: Empty or invalid API response: ' . print_r($response_body, true));
      return $error_message . ' [API: ' . __('empty or invalid response', 'boxnowbulgaria') . ']';
    }

    // Log the full response for debugging
    boxnow_log('BOX NOW: Parsing API error response: ' . print_r($response_body, true));

    // Extract the raw API error text — this is always appended to the final message
    // so partners can see exactly what the API said alongside the human-readable label.
    $raw_api_error = '';
    if (isset($response_body['message']) && is_string($response_body['message'])) {
      $raw_api_error = $response_body['message'];
    } elseif (isset($response_body['error']) && is_string($response_body['error'])) {
      $raw_api_error = $response_body['error'];
    } elseif (isset($response_body['error_description'])) {
      $raw_api_error = $response_body['error_description'];
    } elseif (isset($response_body['detail'])) {
      $raw_api_error = is_string($response_body['detail']) ? $response_body['detail'] : json_encode($response_body['detail']);
    } elseif (isset($response_body['title'])) {
      $raw_api_error = $response_body['title'];
    } elseif (isset($response_body['errors']) && is_array($response_body['errors'])) {
      $parts = array();
      foreach ($response_body['errors'] as $field => $messages) {
        $parts[] = $field . ': ' . (is_array($messages) ? implode(', ', $messages) : $messages);
      }
      $raw_api_error = implode('; ', $parts);
    }

    // Use raw text as the working error message for keyword detection
    $error_message = $raw_api_error ?: $error_message;

    $http_status = isset($response_body['status']) ? intval($response_body['status']) : 0;

    // ── 1. AUTHENTICATION ERRORS (checked first — a 401 must never be shown as a locker error)
    $is_auth_error = false;
    if ($http_status === 401) {
      $is_auth_error = true;
    } else {
      $auth_keywords = array('unauthorized', 'unauthenticated', 'authentication', 'invalid_client',
                             'invalid_grant', 'access denied', 'invalid credentials',
                             'authentication_failed');
      foreach ($auth_keywords as $kw) {
        if (stripos($error_message, $kw) !== false || stripos($raw_api_error, $kw) !== false) {
          $is_auth_error = true;
          break;
        }
      }
    }

    if ($is_auth_error) {
      return __('Authentication error with BOX NOW API — please check Client ID and Client Secret in the plugin settings.', 'boxnowbulgaria')
        . ($raw_api_error ? ' [API: ' . $raw_api_error . ']' : '');
    }

    // ── 2. SIZE / DIMENSIONS ERRORS
    $size_keywords = array(
      'weight', 'dimensions', 'size', 'compartment',
      'тегло', 'размер', 'габарит', 'клетка', 'пратка',
      'too large', 'too heavy', 'exceeds', 'maximum', 'limit',
      'validation', 'constraint', 'out of range', 'unprocessable',
    );
    $is_size_error = false;
    foreach ($size_keywords as $kw) {
      if (stripos($error_message, $kw) !== false) {
        $is_size_error = true;
        break;
      }
    }
    if (!$is_size_error && is_array($response_body)) {
      $size_fields = array('weight', 'length', 'width', 'height', 'dimensions', 'compartmentSize');
      foreach ($size_fields as $field) {
        if (isset($response_body['errors'][$field]) ||
            (isset($response_body['violations']) && is_array($response_body['violations']))) {
          $is_size_error = true;
          break;
        }
      }
    }

    if ($is_size_error) {
      return __('Invalid product dimensions — please ensure the product(s) fit in a BOX NOW locker!', 'boxnowbulgaria')
        . ($raw_api_error ? ' [API: ' . $raw_api_error . ']' : '');
    }

    // ── 3. COD / PAYMENT MODE NOT ALLOWED
    $cod_keywords = array(
      'cod', 'cash on delivery', 'paymentmode', 'payment mode',
      'payment method not', 'payment not allowed', 'payment not supported',
      'amounttobecollected', 'invoice',
    );
    $is_cod_error = false;
    foreach ($cod_keywords as $kw) {
      if (stripos($error_message, $kw) !== false) {
        $is_cod_error = true;
        break;
      }
    }

    if ($is_cod_error) {
      return __('Cash on delivery is not enabled for your BOX NOW account. Please use a prepaid payment method or contact BOX NOW support.', 'boxnowbulgaria')
        . ($raw_api_error ? ' [API: ' . $raw_api_error . ']' : '');
    }

    // ── 4. DEFAULT FOR ALL OTHER 4xx: locker / destination not available
    // Any 400 on the delivery-requests endpoint that isn't auth or COD is a
    // locker/region/partner restriction — show the original user-friendly message.
    if ($http_status >= 400 && $http_status < 500 || !empty($raw_api_error)) {
      return __('The selected locker is currently not available in the BOX NOW network. Please edit the order and choose a different locker.', 'boxnowbulgaria')
        . ($raw_api_error ? ' [API: ' . $raw_api_error . ']' : '');
    }

    // ── 6. FALLBACK: return whatever text we extracted from the API
    return __('Error generating voucher', 'boxnowbulgaria')
      . ($raw_api_error ? ' [API: ' . $raw_api_error . ']' : '');
  }

  function boxnow_create_box_now_vouchers_callback()
  {
    check_ajax_referer('box-now-delivery-nonce', 'security');

    if (!current_user_can('edit_shop_orders')) {
      wp_send_json_error(__('Unauthorized', 'boxnowbulgaria'), 403);
    }

    if (!isset($_POST['order_id']) || !isset($_POST['voucher_quantity']) || !isset($_POST['compartment_size'])) {
      wp_send_json_error('Error: Missing required data.');
    }

    $order_id = intval($_POST['order_id']);
    $voucher_quantity = intval($_POST['voucher_quantity']);
    $compartment_size = intval(sanitize_text_field($_POST['compartment_size']));

    $order = wc_get_order($order_id);
    if (!$order) {
      wp_send_json_error('Error: Order not found.');
    }

    // Prepare data; locker validity is enforced by the delivery-requests API.
    try {
      $prep_data = boxnow_prepare_data($order);
    } catch (Exception $e) {
      boxnow_log('BOX NOW: Error preparing data: ' . $e->getMessage());
      wp_send_json_error(__('Invalid product dimensions - please ensure the product(s) fit in a BOX NOW locker!', 'boxnowbulgaria'));
      return;
    }

    // 3) Attempt to create vouchers
    try {
      $delivery_request_response = boxnow_send_delivery_request($prep_data, $order_id, $voucher_quantity, $compartment_size);
      
      // Log the response for debugging
      boxnow_log('BOX NOW Single Voucher: API Response: ' . $delivery_request_response);
      
      // Handle null or empty response
      if (empty($delivery_request_response)) {
        wp_send_json_error(__('Empty response from BOX NOW API', 'boxnowbulgaria'));
        return;
      }
      
      $response_body = json_decode($delivery_request_response, true);
      
      // Handle JSON decode failure
      if ($response_body === null && json_last_error() !== JSON_ERROR_NONE) {
        boxnow_log('BOX NOW: JSON decode error: ' . json_last_error_msg());
        wp_send_json_error(__('Error processing API response', 'boxnowbulgaria'));
        return;
      }

      if (isset($response_body['id']) && isset($response_body['parcels'])) {
        // Vouchers created successfully; store parcel IDs
        $parcel_ids = $order->get_meta('_boxnow_parcel_ids', true);
        if (!is_array($parcel_ids)) {
          $parcel_ids = [];
        }

        foreach ($response_body['parcels'] as $parcel) {
          $parcel_ids[] = $parcel['id'];
          // Not autoloaded: one row per parcel would otherwise be loaded on
          // every single request forever.
          update_option('_boxnow_parcel_order_id_' . $parcel['id'], $order_id, false);
        }

        $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
        $order->update_meta_data('_boxnow_vouchers_created', 1);
        
        // Also set _voucher_created to prevent boxnow_order_completed hook from interfering
        $order->update_meta_data('_voucher_created', 'yes');
        $order->save();

        // Get the newly created parcel IDs for the response
        $new_parcel_ids = array_slice($parcel_ids, -$voucher_quantity);

        // Send success response (order status is NOT changed - let shop owner manage it manually)
        wp_send_json_success(array('new_parcel_ids' => $new_parcel_ids));
      } else {
        // Use shared helper function to parse API error
        $error_message = boxnow_parse_api_error($response_body);
        wp_send_json_error($error_message);
      }
    } catch (Exception $e) {
      boxnow_log('BOX NOW: Exception during voucher creation: ' . $e->getMessage());
      wp_send_json_error($e->getMessage());
    } catch (Error $e) {
      boxnow_log('BOX NOW: Fatal error during voucher creation: ' . $e->getMessage());
      wp_send_json_error(__('Internal error generating voucher', 'boxnowbulgaria'));
    }
  }

  add_action('wp_ajax_create_box_now_vouchers', 'boxnow_create_box_now_vouchers_callback');

  /**
   * AJAX handler to update locker ID in order
   */
  function boxnow_update_order_locker_callback() {
    check_ajax_referer('box-now-delivery-nonce', 'security');

    if (!current_user_can('edit_shop_orders')) {
      wp_send_json_error(__('Unauthorized', 'boxnowbulgaria'), 403);
    }

    if (!isset($_POST['order_id']) || !isset($_POST['locker_id'])) {
      wp_send_json_error(__('Missing order or locker information.', 'boxnowbulgaria'));
    }

    $order_id = intval($_POST['order_id']);
    $locker_id = sanitize_text_field($_POST['locker_id']);

    $order = wc_get_order($order_id);
    if (!$order) {
      wp_send_json_error(__('Order not found.', 'boxnowbulgaria'));
    }

    // Update locker ID
    $order->update_meta_data('_boxnow_locker_id', $locker_id);
    
    // Fetch and update the real address
    $real_address = boxnow_get_locker_address_line1($locker_id);
    if (!empty($real_address)) {
      $order->update_meta_data('_boxnow_real_address', $real_address);
    }
    
    $order->save();

    wp_send_json_success(array(
      'message' => __('Locker updated successfully.', 'boxnowbulgaria'),
      'locker_id' => $locker_id,
      'address' => $real_address
    ));
  }
  add_action('wp_ajax_boxnow_update_order_locker', 'boxnow_update_order_locker_callback');

  function boxnow_print_box_now_voucher_callback()
  {
    if (!current_user_can('edit_shop_orders')) {
      wp_die(esc_html__('Unauthorized', 'boxnowbulgaria'), '', array('response' => 403));
    }

    if (!isset($_GET['parcel_id'])) {
      wp_die('Error: Missing required data.');
    }

    $parcel_id = sanitize_text_field($_GET['parcel_id']);

    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'box-now-delivery-nonce')) {
      wp_die(esc_html__('Invalid or expired link.', 'boxnowbulgaria'), '', array('response' => 403));
    }

    $order_id = get_option('_boxnow_parcel_order_id_' . $parcel_id);

    if (!$order_id) {
      wp_die('Error: Order not found.');
    }

    $order = wc_get_order($order_id);

    if (!$order) {
      wp_die('Error: Order not found.');
    }

    try {
      boxnow_print_voucher_pdf($parcel_id);
    } catch (Exception $e) {
      wp_die('Error: ' . $e->getMessage());
    }

    exit();
  }
  add_action('wp_ajax_print_box_now_voucher', 'boxnow_print_box_now_voucher_callback');


  /**
   * Add voucher email validation script to the admin footer.
   */
  function boxnow_voucher_email_validation()
  {
    if (is_admin()) {
      ?>
      <script>
        function isValidEmail(email) {
          const re = /^(([^<>()[\]\\.,;:\s@"]+(.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@(([[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}]|(([a-zA-Z\-0-9]+.)+[a-zA-Z]{2,}))$/;
          return re.test(email.toLowerCase());
        }

        function displayEmailValidationMessage(message) {
          const messageContainer = document.getElementById('email_validation_message');
          messageContainer.textContent = message;
        }

        document.addEventListener('DOMContentLoaded', function() {
          const emailInput = document.querySelector('input[name="boxnow_voucher_email"]');

          if (emailInput) {
            emailInput.addEventListener('input', function() {
              if (!isValidEmail(emailInput.value)) {
                displayEmailValidationMessage('<?php echo esc_js(__('Please enter a valid email address!', 'boxnowbulgaria')); ?>');
              } else {
                displayEmailValidationMessage('');
              }
            });
          } else {
            console.warn("Email input element not found.");
          }
        });
      </script>
    <?php
    }
  }
  add_action('admin_footer', 'boxnow_voucher_email_validation');

  add_action('admin_enqueue_scripts', 'boxnow_load_jquery_in_admin');
  function boxnow_load_jquery_in_admin()
  {
    wp_enqueue_script('jquery');
  }
} else {
  /**
   * Display admin notice if WooCommerce is not active.
   */
  function bndp_box_now_delivery_admin_notice()
  {
    ?>
    <div class="notice notice-error is-dismissible">
      <p>BOX NOW requires WooCommerce to be installed and active.</p>
    </div>
<?php
  }

  add_action('admin_notices', 'bndp_box_now_delivery_admin_notice');
}
