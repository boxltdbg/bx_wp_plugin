<?php

class BNDP_Serializer
{
  public function init()
  {
    add_action('admin_post_boxnow-settings-save', array($this, 'boxnow_settings_save'));
  }

  public function boxnow_settings_save()
  {
    // Capability check first — a valid nonce alone does not make a request
    // authorized, and these options hold the shop's BOX NOW API credentials.
    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You are not allowed to change these settings.', 'boxnowbulgaria'), '', array('response' => 403));
    }

    if (!$this->has_valid_nonce()) {
      wp_die('Invalid nonce specified.');
    }

    $environment = 'production';
    if (isset($_POST['boxnow_environment'])) {
      $env_value = sanitize_key($_POST['boxnow_environment']);
      $environment = $env_value === 'stage' ? 'stage' : 'production';
    }

    update_option('boxnow_environment', $environment);

    // Set URL according to the selected environment and prevent arbitrary value
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

    // Save shipping countries (at least one must be selected).
    $raw_countries = isset($_POST['boxnow_countries']) && is_array($_POST['boxnow_countries'])
      ? $_POST['boxnow_countries']
      : array('bg');
    $clean_countries = array_values(array_unique(array_filter(array_map('sanitize_key', $raw_countries))));
    if (empty($clean_countries)) {
      $clean_countries = array('bg');
    }
    update_option('boxnow_allowed_countries', implode(',', $clean_countries));

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
    if (isset($_POST['boxnow_sender_name'])) {
      $boxnow_sender_name = sanitize_text_field($_POST['boxnow_sender_name']);
      update_option('boxnow_sender_name', $boxnow_sender_name);
    }

    if (isset($_POST['boxnow_sender_email'])) {
      update_option('boxnow_sender_email', sanitize_email($_POST['boxnow_sender_email']));
    }

    if (isset($_POST['boxnow_sender_phone'])) {
      $boxnow_sender_phone = sanitize_text_field($_POST['boxnow_sender_phone']);
      // Sender is always a Bulgarian operator — normalize with +359.
      $boxnow_sender_phone = boxnow_normalize_phone($boxnow_sender_phone, 'bg');
      update_option('boxnow_sender_phone', $boxnow_sender_phone);
    }
    if (isset($_POST['boxnow_locker_not_selected_message'])) {
      update_option('boxnow_locker_not_selected_message', sanitize_text_field($_POST['boxnow_locker_not_selected_message']));
    }

    // New: Save the checkout type setting
    if (isset($_POST['boxnow_checkout_type'])) {
      update_option('boxnow_checkout_type', sanitize_key($_POST['boxnow_checkout_type']));
    }

    $allow_multiple_labels = (isset($_POST['boxnow_allow_multiple_labels']) && $_POST['boxnow_allow_multiple_labels'] === 'on') ? 'on' : 'off';
    update_option('boxnow_allow_multiple_labels', $allow_multiple_labels);

    if (class_exists('WC_Cache_Helper')) {
      WC_Cache_Helper::get_transient_version('shipping', true);
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
