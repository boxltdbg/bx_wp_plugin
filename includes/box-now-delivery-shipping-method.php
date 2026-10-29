<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

add_action('plugins_loaded', 'box_now_delivery_shipping_method');

/**
 * Initialize the Box Now Delivery shipping method.
 */
function box_now_delivery_shipping_method()
{
    if (!class_exists('Box_Now_Delivery_Shipping_Method')) {
        /**
         * Class Box_Now_Delivery_Shipping_Method
         *
         * @property array $form_fields
         */
        class Box_Now_Delivery_Shipping_Method extends WC_Shipping_Method
        {
            public $cost;
            public $free_delivery_threshold;
            public $taxable;

            /**
             * Constructor for the shipping class.
             */
            public function __construct($instance_id = 0)
            {
                $this->id = 'box_now_delivery';
                $this->instance_id = absint($instance_id);
                $this->method_title = __('BOX NOW Bulgaria', 'boxnowbulgaria');
                $this->method_description = __('Settings for BOX NOW Bulgaria', 'boxnowbulgaria');

                $this->supports = array(
                    'shipping-zones',
                    'instance-settings',
                    'instance-settings-modal',
                );

                $this->init();

                // Load the settings.
                $this->init_settings();

                // Define user set variables.
                $this->title = $this->get_option('title');
                $this->free_delivery_threshold = $this->get_option('free_delivery_threshold');
                $this->taxable = $this->get_option('taxable');
            }

            /**
             * Initialize settings and form fields.
             */
            public function init()
            {
                $this->init_form_fields();
                $this->init_settings();
            }

            /**
             * Processes and saves options.
             * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
             *
             * @return bool was anything saved?
             */
            public function process_admin_options()
            {
                $this->init_settings();

                $post_data = $this->get_post_data();

                foreach ($this->get_form_fields() as $key => $field) {
                    if ('title' !== $this->get_field_type($field)) {
                        try {
                            $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                        } catch (Exception $e) {
                            $this->add_error($e->getMessage());
                        }
                    }
                }

                $saved = update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');

                if (class_exists('WC_Cache_Helper')) {
                    WC_Cache_Helper::get_transient_version('shipping', true);
                }

                return $saved;
            }

            public function get_option_key()
            {
                return $this->plugin_id . $this->id . '_' . $this->instance_id . '_settings';
            }

            /**
             * Define settings fields for the shipping method.
             */
            public function init_form_fields()
            {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable / Disable', 'boxnowbulgaria'),
                        'type' => 'checkbox',
                        'description' => __('Enable or disable BOX NOW delivery method', 'boxnowbulgaria'),
                        'default' => 'yes',
                    ),
                    'title' => array(
                        'title' => __('Shipping Method Name', 'boxnowbulgaria'),
                        'type' => 'text',
                        'description' => __('Shipping method name visible to customers at checkout.', 'boxnowbulgaria'),
                        'default' => __('Delivery to BOX NOW locker - available 24/7', 'boxnowbulgaria'),
                        'desc_tip' => true,
                    ),
                    'costbr1' => array(
                        'title' => __('Shipping cost 0 kg - 20 kg', 'boxnowbulgaria'),
                        'type' => 'text',
                        'description' => __('Shipping cost for parcels up to 20 kg.', 'boxnowbulgaria'),
                        'default' => 0,
                        'desc_tip' => true,
                    ),
                    'free_delivery_threshold' => array(
                        'title' => __('Free delivery threshold', 'boxnowbulgaria'),
                        'type' => 'number',
                        'description' => __('If the order value exceeds this amount, no shipping fee will be charged', 'boxnowbulgaria'),
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'taxable' => array(
                        'title' => __('Taxable', 'boxnowbulgaria'),
                        'type' => 'select',
                        'description' => __('Apply VAT to shipping cost?', 'boxnowbulgaria'),
                        'default' => 'no',
                        'options' => array(
                            'yes' => __('Yes', 'boxnowbulgaria'),
                            'no' => __('No', 'boxnowbulgaria'),
                        ),
                    ),
                    'custom_weight' => array(
                        'title' => __('Maximum allowed weight (kg)', 'boxnowbulgaria'),
                        'type' => 'number',
                        'description' => __('Maximum allowed weight (kg)', 'boxnowbulgaria'),
                        'placeholder' => __('20kg', 'boxnowbulgaria'),
                        'default' => 20,
                        'desc_tip' => true,
                        'custom_attributes' => array(
                            'step' => '0.1',
                            'min' => '0.1',
                        ),
                    ),
                    'dimensions' => array(
                        'title' => __('Maximum parcel dimensions', 'boxnowbulgaria'),
                        'type' => 'title',
                        'description' => __('Maximum parcel dimensions for BOX NOW delivery', 'boxnowbulgaria'),
                    ),
                    'max_length' => array(
                        'title' => __('Maximum length (cm)', 'boxnowbulgaria'),
                        'type' => 'number',
                        'description' => __('Maximum parcel length allowed for this shipping method (in cm)', 'boxnowbulgaria'),
                        'placeholder' => __('60 cm', 'boxnowbulgaria'),
                        'default' => 60,
                        'custom_attributes' => array(),
                    ),
                    'max_width' => array(
                        'title' => __('Maximum width (cm)', 'boxnowbulgaria'),
                        'type' => 'number',
                        'description' => __('Maximum parcel width allowed for this shipping method (in cm)', 'boxnowbulgaria'),
                        'placeholder' => __('45 cm', 'boxnowbulgaria'),
                        'default' => 45,
                        'custom_attributes' => array(),
                    ),
                    'max_height' => array(
                        'title' => __('Maximum height (cm)', 'boxnowbulgaria'),
                        'type' => 'number',
                        'description' => __('Maximum parcel height allowed for this shipping method (in cm)', 'boxnowbulgaria'),
                        'placeholder' => __('36 cm', 'boxnowbulgaria'),
                        'default' => 36,
                        'custom_attributes' => array(),
                    ),
                    'cod_description' => array(
                        'title' => __('Cash on Delivery description settings', 'boxnowbulgaria'),
                        'type' => 'title',
                        'description' => __('Modify the description text for Cash on Delivery payment method', 'boxnowbulgaria'),
                    ),
                    'enable_custom_cod_description' => array(
                        'title' => __('Modify COD text', 'boxnowbulgaria'),
                        'type' => 'checkbox',
                        'description' => __('Enable / disable modification of Cash on Delivery payment method text.', 'boxnowbulgaria'),
                        'default' => 'yes',
                        'class' => 'enable_custom_cod_description',
                    ),
                    'custom_cod_description' => array(
                        'title' => __('COD Method Description (Bulgarian)', 'boxnowbulgaria'),
                        'type' => 'text',
                        'description' => __('Enter custom text for Cash on Delivery payment method', 'boxnowbulgaria'),
                        'default' => __('NOTICE! For BOX NOW locker delivery with Cash on Delivery, there is no cash payment option. Payment is made by bank card via a link you will receive by SMS/Viber/email along with the shipment confirmation.', 'boxnowbulgaria'),
                        'desc_tip' => true,
                        'class' => 'custom_cod_description_field',
                    ),
                    'custom_cod_description_en' => array(
                        'title' => __('COD Method Description (English)', 'boxnowbulgaria'),
                        'type' => 'text',
                        'description' => __('Enter custom text for Cash on Delivery payment method', 'boxnowbulgaria'),
                        'default' => 'NOTICE! For BOX NOW locker delivery with Cash on Delivery, there is no cash payment option. Payment is made by bank card via a link you will receive by SMS/Viber/email along with the shipment confirmation.',
                        'desc_tip' => true,
                        'class' => 'custom_cod_description_en_field',
                    ),
                );
            }
            /**
             * Calculate the shipping cost.
             *
             * @param array $package Shipping package.
             */
            public function calculate_shipping($package = [])
            {
                // Hide the method when the parcel provably cannot be shipped
                // (too big for the largest compartment, or over the weight limit).
                if ($this->has_oversized_products($package)) {
                    return;
                }

                // Taxable yes or no
                $taxable = ($this->taxable == 'yes') ? true : false;

                // Get the order total
                $order_total = WC()->cart->get_displayed_subtotal();

                // Adjust total for any coupons. Percentage coupons are applied
                // first so that a following fixed-cart discount is not itself
                // reduced by the percentage.
                if (!empty(WC()->cart->get_coupons())) {
                    $coupons = WC()->cart->get_coupons();
                    foreach ($coupons as $code => $coupon) {
                        if ($coupon->is_type('percent')) {
                            $order_total -= ($coupon->get_amount() / 100) * $order_total;
                        }
                    }
                    foreach ($coupons as $code => $coupon) {
                        if ($coupon->is_type('fixed_cart')) {
                            $order_total -= $coupon->get_amount();
                        }
                    }
                }
                $order_total = max(0, $order_total);

                // Get the user-defined threshold for free delivery
                $free_delivery_threshold = $this->get_option('free_delivery_threshold');

                // Check if the order total is above the threshold for free delivery
                if (is_numeric($free_delivery_threshold) && (float) $free_delivery_threshold > 0 && $order_total >= (float) $free_delivery_threshold) {
                    $this->cost = 0.00;
                } else {
                    // Single price for parcels up to the configured weight limit.
                    // Anything heavier is already filtered out by
                    // has_oversized_products(), so the cost is always defined here.
                    $this->cost = (float) $this->get_option('costbr1');

                    // Add 10% tax if taxable
                    if ($taxable) {
                        $this->cost *= 1.10;
                    }
                }

                $rate = [
                    'id' => $this->id,
                    'label' => $this->title,
                    'cost' => $this->cost,
                    'taxes' => $taxable ? WC_Tax::calc_shipping_tax($this->cost, WC_Tax::get_shipping_tax_rates()) : '',
                    'calc_tax' => 'per_item',
                ];

                // Register the rate.
                $this->add_rate($rate);
            }

            /**
             * Size/weight limits configured on this shipping method instance.
             *
             * @return array{length: float, width: float, height: float, weight: float}
             */
            private function get_limits()
            {
                return array(
                    'length' => boxnow_positive_float($this->get_option('max_length'), 60.0),
                    'width'  => boxnow_positive_float($this->get_option('max_width'), 45.0),
                    'height' => boxnow_positive_float($this->get_option('max_height'), 36.0),
                    'weight' => boxnow_positive_float($this->get_option('custom_weight'), 20.0),
                );
            }

            /**
             * Should the BOX NOW method be hidden for the current cart?
             *
             * Returns true only when the parcel provably cannot be shipped:
             *
             *   - a single item does not fit in the compartment in ANY orientation,
             *     including tilted/diagonal placement;
             *   - the summed volume of the cart exceeds the compartment volume;
             *   - the summed weight of the whole cart exceeds the weight limit.
             *
             * The weight check is on the cart total, not per line item — ten 3 kg
             * products are 30 kg and must hide the method even though no single
             * item is over 20 kg.
             *
             * @param array|null $package Shipping package, when available.
             * @return bool
             */
            private function has_oversized_products($package = null)
            {
                $limits = $this->get_limits();
                $items  = boxnow_get_cart_items($package);

                if (empty($items)) {
                    return false;
                }

                $box = array(
                    'length' => $limits['length'],
                    'width'  => $limits['width'],
                    'height' => $limits['height'],
                );

                $result = boxnow_check_items_fit($items, $box, $limits['weight']);

                if (!$result['fits']) {
                    boxnow_log(sprintf(
                        'BOX NOW: hiding shipping method — %s (volume %.1f/%.1f cm3, weight %.2f/%.2f kg%s)',
                        $result['reason'],
                        $result['total_volume'],
                        $result['box_volume'],
                        $result['total_weight'],
                        $limits['weight'],
                        $result['oversized_item'] ? ', item: ' . $result['oversized_item'] : ''
                    ));
                }

                return !$result['fits'];
            }
        }
    }
}

