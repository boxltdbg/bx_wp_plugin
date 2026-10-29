=== BOX NOW Bulgaria ===
Contributors: boxnowbulgaria, drago ivanov, radoslav radoslavov, denis iliev
Tags: delivery, boxnow
Requires at least: 6.2
Tested up to: 6.6
Stable tag: 3.0.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

BOX NOW the future of parcel delivery.

== Description ==

BOX NOW Delivery is used for connecting e-shops with parcel delivery services from BOX NOW.

== Changelog ==

= 3.0.0 =
* New: Bulk cancellation of BOX NOW vouchers from the Orders list (Bulk actions → "Cancel BOX NOW vouchers"), with a confirmation prompt and a success/error result popup. Complements the existing single-order cancellation.
* Security (critical): The voucher label PDF endpoint (print_box_now_voucher) is no longer accessible to logged-out users and now requires the "edit_shop_orders" capability plus a valid nonce — closes a customer-PII / GDPR exposure.
* Security: Removed nopriv access from the voucher cancel endpoint and added capability checks to the cancel, create-vouchers and update-order-locker AJAX handlers; added a CSRF nonce to the checkout locker-session endpoint.
* Compatibility: Declared WooCommerce High-Performance Order Storage (HPOS) compatibility; replaced post-meta reads/writes with order CRUD so voucher/warehouse meta works under HPOS.
* PHP 8.2: Declared shipping-method class properties to remove "dynamic property" deprecations; removed a dead cost-option read.
* Bulk vouchers: The combined label PDF is now fetched already-merged from the BOX NOW API (/api/v1/labels:search) in a single request, instead of downloading each label and merging locally with Ghostscript — no server dependency and always a valid PDF.
* Bulk vouchers: The combined PDF is served through an authenticated admin-ajax download endpoint (capability + nonce) instead of a public uploads URL — fixes the 403 some servers return on direct uploads access and stops the PII file from being publicly reachable.
* Checkout map: Geolocation (GPS) is the primary localization; the postal code is no longer injected into the widget. Debug logging is now gated behind WP_DEBUG.
* Admin UX: New multi-step settings wizard, compact layout, color picker with HEX input, auto-dismiss success notification, custom message field when no locker is selected.
* Options: Trim whitespace from Client ID/Secret on save, voucher field always enabled, default message "Please select a locker to continue!"
* Shipping: Single price for 0-20 kg (removed other weight brackets).
* Orders: BOX NOW indicator displayed directly in order number (no separate column), forced display for BOX NOW shipping methods.
* Branding: Logo/badge in settings header; unified "Back"/"Save Changes" buttons.
* Added block-based checkout as an option in admin panel settings.
* Added functionality to edit locker address dynamically via the admin panel.
* Improved UX by showing the real address in the admin panel inside the BOX NOW delivery column info.
* Added internationalization support with translation files for Bulgarian (BG) and English (EN).

= 2.1.3 =
* Fixed iframe embedded map
* Resolved problem with losing apm id on payment method change.

= 2.0.1 =
* Added BG languadge

= 2.0 =
* Added widget v5 integration.
* Changed cURL functions.
* WooCommerce HPOS Ready.
* Added coupons calculations functionality.

== Upgrade Notice ==

= 2.1.5 =
Major update with bulk voucher generation functionality. New features include combined PDF download for multiple orders, visual indicators for BOX NOW deliveries, and improved bulk operations workflow. Please upgrade to take advantage of these new features.

= 2.0 =
Please upgrade to our latest plugin version to avoid coflicts and errors of any older versions.
