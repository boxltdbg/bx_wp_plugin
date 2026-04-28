<?php

class BNDP_Serializer
{
  public function init()
  {
    add_action('admin_post_boxnow-settings-save', array($this, 'boxnow_settings_save'));
  }

  public function boxnow_settings_save()
  {
    if (!$this->has_valid_nonce()) {
      wp_die('Invalid nonce specified.');
    }

    $environment = 'production';
    if (isset($_POST['boxnow_environment'])) {
      $env_value = sanitize_key($_POST['boxnow_environment']);
      $environment = $env_value === 'stage' ? 'stage' : 'production';
    }

    update_option('boxnow_environment', $environment);

    // Определяме URL според избраната среда и не позволяваме произволна стойност
    $api_url = $environment === 'stage' ? 'api-stage.boxnow.bg' : 'api-production.boxnow.bg';
    update_option('boxnow_api_url', $api_url);

    if (isset($_POST['boxnow_warehouse_id'])) {
      update_option('boxnow_warehouse_id', sanitize_text_field($_POST['boxnow_warehouse_id']));
    }

    if (isset($_POST['boxnow_client_id'])) {
      $client_id = trim(sanitize_text_field($_POST['boxnow_client_id']));
      update_option('boxnow_client_id', $client_id);
    }

    if (isset($_POST['boxnow_partner_id'])) {
      update_option('boxnow_partner_id', sanitize_text_field($_POST['boxnow_partner_id']));
    }

    if (isset($_POST['boxnow_client_secret'])) {
      $client_secret = trim(sanitize_text_field($_POST['boxnow_client_secret']));
      update_option('boxnow_client_secret', $client_secret);
    }

    if (isset($_POST['boxnow_button_color'])) {
      update_option('boxnow_button_color', sanitize_hex_color($_POST['boxnow_button_color']));
    }

    if (isset($_POST['boxnow_button_text'])) {
      update_option('boxnow_button_text', sanitize_text_field($_POST['boxnow_button_text']));
    }

    if (isset($_POST['box_now_display_mode'])) {
      update_option('box_now_display_mode', sanitize_key($_POST['box_now_display_mode']));
    }

    if (isset($_POST['boxnow_gps_tracking'])) {
      update_option('boxnow_gps_tracking', sanitize_key($_POST['boxnow_gps_tracking']));
    }

    if (isset($_POST['boxnow_voucher_option'])) {
      update_option('boxnow_voucher_option', sanitize_key($_POST['boxnow_voucher_option']));
    }
    // Toggle for order indicator
    $order_indicator_value = (isset($_POST['boxnow_show_order_indicator']) && $_POST['boxnow_show_order_indicator'] === 'on') ? 'on' : 'off';
    update_option('boxnow_show_order_indicator', $order_indicator_value);

    // Toggle for allowing multiple labels per order (prepaid only)
    $allow_multiple_labels = (isset($_POST['boxnow_allow_multiple_labels']) && $_POST['boxnow_allow_multiple_labels'] === 'on') ? 'on' : 'off';
    update_option('boxnow_allow_multiple_labels', $allow_multiple_labels);
    if (isset($_POST['boxnow_sender_name'])) {
      $boxnow_sender_name = sanitize_text_field($_POST['boxnow_sender_name']);
      update_option('boxnow_sender_name', $boxnow_sender_name);
    }

    if (isset($_POST['boxnow_sender_email'])) {
      update_option('boxnow_sender_email', sanitize_email($_POST['boxnow_sender_email']));
    }

    if (isset($_POST['boxnow_sender_phone'])) {
      $boxnow_sender_phone = sanitize_text_field($_POST['boxnow_sender_phone']);
      // Remove leading zero if it exists and prefix with +359 if necessary
      if (substr($boxnow_sender_phone, 0, 1) === '0') {
        $boxnow_sender_phone = substr($boxnow_sender_phone, 1);
      }
      if (substr($boxnow_sender_phone, 0, 1) !== '+' && substr($boxnow_sender_phone, 0, 2) !== '00') {
        $boxnow_sender_phone = '+359' . $boxnow_sender_phone;
      }
      update_option('boxnow_sender_phone', $boxnow_sender_phone);
    }
    if (isset($_POST['boxnow_locker_not_selected_message'])) {
      update_option('boxnow_locker_not_selected_message', sanitize_text_field($_POST['boxnow_locker_not_selected_message']));
    }

    // New: Save the checkout type setting
    if (isset($_POST['boxnow_checkout_type'])) {
      update_option('boxnow_checkout_type', sanitize_key($_POST['boxnow_checkout_type']));
    }

    $this->redirect();
  }

  private function has_valid_nonce()
  {
    if (!isset($_POST['boxnow-custom-message'])) {
      return false;
    }

    $field = sanitize_text_field(wp_unslash($_POST['boxnow-custom-message']));

    $action = 'boxnow-settings-save';
    if (!wp_verify_nonce($field, $action)) {
      return false;
    }

    return true;
  }

  private function redirect()
  {
    $url = admin_url('admin.php?page=box-now-delivery&status=success');
    wp_safe_redirect($url);
    exit;
  }
}
