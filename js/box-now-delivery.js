(function ($) {
  function escapeHtml(str) {
    return String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  // ---------------------------------------------------------------------------
  // Shared helpers (used by both classic and block checkout logic below)
  // ---------------------------------------------------------------------------

  // Safe localStorage wrappers — private mode / strict cookie blockers throw on
  // access, which must never abort locker selection.
  function safeLocalGet(key) {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  }
  function safeLocalSet(key, value) {
    try { localStorage.setItem(key, value); } catch (e) {}
  }
  function safeLocalRemove(key) {
    try { localStorage.removeItem(key); } catch (e) {}
  }

  function normalizeBoxnowMessage(event) {
    var raw = event.data;
    if (raw === "closeIframe") return { type: "close" };

    var data = raw;
    if (typeof data === "string") {
      try { data = JSON.parse(data); } catch (e) { return null; }
    }
    if (!data || typeof data !== "object") return null;

    if (data.boxnowClose !== undefined) return { type: "close" };
    if (data.boxnowLockerId !== undefined && data.boxnowLockerId !== "") {
      return { type: "locker", data: data };
    }
    return null;
  }

  function removeBoxnowPopup() {
    $("#box_now_delivery_overlay").remove();
    $("iframe.boxnow-popup-iframe").remove();
    $("#boxnow-popup-spinner").remove();
  }

  function ensureBoxnowSpinnerStyles() {
    if ($("#boxnow-popup-spinner-style").length === 0) {
      $("head").append(
        '<style id="boxnow-popup-spinner-style">' +
        "@keyframes boxnowspin{to{transform:rotate(360deg)}}" +
        "#boxnow-popup-spinner{position:fixed;top:50%;left:50%;width:48px;height:48px;" +
        "margin:-24px 0 0 -24px;border:4px solid rgba(255,255,255,.35);border-top-color:#fff;" +
        "border-radius:50%;animation:boxnowspin .8s linear infinite;z-index:1000001;pointer-events:none}" +
        ".boxnow-inline-spinner{position:absolute;top:50%;left:50%;width:36px;height:36px;" +
        "margin:-18px 0 0 -18px;border:4px solid rgba(0,0,0,.12);border-top-color:#84C33F;" +
        "border-radius:50%;animation:boxnowspin .8s linear infinite;z-index:5;pointer-events:none}" +
        "</style>"
      );
    }
  }

  function showBoxnowPopupSpinner() {
    ensureBoxnowSpinnerStyles();
    if ($("#boxnow-popup-spinner").length === 0) {
      $("body").append('<div id="boxnow-popup-spinner"></div>');
    }
  }

  function hideBoxnowPopupSpinner() {
    $("#boxnow-popup-spinner").remove();
  }

  function showBoxnowInlineSpinner($container) {
    if (!$container || !$container.length) return;
    ensureBoxnowSpinnerStyles();
    if ($container.css("position") === "static") {
      $container.css("position", "relative");
    }
    if ($container.find(".boxnow-inline-spinner").length === 0) {
      $container.append('<div class="boxnow-inline-spinner"></div>');
    }
  }

  function hideBoxnowInlineSpinner($container) {
    if ($container && $container.length) {
      $container.find(".boxnow-inline-spinner").remove();
    }
  }

  // Build the widget URL consistently for popup and embedded, single or multi country.
  function buildBoxnowWidgetUrl(isPopup) {
    var base = ((boxNowDeliverySettings && boxNowDeliverySettings.widgetBaseUrl)
      ? boxNowDeliverySettings.widgetBaseUrl
      : "https://map.boxnow.gr").replace(/\/$/, "");

    var countryCodes = ((boxNowDeliverySettings && boxNowDeliverySettings.countryCodes)
      ? boxNowDeliverySettings.countryCodes
      : "bg").replace(/\s/g, "");

    var gpsOption = (boxNowDeliverySettings && boxNowDeliverySettings.gps_option)
      ? boxNowDeliverySettings.gps_option
      : "on";

    var partnerId = (boxNowDeliverySettings && boxNowDeliverySettings.partnerId)
      ? boxNowDeliverySettings.partnerId
      : "";

    var useGps = gpsOption !== "off";

    var params = [];

    if (partnerId) params.push("partnerId=" + encodeURIComponent(partnerId));

    params.push("gps=" + (useGps ? "yes" : "no"));

    var ccList = countryCodes.split(",").filter(Boolean);
    var widgetLanguage = (ccList.length > 1)
      ? "en"
      : ((boxNowDeliverySettings && boxNowDeliverySettings.widgetLanguage)
          ? boxNowDeliverySettings.widgetLanguage
          : boxnowWidgetLanguage(countryCodes));
    params.push("countryCode=" + encodeURIComponent(countryCodes));
    params.push("language=" + encodeURIComponent(widgetLanguage));

    params.push("autoselect=" + (isPopup ? "no" : "yes"));
    params.push("autoclose=" + (isPopup ? "yes" : "no"));

    return base + (isPopup ? "/popup.html" : "/iframe.html") + "?" + params.join("&");
  }

  function boxnowWidgetLanguage(countryCodes) {
    var list = String(countryCodes || "").split(",").filter(Boolean);
    if (list.length > 1) return "en";
    var map = { bg: "bg", gr: "gr", hr: "hr", si: "si", cy: "gr" };
    var single = (list[0] || "bg").toLowerCase();
    return map[single] || "en";
  }

  var checkoutType = boxNowDeliverySettings.checkoutType || 'classic';

  if (checkoutType === 'classic') {
    // CLASSIC CHECKOUT LOGIC

    function addButton() {
      if (
        $("#box_now_delivery_button").length === 0 &&
        boxNowDeliverySettings.displayMode === "popup"
      ) {
        var buttonText = boxNowDeliverySettings.buttonText || "Select BOX NOW Locker";

        $('label[for="shipping_method_0_box_now_delivery"]').after(
          '<button type="button" id="box_now_delivery_button" style="display:none; margin-top:6px;">' +
            buttonText +
            "</button>" +
            '<div id="box_now_selected_locker_details" style="display:none;"></div>'
        );

        attachButtonClickListener();
      } else if (boxNowDeliverySettings.displayMode === "embedded") {
        if ($("#box_now_delivery_embedded_map").length === 0) {
          $('label[for="shipping_method_0_box_now_delivery"]').after(
            '<div id="box_now_delivery_embedded_map" style="display:none;"></div>'
          );
          embedMap();
        }
      }
      
      applyButtonStyles();
    }

    function applyButtonStyles() {
      var buttonColor = boxNowDeliverySettings.buttonColor || "#84C33F";

      if ($("#box-now-delivery-button-styles").length === 0) {
        var styleBlock =
          '<style id="box-now-delivery-button-styles">' +
          '#box_now_delivery_button {' +
          "background-color: " + buttonColor + " !important;" +
          "color: #fff !important;" +
          "cursor: pointer !important;" +
          "margin-top: 8px;" +
          "}" +
          "#box_now_delivery_button.disabled {" +
          "background-color: #cccccc !important;" +
          "cursor: not-allowed !important;" +
          "}" +
          ".boxnow-error-message {" +
          "color: red;" +
          "display: block;" +
          "margin-top: 6px;" +
          "font-size: 14px;" +
          "font-weight: bold;" +
          "}" +
          "#box_now_selected_locker_details {" +
          "margin-top: 8px;" +
          "}" +
          "</style>";

        $("head").append(styleBlock);
      }
    }

    function attachButtonClickListener() {
      $("#box_now_delivery_button").on("click", function (event) {
        event.preventDefault();
        createPopupMap();
      });
    }

    function embedMap() {
      var iframe = $("#box_now_delivery_embedded_map iframe");

      if (iframe.length === 0) {
        iframe = createEmbeddedIframe();

        var lockerDetailsContainer = $("<div>", {
          id: "box_now_selected_locker_details",
          css: {
            display: "none",
            marginTop: "6px",
          },
        });

        var lockerInfoContainer = $("<div>", {
          id: "locker_info_container",
        });

        $("#box_now_delivery_embedded_map")
          .css({
            position: "relative",
            width: "100%",
            height: "100%",
            marginBottom: "8px",
          })
          .append(iframe)
          .append(lockerInfoContainer.append(lockerDetailsContainer));
      }

      // Show when BOX NOW is selected, or when it is the only shipping option (nothing checked yet)
      var isBoxNowSelected = $("#shipping_method_0_box_now_delivery").is(":checked");
      var noMethodSelected = !$('input[name="shipping_method[0]"]:checked').val();

      if (isBoxNowSelected || noMethodSelected) {
        $(".woocommerce-shipping-fields").append($("#box_now_delivery_embedded_map"));
        $("#box_now_delivery_embedded_map").show();
      } else {
        $("#box_now_delivery_embedded_map").hide();
        $("#box_now_selected_locker_details").hide();
      }
    }

    function createOverlay() {
      var overlay = $("<div>", {
        id: "box_now_delivery_overlay",
        css: {
          position: "fixed",
          top: 0,
          left: 0,
          width: "100%",
          height: "100%",
          backgroundColor: "rgba(0, 0, 0, 0.6)",
          zIndex: 999999,
        },
      });

      overlay.on("click", function () {
        removeBoxnowPopup();
      });

      $("body").append(overlay);
    }

    function createPopupMap() {
      createOverlay();
      showBoxnowPopupSpinner();

      var iframe = $("<iframe>", {
        src: buildBoxnowWidgetUrl(true),
        class: "boxnow-popup-iframe",
        css: {
          position: "fixed",
          top: 0,
          left: 0,
          border: 0,
          zIndex: 1000000,
          width: "100vw",
          height: "100vh",
          // Opaque white while the widget loads so the dark overlay never bleeds
          // through a transparent iframe (the "blurred/dark" loading state).
          background: "#fff",
          opacity: 0,
          transition: "opacity .2s ease",
        },
        allow: 'geolocation',
      });

      iframe.on("load", function () {
        hideBoxnowPopupSpinner();
        $(this).css("opacity", 1);
      });

      $("body").append(iframe);
    }

    /**
     * Записва избрания автомат в сесията и скритото поле на чекаута.
     * Наместваме уникално име, за да не колидираме с block checkout функцията.
     */
    // Track last stored locker ID to avoid redundant AJAX calls
    var lastStoredLockerIdClassic = null;

    function storeLockerIdInSessionClassic(lockerId, lockerCountry) {
      const safeLockerId = lockerId || "";

      // Skip if the same ID is already stored in session
      if (safeLockerId === lastStoredLockerIdClassic) {
        return;
      }
      lastStoredLockerIdClassic = safeLockerId;

      $.ajax({
        url: boxNowDeliverySettings.ajax_url,
        type: "POST",
        data: {
          action: "set_boxnow_locker_id",
          nonce: (boxNowDeliverySettings && boxNowDeliverySettings.lockerNonce) || "",
          locker_id: safeLockerId,
          locker_country: (lockerCountry || "").toLowerCase(),
        },
      });

      const $hiddenField = $('input[name="_boxnow_locker_id"]');
      if ($hiddenField.length) {
        $hiddenField.val(safeLockerId);
      }
    }

    function createEmbeddedIframe() {
      var $mapContainer = $("#box_now_delivery_embedded_map");
      var iframe = $("<iframe>", {
        src: buildBoxnowWidgetUrl(false),
        class: "boxnow-embedded-iframe",
        css: {
          width: "100%",
          height: "520px",
          border: 0,
        },
        allow: 'geolocation',
      });

      iframe.on("load", function () {
        hideBoxnowInlineSpinner($mapContainer);
      });

      showBoxnowInlineSpinner($mapContainer);

      return iframe;
    }

    // Single message listener for the classic checkout. Handles both selection
    // and close, and tolerates string/object payloads + foreign messages.
    window.addEventListener("message", function (event) {
      var msg = normalizeBoxnowMessage(event);
      if (!msg) return;
      if (msg.type === "close") {
        removeBoxnowPopup();
        return;
      }
      updateLockerDetailsContainer(msg.data);
      // Close the popup (if any) once a locker is chosen; no-op for embedded mode.
      removeBoxnowPopup();
    });


    function updateLockerDetailsContainer(lockerData, saveToSession) {
      if (saveToSession === undefined) saveToSession = true;
      // Only the locker ID is strictly required; name/address are best-effort so a
      // minor widget schema change never silently drops the whole selection.
      if (!lockerData || !lockerData.boxnowLockerId) {
        return;
      }

      const lockerId      = lockerData.boxnowLockerId;
      const lockerCountry = lockerData.boxnowCountry || "";
      const lockerAddress = lockerData.boxnowLockerAddressLine1 || "";
      const lockerName    = lockerData.boxnowLockerName || "";
      const i18n = (boxNowDeliverySettings && boxNowDeliverySettings.i18n) ? boxNowDeliverySettings.i18n : {};
      const selectedLockerLabel = i18n.selectedLocker || "Selected locker";
      const lockerNameLabel = i18n.lockerName || "Locker Name:";
      const lockerAddressLabel = i18n.lockerAddress || "Locker Address:";

      safeLocalSet("box_now_selected_locker", JSON.stringify(lockerData));

      // Добавяме контейнер при popup режима, ако липсва.
      if ($("#box_now_selected_locker_details").length === 0) {
        $("#box_now_delivery_button").after(
          '<div id="box_now_selected_locker_details" style="display:none;"></div>'
        );
      }

      const content =
        '<div class="boxnow-selected-locker-info">' +
          '<div class="boxnow-locker-label">' + escapeHtml(selectedLockerLabel) + '</div>' +
          '<div class="boxnow-locker-name"><strong>' + escapeHtml(lockerNameLabel) + '</strong> ' + escapeHtml(lockerName) + '</div>' +
          '<div class="boxnow-locker-address"><strong>' + escapeHtml(lockerAddressLabel) + '</strong> ' + escapeHtml(lockerAddress) + '</div>' +
        '</div>';

      $("#box_now_selected_locker_details").html(content).show();

      // Close popup, overlay and spinner immediately when a locker is selected.
      removeBoxnowPopup();

      if ($("#box_now_selected_locker_input").length === 0) {
        $("<input>")
          .attr({
            type: "hidden",
            id: "box_now_selected_locker_input",
            name: "box_now_selected_locker",
            value: JSON.stringify(lockerData),
          })
          .appendTo("#box_now_selected_locker_details");
      } else {
        $("#box_now_selected_locker_input").val(JSON.stringify(lockerData));
      }

      if (saveToSession) {
        storeLockerIdInSessionClassic(lockerId, lockerCountry);
      }
    }

    function isClassicBoxNowSelected() {
      return $("#shipping_method_0_box_now_delivery").is(":checked");
    }

    function showSelectedLockerDetailsFromLocalStorage() {
      if (!isClassicBoxNowSelected()) {
        return;
      }

      var lockerData = safeLocalGet("box_now_selected_locker");
      if (lockerData) {
        // Pass false: restoring from localStorage only — locker ID is already in
        // WC session from original selection. Firing the session AJAX here races
        // with WooCommerce's update_order_review and causes PHP session lock
        // contention that prevents payment methods from loading.
        try {
          updateLockerDetailsContainer(JSON.parse(lockerData), false);
        } catch (e) {
          safeLocalRemove("box_now_selected_locker");
        }
      }
    }

    function toggleBoxNowDeliveryButton() {
      var isBoxNowSelected = $("#shipping_method_0_box_now_delivery").is(":checked");
      // Also show the button when BOX NOW is the only shipping option (nothing pre-selected)
      var noMethodSelected = !$('input[name="shipping_method[0]"]:checked').val();

      if (isBoxNowSelected || noMethodSelected) {
        $("#box_now_delivery_button")
          .css("background-color", boxNowDeliverySettings.buttonColor)
          .show();
        // Show locker details if a locker was previously selected
        showSelectedLockerDetailsFromLocalStorage();
      } else {
        // Hide button and locker details when a different method is selected
        $("#box_now_delivery_button").hide();
        $("#box_now_selected_locker_details").hide();
      }
    }

    var originalCodDescriptionClassic = null;

    function updateClassicCodDescription(isBoxNowSelected) {
      var customDesc = boxNowDeliverySettings.customCodDescription;
      if (!customDesc) return;
      var $codBox = $(".payment_method_cod .payment_box");
      if (!$codBox.length) return;
      if (isBoxNowSelected) {
        if (originalCodDescriptionClassic === null) {
          originalCodDescriptionClassic = $codBox.html();
        }
        $codBox.html("<p>" + $("<div>").text(customDesc).html() + "</p>");
      } else {
        if (originalCodDescriptionClassic !== null) {
          $codBox.html(originalCodDescriptionClassic);
          originalCodDescriptionClassic = null;
        }
      }
    }

    function toggleBoxNowDelivery() {
      var isBoxNowSelected = $("#shipping_method_0_box_now_delivery").is(":checked");
      // Also show when BOX NOW is the only shipping option (nothing pre-selected)
      var noMethodSelected = !$('input[name="shipping_method[0]"]:checked').val();

      if (boxNowDeliverySettings.displayMode === "popup") {
        toggleBoxNowDeliveryButton();
      } else if (boxNowDeliverySettings.displayMode === "embedded") {
        if (isBoxNowSelected || noMethodSelected) {
          $("#box_now_delivery_embedded_map").show();
          showSelectedLockerDetailsFromLocalStorage();
        } else {
          $("#box_now_delivery_embedded_map").hide();
          $("#box_now_selected_locker_details").hide();
        }
      }

      updateClassicCodDescription(isBoxNowSelected);
    }

    function clearSelectedLockerDetails() {
      safeLocalRemove("box_now_selected_locker");
      $("#box_now_selected_locker_details").hide().empty();
      storeLockerIdInSessionClassic("");
    }

    $(document).ready(function () {
      function addOrderValidation() {
        $(document.body).on("click", "#place_order", function (event) {
          var selectedMethod = $('input[type="radio"][name="shipping_method[0]"]:checked').val();
          var isBoxNow = selectedMethod === "box_now_delivery";
          var lockerData = safeLocalGet("box_now_selected_locker");

          if (!lockerData && isBoxNow) {
            event.preventDefault();
            event.stopImmediatePropagation();
            const i18n = (boxNowDeliverySettings && boxNowDeliverySettings.i18n) ? boxNowDeliverySettings.i18n : {};
            alert(
              boxNowDeliverySettings.lockerNotSelectedMessage ||
              i18n.selectLockerAlert ||
              "Please select a locker to continue!"
            );
            return false;
          }

          if (!isBoxNow && lockerData) {
            // Placing order with a different method — clear stale locker data
            clearSelectedLockerDetails();
          }
        });
      }

      // Do NOT call addButton/toggleBoxNowDelivery/showSelectedLockerDetailsFromLocalStorage
      // directly here on document.ready. WooCommerce fires its own update_checkout AJAX
      // on document.ready, which blocks the checkout form while the AJAX is in flight.
      // Running our DOM manipulation at the same time can cause WooCommerce to detect
      // a DOM change and trigger a second update_checkout cycle that keeps the form
      // blocked, hiding payment methods until the user manually switches methods.
      // Instead, let updated_checkout (which fires after WooCommerce's AJAX completes)
      // do the initial setup — the DOM is stable and safe to touch at that point.

      $(document.body).on("updated_checkout", function () {
        // Reset cached COD description since WooCommerce re-renders #payment on each update
        originalCodDescriptionClassic = null;
        addButton();
        toggleBoxNowDelivery();
        showSelectedLockerDetailsFromLocalStorage();
      });

      $(document.body).on(
        "change",
        'input[type="radio"][name="shipping_method[0]"]',
        toggleBoxNowDelivery
      );

      addOrderValidation();
    });

  } else if (checkoutType === 'block') {
    // BLOCK-BASED CHECKOUT LOGIC

    /**
     * Clears locker data from the session.
     */
    function clearLockerIdInSession() {
      lastSessionLockerIdBlock = null;
      $.ajax({
        url: boxNowDeliverySettings.ajax_url,
        type: "POST",
        data: {
          action: "set_boxnow_locker_id",
          nonce: (boxNowDeliverySettings && boxNowDeliverySettings.lockerNonce) || "",
          locker_id: "",
        },
        success: function () {
          console.log("Box Now locker session cleared.");
        },
        error: function () {
          console.error("Failed to clear Box Now locker session.");
        },
      });
    }

    // Re-assert the stored locker into the WC session. Block checkout saves the
    // order from the session (server side), but the session is set via async AJAX
    // on selection — if that ever fails or is cleared (e.g. checkout_init), the
    // order could be saved without a locker. Calling this whenever BOX NOW is the
    // selected method guarantees the session is populated before place-order.
    function ensureLockerInSession() {
      var raw = safeLocalGet("box_now_selected_locker");
      if (!raw) return;
      try {
        var d = JSON.parse(raw);
        if (d && d.boxnowLockerId && d.boxnowLockerId !== lastSessionLockerIdBlock) {
          storeLockerIdInSession(d.boxnowLockerId, d.boxnowCountry || "");
        }
      } catch (e) {}
    }

    // Last locker ID confirmed stored into the WC session this page-load. Used to
    // avoid redundant AJAX and to drive the place-order re-assert (race guard).
    var lastSessionLockerIdBlock = null;

    /**
     * Stores the selected locker ID in the session.
     */
    function storeLockerIdInSession(lockerId, lockerCountry) {
      if (!lockerId || lockerId === "") {
        // Don't log error for empty locker ID (when clearing selection)
        if (lockerId === "") {
          return;
        }
        console.error("No locker ID provided.");
        return;
      }

      lastSessionLockerIdBlock = lockerId;

      $.ajax({
        url: boxNowDeliverySettings.ajax_url,
        type: "POST",
        data: {
          action: "set_boxnow_locker_id",
          nonce: (boxNowDeliverySettings && boxNowDeliverySettings.lockerNonce) || "",
          locker_id: lockerId,
          locker_country: (lockerCountry || "").toLowerCase(),
        },
        error: function () {
          console.error("Error occurred while storing locker ID in session.");
        },
      });
    }

    function addButton() {
      var boxNowInput = findBoxNowInput();
      if (boxNowInput.length === 0) {
        return; // BoxNow shipping not available
      }

      var parentOption = boxNowInput.closest(
        ".wc-block-components-radio-control__option"
      );
      
      if (parentOption.length === 0) {
        return;
      }

      parentOption.addClass("boxnow-block-option");
      parentOption
        .find(".wc-block-components-radio-control")
        .addClass("boxnow-block-control");

      // Check if our container already exists
      var existingContainer = parentOption.find(".boxnow-elements-container");
      
      if (existingContainer.length === 0) {
        // Remove any orphaned elements first
        $("#box_now_delivery_button").remove();
        $("#box_now_selected_locker_details").remove();
        $(".boxnow-elements-container").remove();
        
        // Create a wrapper container for consistent positioning
        var containerHtml = '<div class="boxnow-elements-container"></div>';
        parentOption.append(containerHtml);
        existingContainer = parentOption.find(".boxnow-elements-container");
      }

      if (boxNowDeliverySettings.displayMode === "popup") {
        // Check if button exists in the container
        var existingButton = existingContainer.find("#box_now_delivery_button");
        
        if (existingButton.length === 0) {
          var buttonText =
            boxNowDeliverySettings.buttonText || "Select BOX NOW Locker";
          
          // Create button and locker details container inside the wrapper
          var buttonHtml = '<button type="button" id="box_now_delivery_button" style="display:none;">' +
              buttonText + "</button>";
          var lockerDetailsHtml = '<div id="box_now_selected_locker_details" style="display:none;"></div>';
          
          existingContainer.append(buttonHtml);
          existingContainer.append(lockerDetailsHtml);
          
          attachButtonClickListener();
        }
      }

      if (boxNowDeliverySettings.displayMode === "embedded") {
        var existingMap = existingContainer.find("#box_now_delivery_embedded_map");
        if (existingMap.length === 0) {
          existingContainer.append(
            '<div id="box_now_delivery_embedded_map" style="display:none;"></div>'
          );
          embedMap();
        }
      }

      applyButtonStyles();
    }

    function findBoxNowInput() {
      return $(
        ".wc-block-components-radio-control__input[value='box_now_delivery']"
      );
    }

    function applyButtonStyles() {
      var buttonColor = boxNowDeliverySettings.buttonColor || "#84C33F";

      if ($("#box-now-delivery-button-styles").length === 0) {
        var styleBlock = `
          <style id="box-now-delivery-button-styles">
            #box_now_delivery_button {
              background-color: ${buttonColor} !important;
              color: #fff !important;
              cursor: pointer !important;
              padding: 12px 20px;
              border: none !important;
              border-radius: 8px;
              font-weight: 600;
              font-size: 14px;
              font-family: inherit;
              transition: all 0.2s ease;
              display: none;
              box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            #box_now_delivery_button.disabled {
              background-color: #cccccc !important;
              cursor: not-allowed !important;
            }
            .wc-block-components-checkout-place-order-button.disabled {
              background-color: #cccccc !important;
              cursor: not-allowed !important;
              pointer-events: none;
            }
            .boxnow-error-message {
              color: red;
              font-size: 14px;
              font-weight: bold;
              opacity: 0;
              transition: opacity 0.3s ease-in-out;
              display: block;
              margin-top: 8px;
              margin-left: 0;
              padding-left: 0;
            }
            .boxnow-error-message.show {
              opacity: 1;
            }
            .boxnow-block-option {
              position: relative;
            }
            /* BoxNow elements container - consistent positioning */
            .boxnow-block-option .boxnow-elements-container {
              display: block;
              width: 100%;
              margin-top: 12px;
              padding-left: 0;
              margin-left: 0;
            }
            .boxnow-block-option #box_now_delivery_button {
              display: none;
              margin-left: 0;
              margin-top: 0;
            }
            .boxnow-block-option #box_now_selected_locker_details {
              display: none;
              margin-top: 10px;
              margin-left: 0;
              padding-left: 0;
            }
            .boxnow-block-option #box_now_delivery_embedded_map {
              display: none;
              margin-top: 10px;
              margin-left: 0;
              width: 100%;
            }
            .boxnow-block-option #box_now_selected_locker_details p {
              margin: 2px 0;
            }
          </style>
        `;
        $("head").append(styleBlock);
      }
    }

    function attachButtonClickListener() {
      $("#box_now_delivery_button")
        .off("click")
        .on("click", function (event) {
          event.preventDefault();
          createPopupMap();
        });
    }

    function embedMap() {
      var iframe = $("#box_now_delivery_embedded_map iframe");

      if (iframe.length === 0) {
        iframe = createEmbeddedIframe();

        var lockerDetailsContainer = $("<div>", {
          id: "box_now_selected_locker_details",
          css: {
            display: "none",
            marginTop: "10px",
          },
        });

        var lockerInfoContainer = $("<div>", {
          id: "locker_info_container",
        });

        $("#box_now_delivery_embedded_map")
          .css({
            position: "relative",
            width: "100%",
            height: "100%",
            marginBottom: "8px",
          })
          .append(iframe)
          .append(lockerInfoContainer.append(lockerDetailsContainer));
      }

      toggleBoxNowDelivery();
    }

    function createEmbeddedIframe() {
      var $mapContainer = $("#box_now_delivery_embedded_map");
      var iframe = $("<iframe>", {
        src: buildBoxnowWidgetUrl(false),
        class: "boxnow-embedded-iframe",
        css: {
          width: "100%",
          height: "520px",
          border: 0,
        },
        allow: 'geolocation',
      });

      iframe.on("load", function () {
        hideBoxnowInlineSpinner($mapContainer);
      });

      showBoxnowInlineSpinner($mapContainer);

      return iframe;
    }

    // Track when popup was opened to prevent immediate closure
    var popupOpenTime = null;

    // Track previous BoxNow selection state to avoid redundant AJAX calls
    var prevBoxNowSelected = null;

    function createPopupMap() {
      createOverlay();
      showBoxnowPopupSpinner();

      var iframe = $("<iframe>", {
        src: buildBoxnowWidgetUrl(true),
        class: "boxnow-popup-iframe",
        css: {
          position: "fixed",
          top: 0,
          left: 0,
          border: 0,
          zIndex: 1000000,
          width: "100vw",
          height: "100vh",
          // Opaque white while loading so the dark overlay never bleeds through.
          background: "#fff",
          opacity: 0,
          transition: "opacity .2s ease",
        },
        allow: 'geolocation',
      });

      iframe.on("load", function () {
        hideBoxnowPopupSpinner();
        $(this).css("opacity", 1);
      });

      popupOpenTime = Date.now();

      $("body").append(iframe);
    }

    function createOverlay() {
      var overlay = $("<div>", {
        id: "box_now_delivery_overlay",
        css: {
          position: "fixed",
          top: 0,
          left: 0,
          width: "100%",
          height: "100%",
          backgroundColor: "rgba(0, 0, 0, 0.6)",
          zIndex: 999999,
        },
      });

      overlay.on("click", function () {
        removeBoxnowPopup();
        popupOpenTime = null;
      });

      $("body").append(overlay);
    }

    window.addEventListener("message", function (event) {
      var msg = normalizeBoxnowMessage(event);
      if (!msg) return;
      if (msg.type === "close") {
        removeBoxnowPopup();
        popupOpenTime = null;
        return;
      }
      // updateLockerDetailsContainer closes the popup itself (with its new-selection guard).
      updateLockerDetailsContainer(msg.data);
    });

    function updateLockerDetailsContainer(lockerData) {
      // Only the locker ID is strictly required; name/address are best-effort so a
      // minor widget schema change never silently drops the whole selection.
      if (!lockerData || !lockerData.boxnowLockerId) {
        return;
      }

      var locker_id      = lockerData.boxnowLockerId;
      var locker_country = lockerData.boxnowCountry || "";
      var locker_address = lockerData.boxnowLockerAddressLine1 || "";
      var locker_name    = lockerData.boxnowLockerName || "";

      // Check if this is a new locker selection (different from currently selected)
      var isNewSelection = false;
      var currentLockerData = safeLocalGet("box_now_selected_locker");
      if (currentLockerData) {
        try {
          var parsedCurrent = JSON.parse(currentLockerData);
          if (parsedCurrent.boxnowLockerId !== locker_id) {
            isNewSelection = true;
          }
        } catch (e) {
          // If we can't parse, treat as new selection
          isNewSelection = true;
        }
      } else {
        // No previous selection, so this is new
        isNewSelection = true;
      }

      safeLocalSet("box_now_selected_locker", JSON.stringify(lockerData));

      // Ensure the container exists inside our wrapper
      var $elementsContainer = $(".boxnow-elements-container");
      var $container = $("#box_now_selected_locker_details");
      
      if ($container.length === 0) {
        if (boxNowDeliverySettings.displayMode === "popup") {
          // First ensure button and container exist
          if ($elementsContainer.length === 0 || $("#box_now_delivery_button").length === 0) {
            addButton();
            $elementsContainer = $(".boxnow-elements-container");
          }
          // Now add locker details container if it still doesn't exist
          $container = $("#box_now_selected_locker_details");
          if ($container.length === 0 && $elementsContainer.length > 0) {
            $elementsContainer.append(
              '<div id="box_now_selected_locker_details" style="display:none;"></div>'
            );
            $container = $("#box_now_selected_locker_details");
          }
        }
      }

      const i18n = (boxNowDeliverySettings && boxNowDeliverySettings.i18n) ? boxNowDeliverySettings.i18n : {};
      const selectedLockerLabel = i18n.selectedLocker || "Selected locker";
      const lockerNameLabel = i18n.lockerName || "Locker Name:";
      const lockerAddressLabel = i18n.lockerAddress || "Locker Address:";

      var content =
        '<div class="boxnow-selected-locker-info">' +
          '<div class="boxnow-locker-label">' + escapeHtml(selectedLockerLabel) + '</div>' +
          '<div class="boxnow-locker-name"><strong>' + escapeHtml(lockerNameLabel) + '</strong> ' + escapeHtml(locker_name) + '</div>' +
          '<div class="boxnow-locker-address"><strong>' + escapeHtml(lockerAddressLabel) + '</strong> ' + escapeHtml(locker_address) + '</div>' +
        '</div>';

      $container.html(content);
      
      // Only show if BoxNow is selected
      if (isBoxNowDeliverySelected()) {
        $container.show();
      }

      if ($("#box_now_selected_locker_input").length === 0) {
        $("<input>")
          .attr({
            type: "hidden",
            id: "box_now_selected_locker_input",
            name: "box_now_selected_locker",
            value: JSON.stringify(lockerData),
          })
          .appendTo($container);
      } else {
        $("#box_now_selected_locker_input").val(JSON.stringify(lockerData));
      }

      // Close popup only if this is a NEW locker selection (not re-selection of the same locker)
      // Also ensure popup has been open for at least 500ms to prevent immediate closure
      // This allows users to click the button again and select a different locker
      var timeSinceOpen = popupOpenTime ? (Date.now() - popupOpenTime) : 0;
      if (boxNowDeliverySettings.displayMode === "popup" && isNewSelection && timeSinceOpen > 500) {
        removeBoxnowPopup();
        popupOpenTime = null;
      }

      // Only store in session when the locker actually changed (not on every periodic re-render)
      if (isNewSelection) {
        storeLockerIdInSession(locker_id, locker_country);
      }
      removeErrorMessage();
      togglePlaceOrderButton();
    }

    function showSelectedLockerDetailsFromLocalStorage() {
      // Only show locker details if BoxNow is selected
      if (!isBoxNowDeliverySelected()) {
        // Make sure locker details are hidden
        $("#box_now_selected_locker_details").hide();
        return;
      }
      var lockerData = safeLocalGet("box_now_selected_locker");
      if (lockerData) {
        try {
          var parsedData = JSON.parse(lockerData);
          if (parsedData.boxnowLockerId) {
            updateLockerDetailsContainer(parsedData);
          }
        } catch (e) {
          console.error("Error parsing locker data:", e);
        }
      }
    }

    function isLockerSelected() {
      const lockerData = safeLocalGet("box_now_selected_locker");
      if (lockerData) {
        try {
          const parsedData = JSON.parse(lockerData);
          return (
            parsedData.boxnowLockerId !== undefined &&
            parsedData.boxnowLockerId !== ""
          );
        } catch (e) {
          console.error("Error parsing locker data:", e);
          return false;
        }
      }
      return false;
    }

    function isBoxNowDeliverySelected() {
      var boxNowInput = findBoxNowInput();
      return boxNowInput.is(":checked");
    }

    function handleOrderSubmission(event) {
      if (isBoxNowDeliverySelected() && !isLockerSelected()) {
        event.preventDefault();
        event.stopImmediatePropagation();
        const i18n = (boxNowDeliverySettings && boxNowDeliverySettings.i18n) ? boxNowDeliverySettings.i18n : {};
        alert(i18n.selectLockerAlert || "Please select a locker to continue!");
        $('html, body').animate({
          scrollTop: $("#box_now_delivery_button").offset().top - 100
        }, 500, function() {
          $("#box_now_delivery_button").focus();
        });
        return false;
      }
    }

    function attachOrderPrevention() {
      $("form.checkout").off("submit").on("submit", handleOrderSubmission);
      $(document).off("click.boxnow").on("click.boxnow", ".wc-block-components-checkout-place-order-button", function (event) {
        if (isBoxNowDeliverySelected() && !isLockerSelected()) {
          event.preventDefault();
          event.stopImmediatePropagation();
          const i18n = (boxNowDeliverySettings && boxNowDeliverySettings.i18n) ? boxNowDeliverySettings.i18n : {};
          alert(i18n.selectLockerAlert || "Please select a locker to continue!");
          $('html, body').animate({
            scrollTop: $("#box_now_delivery_button").offset().top - 100
          }, 500, function() {
            $("#box_now_delivery_button").focus();
          });
          return false;
        }
      });
    }

    function appendErrorMessage() {
      const i18n = (boxNowDeliverySettings && boxNowDeliverySettings.i18n) ? boxNowDeliverySettings.i18n : {};
      const msg = (boxNowDeliverySettings.lockerNotSelectedMessage || i18n.selectLockerAlert || "Please select a locker to continue!");
      if (boxNowDeliverySettings.displayMode === "popup") {
        var lockerButton = $("#box_now_delivery_button");
        if (lockerButton.length > 0 && lockerButton.next(".boxnow-error-message").length === 0) {
          lockerButton.after('<span class="boxnow-error-message show">' + escapeHtml(msg) + '</span>');
        }
      } else if (boxNowDeliverySettings.displayMode === "embedded") {
        var embeddedMap = $("#box_now_delivery_embedded_map");
        if (embeddedMap.length > 0 && embeddedMap.next(".boxnow-error-message").length === 0) {
          embeddedMap.after('<span class="boxnow-error-message show">' + escapeHtml(msg) + '</span>');
        }
      }
    }

    function removeErrorMessage() {
      if (boxNowDeliverySettings.displayMode === "popup") {
        $("#box_now_delivery_button").next(".boxnow-error-message").removeClass("show").remove();
      } else if (boxNowDeliverySettings.displayMode === "embedded") {
        $("#box_now_delivery_embedded_map").next(".boxnow-error-message").removeClass("show").remove();
      }

      if ($("#box_now_selected_locker_details").is(":visible")) {
        $("#box_now_selected_locker_details").siblings(".boxnow-error-message").removeClass("show").remove();
      }
    }

    function togglePlaceOrderButton() {
      const isBoxNow = isBoxNowDeliverySelected();
      const hasLocker = isLockerSelected();
      const placeOrderButton = $(".wc-block-components-checkout-place-order-button");
      
      if (isBoxNow && !hasLocker) {
        placeOrderButton.addClass("disabled");
        appendErrorMessage();
      } else {
        placeOrderButton.removeClass("disabled");
        removeErrorMessage();
      }
    }

    function toggleBoxNowDelivery() {
      var boxNowInput = findBoxNowInput();
      var isBoxNowSelected = boxNowInput.length > 0 && boxNowInput.is(":checked");
      
      var $button = $("#box_now_delivery_button");
      var $lockerDetails = $("#box_now_selected_locker_details");
      var $embeddedMap = $("#box_now_delivery_embedded_map");
      var $container = $(".boxnow-elements-container");
      
      if (isBoxNowSelected) {
        // Show the container
        $container.show();
        
        // Show BoxNow button
        if ($button.length > 0) {
          $button.show();
        }
        
        // Show embedded map if in embedded mode
        if ($embeddedMap.length > 0 && boxNowDeliverySettings.displayMode === "embedded") {
          $embeddedMap.show();
        }
        
        // Restore selected locker from localStorage if available
        showSelectedLockerDetailsFromLocalStorage();

        // Make sure the WC session holds the locker (guards the save-from-session race).
        ensureLockerInSession();
      } else {
        // Completely hide all BoxNow elements when not selected
        $button.hide();
        $lockerDetails.hide();
        $embeddedMap.hide();
        $container.hide();
        $(".boxnow-error-message").hide();
        // Only clear on transition from selected → deselected, not on every periodic tick
        // Clear WC session so no stale locker ID goes into the order if user checks out
        // with a different method. Do NOT clear localStorage — preserves the selection
        // if the user returns to BOX NOW before placing the order.
        if (prevBoxNowSelected === true) {
          clearLockerIdInSession();
        }
      }
      prevBoxNowSelected = isBoxNowSelected;
      togglePlaceOrderButton();
    }

    // // Ако трябва изрично да занулим избрания автомат (напр. при натискане на "Изчисти"),
    // // може да се извика тази функция. По подразбиране при смяна на метод не трие localStorage,
    // // за да запазим избора при връщане към BOX NOW.
    // function clearSelectedLockerDetails() {
    //   localStorage.removeItem("box_now_selected_locker");
    //   $("#box_now_selected_locker_details").hide().empty();
    //   storeLockerIdInSession("");
    //   togglePlaceOrderButton();
    // }

    function init() {
      if (init.initialized) {
        // Re-check elements and state
        addButton();
        toggleBoxNowDelivery();
        togglePlaceOrderButton();
        return;
      }
      init.initialized = true;

      addButton();
      
      // Initial state - ensure elements are hidden until properly selected
      toggleBoxNowDelivery();

      $(document).off("change.boxnow").on(
        "change.boxnow",
        ".wc-block-components-radio-control__input",
        function () {
          // Delay to let React update first
          setTimeout(function() {
            toggleBoxNowDelivery();
            togglePlaceOrderButton();
          }, 10);
        }
      );

      attachOrderPrevention();
      togglePlaceOrderButton();
    }

    // Function to reinject BoxNow elements after React re-renders
    function reinjectBoxNowElements() {
      // Small delay to let React finish rendering
      setTimeout(function() {
        addButton();
        toggleBoxNowDelivery();
        togglePlaceOrderButton();
      }, 100);
    }

    // Periodic check to ensure BoxNow elements exist (handles React re-renders)
    function startPeriodicCheck() {
      setInterval(function() {
        var boxNowInput = findBoxNowInput();
        if (boxNowInput.length > 0) {
          var parentOption = boxNowInput.closest('.wc-block-components-radio-control__option');
          var containerInParent = parentOption.find('.boxnow-elements-container');
          
          // If BoxNow is available but container is missing, reinject
          if (containerInParent.length === 0) {
            addButton();
          }
          
          // Always ensure correct visibility state
          toggleBoxNowDelivery();
          togglePlaceOrderButton();
        }
      }, 500); // Check every 500ms
    }

    // Start periodic COD description check - this handles all scenarios
    function startCodDescriptionCheck() {
      var customDescription = boxNowDeliverySettings.customCodDescription;
      if (!customDescription) {
        return;
      }
      
      // Store original COD text globally to persist across React re-renders
      var originalCodText = null;
      var customDescriptionTrimmed = customDescription.trim();
      
      function findCodContentElement() {
        // Try multiple selectors to find COD content
        var selectors = [
          '[id*="cod_content"] > div',
          '[id*="cod"][id*="content"] > div',
          '#radio-control-wc-payment-method-options-cod_content > div',
          '.wc-block-components-radio-control-accordion-content[id*="cod"] > div'
        ];
        
        for (var i = 0; i < selectors.length; i++) {
          var $el = $(selectors[i]).first();
          if ($el.length > 0 && $el.text().trim() !== '') {
            return $el;
          }
        }
        return null;
      }
      
      function updateCodDescription() {
        var isBoxNowSelected = isBoxNowDeliverySelected();
        var $codContent = findCodContentElement();

        if (!$codContent) {
          return;
        }

        var currentText = $codContent.text().trim();

        // If current text is empty, skip
        if (currentText === '') {
          return;
        }

        // Capture original text - but only if it's not our custom message
        if (currentText !== customDescriptionTrimmed) {
          originalCodText = currentText;
        }

        if (isBoxNowSelected) {
          // BoxNow is selected - show custom description
          if (currentText !== customDescriptionTrimmed) {
            $codContent.text(customDescription);
          }
        } else {
          // BoxNow not selected - restore original if we changed it
          if (currentText === customDescriptionTrimmed && originalCodText) {
            $codContent.text(originalCodText);
          }
        }
      }
      
      // Run periodic check
      setInterval(updateCodDescription, 200);
      
      // Also listen to clicks on shipping methods - use event delegation for React elements
      $(document).on('click', '.wc-block-components-radio-control__input', function() {
        // After clicking any shipping method, wait for React to render and check
        setTimeout(updateCodDescription, 50);
        setTimeout(updateCodDescription, 150);
        setTimeout(updateCodDescription, 300);
        setTimeout(updateCodDescription, 500);
      });
      
      // Listen to clicks on payment methods too
      $(document).on('click', '[id*="cod"]', function() {
        setTimeout(updateCodDescription, 50);
        setTimeout(updateCodDescription, 150);
        setTimeout(updateCodDescription, 300);
      });
      
      // Listen to any click in the payment methods area
      $(document).on('click', '.wc-block-components-payment-method-label', function() {
        setTimeout(updateCodDescription, 50);
        setTimeout(updateCodDescription, 150);
        setTimeout(updateCodDescription, 300);
      });
    }

    $(window).on("load", function () {
      // Message handling is registered once at the top of the block branch via
      // normalizeBoxnowMessage — no second listener here (avoids double handling).

      // Delay init slightly to let WooCommerce blocks render first
      setTimeout(function() {
        init();
        
        // Ensure correct visibility state after init
        toggleBoxNowDelivery();
        togglePlaceOrderButton();
        
        // Start periodic check for React re-renders
        startPeriodicCheck();
        
        // Start periodic COD description check for all scenarios
        startCodDescriptionCheck();
      }, 200);

      // Also listen to WooCommerce block events
      $(document.body).on(
        "wc_blocks_cart_update wc_blocks_checkout_update",
        function () {
          reinjectBoxNowElements();
        }
      );
    });

  }
})(jQuery);