// Modify the Cash on Delivery payment method's description based on the shipping zone
add_filter('woocommerce_gateway_description', 'boxnow_change_cod_description', 10, 2);

/**
 * Resolve custom COD description for Box Now (shared helper).
 */
function boxnow_get_custom_cod_description()
{
    // Collect selected shipping methods from POST (if present) and from session
    $selected_methods = array();

    if (!empty($_POST['shipping_method'])) {
        $methods_post = wc_clean(wp_unslash($_POST['shipping_method']));
        if (is_array($methods_post)) {
            $selected_methods = array_merge($selected_methods, $methods_post);
        } elseif (is_string($methods_post)) {
            $selected_methods[] = $methods_post;
        }
    }

    if (WC()->session) {
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        if (is_array($chosen_shipping_methods)) {
            $selected_methods = array_merge($selected_methods, $chosen_shipping_methods);
        }
    }

    // Keep only box_now_delivery selections and capture instance id if present (format: box_now_delivery:ID)
    $boxnow_instance_ids = array();
    foreach ($selected_methods as $method) {
        if (strpos($method, 'box_now_delivery') === 0) {
            $parts = explode(':', $method);
            $instance_id = isset($parts[1]) ? absint($parts[1]) : null;
            if ($instance_id !== null) {
                $boxnow_instance_ids[] = $instance_id;
            } else {
                // No instance in string; treat as generic boxnow
                $boxnow_instance_ids[] = null;
            }
        }
    }

    if (empty($boxnow_instance_ids)) {
        return null;
    }

    // Helper: find Box Now shipping methods across all zones (including "Locations not covered by your other zones")
    $all_boxnow_methods = array();

    $zones = WC_Shipping_Zones::get_zones();
    foreach ($zones as $zone) {
        if (!empty($zone['shipping_methods'])) {
            foreach ($zone['shipping_methods'] as $method) {
                if ($method->id === 'box_now_delivery') {
                    $all_boxnow_methods[$method->instance_id] = $method;
                }
            }
        }
    }

    // Add "Rest of the world" zone (ID 0)
    $default_zone = new WC_Shipping_Zone(0);
    foreach ($default_zone->get_shipping_methods() as $method) {
        if ($method->id === 'box_now_delivery') {
            $all_boxnow_methods[$method->instance_id] = $method;
        }
    }

    // Iterate over selected instances; return first custom description found and enabled
    foreach ($boxnow_instance_ids as $instance_id) {
        // If instance id is null, fall back to any boxnow method
        if ($instance_id !== null && isset($all_boxnow_methods[$instance_id])) {
            $candidate = $all_boxnow_methods[$instance_id];
        } else {
            $candidate = !empty($all_boxnow_methods) ? reset($all_boxnow_methods) : null;
        }

        if ($candidate) {
            $enable_custom_cod_description = $candidate->get_option('enable_custom_cod_description');
            $custom_cod_description    = $candidate->get_option('custom_cod_description');
            $custom_cod_description_en = $candidate->get_option('custom_cod_description_en');

            if ('yes' === $enable_custom_cod_description) {
                $locale = get_locale();
                $is_bg  = (strpos($locale, 'bg') === 0);
                $desc   = (!$is_bg && !empty($custom_cod_description_en)) ? $custom_cod_description_en : $custom_cod_description;
                if (!empty($desc)) {
                    return $desc;
                }
            }
        }
    }

    return null;
}

