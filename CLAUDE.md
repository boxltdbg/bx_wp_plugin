# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **BOX NOW Delivery** WordPress/WooCommerce plugin (v3.0.0) that integrates BOX NOW parcel locker delivery into WooCommerce checkout. It supports multiple countries (Bulgaria, Greece, Croatia, Cyprus).

**Active codebase:** `bx_wp_plugin_v3.0.0/` — this is the production target (the only folder you may modify). `bx_wp_plugin_v3.0.0_global/` is a read-only reference.

## No Build System

This is a plain PHP WordPress plugin — there is no npm, Composer, Webpack, or Makefile. Files are loaded directly by WordPress at runtime. Deployment means copying the plugin folder to `/wp-content/plugins/`.

## PHP Syntax Check

```bash
php bx_wp_plugin_v3.0.0_global/check-syntax.php
```

This validates PHP syntax across plugin files. There are no automated tests or linters configured.

## Architecture

**Main entry point:** `bx_wp_plugin_v3.0.0_global/box-now-delivery.php` (2,764 lines)
- Defines plugin metadata, loads i18n, conditionally loads all modules based on WooCommerce being active
- Registers all WordPress hooks and actions

**Includes (core modules):**
- `box-now-delivery-shipping-method.php` — `Box_Now_Delivery_Shipping_Method` class extending `WC_Shipping_Method`; handles weight-based pricing, checkout field injection, order meta saving
- `box-now-delivery-admin-page.php` — multi-step settings wizard UI (rendered in WordPress admin)
- `box-now-delivery-api-helpers.php` — BOX NOW API calls (token fetch, voucher creation, locker lookup); multi-country endpoint logic
- `box-now-delivery-bulk-vouchers.php` — bulk voucher generation for WooCommerce order list bulk actions
- `box-now-delivery-order-column.php` — adds BOX NOW indicator column to WooCommerce orders list
- `box-now-delivery-validation.php` — `BNDP_Serializer` class for checkout form validation
- `box-now-delivery-cancel-order.php` — handles order cancellation via BOX NOW API
- `box-now-delivery-print-order.php` — PDF shipping label generation

**Frontend JS (`js/`):**
- `box-now-delivery.js` (1,279 lines) — jQuery-based checkout interaction; handles both Classic and Block checkout modes, popup vs. embedded map display, locker selection persistence via `localStorage`, Haversine distance calculation for nearby lockers
- `box-now-create-voucher.js` (571 lines) — admin-side voucher creation UI
- `box-now-delivery-admin-page.js` (38 lines) — admin settings wizard UI helpers

## Key Configuration (WordPress Options)

All settings are stored in the WordPress options table:

| Option Key | Description |
|---|---|
| `boxnow_environment` | `production` or `stage` |
| `boxnow_api_url` | Auto-set from environment |
| `boxnow_client_id` / `boxnow_client_secret` | API credentials |
| `boxnow_warehouse_id` / `boxnow_partner_id` | Partner identifiers |
| `boxnow_button_color` | HEX color (default `#84C33F`) |
| `boxnow_checkout_type` | `classic` or `block` |
| `box_now_display_mode` | `popup` or `embedded` |
| `boxnow_allowed_countries` | Comma-separated codes: `bg,gr,hr,cy` |

## i18n

- Text domain: `boxnowbulgaria`
- Domain path: `/languages`
- Languages: `boxnowbulgaria-en_US.php`, `boxnowbulgaria-bg_BG.php`
- Uses a custom `gettext` filter to load translations immediately on plugin load (bypassing the standard WP late-loading behavior)

## WordPress/WooCommerce Integration Points

- `woocommerce_shipping_init` — registers the custom shipping method
- `plugins_loaded` — loads textdomain and translations
- `bulk_actions-edit-shop_order` / `bulk_actions-woocommerce_page_wc-orders` — bulk voucher actions (both HPOS and legacy order tables supported)
- `woocommerce_thankyou` — displays delivery details after order completion
- `admin_menu` / `admin_init` — registers settings page and options

## Requirements

- WordPress 6.2+
- WooCommerce (any recent version; tested up to 6.3)
- PHP 7.0+
