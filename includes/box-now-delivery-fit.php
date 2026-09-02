<?php
/**
 * BOX NOW — parcel fit helpers.
 *
 * Central place for "does this cart / order physically fit into a BOX NOW
 * locker compartment, and is it under the weight limit?".
 *
 * Both the checkout-side shipping method (hide the method when it cannot be
 * shipped) and the voucher-creation side (refuse to build a label) call in
 * here, so the two can never disagree.
 *
 * Geometry notes
 * --------------
 * The old code stacked heights (h1 + h2 + h3 …) and compared the sum against
 * the compartment height. That blocks plenty of orders that fit perfectly
 * well — two 20 cm cubes do not need 40 cm of height, they sit side by side.
 *
 * What we do instead:
 *
 *   1. Every single item must fit inside the compartment on its own. That test
 *      is orientation aware: all 6 axis-aligned rotations are tried, and on top
 *      of that each item is allowed to be *tilted inside a face* — the exact
 *      "rectangle in a rectangle" test — so a 70 cm long flat item can still go
 *      in diagonally across a 60x45 floor.
 *   2. The summed volume of the whole cart must not exceed the compartment
 *      volume (optionally scaled by a packing-efficiency factor).
 *   3. The summed weight of the whole cart must not exceed the weight limit.
 *
 * All three are *necessary* conditions — if any one fails the parcel provably
 * cannot be shipped. We deliberately do not add heuristic sufficiency checks
 * (like height stacking) because those reject orders that are actually fine.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default largest BOX NOW compartment, in centimetres.
 */
function boxnow_default_box_dims()
{
    return array('length' => 60.0, 'width' => 45.0, 'height' => 36.0);
}

/**
 * The three BOX NOW compartment sizes, smallest first.
 * Keys map to the compartmentSize value expected by the delivery-requests API.
 *
 * @return array<int, array{length: float, width: float, height: float}>
 */
function boxnow_get_compartments()
{
    return array(
        1 => array('length' => 60.0, 'width' => 45.0, 'height' => 8.0),
        2 => array('length' => 60.0, 'width' => 45.0, 'height' => 17.0),
        3 => array('length' => 60.0, 'width' => 45.0, 'height' => 36.0),
    );
}

/**
 * Read the BOX NOW shipping method's size/weight limits.
 *
 * Prefers the settings of a concrete shipping-method instance; when no instance
 * is given (or it has no saved settings) the first BOX NOW instance found in any
 * shipping zone is used, and finally the plugin defaults.
 *
 * @param int|null $instance_id Shipping method instance id, if known.
 * @return array{length: float, width: float, height: float, weight: float}
 */
function boxnow_get_shipping_limits($instance_id = null)
{
    static $cache = array();

    $key = $instance_id === null ? 'default' : (string) $instance_id;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $settings = array();

    if ($instance_id !== null) {
        $stored = get_option('woocommerce_box_now_delivery_' . absint($instance_id) . '_settings', array());
        if (is_array($stored)) {
            $settings = $stored;
        }
    }

    if (empty($settings) && class_exists('WC_Shipping_Zones')) {
        foreach (boxnow_get_shipping_instance_ids() as $id) {
            $stored = get_option('woocommerce_box_now_delivery_' . $id . '_settings', array());
            if (is_array($stored) && !empty($stored)) {
                $settings = $stored;
                break;
            }
        }
    }

    $defaults = boxnow_default_box_dims();

    $limits = array(
        'length' => boxnow_positive_float(isset($settings['max_length']) ? $settings['max_length'] : null, $defaults['length']),
        'width'  => boxnow_positive_float(isset($settings['max_width']) ? $settings['max_width'] : null, $defaults['width']),
        'height' => boxnow_positive_float(isset($settings['max_height']) ? $settings['max_height'] : null, $defaults['height']),
        'weight' => boxnow_positive_float(isset($settings['custom_weight']) ? $settings['custom_weight'] : null, 20.0),
    );

    /**
     * Filter the BOX NOW size/weight limits.
     *
     * @param array    $limits      length/width/height in cm, weight in kg.
     * @param int|null $instance_id Shipping method instance id, if known.
     */
    $limits = apply_filters('boxnow_shipping_limits', $limits, $instance_id);

    $cache[$key] = $limits;

    return $limits;
}