function boxnow_change_cod_description($description, $payment_id)
{
    if ('cod' !== $payment_id) {
        return $description;
    }

    $custom = boxnow_get_custom_cod_description();
    if (!empty($custom)) {
        return $custom;
    }

    return $description;
}


// Support for WooCommerce Blocks (payment methods data sent to frontend)
add_filter('woocommerce_blocks_checkout_payment_method_data', 'boxnow_blocks_cod_description', 10, 2);
function boxnow_blocks_cod_description($payment_methods_data, $page_context)
{
    if (!is_array($payment_methods_data)) {
        return $payment_methods_data;
    }

    $custom = boxnow_get_custom_cod_description();
    if (empty($custom)) {
        return $payment_methods_data;
    }

    foreach ($payment_methods_data as &$method) {
        if (isset($method['method_id']) && $method['method_id'] === 'cod') {
            $method['description'] = $custom;
        }
    }

    return $payment_methods_data;
}

/**
 * JS за Block Checkout: слуша промени в доставките и обновява описанието на COD.
 */
function custom_cod_block_checkout_js_update()
{
    if (is_checkout() && wp_script_is('jquery')) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            const codDescriptionSelector = '.wc-block-components-payment-method-description';

            function fetchAndUpdateCodDescription() {
                const chosenShipping = $('input[name^="radio-control-"]:checked').val();
                if (!chosenShipping) {
                    return;
                }
                $.ajax({
                    type: 'POST',
                    url: wc_checkout_params.ajax_url,
                    data: {
                        action: 'woocommerce_update_order_review',
                        security: wc_checkout_params.update_order_review_nonce,
                        shipping_method: chosenShipping
                    },
                    success: function(data) {
                        if (!data || !data.fragments || !data.fragments.refresh_payment_methods) {
                            return;
                        }
                        const $newPaymentMethods = $(data.fragments.refresh_payment_methods);
                        const $newCodDescriptionContainer = $newPaymentMethods.find('#wc-payment-method-cod').closest('.wc-payment-method-cod');
                        const $newCodDescription = $newCodDescriptionContainer.find(codDescriptionSelector).text().trim();

                        const $currentCodLabel = $('label[for="wc-payment-method-cod"]');
                        const $currentCodDescriptionContainer = $currentCodLabel.find(codDescriptionSelector);

                        if ($newCodDescription.length > 0 && $currentCodDescriptionContainer.length) {
                            $currentCodDescriptionContainer.text($newCodDescription);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('AJAX Error on Block Checkout:', textStatus, errorThrown);
                    }
                });
            }

            $(document).on('change', 'input[name^="radio-control-"]', function() {
                setTimeout(fetchAndUpdateCodDescription, 100);
            });

            fetchAndUpdateCodDescription();
        });
        </script>
        <?php
    }
}
add_action('wp_footer', 'custom_cod_block_checkout_js_update');

