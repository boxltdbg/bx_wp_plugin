<?php

function box_now_delivery_menu()
{
  add_menu_page(
    'BOX NOW Delivery',
    'BOX NOW Delivery',
    'manage_options',
    'box-now-delivery',
    'box_now_delivery_options',
    'dashicons-location',
    80
  );
}

require_once 'box-now-delivery-validation.php';

// Enqueue admin scripts
function box_now_delivery_enqueue_admin_scripts($hook)
{
  if ($hook != 'toplevel_page_box-now-delivery') {
    return;
  }

  wp_enqueue_script('box_now_delivery_admin_page_script', plugins_url('../js/box-now-delivery-admin-page.js', __FILE__), array(), '1.0', true);
}

add_action('admin_enqueue_scripts', 'box_now_delivery_enqueue_admin_scripts');

function box_now_delivery_options()
{
  $saved_environment = get_option('boxnow_environment', '');
  $boxnow_environment = in_array($saved_environment, array('production', 'stage'), true) ? $saved_environment : '';

  // Подсказка за стари инсталации: определяме stage спрямо запазения URL, иначе production
  if (empty($boxnow_environment)) {
    $stored_api = get_option('boxnow_api_url', '');
    if (stripos($stored_api, 'stage') !== false) {
      $boxnow_environment = 'stage';
    } else {
      $boxnow_environment = 'production';
    }
  }

  $boxnow_api_url = $boxnow_environment === 'stage' ? 'api-stage.boxnow.bg' : 'api-production.boxnow.bg';
?>
  <div class="wrap">
    <?php settings_fields('box-now-delivery-settings-group'); ?>
    <?php do_settings_sections('box-now-delivery-settings-group'); ?>
    <?php if (isset($_GET['status']) && $_GET['status'] === 'success') : ?>
      <div class="notice notice-success is-dismissible" id="boxnow-save-notice">
        <p><?php esc_html_e('Settings saved successfully.', 'boxnowbulgaria'); ?></p>
      </div>
      <script>
        (function() {
          const n = document.getElementById('boxnow-save-notice');
          if (!n) return;
          // Auto-dismiss after 4s
          setTimeout(function() {
            n.style.transition = 'opacity 0.3s';
            n.style.opacity = '0';
            setTimeout(function() {
              n.remove();
            }, 300);
          }, 4000);
          // Remove status param from URL without reload
          const url = new URL(window.location.href);
          if (url.searchParams.has('status')) {
            url.searchParams.delete('status');
            window.history.replaceState({}, document.title, url.toString());
          }
        })();
      </script>
    <?php endif; ?>

    <style>
      .boxnow-admin {
        max-width: 1140px;
        margin-top: 4px;
      }
      .boxnow-hero {
        display: grid;
        grid-template-columns: auto 1fr;
        align-items: center;
        gap: 12px;
        background: #f5fff0;
        border: 1px solid #d7e6d0;
        border-radius: 12px;
        padding: 12px 16px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        margin-bottom: 12px;
      }
      .boxnow-hero__left {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
      }
      .boxnow-hero__logo {
        height: 44px;
      }
      .boxnow-hero__tag {
        background: #84C33F;
        color: #fff;
        padding: 6px 12px;
        min-width: 160px; /* приблизително ширината на логото */
        display: inline-flex;
        justify-content: center;
        border-radius: 999px;
        font-weight: 700;
        font-size: 13px;
      }
      .boxnow-note {
        color: #55606e;
        font-size: 13px;
        margin: 4px 0 0 0;
      }
      .boxnow-step {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px;
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.05);
        margin-bottom: 16px;
      }
      .boxnow-step--hidden {
        display: none;
      }
      .boxnow-step__title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0 0 12px 0;
        font-size: 16px;
        font-weight: 700;
        color: #1d2327;
      }
      .boxnow-step__badge {
        background: #84C33F;
        color: #fff;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 13px;
        font-weight: 700;
      }
      .boxnow-columns {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 12px;
        margin-top: 4px;
      }
      .boxnow-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px 16px;
        box-shadow: 0 6px 14px rgba(0, 0, 0, 0.04);
      }
      .boxnow-card h3 {
        margin: 0 0 10px 0;
        font-size: 15px;
        color: #1d2327;
      }
      .boxnow-inline-options {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin: 6px 0 4px 0;
      }
      .boxnow-two-col {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 10px;
      }
      .boxnow-field label {
        font-weight: 600;
        display: block;
        margin-bottom: 4px;
      }
      .boxnow-pill {
        padding: 6px 10px;
        border-radius: 10px;
        border: 1px solid #cfe7c7;
        background: #f8fff5;
        color: #2e3c2b;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
      }
      .boxnow-three-col {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 12px;
        margin-top: 8px;
      }
      .boxnow-color-row {
        display: flex;
        gap: 8px;
        align-items: center;
      }
      .boxnow-color-hex {
        max-width: 120px;
      }
      .boxnow-submit {
        margin-top: 10px;
      }
      .boxnow-step-footer {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
        margin-top: 8px;
      }
      .boxnow-secondary-btn {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #1d2327;
        padding: 0 20px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 700;
        font-size: 15px;
        height: 48px;
        min-width: 170px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
      }
      .boxnow-secondary-btn:hover {
        background: #e2e8f0;
      }
      @media (max-width: 768px) {
        .boxnow-hero {
          flex-direction: column;
          align-items: flex-start;
        }
      }
    </style>

    <div class="boxnow-admin">
      <div class="boxnow-hero">
        <div class="boxnow-hero__left">
          <div class="boxnow-hero__tag">Bulgaria</div>
          <img class="boxnow-hero__logo" src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../css/img/boxnow-logo-wide.png'); ?>" alt="BOX NOW">
        </div>
        <div>
          <p class="boxnow-note"><?php printf(esc_html__('Thank you for choosing BOX NOW as your trusted delivery partner! For more information visit %1$s or email us at %2$s.', 'boxnowbulgaria'), '<a href="https://boxnow.bg/">BOX NOW</a>', '<a href="mailto:integrationsupport@boxnow.bg">integrationsupport@boxnow.bg</a>'); ?></p>
        </div>
      </div>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="boxnow-settings-save">
        <?php wp_nonce_field('boxnow-settings-save', 'boxnow-custom-message'); ?>
        <input type="hidden" name="boxnow_voucher_option" value="button" />

        <div class="boxnow-step" id="boxnow-step-1">
          <div class="boxnow-step__title">
            <span class="boxnow-step__badge"><?php esc_html_e('Step 1', 'boxnowbulgaria'); ?></span>
            <span><?php esc_html_e('Plugin Configuration', 'boxnowbulgaria'); ?></span>
          </div>
          <div class="boxnow-columns">
            <div class="boxnow-card">
              <h3><?php esc_html_e('Environment and API', 'boxnowbulgaria'); ?></h3>
              <div class="boxnow-inline-options">
                <label class="boxnow-pill">
                  <input type="radio" id="boxnow_env_production" name="boxnow_environment" value="production" <?php checked($boxnow_environment, 'production'); ?> />
                  <?php esc_html_e('Production (default)', 'boxnowbulgaria'); ?>
                </label>
                <label class="boxnow-pill">
                  <input type="radio" id="boxnow_env_stage" name="boxnow_environment" value="stage" <?php checked($boxnow_environment, 'stage'); ?> />
                  Stage
                </label>
              </div>
              <div class="boxnow-two-col">
                <div class="boxnow-field">
                  <label for="boxnow_api_url"><?php esc_html_e('Your API URL', 'boxnowbulgaria'); ?></label>
                  <input id="boxnow_api_url" type="text" name="boxnow_api_url" value="<?php echo esc_attr($boxnow_api_url); ?>" placeholder="Enter your API URL without the http:// or https:// prefix" readonly />
                </div>
                <div class="boxnow-field">
                  <label for="boxnow_warehouse_id"><?php esc_html_e('Warehouse ID(s)', 'boxnowbulgaria'); ?></label>
                  <input id="boxnow_warehouse_id" type="text" name="boxnow_warehouse_id" value="<?php echo esc_attr(get_option('boxnow_warehouse_id', '')); ?>" placeholder="Enter your Warehouse ID" />
                </div>
              </div>
              <p class="boxnow-note"><?php esc_html_e('If you have more than 1 warehouse, separate their IDs with a comma.', 'boxnowbulgaria'); ?></p>
            </div>

            <div class="boxnow-card">
              <h3><?php esc_html_e('Access Credentials', 'boxnowbulgaria'); ?></h3>
              <div class="boxnow-two-col">
                <div class="boxnow-field">
                  <label for="boxnow_client_id"><?php esc_html_e('Your Client ID', 'boxnowbulgaria'); ?></label>
                  <input id="boxnow_client_id" type="text" name="boxnow_client_id" value="<?php echo esc_attr(get_option('boxnow_client_id', '')); ?>" placeholder="Enter your Client ID" />
                </div>
                <div class="boxnow-field">
                  <label for="boxnow_client_secret"><?php esc_html_e('Your Client Secret', 'boxnowbulgaria'); ?></label>
                  <input id="boxnow_client_secret" type="text" name="boxnow_client_secret" value="<?php echo esc_attr(get_option('boxnow_client_secret', '')); ?>" placeholder="Enter your Client Secret" />
                </div>
                <div class="boxnow-field">
                  <label for="boxnow_partner_id"><?php esc_html_e('Your Partner ID', 'boxnowbulgaria'); ?></label>
                  <input id="boxnow_partner_id" type="text" name="boxnow_partner_id" value="<?php echo esc_attr(get_option('boxnow_partner_id', '')); ?>" placeholder="Enter your Partner ID" />
                </div>
              </div>
            </div>
          </div>
          <div class="boxnow-step-footer">
            <button type="button" class="button button-primary" id="boxnow-step-next"><?php esc_html_e('Next', 'boxnowbulgaria'); ?></button>
          </div>
        </div>

        <div class="boxnow-step boxnow-step--hidden" id="boxnow-step-2">
          <div class="boxnow-step__title">
            <span class="boxnow-step__badge"><?php esc_html_e('Step 2', 'boxnowbulgaria'); ?></span>
            <span><?php esc_html_e('Button, Map and Messages Settings', 'boxnowbulgaria'); ?></span>
          </div>
          <div class="boxnow-card">
            <div class="boxnow-three-col">
              <div class="boxnow-field">
                <label><?php esc_html_e('Checkout Type', 'boxnowbulgaria'); ?></label>
                <div class="boxnow-inline-options">
                  <label class="boxnow-pill">
                    <input type="radio" id="checkout_type_classic" name="boxnow_checkout_type" value="classic" <?php checked(get_option('boxnow_checkout_type', 'classic'), 'classic'); ?>>
                    Classic Checkout
                  </label>
                  <label class="boxnow-pill">
                    <input type="radio" id="checkout_type_block" name="boxnow_checkout_type" value="block" <?php checked(get_option('boxnow_checkout_type', 'classic'), 'block'); ?>>
                    Block Checkout
                  </label>
                </div>
              </div>

              <div class="boxnow-field">
                <label><?php esc_html_e('Map Display', 'boxnowbulgaria'); ?></label>
                <div class="boxnow-inline-options">
                  <label class="boxnow-pill">
                    <input type="radio" id="box_now_display_mode_popup" name="box_now_display_mode" value="popup" <?php checked(get_option('box_now_display_mode', 'popup'), 'popup'); ?>>
                    <?php esc_html_e('Modal Pop-up', 'boxnowbulgaria'); ?>
                  </label>
                  <label class="boxnow-pill">
                    <input type="radio" id="box_now_display_mode_embedded" name="box_now_display_mode" value="embedded" <?php checked(get_option('box_now_display_mode', 'popup'), 'embedded'); ?>>
                    <?php esc_html_e('iFrame Type', 'boxnowbulgaria'); ?>
                  </label>
                </div>
              </div>

              <div class="boxnow-field">
                <label><?php esc_html_e('GPS Location', 'boxnowbulgaria'); ?></label>
                <div class="boxnow-inline-options">
                  <label class="boxnow-pill">
                    <input type="radio" id="gps_tracking_on" name="boxnow_gps_tracking" value="on" <?php checked(get_option('boxnow_gps_tracking', 'on'), 'on'); ?>>
                    <?php esc_html_e('Enabled', 'boxnowbulgaria'); ?>
                  </label>
                  <label class="boxnow-pill">
                    <input type="radio" id="gps_tracking_off" name="boxnow_gps_tracking" value="off" <?php checked(get_option('boxnow_gps_tracking', 'on'), 'off'); ?>>
                    <?php esc_html_e('Disabled', 'boxnowbulgaria'); ?>
                  </label>
                </div>
              </div>

            </div>

            <?php
            $active_countries = function_exists('boxnow_get_allowed_countries') ? boxnow_get_allowed_countries() : array('bg');
            $country_options  = array('bg' => 'Bulgaria (BG)', 'gr' => 'Greece (GR)', 'hr' => 'Croatia (HR)', 'cy' => 'Cyprus (CY)');
            ?>
            <div class="boxnow-field" style="margin-bottom:16px;">
              <label><?php esc_html_e('Shipping Countries', 'boxnowbulgaria'); ?></label>
              <div class="boxnow-inline-options" style="flex-wrap:nowrap;" id="boxnow-country-checkboxes">
                <?php foreach ($country_options as $code => $label) : ?>
                  <label class="boxnow-pill">
                    <input type="checkbox" name="boxnow_countries[]" value="<?php echo esc_attr($code); ?>" <?php checked(in_array($code, $active_countries, true)); ?> class="boxnow-country-cb">
                    <?php echo esc_html($label); ?>
                  </label>
                <?php endforeach; ?>
              </div>
              <p class="boxnow-note"><?php esc_html_e('At least one country must be selected. Selecting multiple countries enables multi-language support on the locker map.', 'boxnowbulgaria'); ?></p>
            </div>

            <div class="boxnow-three-col">
              <div class="boxnow-field">
                <label for="boxnow_button_color"><?php esc_html_e('Change Button Color', 'boxnowbulgaria'); ?></label>
                <div class="boxnow-color-row">
                  <input id="boxnow_button_color" type="color" name="boxnow_button_color" value="<?php echo esc_attr(get_option('boxnow_button_color', '#84C33F')); ?>" />
                  <input id="boxnow_button_color_text" class="boxnow-color-hex" type="text" value="<?php echo esc_attr(get_option('boxnow_button_color', '#84C33F')); ?>" placeholder="#84C33F" />
                </div>
              </div>
              <div class="boxnow-field">
                <label for="button_text_input"><?php esc_html_e('Change Button Text', 'boxnowbulgaria'); ?></label>
                <input type="text" id="button_text_input" name="boxnow_button_text" value="<?php echo esc_attr(get_option('boxnow_button_text', __('Select BOX NOW Locker', 'boxnowbulgaria'))); ?>" placeholder="<?php esc_attr_e('Select BOX NOW Locker', 'boxnowbulgaria'); ?>" />
              </div>
              <div class="boxnow-field">
                <label for="boxnow_locker_not_selected_message"><?php esc_html_e('Message when no locker is selected', 'boxnowbulgaria'); ?></label>
                <input id="boxnow_locker_not_selected_message" type="text" name="boxnow_locker_not_selected_message" value="<?php echo esc_attr(get_option('boxnow_locker_not_selected_message', __('Please select a locker to continue!', 'boxnowbulgaria'))); ?>" placeholder="<?php esc_attr_e('Enter desired message', 'boxnowbulgaria'); ?>" />
              </div>
            </div>

            <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid #e2e8f0;">
              <div class="boxnow-field">
                <label style="font-weight: 600; display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                  <?php esc_html_e('Allow multiple labels per order', 'boxnowbulgaria'); ?>
                  <span title="<?php esc_attr_e('When enabled, merchants can generate multiple shipping labels from a single order. This applies to prepaid orders only. COD orders always generate a single label to prevent duplicate payment requests.', 'boxnowbulgaria'); ?>" style="display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; border-radius: 50%; background: #84C33F; color: #fff; font-size: 11px; font-weight: 700; cursor: help; flex-shrink: 0;">?</span>
                </label>
                <label class="boxnow-pill" style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                  <input type="checkbox" name="boxnow_allow_multiple_labels" value="on" <?php checked(get_option('boxnow_allow_multiple_labels', 'off'), 'on'); ?> />
                  <?php esc_html_e('Enable (prepaid orders only)', 'boxnowbulgaria'); ?>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="boxnow-step boxnow-step--hidden" id="boxnow-step-3">
          <div class="boxnow-step__title">
            <span class="boxnow-step__badge"><?php esc_html_e('Step 3', 'boxnowbulgaria'); ?></span>
            <span><?php esc_html_e('Sender Settings', 'boxnowbulgaria'); ?></span>
          </div>
          <div class="boxnow-columns">
            <div class="boxnow-card">
              <div class="boxnow-field">
                <label for="boxnow_sender_name"><?php esc_html_e('Sender Name', 'boxnowbulgaria'); ?></label>
                <input id="boxnow_sender_name" type="text" name="boxnow_sender_name" value="<?php echo esc_attr(get_option('boxnow_sender_name', '')); ?>" placeholder="<?php esc_attr_e('Sender Name', 'boxnowbulgaria'); ?>" />
              </div>
              <div class="boxnow-field">
                <label for="boxnow_sender_email"><?php esc_html_e('Sender Email', 'boxnowbulgaria'); ?></label>
                <input id="boxnow_sender_email" type="text" name="boxnow_sender_email" value="<?php echo esc_attr(get_option('boxnow_sender_email', '')); ?>" placeholder="<?php esc_attr_e('Sender Email', 'boxnowbulgaria'); ?>" />
              </div>
              <div class="boxnow-field">
                <label for="boxnow_sender_phone"><?php esc_html_e('Sender Phone', 'boxnowbulgaria'); ?></label>
                <input id="boxnow_sender_phone" type="text" name="boxnow_sender_phone" value="<?php echo esc_attr(get_option('boxnow_sender_phone', '')); ?>" placeholder="<?php esc_attr_e('Sender Phone', 'boxnowbulgaria'); ?>" />
              </div>
            </div>
          </div>
        </div>

        <div class="boxnow-submit boxnow-step--hidden" id="boxnow-step-2-footer">
          <div class="boxnow-step-footer">
            <button type="button" class="boxnow-secondary-btn" id="boxnow-step-back"><?php esc_html_e('Back', 'boxnowbulgaria'); ?></button>
            <?php submit_button(); ?>
          </div>
        </div>
      </form>
    </div>
  </div>
  <script>
    (function() {
      const step1 = document.getElementById('boxnow-step-1');
      const step2 = document.getElementById('boxnow-step-2');
      const step3 = document.getElementById('boxnow-step-3');
      const footer2 = document.getElementById('boxnow-step-2-footer');
      const nextBtn = document.getElementById('boxnow-step-next');
      const backBtn = document.getElementById('boxnow-step-back');
      const colorInput = document.getElementById('boxnow_button_color');
      const colorText = document.getElementById('boxnow_button_color_text');

      if (!step1 || !step2 || !step3 || !footer2 || !nextBtn || !backBtn || !colorInput || !colorText) {
        return;
      }

      nextBtn.addEventListener('click', function() {
        step1.classList.add('boxnow-step--hidden');
        step2.classList.remove('boxnow-step--hidden');
        step3.classList.remove('boxnow-step--hidden');
        footer2.classList.remove('boxnow-step--hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });

      backBtn.addEventListener('click', function() {
        step2.classList.add('boxnow-step--hidden');
        step3.classList.add('boxnow-step--hidden');
        footer2.classList.add('boxnow-step--hidden');
        step1.classList.remove('boxnow-step--hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });

      // Prevent deselecting the last country checkbox
      const countryCheckboxes = document.querySelectorAll('.boxnow-country-cb');
      countryCheckboxes.forEach(function(cb) {
        cb.addEventListener('change', function() {
          const checked = document.querySelectorAll('.boxnow-country-cb:checked');
          if (checked.length === 0) {
            cb.checked = true;
          }
        });
      });

      // Sync color picker and hex text
      const normalizeHex = (val) => {
        if (!val) return '';
        let v = val.trim();
        if (v[0] !== '#') {
          v = '#' + v;
        }
        if (v.length === 4) {
          // short form #abc -> #aabbcc
          v = '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3];
        }
        return v;
      };

      colorInput.addEventListener('input', function () {
        colorText.value = colorInput.value;
      });

      colorText.addEventListener('input', function () {
        const v = normalizeHex(colorText.value);
        const isValid = /^#[0-9a-fA-F]{6}$/.test(v);
        if (isValid) {
          colorInput.value = v;
        }
      });
    })();
  </script>
<?php
}

function box_now_delivery_settings()
{
  $serializer = new BNDP_Serializer();
  $serializer->init();
}


add_action('admin_menu', 'box_now_delivery_menu');
add_action('admin_init', 'box_now_delivery_settings');

// Hide WordPress footer text on the plugin settings page
add_filter('admin_footer_text', function ($text) {
  $screen = get_current_screen();
  if ($screen && $screen->id === 'toplevel_page_box-now-delivery') {
    return '';
  }
  return $text;
});

function box_now_delivery_enqueue_admin_styles($hook)
{
  if ($hook != 'toplevel_page_box-now-delivery') {
    return;
  }

  wp_register_style('box_now_delivery_admin_styles', plugin_dir_url(__FILE__) . '../css/box-now-delivery-admin.css');
  wp_enqueue_style('box_now_delivery_admin_styles');
}

add_action('admin_enqueue_scripts', 'box_now_delivery_enqueue_admin_styles');

function box_now_delivery_enqueue_styles($hook = '')
{
  // Only on the plugin's own settings screen. This used to load the *front-end*
  // stylesheet on every single wp-admin page, where it has no business being.
  if ($hook != 'toplevel_page_box-now-delivery') {
    return;
  }

  wp_register_style('box_now_delivery_styles', plugin_dir_url(__FILE__) . '../css/box-now-delivery.css', array(), '1.0.0');
  wp_enqueue_style('box_now_delivery_styles');
}

add_action('admin_enqueue_scripts', 'box_now_delivery_enqueue_styles');
