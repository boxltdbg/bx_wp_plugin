/**
 * Calculate distance between two coordinates using Haversine Formula
 * @param {number} lat1 Latitude of first point
 * @param {number} lon1 Longitude of first point
 * @param {number} lat2 Latitude of second point
 * @param {number} lon2 Longitude of second point
 * @returns {number} Distance in kilometers
 */
function calculateHaversineDistance(lat1, lon1, lat2, lon2) {
  const earthRadius = 6371; // Earth radius in kilometers
  
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  
  const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
  
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  const distance = earthRadius * c;
  
  return distance;
}

/**
 * Find nearest lockers within 1km radius using strict mathematical filtering
 * Pure JavaScript code for browser console execution
 * NO filtering by city (addressLine2) or postal code - ONLY by distance
 */
async function findNearestLockersStrict() {
  // 3. STRICT FILTER - Maximum distance in kilometers
  const MAX_DISTANCE = 1.0;
  
  try {
    // 1. Get center point from localStorage
    const selectedLockerStr = localStorage.getItem('box_now_selected_locker');
    
    if (!selectedLockerStr) {
      console.log('BOX NOW: No selected locker found in localStorage. Skipping nearby search.');
      return [];
    }
    
    let selectedLocker;
    try {
      selectedLocker = JSON.parse(selectedLockerStr);
    } catch (e) {
      console.error('BOX NOW: Error parsing data from localStorage:', e);
      return [];
    }
    
    // 2. Get coordinates and ID
    const selectedLat = Number(selectedLocker.boxnowLockerLat);
    const selectedLng = Number(selectedLocker.boxnowLockerLng);
    const selectedId = selectedLocker.boxnowLockerId;
    
    // Validate coordinates
    if (isNaN(selectedLat) || isNaN(selectedLng) || 
        selectedLat === 0 || selectedLng === 0 ||
        Math.abs(selectedLat) > 90 || Math.abs(selectedLng) > 180) {
      console.log('BOX NOW: Invalid coordinates in localStorage. Skipping nearby search.');
      return [];
    }
    
    if (!selectedId) {
      console.log('BOX NOW: Missing Locker ID in localStorage. Skipping nearby search.');
      return [];
    }

    // DEBUG: Show center point being used
    console.log(`Calculating distance from Locker ID ${selectedId} located at [${selectedLat}, ${selectedLng}]`);
    
    // 1. Fetch all lockers from API
    const apiUrl = 'https://locationapi-production.boxnow.bg/v1/apms_bg-BG.json?size=medium';
    const response = await fetch(apiUrl);
    
    if (!response.ok) {
      throw new Error('Грешка при зареждане на автомати от API: ' + response.status);
    }
    
    const data = await response.json();
    if (!data || !Array.isArray(data.data)) {
      throw new Error('Грешка: Невалиден формат на отговора от API');
    }
    
    const nearbyLockers = [];
    
    // Process each locker
    data.data.forEach(locker => {
      // 1. STRICT EXCLUSION: Filter out the locker if its id matches boxnowLockerId from localStorage
      const lockerId = locker.id ? String(locker.id) : '';
      const selectedIdStr = selectedId ? String(selectedId) : '';
      
      if (lockerId === selectedIdStr) {
        return;
      }
      
      // 2. COORDINATE MAPPING & CONVERSION: Convert ALL to numbers immediately
      // Check both 'lat'/'lng' and 'latitude'/'longitude' from API JSON
      const lockerLat = Number(locker.lat || locker.latitude || 0);
      const lockerLng = Number(locker.lng || locker.longitude || 0);
      
      // 3. VALIDATION: Remove any locker if coordinates are missing or zero
      if (lockerLat === 0 || lockerLng === 0 || isNaN(lockerLat) || isNaN(lockerLng)) {
        return;
      }
      
      // 4. MATH: Use standard Haversine Formula (Earth's radius 6371)
      const distance = calculateHaversineDistance(selectedLat, selectedLng, lockerLat, lockerLng);
      
      // DEBUGGING: Required specific log for tracking
      console.log("Checking Locker " + lockerId + ": Distance is " + distance + " km");
      
      // 5. STRICT RADIUS & EXCLUSION: Only show lockers where distance > 0 AND distance <= 1.0 km
      if (distance > 0 && distance <= MAX_DISTANCE) {
        const distanceMeters = Math.round(distance * 1000);
        
        nearbyLockers.push({
          id: lockerId,
          name: locker.name || 'Автомат ' + lockerId,
          address: locker.addressLine1 || locker.address || '',
          city: locker.city || locker.municipality || '',
          distance: Math.round(distance * 1000) / 1000, // Matching the field name used in the dialog loop
          distanceKm: Math.round(distance * 1000) / 1000,
          distanceMeters: distanceMeters,
          image: locker.image || '',
          description: locker.description || ''
        });
      }
    });
    
    // Sort by distance (ascending) - closest first
    nearbyLockers.sort((a, b) => {
      return a.distanceKm - b.distanceKm;
    });
    
    // 4. TEST OUTPUT
    console.log(`Намерени са ${nearbyLockers.length} автомата в радиус от 1 км`);
    
    if (nearbyLockers.length === 0) {
      console.log('Няма автомати в радиус от 1 км.');
    } else {
      nearbyLockers.forEach((locker, index) => {
        console.log(`${index + 1}. ${locker.name} - Разстояние: ${locker.distanceMeters} метра (${locker.distanceKm} км)`);
      });
    }
    
    return nearbyLockers;
  } catch (error) {
    console.error('Грешка при намиране на най-близките автомати:', error);
    return [];
  }
}