/**
 * MutationObserver за Block Checkout: следи промени и подменя описанието на COD.
 */
function custom_cod_mutation_observer_js()
{
    if (is_checkout() && wp_script_is('jquery')) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            const codMethodId = 'radio-control-wc-payment-method-options-cod';
            const codDescriptionContainerSelector = `#${codMethodId}_content > div`;

            function updateCodDescription() {
                const chosenShipping = $('input[name^="radio-control-"]:checked').val();
                if (!chosenShipping) {
                    return;
                }
                $.ajax({
                    type: 'POST',
                    url: wc_checkout_params.ajax_url,
                    data: {
                        action: 'woocommerce_update_order_review',
                        security: wc_checkout_params.update_order_review_nonce,
                        shipping_method: chosenShipping
                    },
                    success: function(data) {
                        if (!data || !data.fragments || !data.fragments.refresh_payment_methods) {
                            return;
                        }
                        const $paymentMethodsHtml = $(data.fragments.refresh_payment_methods);
                        const $newCodContainer = $paymentMethodsHtml.find(`#${codMethodId}`).closest('div[class*="wc-block-components-radio-control-accordion-option"]');
                        const $newCodDescriptionElement = $newCodContainer.find('div[id*="_content"] > div');
                        const newDescriptionText = $newCodDescriptionElement.text().trim();

                        const $currentDescriptionElement = $(codDescriptionContainerSelector);
                        if (newDescriptionText.length > 0 && $currentDescriptionElement.length) {
                            $currentDescriptionElement.text(newDescriptionText);
                        }
                    }
                });
            }

            const checkoutArea = document.querySelector('.wc-block-components-checkout-step__content');
            if (checkoutArea && typeof MutationObserver !== 'undefined') {
                const observer = new MutationObserver(function() {
                    clearTimeout(window.codUpdateTimer);
                    window.codUpdateTimer = setTimeout(updateCodDescription, 50);
                });
                observer.observe(checkoutArea, { childList: true, subtree: true });
            }

            updateCodDescription();
        });
        </script>
        <?php
    }
}
add_action('wp_footer', 'custom_cod_mutation_observer_js');


