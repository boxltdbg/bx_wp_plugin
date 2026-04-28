<?php
/**
 * English translations for BOX NOW Delivery plugin.
 * Locale: en_US
 * Text Domain: boxnowbulgaria
 *
 * English is the default language - these strings match the code.
 * This file exists for consistency and easy customization if needed.
 */

return [
  // ============================================
  // CHECKOUT & FRONTEND STRINGS
  // ============================================
  'Select BOX NOW Locker' => 'Select BOX NOW Locker',
  'Please select a locker to continue!' => 'Please select a locker to continue!',
  'BOX NOW Locker' => 'BOX NOW Locker',
  'Delivery Details' => 'Delivery Details',
  'Address' => 'Address',
  'Address not available' => 'Address not available',
  'Locker ID' => 'Locker ID',
  'Locker ID:' => 'Locker ID:',
  'Locker ID: ' => 'Locker ID: ',
  'Locker Name:' => 'Locker Name:',
  'Locker Address:' => 'Locker Address:',
  'Locker Address: ' => 'Locker Address: ',
  'Name:' => 'Name:',
  'Address:' => 'Address:',
  'Description' => 'Description',
  'Description:' => 'Description:',
  'Note' => 'Note',
  'Note:' => 'Note:',
  'Selected locker' => 'Selected locker',
  'Delivery to selected locker' => 'Delivery to selected locker',
  'Delivery to BOX NOW locker' => 'Delivery to BOX NOW locker',
  'Details will be loaded when the order is processed.' => 'Details will be loaded when the order is processed.',
  'Details will be available upon delivery.' => 'Details will be available upon delivery.',
  'Close view' => 'Close view',
  'BOX NOW LOCKER' => 'BOX NOW LOCKER',
  'Your order will be delivered to your selected BOX NOW locker.' => 'Your order will be delivered to your selected BOX NOW locker.',
  'BOX NOW Locker ID' => 'BOX NOW Locker ID',

  // ============================================
  // ADMIN ORDER PAGE
  // ============================================
  'BOX NOW Delivery' => 'BOX NOW Delivery',
  'Edit' => 'Edit',
  'Change Address' => 'Change Address',
  'Warehouse ID: ' => 'Warehouse ID: ',

  // ============================================
  // PAYMENT METHODS
  // ============================================
  'Cash on Delivery (COD)' => 'Cash on Delivery (COD)',

  // ============================================
  // VOUCHER GENERATION
  // ============================================
  'Generate BOX NOW Voucher' => 'Generate BOX NOW Voucher',
  'Voucher(s) for this order (Maximum possible vouchers:' => 'Voucher(s) for this order (Maximum possible vouchers:',
  'Enter number of vouchers' => 'Enter number of vouchers',
  'Create Voucher (Small)' => 'Create Voucher (Small)',
  'Create Voucher (Medium)' => 'Create Voucher (Medium)',
  'Create Voucher (Large)' => 'Create Voucher (Large)',
  'Cancel generated voucher(s)' => 'Cancel generated voucher(s)',
  'Error: Failed to create voucher.' => 'Error: Failed to create voucher.',

  // ============================================
  // ERROR MESSAGES
  // ============================================
  'Invalid product dimensions - please ensure the product(s) fit in a BOX NOW locker!' => 'Invalid product dimensions - please ensure the product(s) fit in a BOX NOW locker!',
  'Invalid order number' => 'Invalid order number',
  'Error generating voucher' => 'Error generating voucher',
  'empty or invalid API response' => 'empty or invalid API response',
  'Problem with the selected locker. Please select another locker or check availability.' => 'Problem with the selected locker. Please select another locker or check availability.',
  'Authentication error with BOX NOW API. Please check plugin settings.' => 'Authentication error with BOX NOW API. Please check plugin settings.',
  'The selected locker (ID: %s) is not available in the BOX NOW network. Please select another locker.' => 'The selected locker (ID: %s) is not available in the BOX NOW network. Please select another locker.',
  'The selected locker (ID: %s) is not currently available in BoxNow\'s network. Please choose another location.' => 'The selected locker (ID: %s) is not currently available in BoxNow\'s network. Please choose another location.',
  'Empty response from BOX NOW API' => 'Empty response from BOX NOW API',
  'Error processing API response' => 'Error processing API response',
  'Internal error generating voucher' => 'Internal error generating voucher',
  'Missing order or locker information.' => 'Missing order or locker information.',
  'Order not found.' => 'Order not found.',
  'Locker updated successfully.' => 'Locker updated successfully.',
  'Please enter a valid email address!' => 'Please enter a valid email address!',
  'No BOX NOW locker ID was found in this order. Cannot proceed.' => 'No BOX NOW locker ID was found in this order. Cannot proceed.',
  'Missing Locker ID' => 'Missing Locker ID',
  'Invalid BoxNow Locker' => 'Invalid BoxNow Locker',

  // ============================================
  // ADMIN SETTINGS PAGE - STEP 1
  // ============================================
  'Settings saved successfully.' => 'Settings saved successfully.',
  'Thank you for choosing BOX NOW as your trusted delivery partner! For more information visit %1$s or email us at %2$s.' => 'Thank you for choosing BOX NOW as your trusted delivery partner! For more information visit %1$s or email us at %2$s.',
  'Step 1' => 'Step 1',
  'Plugin Configuration' => 'Plugin Configuration',
  'Environment and API' => 'Environment and API',
  'Production (default)' => 'Production (default)',
  'Stage' => 'Stage',
  'Your API URL' => 'Your API URL',
  'Warehouse ID(s)' => 'Warehouse ID(s)',
  'If you have more than 1 warehouse, separate their IDs with a comma.' => 'If you have more than 1 warehouse, separate their IDs with a comma.',
  'Access Credentials' => 'Access Credentials',
  'Your Client ID' => 'Your Client ID',
  'Your Client Secret' => 'Your Client Secret',
  'Your Partner ID' => 'Your Partner ID',
  'Next' => 'Next',

  // ============================================
  // ADMIN SETTINGS PAGE - STEP 2
  // ============================================
  'Step 2' => 'Step 2',
  'Button, Map and Messages Settings' => 'Button, Map and Messages Settings',
  'Checkout Type' => 'Checkout Type',
  'Classic Checkout' => 'Classic Checkout',
  'Block Checkout' => 'Block Checkout',
  'Map Display' => 'Map Display',
  'Modal Pop-up' => 'Modal Pop-up',
  'iFrame Type' => 'iFrame Type',
  'GPS Location' => 'GPS Location',
  'Enabled' => 'Enabled',
  'Disabled' => 'Disabled',
  'Change Button Color' => 'Change Button Color',
  'Change Button Text' => 'Change Button Text',
  'Message when no locker is selected' => 'Message when no locker is selected',
  'Enter desired message' => 'Enter desired message',
  'Allow multiple labels per order' => 'Allow multiple labels per order',
  'Enable (prepaid orders only)' => 'Enable (prepaid orders only)',
  'When enabled, merchants can generate multiple shipping labels from a single order. This applies to prepaid orders only. COD orders always generate a single label to prevent duplicate payment requests.' => 'When enabled, merchants can generate multiple shipping labels from a single order. This applies to prepaid orders only. COD orders always generate a single label to prevent duplicate payment requests.',
  'COD order: always 1 label to prevent duplicate payment requests.' => 'COD order: always 1 label to prevent duplicate payment requests.',

  // ============================================
  // ADMIN SETTINGS PAGE - STEP 3
  // ============================================
  'Step 3' => 'Step 3',
  'Sender Settings' => 'Sender Settings',
  'Sender Name' => 'Sender Name',
  'Sender Email' => 'Sender Email',
  'Sender Phone' => 'Sender Phone',
  'Back' => 'Back',

  // ============================================
  // SHIPPING METHOD SETTINGS
  // ============================================
  'BOX NOW Bulgaria' => 'BOX NOW Bulgaria',
  'Settings for BOX NOW Bulgaria' => 'Settings for BOX NOW Bulgaria',
  'Enable / Disable' => 'Enable / Disable',
  'Enable or disable BOX NOW delivery method' => 'Enable or disable BOX NOW delivery method',
  'Shipping Method Name' => 'Shipping Method Name',
  'Shipping method name visible to customers at checkout.' => 'Shipping method name visible to customers at checkout.',
  'Delivery to BOX NOW locker - available 24/7' => 'Delivery to BOX NOW locker - available 24/7',
  'Shipping cost 0 kg - 20 kg' => 'Shipping cost 0 kg - 20 kg',
  'Shipping cost for parcels up to 20 kg.' => 'Shipping cost for parcels up to 20 kg.',
  'Free delivery' => 'Free delivery',
  'Free delivery threshold' => 'Free delivery threshold',
  'If the order value exceeds this amount, no shipping fee will be charged' => 'If the order value exceeds this amount, no shipping fee will be charged',
  'Taxable' => 'Taxable',
  'Apply VAT to shipping cost?' => 'Apply VAT to shipping cost?',
  'Yes' => 'Yes',
  'No' => 'No',
  'Maximum allowed weight (kg)' => 'Maximum allowed weight (kg)',
  '20kg' => '20kg',
  'Maximum parcel dimensions' => 'Maximum parcel dimensions',
  'Maximum parcel dimensions for BOX NOW delivery' => 'Maximum parcel dimensions for BOX NOW delivery',
  'Maximum length (cm)' => 'Maximum length (cm)',
  'Maximum parcel length allowed for this shipping method (in cm)' => 'Maximum parcel length allowed for this shipping method (in cm)',
  '60 cm' => '60 cm',
  'Maximum width (cm)' => 'Maximum width (cm)',
  'Maximum parcel width allowed for this shipping method (in cm)' => 'Maximum parcel width allowed for this shipping method (in cm)',
  '45 cm' => '45 cm',
  'Maximum height (cm)' => 'Maximum height (cm)',
  'Maximum parcel height allowed for this shipping method (in cm)' => 'Maximum parcel height allowed for this shipping method (in cm)',
  '36 cm' => '36 cm',

  // ============================================
  // COD (CASH ON DELIVERY) SETTINGS
  // ============================================
  'Cash on Delivery description settings' => 'Cash on Delivery description settings',
  'Modify the description text for Cash on Delivery payment method' => 'Modify the description text for Cash on Delivery payment method',
  'Modify COD text' => 'Modify COD text',
  'Enable / disable modification of Cash on Delivery payment method text.' => 'Enable / disable modification of Cash on Delivery payment method text.',
  'COD Method Description' => 'COD Method Description',
  'Enter custom text for Cash on Delivery payment method' => 'Enter custom text for Cash on Delivery payment method',
  'NOTICE! For BOX NOW locker delivery with Cash on Delivery, there is no cash payment option. Payment is made by bank card via a link you will receive by SMS/Viber/email along with the shipment confirmation.' => 'NOTICE! For BOX NOW locker delivery with Cash on Delivery, there is no cash payment option. Payment is made by bank card via a link you will receive by SMS/Viber/email along with the shipment confirmation.',

  // ============================================
  // BULK VOUCHERS
  // ============================================
  'Generate BOX NOW bulk vouchers' => 'Generate BOX NOW bulk vouchers',
  'Order #%d: %s' => 'Order #%d: %s',
  'Voucher already exists' => 'Voucher already exists',
  'Missing locker ID' => 'Missing locker ID',
  'Locker %s is not available in the BOX NOW network. Please open the order and select another locker from the order details.' => 'Locker %s is not available in the BOX NOW network. Please open the order and select another locker from the order details.',
  'Error getting access token' => 'Error getting access token',
  'No valid PDF files found for download' => 'No valid PDF files found for download',
  'Error saving combined PDF file' => 'Error saving combined PDF file',
  'Successfully generated vouchers for %d orders.' => 'Successfully generated vouchers for %d orders.',
  'Download combined PDF' => 'Download combined PDF',
  'No orders with BOX NOW delivery among selected.' => 'No orders with BOX NOW delivery among selected.',
  'Error generating PDF file.' => 'Error generating PDF file.',
  'No vouchers were generated.' => 'No vouchers were generated.',
  'An error occurred during processing.' => 'An error occurred during processing.',
  'Partial errors:' => 'Partial errors:',

  // ============================================
  // ORDER COLUMN
  // ============================================
  'Voucher' => 'Voucher',
  'No voucher created yet' => 'No voucher created yet',
  'Pending' => 'Pending',
  'Open voucher' => 'Open voucher',

  // ============================================
  // ORDER STATUS & CANCELLATION
  // ============================================
  'Box Now Canceled' => 'Box Now Canceled',
  'BOX NOW Canceled' => 'BOX NOW Canceled',
  'Cancel Order' => 'Cancel Order',

  // ============================================
  // PRINT VOUCHER
  // ============================================
  'Parcel ID was not found!' => 'Parcel ID was not found!',
  'Error: Authentication failed.' => 'Error: Authentication failed.',
  'Error:' => 'Error:',
];
