<?php
/**
 * BOX NOW API Helper Functions
 * Multi-country support: allowed countries, region mapping, location API URLs.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get allowed country codes from the admin setting.
 *
 * @return array Lowercase ISO2 country codes, e.g. ['bg','gr']. At least one code is always returned.
 */
function boxnow_get_allowed_countries()
{
    $raw = get_option('boxnow_allowed_countries', 'bg');
    $raw = trim((string) $raw);
    if (empty($raw)) {
        return array('bg');
    }
    $codes = array_values(array_unique(array_filter(array_map('trim', array_map('strtolower', explode(',', $raw))))));
    return empty($codes) ? array('bg') : $codes;
}

/**
 * Returns true when more than just Bulgaria is configured,
 * which triggers countryCode + language=en on the widget URL.
 *
 * @return bool
 */
function boxnow_is_multi_country()
{
    $countries = boxnow_get_allowed_countries();
    return count($countries) > 1 || (count($countries) === 1 && $countries[0] !== 'bg');
}

/**
 * Map ISO2 country code to BOX NOW API region string.
 *
 * @param string $country_code Lowercase ISO2 code (bg, gr, hr, cy).
 * @return string Region code, e.g. bg-BG.
 */
function boxnow_country_to_region($country_code)
{
    $map = array(
        'bg' => 'bg-BG',
        'gr' => 'el-GR',
        'el' => 'el-GR',
        'hr' => 'hr-HR',
        'cy' => 'cy-CY',
    );
    $code = strtolower(trim((string) $country_code));
    return isset($map[$code]) ? $map[$code] : $code . '-' . strtoupper($code);
}

/**
 * Get Location API base URL (respects production/stage environment setting).
 *
 * @return string Base URL without trailing slash.
 */
function boxnow_get_location_api_base_url()
{
    $env = get_option('boxnow_environment', 'production');
    return $env === 'stage'
        ? 'https://locationapi-stage.boxnow.bg'
        : 'https://locationapi-production.boxnow.bg';
}

/**
 * Build Location API JSON URLs for all partner shipping regions.
 * Format: /v1/apms_{region}.json (e.g. apms_bg-BG.json).
 *
 * @return array Full URLs for each allowed region.
 */
function boxnow_get_location_api_urls()
{
    $base      = boxnow_get_location_api_base_url();
    $countries = boxnow_get_allowed_countries();
    $urls      = array();
    foreach ($countries as $cc) {
        $region = boxnow_country_to_region($cc);
        if ($region !== '') {
            $urls[] = $base . '/v1/apms_' . $region . '.json';
        }
    }
    return $urls;
}

/**
 * Fetch raw locker data for a given locker ID from all allowed region APIs.
 * Iterates each region URL in order and returns on first match.
 *
 * @param string $locker_id Locker ID to search for.
 * @return array|false Raw locker array from the API, or false if not found.
 */
function boxnow_fetch_locker_by_id($locker_id)
{
    if (empty($locker_id)) {
        return false;
    }

    $countries = boxnow_get_allowed_countries();
    $base      = boxnow_get_location_api_base_url();

    foreach ($countries as $cc) {
        $region = boxnow_country_to_region($cc);
        if ($region === '') {
            continue;
        }
        $url      = $base . '/v1/apms_' . $region . '.json';
        $response = wp_remote_get($url, array('timeout' => 30));
        if (is_wp_error($response)) {
            continue;
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (empty($data['data']) || !is_array($data['data'])) {
            continue;
        }
        foreach ($data['data'] as $locker) {
            if (!empty($locker['id']) && (string) $locker['id'] === (string) $locker_id) {
                // Inject the country code so callers don't need to re-derive it.
                $locker['_locker_country'] = $cc;
                return $locker;
            }
        }
    }
    return false;
}

/**
 * Fetch all lockers from all allowed region APIs, deduplicating by ID.
 *
 * @return array Merged data: array('data' => [...lockers]).
 */
function boxnow_fetch_all_lockers_data()
{
    $urls       = boxnow_get_location_api_urls();
    $all        = array();
    $seen_ids   = array();

    foreach ($urls as $url) {
        $response = wp_remote_get($url . '?size=medium', array('timeout' => 30));
        if (is_wp_error($response)) {
            continue;
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (empty($data['data']) || !is_array($data['data'])) {
            continue;
        }
        foreach ($data['data'] as $locker) {
            $id = isset($locker['id']) ? (string) $locker['id'] : '';
            if ($id !== '' && !isset($seen_ids[$id])) {
                $seen_ids[$id] = true;
                $all[] = $locker;
            }
        }
    }
    return array('data' => $all);
}

/**
 * Get the BOX NOW widget base URL (no trailing slash).
 *
 * @return string
 */
function boxnow_get_widget_base_url()
{
    // BOX NOW global map widget host, per https://map-docs.boxnow.bg/configuration .
    // This single host serves EVERY country — the target country and UI language
    // are selected via the countryCode + language query params (do NOT use a
    // country-specific host such as widget-v5.boxnow.gr, which forces the Greek
    // map regardless of countryCode). It serves /iframe.html (embedded) and
    // /popup.html (popup) and posts the locker selection back on every pick.
    return 'https://map.boxnow.gr';
}

/**
 * Normalize a phone number for the given ISO2 country code.
 * Leaves numbers that already carry an international prefix alone (converting
 * a leading 00 into +), otherwise strips the national trunk 0 and prepends the
 * country calling code.
 *
 * @param string $phone       Raw phone number.
 * @param string $country_iso2 Lowercase ISO2 country code (bg, gr, cy, hr).
 * @return string Normalized phone with international prefix.
 */
function boxnow_normalize_phone($phone, $country_iso2)
{
    $calling_codes = array(
        'bg' => '+359',
        'gr' => '+30',
        'cy' => '+357',
        'hr' => '+385',
    );

    // Drop spaces, dashes, brackets and dots so prefix detection is reliable.
    $phone = preg_replace('/[\s\-().]/', '', trim((string) $phone));

    if ($phone === '') {
        return '';
    }

    // Already has an international prefix — leave as-is. This MUST be checked
    // before the leading-zero strip, otherwise "00359..." loses one zero and is
    // then treated as a local number and prefixed again ("+3590359...").
    if (substr($phone, 0, 1) === '+') {
        return $phone;
    }
    if (substr($phone, 0, 2) === '00') {
        return '+' . substr($phone, 2);
    }

    // Strip the national trunk prefix.
    if (substr($phone, 0, 1) === '0') {
        $phone = substr($phone, 1);
    }

    $code = strtolower(trim((string) $country_iso2));
    $prefix = isset($calling_codes[$code]) ? $calling_codes[$code] : '+359';

    return $prefix . $phone;
}
