<?php

// Register custom order status
add_action('init', 'boxnow_register_boxnow_canceled_order_status');
// Add custom order status to WooCommerce
add_filter('wc_order_statuses', 'boxnow_add_canceled_order_status');
add_filter('woocommerce_admin_order_actions', 'boxnow_add_cancel_order_button', 10, 2);
add_action('admin_head', 'boxnow_add_cancel_order_button_css');
// Add the action for sending a cancellation request to the Box Now API
add_action('woocommerce_order_status_changed', 'boxnow_order_canceled', 5, 4);
add_action('transition_post_status', 'boxnow_log_order_status_transition', 10, 3);

function boxnow_log_order_status_transition($new_status, $old_status, $post)
{
  if ('shop_order' === $post->post_type) {
    boxnow_log("Order ID: {$post->ID} status transitioned from {$old_status} to {$new_status}");
  }
}

function boxnow_register_boxnow_canceled_order_status()
{
  register_post_status('wc-boxnow-canceled', array(
    'label' => __('Box Now Canceled', 'boxnowbulgaria'),
    'public' => true,
    'exclude_from_search' => false,
    'show_in_admin_all_list' => true,
    'show_in_admin_status_list' => true,
    'label_count' => _n_noop('Box Now Canceled <span class="count">(%s)</span>', 'Box Now Canceled <span class="count">(%s)</span>', 'boxnowbulgaria')
  ));
}

function boxnow_add_canceled_order_status($order_statuses)
{
  $order_statuses['wc-boxnow-canceled'] = __('BOX NOW Canceled', 'boxnowbulgaria');
  return $order_statuses;
}

function boxnow_add_cancel_order_button($actions, $order)
{
  if ($order->has_status(array('completed'))) {
    $actions['boxnow_cancel'] = array(
      'url' => wp_nonce_url(admin_url('admin-ajax.php?action=woocommerce_mark_order_status&status=wc-boxnow-canceled&order_id=' . $order->get_id()), 'woocommerce-mark-order-status'),
      'name' => __('Cancel Order', 'boxnowbulgaria'),
      'action' => "boxnow_cancel",
    );
  }
  return $actions;
}

function boxnow_add_cancel_order_button_css()
{
  echo '<style>
  .wc-action-button-boxnow_cancel::after {
    content: "\f153";
    color: #a00;
  }
</style>';
}

function boxnow_order_canceled($order_id, $old_status, $new_status, $order)
{
  boxnow_log("Order ID: $order_id status transitioned from $old_status to $new_status");

  // Check if the new status is "wc-boxnow-canceled" or "boxnow-canceled"
  if ($new_status != 'wc-boxnow-canceled' && $new_status != 'boxnow-canceled') {
    return;
  }

  if ($order->has_shipping_method('box_now_delivery')) {
    // Vouchers created from the order screen or in bulk are stored under
    // _boxnow_parcel_ids (plural); only the automatic "order completed" flow
    // writes _boxnow_parcel_id. Cancel every parcel we know about, otherwise
    // manually created labels stay live at BOX NOW after the order is cancelled.
    $parcel_ids = $order->get_meta('_boxnow_parcel_ids');
    if (!is_array($parcel_ids)) {
      $parcel_ids = array();
    }

    $single = $order->get_meta('_boxnow_parcel_id');
    if (!empty($single)) {
      $parcel_ids[] = $single;
    }

    $parcel_ids = array_values(array_unique(array_filter($parcel_ids)));

    if (!empty($parcel_ids)) {
      $remaining = array();
      foreach ($parcel_ids as $parcel_id) {
        $result = boxnow_send_cancellation_request($parcel_id);
        boxnow_log("Box Now Cancellation Result for parcel $parcel_id: " . print_r($result, true));
        if ($result === 'success') {
          delete_option('_boxnow_parcel_order_id_' . $parcel_id);
        } else {
          $remaining[] = $parcel_id;
          $order->add_order_note(
            sprintf(
              /* translators: 1: parcel id, 2: API error */
              __('BOX NOW could not cancel parcel %1$s: %2$s', 'boxnowbulgaria'),
              $parcel_id,
              is_string($result) ? $result : 'unknown error'
            )
          );
        }
      }

      $order->update_meta_data('_boxnow_parcel_ids', $remaining);
      if (empty($remaining)) {
        $order->delete_meta_data('_boxnow_parcel_id');
        $order->delete_meta_data('_boxnow_vouchers_created');
        $order->delete_meta_data('_voucher_created');
      }
      $order->save();
    } else {
      boxnow_log("No parcel ID found for order ID: $order_id");
    }
  } else {
    boxnow_log("Order ID $order_id does not have Box Now Delivery shipping method");
  }
}

function boxnow_send_cancellation_request($parcel_id)
{
  $access_token = boxnow_get_access_token();

  if (empty($access_token)) {
    boxnow_log('Box Now Cancellation Error: authentication failed (no access token).');
    return 'Authentication failed. Please check the BOX NOW API credentials in the plugin settings.';
  }

  $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/parcels/' . rawurlencode($parcel_id) . ':cancel';

  $response = wp_remote_post($api_url, [
    'headers' => [
      'Authorization' => 'Bearer ' . $access_token,
      'Content-Type' => 'application/json',
    ],
    'body' => '{}',
  ]);

  if (is_wp_error($response)) {
    boxnow_log("Box Now Cancellation Error: " . $response->get_error_message());
    return $response->get_error_message();
  } else {
    $response_body = wp_remote_retrieve_body($response);
    boxnow_log("Box Now Cancellation API Response: \n" . $response_body); // Log the API response

    // Check for empty response and treat as success
    if (empty($response_body)) {
      boxnow_log("Box Now Cancellation Result: Empty response, considering as success");
      return 'success';
    }

    $response_data = json_decode($response_body, true);
    if (isset($response_data['success']) && $response_data['success'] == true) {
      return 'success';
    } else {
      boxnow_log("Something went wrong: " . (isset($response_data['error']) ? $response_data['error'] : 'Unknown error'));
      return 'failed';
    }
  }
}