/**
 * Collect the instance ids of every BOX NOW shipping method across all zones,
 * including the "rest of the world" zone 0.
 *
 * @return int[]
 */
function boxnow_get_shipping_instance_ids()
{
    if (!class_exists('WC_Shipping_Zones')) {
        return array();
    }

    $ids   = array();
    $zones = WC_Shipping_Zones::get_zones();

    $zone_objects = array();
    foreach (array_keys($zones) as $zone_id) {
        $zone_objects[] = WC_Shipping_Zones::get_zone($zone_id);
    }
    $zone_objects[] = WC_Shipping_Zones::get_zone(0); // Locations not covered by other zones.

    foreach ($zone_objects as $zone) {
        if (!$zone) {
            continue;
        }
        foreach ($zone->get_shipping_methods(false) as $method) {
            if (isset($method->id) && $method->id === 'box_now_delivery') {
                $ids[] = absint($method->instance_id);
            }
        }
    }

    return array_values(array_unique(array_filter($ids)));
}

/**
 * Cast to a positive float, falling back when the value is missing/blank/<= 0.
 *
 * @param mixed $value
 * @param float $fallback
 * @return float
 */
function boxnow_positive_float($value, $fallback)
{
    if (is_numeric($value) && (float) $value > 0) {
        return (float) $value;
    }
    return (float) $fallback;
}

/**
 * Can a p x q rectangle be placed inside an L x W rectangle, allowing rotation
 * by an arbitrary angle?
 *
 * Axis-aligned placement is the easy case. When the rectangle is too long to
 * lie straight it may still fit corner-to-corner; the closed-form condition for
 * that tilted placement is the standard result
 *
 *     (2*p*q*L + (p^2 - q^2) * sqrt(p^2 + q^2 - L^2)) / (p^2 + q^2)  <=  W
 *
 * which is what lets a 70 cm item go in diagonally across a 60x45 floor.
 *
 * @param float $p Rectangle side.
 * @param float $q Rectangle side.
 * @param float $l Container side.
 * @param float $w Container side.
 * @return bool
 */
function boxnow_rectangle_fits($p, $q, $l, $w)
{
    // Normalise so p >= q and l >= w.
    if ($p < $q) {
        list($p, $q) = array($q, $p);
    }
    if ($l < $w) {
        list($l, $w) = array($w, $l);
    }

    if ($p <= 0 || $q <= 0) {
        return true; // Nothing to place.
    }
    if ($l <= 0 || $w <= 0) {
        return false;
    }

    // Straight in.
    if ($p <= $l && $q <= $w) {
        return true;
    }

    // Too wide even when tilted, or longer than the container diagonal.
    if ($q > $w || $p > sqrt($l * $l + $w * $w)) {
        return false;
    }

    $radicand = ($p * $p) + ($q * $q) - ($l * $l);
    if ($radicand < 0) {
        // p <= l would have been caught above; guard against float noise.
        return false;
    }

    $needed = ((2 * $p * $q * $l) + (($p * $p) - ($q * $q)) * sqrt($radicand)) / (($p * $p) + ($q * $q));

    return $needed <= $w + 0.0000001;
}

/**
 * Does a single item fit inside the compartment?
 *
 * Tries every pairing of "which item dimension goes along which box axis"
 * (covering all 6 axis-aligned rotations) and, for each, allows the remaining
 * two item dimensions to be tilted within that face.
 *
 * Items with no dimensions recorded are treated as fitting — the plugin has no
 * data to reject them with, and silently hiding the shipping method for every
 * product without dimensions would be worse than letting the API decide.
 *
 * @param array $item Item with length/width/height in cm.
 * @param array $box  Box with length/width/height in cm.
 * @return bool
 */