/**
 * Прост филтър: ако е избран box_now_delivery, сменяме описанието на COD с зададения текст.
 * Оставяме го с по-висок приоритет (20), за да надпише предходни филтри при нужда.
 */
add_filter('woocommerce_gateway_description', 'custom_get_boxnow_cod_description_from_option', 20, 2);
function custom_get_boxnow_cod_description_from_option($description, $payment_id)
{
    if ('cod' !== $payment_id) {
        return $description;
    }

    $shipping_method_id = 'box_now_delivery';
    $chosen_shipping_methods = WC()->session ? WC()->session->get('chosen_shipping_methods') : array();

    if (is_array($chosen_shipping_methods)) {
        foreach ($chosen_shipping_methods as $method) {
            if (strpos($method, $shipping_method_id) === 0) {
                $option_key = 'woocommerce_' . $shipping_method_id . '_settings';
                $all_settings = get_option($option_key);

                if (is_array($all_settings) && isset($all_settings['custom_cod_description'])) {
                    $custom_description = $all_settings['custom_cod_description'];
                    if (!empty($custom_description)) {
                        return $custom_description;
                    }
                }
            }
        }
    }

    return $description;
}



/**
 * Determine the "Free delivery" label for BOX NOW when cost is zero/empty.
 *
 * @param WC_Shipping_Rate $method
 * @param string           $fallback_label
 * @return string
 */