// Make function available globally for console execution
if (typeof window !== 'undefined') {
  window.findNearestLockersStrict = findNearestLockersStrict;
}

document.addEventListener("DOMContentLoaded", function () {
  const createVouchersEnabled = document.getElementById(
    "create_vouchers_enabled"
  );

  const createVouchersBtnSmall = document.getElementById(
    "box_now_create_voucher_small"
  );
  const createVouchersBtnMedium = document.getElementById(
    "box_now_create_voucher_medium"
  );
  const createVouchersBtnLarge = document.getElementById(
    "box_now_create_voucher_large"
  );

  const buttons = [
    createVouchersBtnSmall,
    createVouchersBtnMedium,
    createVouchersBtnLarge,
  ];
  const sizes = ["small", "medium", "large"];

  // Create the sizeMapping object
  const sizeMapping = {
    small: 1,
    medium: 2,
    large: 3,
  };

  for (let i = 0; i < buttons.length; i++) {
    let button = buttons[i];
    let size = sizes[i];

    // Check if button exists and enabled
    if (!button || createVouchersEnabled.value !== "true") {
      if (button) button.disabled = true;
      continue;
    }

    button.disabled = false;

    button.addEventListener("click", function () {
      const order_id = document.getElementById("box_now_order_id").value;
      const max_vouchers = parseInt(
        document.getElementById("max_vouchers").value,
        10
      );

      const allowMultipleEl = document.getElementById("boxnow_allow_multiple");
      const isCodEl = document.getElementById("boxnow_is_cod");
      const allowMultiple = allowMultipleEl && allowMultipleEl.value === "yes";
      const isCod = isCodEl && isCodEl.value === "yes";

      let voucher_quantity;
      if (!allowMultiple || isCod) {
        voucher_quantity = "1";
      } else {
        voucher_quantity = document.getElementById("box_now_voucher_code").value;
        if (!voucher_quantity) {
          alert("Моля добавете задължителнелната информация.");
          return;
        }
        if (parseInt(voucher_quantity, 10) > max_vouchers) {
          alert("Непозволен брой товарителници.");
          return;
        }
      }

      if (!order_id) {
        alert("Моля добавете задължителнелната информация.");
        return;
      }

      // Disable the button immediately after it is clicked
      button.disabled = true;

      // Make an AJAX call to your existing function
      const data = {
        action: "create_box_now_vouchers",
        order_id: order_id,
        voucher_quantity: voucher_quantity,
        compartment_size: sizeMapping[size], // Send the selected compartment size
        security: myAjax.nonce,
      };

      jQuery.post(
        myAjax.ajaxurl,
        data,
        function (response) {
          button.disabled = false;

          if (response.success) {
            if (response.data && response.data.new_parcel_ids) {
              const parcelIds = response.data.new_parcel_ids;
              const boxNowParcelIdsEl =
                document.getElementById("box_now_parcel_ids");
              if (boxNowParcelIdsEl) {
                boxNowParcelIdsEl.value = JSON.stringify(parcelIds);
              }

              // Display the parcel ID links
              displayParcelIdLinks(parcelIds);

              // Disable all buttons after creating the vouchers
              buttons.forEach((btn) => btn && (btn.disabled = true));
            } else {
              alert(
                "Грешка: Нов/нови номера на товарителница(и) не са налични в данните от отговора."
              );
              button.disabled = false; // Re-enable the button if there is an error
            }
          } else {
            // Check if locker is unavailable and show alternative lockers
            if (response.data && typeof response.data === 'object' && response.data.locker_unavailable) {
              showAlternativeLockersDialog(response.data);
            } else {
              var errorMsg = 'Неуспешно създаване на товарителница';
              if (typeof response.data === 'string' && response.data) {
                errorMsg = response.data;
              } else if (response.data && response.data.message) {
                errorMsg = response.data.message;
              }
              alert("Грешка: " + errorMsg);
              console.error('BOX NOW Voucher Error:', response);
            }
            button.disabled = false; // Re-enable the button if there is an error
          }
        },
        "json"
      ).fail(function(jqXHR, textStatus, errorThrown) {
        console.error('BOX NOW AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
        // Try to parse response as JSON for better error message
        var errorMsg = 'Невалидни размери на продукта(продуктите) - моля уверете се че продукта(продуктите) се събират в автомат на BOX NOW!';
        try {
          if (jqXHR.responseText) {
            var jsonResponse = JSON.parse(jqXHR.responseText);
            if (jsonResponse.data) {
              errorMsg = typeof jsonResponse.data === 'string' ? jsonResponse.data : (jsonResponse.data.message || errorMsg);
            }
          }
        } catch (e) {
          console.error('Could not parse error response:', e);
        }
        alert("Грешка: " + errorMsg);
        button.disabled = false;
      });
    });
  }

  const boxNowParcelIdsEl = document.getElementById("box_now_parcel_ids");
  if (boxNowParcelIdsEl) {
    const parcelIds = JSON.parse(boxNowParcelIdsEl.value || "[]");
    displayParcelIdLinks(parcelIds);

    // Enable or disable the button based on whether there are vouchers created for the order
    buttons.forEach((btn) => btn && (btn.disabled = parcelIds.length > 0));
  }
});

function displayParcelIdLinks(parcelIds) {
  const pdfLinkContainer = document.getElementById("box_now_voucher_link");
  pdfLinkContainer.innerHTML = ""; // Clear the container

  parcelIds.forEach((parcelId) => {
    const orderId = document.getElementById("box_now_order_id").value;

    const newLinkHtml = `
      <a href="#" data-parcel-id="${parcelId}" class="parcel-id-link box-now-link">&#128196; ${parcelId}</a>
      <button class="cancel-voucher-btn" data-order-id="${orderId}" style="color: white; background-color: red; margin: 4px 0; border: none; border-radius: 4px; cursor: pointer; padding: 6px 12px; font-size: 13px;">&#9664; Cancel Voucher</button>
      <br>`;

    pdfLinkContainer.innerHTML += newLinkHtml;
  });

  // Event delegation for parcelIdLinks and cancelButtons
  pdfLinkContainer.addEventListener("click", function (event) {
    if (event.target.matches(".parcel-id-link")) {
      event.preventDefault();
      const parcelId = event.target.getAttribute("data-parcel-id");
      window.open(
        myAjax.ajaxurl +
          "?action=print_box_now_voucher&parcel_id=" +
          encodeURIComponent(parcelId) +
          "&_wpnonce=" +
          encodeURIComponent(myAjax.nonce),
        "_blank",
        "noopener,noreferrer"
      );
    }

    if (event.target.matches(".cancel-voucher-btn")) {
      handleCancelVoucherClick(event);
    }
  });
}

function showAlternativeLockersDialog(errorData) {
  // CLEAN START: Clear any existing dialogs before showing new ones
  jQuery('#boxnow-alternative-lockers-dialog').remove();

  const message = errorData.message || 'Избраният автомат не е наличен. Моля изберете друг автомат.';
  const nearbyLockers = errorData.nearby_lockers || [];
  const currentLockerId = errorData.current_locker_id || '';

  if (nearbyLockers.length === 0) {
    alert(message + '\n\nНяма налични алтернативни автомати.');
    return;
  }

  // Create dialog HTML
  let dialogHTML = `
    <div id="boxnow-alternative-lockers-dialog" style="
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      z-index: 100000;
      display: flex;
      align-items: center;
      justify-content: center;
    ">
      <div style="
        background: white;
        border-radius: 12px;
        padding: 24px;
        max-width: 600px;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
      ">
        <h2 style="margin-top: 0; color: #d32f2f;">⚠️ Избраният автомат не е наличен</h2>
        <p style="margin-bottom: 20px; color: #666;">Изберете един от най-близките автомати:</p>
        <div id="boxnow-lockers-list" style="margin-bottom: 20px;">
  `;

  nearbyLockers.forEach((locker, index) => {
    const fullAddress = [locker.address, locker.city].filter(Boolean).join(', ');
    dialogHTML += `
      <div class="boxnow-locker-option" data-locker-id="${locker.id}" style="
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 12px;
        cursor: pointer;
        transition: all 0.2s;
      " onmouseover="this.style.borderColor='#84C33F'; this.style.backgroundColor='#f5fff0';" onmouseout="this.style.borderColor='#e0e0e0'; this.style.backgroundColor='white';">
        <div style="display: flex; justify-content: space-between; align-items: start;">
          <div style="flex: 1;">
            <h3 style="margin: 0 0 8px 0; color: #1d2327; font-size: 16px;">
              ${locker.name || 'Автомат ' + locker.id}
            </h3>
            <p style="margin: 0 0 4px 0; color: #666; font-size: 14px;">
              <strong>Адрес:</strong> ${fullAddress || locker.address || 'Адрес не е наличен'}
            </p>
            ${locker.description ? `<p style="margin: 4px 0; color: #888; font-size: 13px;">${locker.description}</p>` : ''}
            ${locker.distance !== false && locker.distance !== null ? `<p style="margin: 8px 0 0 0; color: #84C33F; font-weight: bold; font-size: 14px;">📍 ${locker.distance} km</p>` : ''}
          </div>
          ${locker.image ? `<img src="${locker.image}" alt="Автомат" style="width: 80px; height: 60px; object-fit: cover; border-radius: 6px; margin-left: 12px;">` : ''}
        </div>
      </div>
    `;
  });

  dialogHTML += `
        </div>
        <div style="display: flex; gap: 12px; justify-content: flex-end;">
          <button id="boxnow-open-map" class="button button-primary" style="
            background: #84C33F;
            border: 1px solid #6aa02f;
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
          ">Отвори карта</button>
          <button id="boxnow-cancel-locker-selection" class="button" style="
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #1d2327;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
          ">Отказ</button>
        </div>
      </div>
    </div>
  `;

  // Add dialog to page
  jQuery('body').append(dialogHTML);

  // Handle locker selection
  jQuery('.boxnow-locker-option').on('click', function() {
    const selectedLockerId = jQuery(this).data('locker-id');
    const orderId = document.getElementById('box_now_order_id').value;
    
    if (!selectedLockerId || !orderId) {
      alert('Грешка: Липсва информация за избрания автомат.');
      return;
    }

    // Update locker ID in order
    jQuery.post(
      myAjax.ajaxurl,
      {
        action: 'boxnow_update_order_locker',
        order_id: orderId,
        locker_id: selectedLockerId,
        security: myAjax.nonce
      },
      function(response) {
        if (response.success) {
          // SYNC DATA: Update localStorage with the newly selected alternative locker
          // Fetch its details to ensure we have lat/lng for future calculations
          jQuery.get('https://locationapi-production.boxnow.bg/v1/apms_bg-BG.json?size=medium')
            .done(function(apiResponse) {
              if (apiResponse && apiResponse.data) {
                const locker = apiResponse.data.find(l => String(l.id) === String(selectedLockerId));
                if (locker) {
                  const lockerData = {
                    boxnowLockerId: locker.id,
                    boxnowLockerLat: locker.lat || locker.latitude,
                    boxnowLockerLng: locker.lng || locker.longitude,
                    boxnowLockerName: locker.name,
                    boxnowLockerAddressLine1: locker.addressLine1 || locker.address
                  };
                  localStorage.setItem("box_now_selected_locker", JSON.stringify(lockerData));
                }
              }
            });

          // Update the locker ID field
          jQuery('#_boxnow_locker_id').val(selectedLockerId);
          
          // Close dialog
          jQuery('#boxnow-alternative-lockers-dialog').remove();
          
          // Show success message
          alert('Автоматът е променен успешно. Можете да опитате отново да създадете товарителница.');

          // Refresh address field if helper is available (avoid full page reload)
          if (typeof window.fetchAndShowBoxNowAddress === 'function') {
            window.fetchAndShowBoxNowAddress(selectedLockerId);
          }
        } else {
          alert('Грешка при промяна на автомата: ' + (response.data || 'Неизвестна грешка'));
        }
      },
      'json'
    );
  });

  // Handle cancel button
  jQuery('#boxnow-cancel-locker-selection').on('click', function() {
    jQuery('#boxnow-alternative-lockers-dialog').remove();
  });

  // Open map (uses existing admin popup trigger)
  jQuery('#boxnow-open-map').on('click', function() {
    const adminButton = jQuery('#admin-boxnow-open-popup');
    if (adminButton.length) {
      adminButton.trigger('click');
    } else if (typeof window.openBoxNowAdminPopup === 'function') {
      window.openBoxNowAdminPopup();
    }
    jQuery('#boxnow-alternative-lockers-dialog').remove();
  });

  // Close on overlay click
  jQuery('#boxnow-alternative-lockers-dialog').on('click', function(e) {
    if (e.target === this) {
      jQuery(this).remove();
    }
  });
}

function handleCancelVoucherClick(event) {
  event.preventDefault();

  const orderId = event.target.getAttribute("data-order-id");
  const parcelId =
    event.target.previousElementSibling.getAttribute("data-parcel-id");

  const nonce = myAjax.nonce;

  const data = {
    action: "cancel_voucher",
    order_id: orderId,
    parcel_id: parcelId,
    nonce: nonce,
  };

  jQuery.post(
    myAjax.ajaxurl,
    data,
    function (response) {
      if (response.success) {
        let canceledParcelId = response.data;
        const boxNowParcelIdsEl = document.getElementById("box_now_parcel_ids");
        if (boxNowParcelIdsEl) {
          const parcelIds = JSON.parse(boxNowParcelIdsEl.value || "[]");
          const index = parcelIds.indexOf(canceledParcelId);
          if (index !== -1) {
            parcelIds.splice(index, 1);
          }
          boxNowParcelIdsEl.value = JSON.stringify(parcelIds);

          // Enable the create voucher button if all vouchers have been canceled
          if (parcelIds.length === 0) {
            const createVouchersBtn = document.getElementById(
              "box_now_create_voucher"
            );
            if (createVouchersBtn) {
              createVouchersBtn.disabled = false;
            }
          }
        }
        location.reload();
      } else {
        console.error("Грешка - при отмяна на товарителницата:", response.data);
      }
    },
    "json"
  );
}
