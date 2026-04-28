# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**BOX NOW Delivery** (v3.0.0) — A WordPress/WooCommerce plugin that integrates parcel delivery to BOX NOW lockers in Bulgaria. No build system, no Composer, no npm — pure PHP + vanilla JS.

## Syntax Checking

```bash
# Check PHP syntax for any file
php -l box-now-delivery.php
php -l includes/box-now-delivery-shipping-method.php

# The check-syntax.php helper requires WordPress to be loaded at ../../../
# (only works when the plugin is installed in a live WP environment)
```

## Architecture

### Entry Point
`box-now-delivery.php` — registers hooks, loads text domain, enqueues scripts/styles, and `require_once`s all includes.

### Includes (`includes/`)

| File | Purpose |
|------|---------|
| `box-now-delivery-shipping-method.php` | `Box_Now_Delivery_Shipping_Method` class extending `WC_Shipping_Method`. Handles shipping cost, free delivery threshold, taxability. Registered via `woocommerce_shipping_init`. |
| `box-now-delivery-admin-page.php` | Admin menu page (`BOX NOW Delivery`). Multi-step settings wizard UI. Includes `box-now-delivery-validation.php`. |
| `box-now-delivery-validation.php` | `BNDP_Serializer` class. Handles POST form save via `admin_post_boxnow-settings-save`. Sanitizes and persists all plugin options. |
| `box-now-delivery-cancel-order.php` | Registers custom `wc-boxnow-canceled` order status. Adds cancel button on completed orders. Fires API cancellation call on status transition. |
| `box-now-delivery-order-column.php` | Adds BOX NOW indicator column and Voucher/parcel-ID column to orders list. Supports both legacy CPT and HPOS. |
| `box-now-delivery-print-order.php` | Fetches and streams voucher label PDF from BOX NOW API (`/api/v1/parcels/{id}/label.pdf`). |
| `box-now-delivery-bulk-vouchers.php` | Adds "Generate BOX NOW bulk vouchers" bulk action to orders list. Works for both CPT and HPOS. |

### JavaScript (`js/`)

| File | Purpose |
|------|---------|
| `box-now-delivery.js` | Frontend checkout logic. Handles both classic and block checkout (`boxNowDeliverySettings.checkoutType`). Manages locker selection popup or embedded iframe map. Stores selected locker in `localStorage` (`box_now_selected_locker`). Validates locker selection before order submission. |
| `box-now-delivery-admin-page.js` | Admin UI — multi-step settings wizard, hex color picker. |
| `box-now-create-voucher.js` | Utility: finds nearest lockers within 1 km using Haversine formula. Reads locker data from localStorage. |

### API Integration

BOX NOW REST API uses OAuth2 client_credentials flow:
- Production: `api-production.boxnow.bg`
- Stage: `api-stage.boxnow.bg`
- Auth endpoint: `POST /api/v1/auth-sessions`
- Parcel endpoints: `POST /api/v1/parcels`, `GET /api/v1/parcels/{id}/label.pdf`

Environment is determined by `boxnow_environment` option (`production` or `stage`). The API URL is derived from environment — never stored as arbitrary user input.

### Key WordPress Options

`boxnow_environment`, `boxnow_api_url`, `boxnow_client_id`, `boxnow_client_secret`, `boxnow_warehouse_id`, `boxnow_partner_id`, `boxnow_button_color`, `boxnow_button_text`, `box_now_display_mode` (popup/embedded), `boxnow_gps_tracking`, `boxnow_checkout_type` (classic/block), `boxnow_locker_not_selected_message`

### Translations

Text domain: `boxnowbulgaria`. Translation files live in `languages/` as PHP arrays returning `['original' => 'translation']` — no `.mo` files required. A built-in fallback via `gettext` filter is registered in `box-now-delivery.php`. To add a locale, create `languages/boxnowbulgaria-{locale}.php` returning the map array.

### HPOS Compatibility

All order list hooks are registered twice — once for the legacy CPT screen (`edit-shop_order`, `shop_order_posts_custom_column`) and once for HPOS (`woocommerce_page_wc-orders`, `woocommerce_page_wc-orders_custom_column`).
