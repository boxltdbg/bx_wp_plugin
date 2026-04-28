(function ($) {
  function escapeHtml(str) {
    return String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
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

        iframe.on("load", function () {
          window.addEventListener("message", function (event) {
            if (typeof event.data.boxnowClose !== "undefined") {
              // Handle close event
            } else {
              updateLockerDetailsContainer(event.data);
            }
          });
        });
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
          backgroundColor: "rgba(0, 0, 0, 0)",
          zIndex: 9998,
        },
      });

      overlay.on("click", function () {
        $("#box_now_delivery_overlay").remove();
        $("iframe[src^='https://widget-v5.boxnow.bg/popup.html']").remove();
      });

      $("body").append(overlay);
    }

    function createPopupMap() {
      let gpsOption = (boxNowDeliverySettings && boxNowDeliverySettings.gps_option) ? boxNowDeliverySettings.gps_option : "on";
      let postalCode = $('input[name="billing_postcode"]').val();
      let src = "https://widget-v5.boxnow.bg/popup.html";

      src += "?zip=" + encodeURIComponent(postalCode);
      if (gpsOption === "on") {
        src += "&gps=yes";
      }
      src += "&autoclose=yes&autoselect=no";

      let iframe = $("<iframe>", {
        src: src,
        css: {
          position: "fixed",
          top: "50%",
          left: "50%",
          width: "80%",
          height: "80%",
          border: 0,
          borderRadius: "20px",
          transform: "translate(-50%, -50%)",
          zIndex: 9999,
        },
        allow: 'geolocation',
      });

      window.addEventListener("message", function (event) {
        if (
          event.data === "closeIframe" ||
          event.data.boxnowClose !== undefined
        ) {
          $("#box_now_delivery_overlay").remove();
          iframe.remove();
        } else {
          updateLockerDetailsContainer(event.data);
        }
      });

      createOverlay();
      $("body").append(iframe);
    }

    /**
     * Записва избрания автомат в сесията и скритото поле на чекаута.
     * Наместваме уникално име, за да не колидираме с block checkout функцията.
     */
    function storeLockerIdInSessionClassic(lockerId) {
      const safeLockerId = lockerId || "";

      $.ajax({
        url: boxNowDeliverySettings.ajax_url,
        type: "POST",
        data: {
          action: "set_boxnow_locker_id",
          locker_id: safeLockerId,
        },
      });

      const $hiddenField = $('input[name="_boxnow_locker_id"]');
      if ($hiddenField.length) {
        $hiddenField.val(safeLockerId);
      }
    }

    function createEmbeddedIframe() {
      let gpsOption = (boxNowDeliverySettings && boxNowDeliverySettings.gps_option) ? boxNowDeliverySettings.gps_option : "on";
      let postalCode = $('input[name="billing_postcode"]').val();
      let src = "https://widget-v5.boxnow.bg/";

      src += "?zip=" + encodeURIComponent(postalCode);
      if (gpsOption === "on") {
        src += "&gps=yes";
      }

      return $("<iframe>", {
        src: src,
        css: {
          width: "100%",
          height: "520px",
          border: 0,
        },
        allow: 'geolocation',
      });
    }

    window.addEventListener("message", function (event) {
      if (typeof event.data.boxnowClose !== "undefined") {
        // handle close
        if (boxNowDeliverySettings.displayMode === "popup") {
          $(".boxnow-popup").remove();
        }
      } else {
        // updateLockerDetailsContainer already stores to localStorage and session;
        // calling showSelectedLockerDetailsFromLocalStorage here too would fire a
        // duplicate session AJAX — skip it.
        updateLockerDetailsContainer(event.data, true);
      }
    });


    function updateLockerDetailsContainer(lockerData, saveToSession) {
      if (saveToSession === undefined) saveToSession = true;
      if (
        lockerData.boxnowLockerId === undefined ||
        lockerData.boxnowLockerAddressLine1 === undefined ||
        lockerData.boxnowLockerName === undefined
      ) {
        return;
      }

      const lockerId = lockerData.boxnowLockerId;
      const lockerAddress = lockerData.boxnowLockerAddressLine1 || "";
      const lockerName = lockerData.boxnowLockerName || "";
      const i18n = (boxNowDeliverySettings && boxNowDeliverySettings.i18n) ? boxNowDeliverySettings.i18n : {};
      const selectedLockerLabel = i18n.selectedLocker || "Selected locker";
      const lockerNameLabel = i18n.lockerName || "Locker Name:";
      const lockerAddressLabel = i18n.lockerAddress || "Locker Address:";

      localStorage.setItem("box_now_selected_locker", JSON.stringify(lockerData));

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
        storeLockerIdInSessionClassic(lockerId);
      }
    }

    function isClassicBoxNowSelected() {
      return $("#shipping_method_0_box_now_delivery").is(":checked");
    }

    function showSelectedLockerDetailsFromLocalStorage() {
      if (!isClassicBoxNowSelected()) {
        return;
      }

      var lockerData = localStorage.getItem("box_now_selected_locker");
      if (lockerData) {
        // Pass false: we are only restoring the UI from localStorage, the locker ID
        // is already in the WC session from when the user originally selected it.
        // Firing the session AJAX here races with WooCommerce's update_order_review
        // request and causes PHP session lock contention that prevents payment
        // methods from loading.
        updateLockerDetailsContainer(JSON.parse(lockerData), false);
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
      localStorage.removeItem("box_now_selected_locker");
      $("#box_now_selected_locker_details").hide().empty();
      storeLockerIdInSessionClassic("");
    }

    $(document).ready(function () {
      function addOrderValidation() {
        $(document.body).on("click", "#place_order", function (event) {
          var selectedMethod = $('input[type="radio"][name="shipping_method[0]"]:checked').val();
          var isBoxNow = selectedMethod === "box_now_delivery";
          var lockerData = localStorage.getItem("box_now_selected_locker");

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
      $.ajax({
        url: boxNowDeliverySettings.ajax_url,
        type: "POST",
        data: {
          action: "set_boxnow_locker_id",
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

    /**
     * Stores the selected locker ID in the session.
     */
    function storeLockerIdInSession(lockerId) {
      if (!lockerId || lockerId === "") {
        // Don't log error for empty locker ID (when clearing selection)
        if (lockerId === "") {
          return;
        }
        console.error("No locker ID provided.");
        return;
      }

      $.ajax({
        url: boxNowDeliverySettings.ajax_url,
        type: "POST",
        data: {
          action: "set_boxnow_locker_id",
          locker_id: lockerId,
        },
        success: function (response) {
          if (response.success) {
            console.log("Locker ID stored in session successfully:", lockerId);
          } else {
            console.error("Failed to store locker ID:", response.data);
          }
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
      let gpsOption = (boxNowDeliverySettings && boxNowDeliverySettings.gps_option) ? boxNowDeliverySettings.gps_option : "on";
      let postalCode = $('input[name="billing_postcode"]').val() || "";
      let src = "https://widget-v5.boxnow.bg/";

      src += "?zip=" + encodeURIComponent(postalCode);
      if (gpsOption === "on") {
        src += "&gps=yes";
      }

      return $("<iframe>", {
        src: src,
        css: {
          width: "100%",
          height: "520px",
          border: 0,
        },
        allow: 'geolocation',
      });
    }

    // Track when popup was opened to prevent immediate closure
    var popupOpenTime = null;

    // Track last known BOX NOW selection state to avoid firing clearLockerIdInSession
    // on every periodic poll tick (would send an AJAX request every 500ms).
    var lastBoxNowSelectedState = null;

    function createPopupMap() {
      let gpsOption = (boxNowDeliverySettings && boxNowDeliverySettings.gps_option) ? boxNowDeliverySettings.gps_option : "on";
      let postalCode = $("input[name='billing_postcode']").val() || "";
      let src = "https://widget-v5.boxnow.bg/popup.html";

      src += "?zip=" + encodeURIComponent(postalCode);
      if (gpsOption === "on") {
        src += "&gps=yes";
      }
      src += "&autoclose=yes&autoselect=no";

      let iframe = $("<iframe>", {
        src: src,
        css: {
          position: "fixed",
          top: "50%",
          left: "50%",
          width: "80%",
          height: "80%",
          border: 0,
          borderRadius: "20px",
          transform: "translate(-50%, -50%)",
          zIndex: 9999,
        },
        allow: 'geolocation',
      });

      // Mark popup as just opened
      popupOpenTime = Date.now();
      
      createOverlay();
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
          backgroundColor: "rgba(0, 0, 0, 0.5)",
          zIndex: 9998,
        },
      });

      overlay.on("click", function () {
        $("#box_now_delivery_overlay").remove();
        $("iframe[src^='https://widget-v5.boxnow.bg/popup.html']").remove();
        popupOpenTime = null;
      });

      $("body").append(overlay);
    }

    window.addEventListener("message", function (event) {
      if (
        event.data === "closeIframe" ||
        event.data.boxnowClose !== undefined
      ) {
        $("#box_now_delivery_overlay").remove();
        $("iframe[src^='https://widget-v5.boxnow.bg/popup.html']").remove();
        popupOpenTime = null;
      } else {
        updateLockerDetailsContainer(event.data);
      }
    });

    function updateLockerDetailsContainer(lockerData) {
      if (
        !lockerData ||
        lockerData.boxnowLockerId === undefined ||
        lockerData.boxnowLockerAddressLine1 === undefined ||
        lockerData.boxnowLockerName === undefined
      ) {
        return;
      }

      var locker_id = lockerData.boxnowLockerId;
      var locker_address = lockerData.boxnowLockerAddressLine1;
      var locker_name = lockerData.boxnowLockerName;

      // Check if this is a new locker selection (different from currently selected)
      var isNewSelection = false;
      var currentLockerData = localStorage.getItem("box_now_selected_locker");
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

      localStorage.setItem("box_now_selected_locker", JSON.stringify(lockerData));

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
        $("#box_now_delivery_overlay").remove();
        $("iframe[src^='https://widget-v5.boxnow.bg/popup.html']").remove();
        popupOpenTime = null;
      }

      storeLockerIdInSession(locker_id);
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
      var lockerData = localStorage.getItem("box_now_selected_locker");
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
      const lockerData = localStorage.getItem("box_now_selected_locker");
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
      } else {
        // Completely hide all BoxNow elements when not selected
        $button.hide();
        $lockerDetails.hide();
        $embeddedMap.hide();
        $container.hide();
        $(".boxnow-error-message").hide();
        // Only clear when the state actually transitions from selected → not-selected.
        // Calling these on every periodic poll tick (every 500ms) would flood the
        // server with AJAX requests and block WooCommerce checkout from updating.
        if (lastBoxNowSelectedState === true) {
          clearLockerIdInSession();
          localStorage.removeItem("box_now_selected_locker");
        }
      }
      lastBoxNowSelectedState = isBoxNowSelected;
      togglePlaceOrderButton();
    }

    // Ако трябва изрично да занулим избрания автомат (напр. при натискане на "Изчисти"),
    // може да се извика тази функция. По подразбиране при смяна на метод не трие localStorage,
    // за да запазим избора при връщане към BOX NOW.
    function clearSelectedLockerDetails() {
      localStorage.removeItem("box_now_selected_locker");
      $("#box_now_selected_locker_details").hide().empty();
      storeLockerIdInSession("");
      togglePlaceOrderButton();
    }

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
          var isNowSelected = boxNowInput.is(":checked");

          // If BOX NOW is available but our container was wiped by a React re-render, reinject.
          if (containerInParent.length === 0) {
            addButton();
            toggleBoxNowDelivery();
            togglePlaceOrderButton();
            return;
          }

          // Only call toggleBoxNowDelivery when selection state actually changed,
          // to avoid firing clearLockerIdInSession AJAX on every tick.
          if (isNowSelected !== lastBoxNowSelectedState) {
            toggleBoxNowDelivery();
            togglePlaceOrderButton();
          }
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
      // Single message listener for popup map
      window.addEventListener("message", function (event) {
        if (
          event.data === "closeIframe" ||
          event.data.boxnowClose !== undefined
        ) {
          $("#box_now_delivery_overlay").remove();
          $("iframe[src^='https://widget-v5.boxnow.bg/popup.html']").remove();
          popupOpenTime = null;
        } else if (event.data && event.data.boxnowLockerId) {
          updateLockerDetailsContainer(event.data);
        }
      });

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