function boxnow_get_free_delivery_label_for_method($method, $fallback_label)
{
    if (!is_object($method) || !method_exists($method, 'get_method_id')) {
        return $fallback_label;
    }

    if ($method->get_method_id() !== 'box_now_delivery') {
        return $fallback_label;
    }

    $raw_cost = isset($method->cost) ? $method->cost : '';
    $numeric_cost = (float) $raw_cost;

    // Treat empty string or 0 as free.
    if ($raw_cost === '' || $numeric_cost === 0.0) {
        return $method->get_label() . ': ' . __('Free delivery', 'boxnowbulgaria');
    }

    return $fallback_label;
}

// Classic cart totals label.
add_filter('woocommerce_cart_totals_shipping_method_label', 'boxnow_free_delivery_label', 10, 2);

function boxnow_free_delivery_label($label, $method)
{
    return boxnow_get_free_delivery_label_for_method($method, $label);
}

// Classic checkout + Blocks: full shipping label used in checkout and Store API.
add_filter('woocommerce_cart_shipping_method_full_label', 'boxnow_free_delivery_full_label', 10, 2);

function boxnow_free_delivery_full_label($label, $method)
{
    return boxnow_get_free_delivery_label_for_method($method, $label);
}

// Add the custom shipping method to WooCommerce
add_filter('woocommerce_shipping_methods', 'boxnow_add_box_now_delivery_shipping_method');

/**
 * Add the custom shipping method to WooCommerce.
 *
 * @param array $methods Existing shipping methods.
 * @return array Updated shipping methods.
 */
function boxnow_add_box_now_delivery_shipping_method($methods)
{
    $methods['box_now_delivery'] = 'Box_Now_Delivery_Shipping_Method';
    return $methods;
}