function boxnow_item_fits_in_box($item, $box)
{
    $dims = array(
        (float) $item['length'],
        (float) $item['width'],
        (float) $item['height'],
    );

    // No usable dimensions on this product — nothing to validate against.
    if (max($dims) <= 0) {
        return true;
    }

    $box_dims = array(
        (float) $box['length'],
        (float) $box['width'],
        (float) $box['height'],
    );

    if (min($box_dims) <= 0) {
        return false;
    }

    // Pick which box axis carries the item's "thickness", and which item
    // dimension that is. The other two of each form the face to fit into.
    for ($bi = 0; $bi < 3; $bi++) {
        $box_thickness = $box_dims[$bi];
        $box_face      = array();
        foreach ($box_dims as $i => $d) {
            if ($i !== $bi) {
                $box_face[] = $d;
            }
        }

        for ($ii = 0; $ii < 3; $ii++) {
            if ($dims[$ii] > $box_thickness) {
                continue;
            }
            $item_face = array();
            foreach ($dims as $i => $d) {
                if ($i !== $ii) {
                    $item_face[] = $d;
                }
            }

            if (boxnow_rectangle_fits($item_face[0], $item_face[1], $box_face[0], $box_face[1])) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Smallest compartment (1 = small, 2 = medium, 3 = large) that the whole parcel
 * fits into, or false when even the large compartment is too small.
 *
 * @param array $items Normalised items (see boxnow_normalize_product()).
 * @return int|false
 */
function boxnow_get_compartment_for_items($items)
{
    foreach (boxnow_get_compartments() as $size => $box) {
        $result = boxnow_check_items_fit($items, $box);
        if ($result['fits']) {
            return $size;
        }
    }
    return false;
}

/**
 * Check a set of items against a compartment.
 *
 * @param array      $items        Normalised items (see boxnow_normalize_product()).
 * @param array      $box          length/width/height in cm.
 * @param float|null $weight_limit Max total weight in kg, or null to skip the weight check.
 * @return array{fits: bool, reason: string, total_volume: float, box_volume: float, total_weight: float, oversized_item: string}
 */
function boxnow_check_items_fit($items, $box, $weight_limit = null)
{
    $box_volume = (float) $box['length'] * (float) $box['width'] * (float) $box['height'];

    /**
     * Filter the usable fraction of the compartment volume.
     *
     * 1.0 keeps the check to a strictly necessary condition (a parcel whose raw
     * volume exceeds the compartment can never fit). Lower it (e.g. 0.85) to
     * leave room for packaging and imperfect packing.
     *
     * @param float $efficiency
     * @param array $box
     */
    $efficiency = (float) apply_filters('boxnow_packing_efficiency', 1.0, $box);
    if ($efficiency <= 0 || $efficiency > 1) {
        $efficiency = 1.0;
    }

    $result = array(
        'fits'           => true,
        'reason'         => '',
        'total_volume'   => 0.0,
        'box_volume'     => $box_volume,
        'total_weight'   => 0.0,
        'oversized_item' => '',
    );

    foreach ($items as $item) {
        $quantity = max(1, (int) $item['quantity']);

        $result['total_volume'] += (float) $item['length'] * (float) $item['width'] * (float) $item['height'] * $quantity;
        $result['total_weight'] += (float) $item['weight'] * $quantity;

        // A single item that cannot be placed in the compartment in any
        // orientation, tilted or not, is a hard stop.
        if ($result['fits'] && !boxnow_item_fits_in_box($item, $box)) {
            $result['fits']           = false;
            $result['reason']         = 'item_too_large';
            $result['oversized_item'] = isset($item['name']) ? $item['name'] : '';
        }
    }

    if ($result['fits'] && $box_volume > 0 && $result['total_volume'] > ($box_volume * $efficiency) + 0.0000001) {
        $result['fits']   = false;
        $result['reason'] = 'volume_exceeded';
    }

    // Total weight across the whole cart/order, not per item.
    if ($result['fits'] && $weight_limit !== null && $weight_limit > 0 && $result['total_weight'] > (float) $weight_limit + 0.0000001) {
        $result['fits']   = false;
        $result['reason'] = 'weight_exceeded';
    }

    return $result;
}

/**
 * Normalise one WC_Product + quantity into the shape the fit checks expect,
 * converting the store's configured units into cm / kg.
 *
 * @param WC_Product $product
 * @param int        $quantity
 * @return array|null Null for products that are not shipped at all.
 */
function boxnow_normalize_product($product, $quantity)
{
    if (!$product || !is_a($product, 'WC_Product')) {
        return null;
    }

    // Downloads / virtual products never occupy a compartment.
    if (method_exists($product, 'is_virtual') && $product->is_virtual()) {
        return null;
    }

    return array(
        'name'     => $product->get_name(),
        'length'   => boxnow_to_cm($product->get_length()),
        'width'    => boxnow_to_cm($product->get_width()),
        'height'   => boxnow_to_cm($product->get_height()),
        'weight'   => boxnow_to_kg($product->get_weight()),
        'quantity' => max(1, (int) $quantity),
    );
}

/**
 * Convert a product dimension from the store's unit into centimetres.
 *
 * @param mixed $value
 * @return float
 */
function boxnow_to_cm($value)
{
    if (!is_numeric($value)) {
        return 0.0;
    }
    if (function_exists('wc_get_dimension')) {
        return (float) wc_get_dimension((float) $value, 'cm');
    }
    return (float) $value;
}

/**
 * Convert a product weight from the store's unit into kilograms.
 *
 * @param mixed $value
 * @return float
 */
function boxnow_to_kg($value)
{
    if (!is_numeric($value)) {
        return 0.0;
    }
    if (function_exists('wc_get_weight')) {
        return (float) wc_get_weight((float) $value, 'kg');
    }
    return (float) $value;
}

/**
 * Normalised items for the current cart.
 *
 * @param array|null $package Shipping package, when called from calculate_shipping().
 * @return array
 */
function boxnow_get_cart_items($package = null)
{
    $items    = array();
    $contents = array();

    if (is_array($package) && !empty($package['contents']) && is_array($package['contents'])) {
        $contents = $package['contents'];
    } elseif (function_exists('WC') && WC()->cart) {
        $contents = WC()->cart->get_cart_contents();
    }

    foreach ($contents as $cart_item) {
        if (empty($cart_item['data'])) {
            continue;
        }
        $normalized = boxnow_normalize_product($cart_item['data'], isset($cart_item['quantity']) ? $cart_item['quantity'] : 1);
        if ($normalized !== null) {
            $items[] = $normalized;
        }
    }

    return $items;
}

/**
 * Normalised items for an order.
 *
 * @param WC_Order $order
 * @return array
 */
function boxnow_get_order_items($order)
{
    $items = array();

    if (!$order) {
        return $items;
    }

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) {
            // Product was deleted after the order was placed — we cannot measure
            // it, so skip it rather than fatal on a null object.
            continue;
        }
        $normalized = boxnow_normalize_product($product, $item->get_quantity());
        if ($normalized !== null) {
            $items[] = $normalized;
        }
    }

    return $items;
}

/**
 * Total shipped weight of an order, in kg.
 *
 * @param WC_Order $order
 * @return float
 */
function boxnow_get_order_weight($order)
{
    $weight = 0.0;
    foreach (boxnow_get_order_items($order) as $item) {
        $weight += $item['weight'] * $item['quantity'];
    }
    return $weight;
}

/**
 * The BOX NOW shipping method instance used by an order, if any.
 *
 * @param WC_Order $order
 * @return int|null
 */
function boxnow_get_order_instance_id($order)
{
    if (!$order) {
        return null;
    }
    foreach ($order->get_shipping_methods() as $method) {
        if ($method->get_method_id() === 'box_now_delivery') {
            $instance_id = $method->get_instance_id();
            return $instance_id ? absint($instance_id) : null;
        }
    }
    return null;
}
