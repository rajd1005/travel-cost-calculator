jQuery(document).ready(function($) {
    
    // --- WIZARD NAVIGATION LOGIC ---
    let currentStep = 1;
    const totalSteps = 2;

    function updateStepView() {
        $('.tcc-step').removeClass('active');
        $('#tcc-step-' + currentStep).addClass('active');

        $('#tcc_prev_step').prop('disabled', currentStep === 1).css('opacity', currentStep === 1 ? '0.5' : '1');
        $('#tcc_next_step').prop('disabled', currentStep === totalSteps).css('opacity', currentStep === totalSteps ? '0.5' : '1');
        $('#tcc_step_indicator').text(`Step ${currentStep} of ${totalSteps}`);
        
        triggerLiveCalculation();
    }

    $('#tcc_next_step').on('click', function() {
        if (currentStep < totalSteps) { currentStep++; updateStepView(); }
    });

    $('#tcc_prev_step').on('click', function() {
        if (currentStep > 1) { currentStep--; updateStepView(); }
    });
    
    updateStepView(); // Initialize step view

    // --- NEW: Quantity Button Action Logic ---
    $(document).on('click', '.tcc-qty-btn', function() {
        let $wrapper = $(this).closest('.tcc-qty-wrapper');
        let $input = $wrapper.find('input[type="number"]');
        let val = parseInt($input.val());
        let min = parseInt($input.attr('min'));
        
        if (isNaN(min)) min = 0;
        if (isNaN(val)) val = min > 0 ? min - 1 : 0; 

        if ($(this).hasClass('plus')) {
            $input.val(val + 1);
        } else if ($(this).hasClass('minus')) {
            if (val > min) {
                $input.val(val - 1);
            }
        }
        $input.trigger('input'); 
    });


    // STEP 1 MUTUALLY EXCLUSIVE ACCORDIONS
    $('.tcc-step-accordion-header').on('click', function() {
        let $body = $(this).next('.tcc-step-accordion-body');
        let $icon = $(this).find('.tcc-acc-icon');
        
        if ($body.is(':visible')) {
            $body.slideUp(150);
            $icon.text('▼'); 
        } else {
            // Close other step accordions
            $('.tcc-step-accordion-body').slideUp(150);
            $('.tcc-step-accordion-header .tcc-acc-icon').text('▼');
            
            // Open clicked
            $body.slideDown(150);
            $icon.text('▲');
        }
    });

    // SETTINGS DASHBOARD ACCORDIONS
    $('.tcc-accordion-header').on('click', function() {
        let $body = $(this).next('.tcc-accordion-body');
        $('.tcc-accordion-body').not($body).slideUp(150);
        $body.slideToggle(150);
    });

    function formatINR(amount) {
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2 }).format(amount);
    }

    function populateDropdown(selectElement, optionsArray) {
        let $el = $(selectElement);
        let currentVal = $el.val();
        $el.empty();
        
        if (typeof optionsArray === 'string') {
            optionsArray = optionsArray.split(',').map(item => item.trim()).filter(item => item);
        }

        if(optionsArray && Array.isArray(optionsArray) && optionsArray.length > 0) {
            let isSpecial = $el.is('#calc_pickup, #calc_drop, .transport_dropdown, .transport_pickup_dropdown');
            if(isSpecial) $el.append('<option value="">-- Select --</option>');

            $.each(optionsArray, function(i, val) { $el.append($('<option></option>').val(val).text(val)); });
            
            if(currentVal && $el.find("option[value='" + currentVal + "']").length) {
                $el.val(currentVal);
            } else if(isSpecial) {
                $el.val('');
            } else {
                $el.prop('selectedIndex', 0); 
            }
        } else {
            $el.append('<option value="">N/A</option>');
        }
    }

    function rebuildDestinationDropdowns() {
        if(typeof tccMasterData === 'undefined') return;
        let dests = Object.keys(tccMasterData);
        
        populateDropdown('#calc_destination', dests);
        populateDropdown('#set_hotel_dest', dests);
        populateDropdown('#set_trans_dest', dests);

        let m_dest = $('#master_dest_select').val();
        let $md = $('#master_dest_select');
        $md.empty().append('<option value="">-- Add New --</option>');
        $.each(dests, function(i, val) { $md.append($('<option></option>').val(val).text(val)); });
        if(m_dest && dests.includes(m_dest)) $md.val(m_dest);
    }

    function updateCalculatorDropdowns() {
        let dest = $('#calc_destination').val();
        if(typeof tccMasterData !== 'undefined' && tccMasterData[dest]) {
            populateDropdown('#calc_pickup', tccMasterData[dest].pickups);
            populateDropdown('#calc_drop', tccMasterData[dest].pickups); 
            populateDropdown('#calc_hotel_cat', tccMasterData[dest].hotel_categories);
            
            $('.stay_place_dropdown').each(function() { 
                populateDropdown(this, tccMasterData[dest].stay_places); 
                $(this).prepend('<option value="No Hotel">No Hotel Required</option>'); 
                $(this).prepend('<option value="" selected>-- Select --</option>'); 
            });
            $('.stay_cat_dropdown').each(function() { populateDropdown(this, tccMasterData[dest].hotel_categories); });
            $('.transport_dropdown').each(function() { populateDropdown(this, tccMasterData[dest].vehicles); });
            $('.transport_pickup_dropdown').each(function() { populateDropdown(this, tccMasterData[dest].pickups); });
            
            setTimeout(function() { $('.night-stay-row').each(function() { updateRowHotels($(this)); }); }, 100);
        }
    }

    function updateRowHotels($row) {
        let dest = $('#calc_destination').val();
        let cat = $row.find('.stay_cat_dropdown').val(); 
        let place = $row.find('.stay_place_dropdown').val();
        let $hotelDrop = $row.find('.stay_hotel_dropdown');
        let $catDrop = $row.find('.stay_cat_dropdown');
        let $hiddenInput = $row.find('.stay_hotel_hidden');
        let preValue = $hiddenInput.val(); 

        if (place === 'No Hotel') {
            $catDrop.prop('disabled', true).val('');
            $hotelDrop.empty().append('<option value="None" selected>No Room Provided</option>').prop('disabled', true);
            $hiddenInput.val('None');
            triggerLiveCalculation();
            return;
        } else {
            $catDrop.prop('disabled', false);
            $hotelDrop.prop('disabled', false);
        }

        if(dest && cat && place) {
            $hotelDrop.html('<option value="">Loading...</option>');
            $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_fetch_hotel_names', dest: dest, place: place, cat: cat }, function(res) {
                $hotelDrop.empty();
                if(res.success && res.data.length > 0) {
                    $.each(res.data, function(i, item) { 
                        let hName = typeof item === 'object' ? item.hotel_name : item;
                        let hLink = typeof item === 'object' ? (item.hotel_website || '') : '';
                        $hotelDrop.append(`<option value="${hName}" data-link="${hLink}">${hName}</option>`); 
                    });
                    
                    if(preValue && preValue !== 'None') {
                        $hotelDrop.val(preValue.split(','));
                    } else {
                        let firstVal = typeof res.data[0] === 'object' ? res.data[0].hotel_name : res.data[0];
                        $hotelDrop.val([firstVal]); 
                    }
                } else {
                    $hotelDrop.append('<option value="">No Hotels Saved</option>');
                }
                let vals = $hotelDrop.val();
                $hiddenInput.val(vals ? (Array.isArray(vals) ? vals.join(',') : vals) : '');
                triggerLiveCalculation();
            }).fail(function() {
                $hotelDrop.empty().append('<option value="">Error Loading</option>');
            });
        } else {
            $hotelDrop.html('<option value="">-- Select --</option>');
            $hiddenInput.val('');
        }
    }

    $(document).on('change', '.stay_hotel_dropdown', function() {
        let vals = $(this).val();
        $(this).siblings('.stay_hotel_hidden').val(vals ? (Array.isArray(vals) ? vals.join(',') : vals) : '');
        triggerLiveCalculation();
    });

    $(document).on('change', '.stay_place_dropdown, .stay_cat_dropdown', function() { updateRowHotels($(this).closest('.night-stay-row')); });
    
    $('#calc_hotel_cat').on('change', function() { 
        let newVal = $(this).val();
        $('.stay_cat_dropdown').val(newVal); 
        $('.night-stay-row').each(function() { updateRowHotels($(this)); }); 
    });

    $('#total_pax, #child_pax, #child_6_12_pax').on('input', function() {
        let pax = parseInt($('#total_pax').val()) || 0;

        let rooms = Math.ceil(pax / 3);
        if(rooms < 1) rooms = 1;
        let baseAdultCap = rooms * 2;
        let extraBeds = pax > baseAdultCap ? pax - baseAdultCap : 0;
        
        $('#no_of_rooms').val(rooms);
        $('#extra_beds').val(extraBeds);

        triggerLiveCalculation();
    });

    $('#calc_pickup').on('change', function() { 
        $('#calc_drop').val($(this).val()); 
        let mainPick = $(this).val();
        if(mainPick) {
            $('.transport_pickup_dropdown').each(function() {
                if(!$(this).val()) $(this).val(mainPick);
            });
        }
        triggerLiveCalculation(); 
    });

    function addTransportRow(vehicleName = '', qty = '', tDays = null, pickupName = '', customRate = '', customTotal = '') {
        if (tDays === null) tDays = $('#total_days').val() || 1;
        let safeCustomRate = (customRate !== null && customRate !== undefined) ? customRate : '';
        let safeCustomTotal = (customTotal !== null && customTotal !== undefined) ? customTotal : '';
        
        let dest = $('#calc_destination').val();
        let optionsHtml = '<option value="">-- Vehicle --</option>';
        let pickupOptionsHtml = '<option value="">-- City --</option>'; 
        
        if(typeof tccMasterData !== 'undefined' && tccMasterData[dest]) {
            let vehs = tccMasterData[dest].vehicles;
            if(typeof vehs === 'string') vehs = vehs.split(',').map(s=>s.trim());
            if(Array.isArray(vehs)) {
                $.each(vehs, function(i, val) {
                    let sel = (val === vehicleName) ? 'selected' : '';
                    optionsHtml += `<option value="${val}" ${sel}>${val}</option>`;
                });
            }
            
            let pickups = tccMasterData[dest].pickups;
            if(typeof pickups === 'string') pickups = pickups.split(',').map(s=>s.trim());
            if(Array.isArray(pickups)) {
                $.each(pickups, function(i, val) {
                    let sel = (val === pickupName) ? 'selected' : '';
                    pickupOptionsHtml += `<option value="${val}" ${sel}>${val}</option>`;
                });
            }
        }
        
        let row = `
        <div class="transport-row tcc-repeater-row tcc-fade-in" style="flex-wrap:wrap; gap:4px;">
            <select name="transport_pickup[]" class="transport_pickup_dropdown" required style="flex:1.5; height:32px;" title="Pickup City">${pickupOptionsHtml}</select>
            <select name="transportation[]" class="transport_dropdown" required style="flex:2; height:32px;">${optionsHtml}</select>
            
            <div class="tcc-qty-wrapper" style="flex:0.7;" title="Qty">
                <button type="button" class="tcc-qty-btn minus">-</button>
                <input type="number" name="transport_qty[]" value="${qty}" min="1" placeholder="Qty" required>
                <button type="button" class="tcc-qty-btn plus">+</button>
            </div>
            
            <div class="tcc-qty-wrapper" style="flex:0.7;" title="No. of Days">
                <button type="button" class="tcc-qty-btn minus">-</button>
                <input type="number" name="transport_days[]" value="${tDays}" min="1" placeholder="Days" required>
                <button type="button" class="tcc-qty-btn plus">+</button>
            </div>

            <input type="number" name="transport_custom_rate[]" value="${safeCustomRate}" step="0.01" min="0" placeholder="Rate/Day (₹)" style="flex:1.2; height:32px;" title="Custom Cab Rate per Day (Optional)">
            <input type="number" name="transport_custom_total[]" value="${safeCustomTotal}" step="0.01" min="0" placeholder="Total Cost (₹)" style="flex:1.2; height:32px;" title="Custom Absolute Total Cab Cost (Optional)">
            <button type="button" class="remove_transport tcc-btn-del" style="height:32px;">X</button>
            <div class="tcc-trans-cost" style="flex: 1 1 100%; text-align:right; font-size:11px; color:#16a34a; font-weight:bold; margin-top:-2px;"></div>
        </div>`;
        $('#transport-wrapper').append(row);
    }

    $('#add_transport').on('click', function() { addTransportRow(); triggerLiveCalculation(); });
    $(document).on('click', '.remove_transport', function() { $(this).closest('.transport-row').remove(); triggerLiveCalculation(); });

    // --- ADDONS LOGIC ---
    function addAddonRow(name = '', price = '', type = 'flat') {
        let row = `
        <div class="addon-row tcc-repeater-row tcc-fade-in" style="display:flex; gap:5px; margin-bottom:5px;">
            <input type="text" name="addon_name[]" class="addon-name" value="${name}" placeholder="Addon Name (e.g. Bonfire)" style="flex:2;" required>
            <select name="addon_type[]" class="addon-type" style="flex:1; padding:4px; font-size:12px; border:1px solid #ccc; border-radius:3px;">
                <option value="flat" ${type === 'flat' ? 'selected' : ''}>Flat Total</option>
                <option value="per_person" ${type === 'per_person' ? 'selected' : ''}>Per Person</option>
            </select>
            <input type="number" name="addon_price[]" class="addon-price" value="${price}" min="0" step="0.01" placeholder="Cost (₹)" style="flex:1;" required>
            <button type="button" class="save_addon_row tcc-btn-secondary" style="padding:4px 8px; margin:0; font-size:11px;" title="Save to Library">💾</button>
            <button type="button" class="remove_addon tcc-btn-del" style="margin:0;">X</button>
        </div>`;
        $('#addons-wrapper').append(row);
    }

    $('#add_addon_btn').on('click', function() { addAddonRow(); triggerLiveCalculation(); });
    $(document).on('click', '.remove_addon', function() { $(this).closest('.addon-row').remove(); triggerLiveCalculation(); });

    // --- INDIVIDUAL ADDON PRESETS LOGIC ---
    function loadAddonPresets() {
        let dest = $('#calc_destination').val();
        let $select = $('#addon_preset_select');
        
        $select.html('<option value="">-- Saved Add-ons --</option>');
        $('#insert_addon_preset, #delete_addon_preset').hide();
        
        if(!dest) return;
        
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_addon_presets', destination: dest }, function(res) {
            if(res.success && res.data) {
                let pData = res.data;
                // Double check it clears correctly before injecting
                $select.html('<option value="">-- Saved Add-ons --</option>');
                
                if(Object.keys(pData).length > 0) {
                    $select.data('presets', pData);
                    $.each(pData, function(presetName, dataObj) {
                        $select.append(`<option value="${presetName}">${presetName}</option>`);
                    });
                }
            }
        });
    }

    $('#addon_preset_select').on('change', function() {
        if($(this).val()) {
            $('#insert_addon_preset, #delete_addon_preset').show();
        } else {
            $('#insert_addon_preset, #delete_addon_preset').hide();
        }
    });

    $('#insert_addon_preset').on('click', function() {
        let presetName = $('#addon_preset_select').val();
        let presets = $('#addon_preset_select').data('presets');
        if(presetName && presets && presets[presetName]) {
            let p = presets[presetName];
            addAddonRow(presetName, p.price || 0, p.type || 'flat');
            triggerLiveCalculation();
            
            // Reset dropdown
            $('#addon_preset_select').val('').trigger('change');
        }
    });

    // Save individual row to Library
    $(document).on('click', '.save_addon_row', function() {
        let row = $(this).closest('.addon-row');
        let name = row.find('.addon-name').val().trim();
        let type = row.find('.addon-type').val();
        let price = row.find('.addon-price').val();
        let dest = $('#calc_destination').val();
        
        if(!name || !dest) return alert("Enter Add-on Name and select Destination first.");
        if(!price) price = 0;
        
        let btn = $(this);
        let oldText = btn.text();
        btn.text('...');
        
        $.post(tcc_ajax_obj.ajax_url, {
            action: 'tcc_save_addon_preset',
            destination: dest,
            preset_name: name,
            addon_type: type,
            addon_price: price
        }, function(res) {
            btn.text(oldText);
            if(res.success) {
                $('#addon_preset_msg').text("Saved '" + name + "' to Library!").show().delay(2000).fadeOut();
                loadAddonPresets();
            } else {
                alert("Error saving Add-on.");
            }
        });
    });

    $('#delete_addon_preset').on('click', function() {
        let presetName = $('#addon_preset_select').val();
        let dest = $('#calc_destination').val();
        if(!presetName || !dest) return;
        if(!confirm("Are you sure you want to permanently delete '" + presetName + "' from your Library?")) return;

        $.post(tcc_ajax_obj.ajax_url, {
            action: 'tcc_delete_addon_preset',
            preset_name: presetName,
            destination: dest
        }, function(res) {
            if(res.success) {
                $('#addon_preset_msg').text("Deleted successfully!").css('color', '#dc2626').fadeIn().delay(2000).fadeOut();
                loadAddonPresets();
            } else {
                alert("Error deleting preset.");
            }
        });
    });

    // --- ITINERARY PRESETS LOGIC ---
    function loadPresets() {
        let dest = $('#calc_destination').val();
        let $select = $('#itinerary_preset_select');
        $select.html('<option value="">-- Load Preset --</option>');
        $('#delete_itinerary_preset').hide();
        if(!dest) return;
        
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_itinerary_presets', destination: dest }, function(res) {
            try {
                if(res.success && res.data) {
                    let pData = res.data;
                    if(typeof pData === 'string') { pData = JSON.parse(pData); }
                    
                    // FIX: Clear the dropdown strictly before rendering to prevent duplicates
                    $select.html('<option value="">-- Load Preset --</option>');

                    if(Object.keys(pData).length > 0) {
                        $select.data('presets', pData);
                        $.each(pData, function(presetName, dataObj) {
                            let daysLen = 0;
                            if (Array.isArray(dataObj)) {
                                daysLen = dataObj.length;
                            } else if (dataObj && dataObj.itinerary !== undefined) {
                                if(Array.isArray(dataObj.itinerary)) {
                                    daysLen = dataObj.itinerary.length;
                                } else if(typeof dataObj.itinerary === 'object' && dataObj.itinerary !== null) {
                                    daysLen = Object.keys(dataObj.itinerary).length;
                                }
                            } else if (dataObj && typeof dataObj === 'object') {
                                daysLen = Object.keys(dataObj).length;
                            }
                            $select.append(`<option value="${presetName}">${presetName} (${daysLen} Days)</option>`);
                        });
                    }
                }
            } catch(e) { console.error("Error loading presets: ", e); }
        });
    }

    $('#total_days').data('prev-days', parseInt($('#total_days').val()) || 1);

    $('#total_days').on('input', function() {
        let days = parseInt($(this).val()) || 0;
        let prevDays = parseInt($(this).data('prev-days')) || days;
        
        $('#total_nights_display').text(days > 0 ? days - 1 : 0);

        $('input[name="transport_days[]"]').each(function() {
            let currentVal = parseInt($(this).val());
            if(isNaN(currentVal) || currentVal === prevDays) {
                $(this).val(days);
            }
        });
        
        $(this).data('prev-days', days);

        triggerLiveCalculation();
    });

    function addStayRow(placeName = '', catVal = '', hotelVal = '', nights = 1) {
        let dest = $('#calc_destination').val();
        
        let placeOptionsHtml = '<option value="No Hotel"' + (placeName === 'No Hotel' ? ' selected' : '') + '>No Hotel Required</option>';
        let catOptionsHtml = '';
        
        if(typeof tccMasterData !== 'undefined' && tccMasterData[dest]) {
            let stays = tccMasterData[dest].stay_places;
            if(typeof stays === 'string') stays = stays.split(',').map(s=>s.trim());
            if(Array.isArray(stays)) {
                $.each(stays, function(i, val) { 
                    let sel = (val === placeName) ? 'selected' : '';
                    placeOptionsHtml += `<option value="${val}" ${sel}>${val}</option>`; 
                });
            }

            let cats = tccMasterData[dest].hotel_categories;
            if(typeof cats === 'string') cats = cats.split(',').map(s=>s.trim());
            let defaultCat = $('#calc_hotel_cat').val();
            let selectedCat = catVal || defaultCat;
            if(Array.isArray(cats)) {
                $.each(cats, function(i, val) { 
                    let sel = (val === selectedCat) ? 'selected' : '';
                    catOptionsHtml += `<option value="${val}" ${sel}>${val}</option>`; 
                });
            }
        }
        
        let row = `
        <div class="night-stay-row tcc-repeater-row tcc-fade-in" style="flex-wrap:wrap; gap:5px;">
            <select name="stay_place[]" class="stay_place_dropdown" required style="flex:2;">
                <option value="">-- Select --</option>
                ${placeOptionsHtml}
            </select>
            <select name="stay_category[]" class="stay_cat_dropdown" required style="flex:1.5;">${catOptionsHtml}</select>
            <div style="flex:3;">
                <select class="stay_hotel_dropdown" multiple required style="width:100%; height:55px !important; border:1px solid #ccc; border-radius:3px; padding:2px; font-size:12px; background:#fff; outline:none;"></select>
                <input type="hidden" name="stay_hotel[]" class="stay_hotel_hidden" value="${hotelVal}">
            </div>
            
            <div class="tcc-qty-wrapper" style="flex:0.8;" title="Nights">
                <button type="button" class="tcc-qty-btn minus">-</button>
                <input type="number" name="stay_nights[]" placeholder="Nights" value="${nights}" class="stay_nights" min="1" required>
                <button type="button" class="tcc-qty-btn plus">+</button>
            </div>

            <button type="button" class="remove_stay_place tcc-btn-del" style="height:32px; margin-top:0;">X</button>
            <div class="tcc-hotel-cost" style="flex: 1 1 100%; text-align:right; font-size:11px; color:#16a34a; font-weight:bold; margin-top:-2px;"></div>
        </div>`;
        let $newRow = $(row);
        $('#night-stay-wrapper').append($newRow);
        updateRowHotels($newRow);
    }

    $('#add_stay_place').on('click', function() { addStayRow(); });
    $(document).on('click', '.remove_stay_place', function() { $(this).closest('.night-stay-row').remove(); triggerLiveCalculation(); });

    function validateCalculator() {
        let pax = parseInt($('#total_pax').val());
        if (isNaN(pax) || pax <= 0) return "Enter No. of Adults.";
        
        let rooms = parseInt($('#no_of_rooms').val()) || 0;
        let extra_beds = parseInt($('#extra_beds').val()) || 0;
        let total_days = parseInt($('#total_days').val()) || 0;
        let expected_nights = total_days > 0 ? total_days - 1 : 0;

        if (extra_beds > rooms) return "Max 1 Extra Bed/room.";
        if ((rooms * 2) + extra_beds < pax) return `Not enough beds for ${pax} Adults.`;

        let sum_nights = 0;
        $('.stay_nights').each(function() { sum_nights += parseInt($(this).val()) || 0; });
        if (sum_nights !== expected_nights) return `Nights (${sum_nights}) must equal ${expected_nights}.`;

        if (!$('#calc_pickup').val() || !$('#calc_drop').val()) return "Select Pickup and Drop locations.";

        let transValid = true;
        $('.transport_pickup_dropdown').each(function(){ if(!$(this).val()) transValid = false; });
        $('.transport_dropdown').each(function(){ if(!$(this).val()) transValid = false; });
        $('input[name="transport_qty[]"]').each(function(){ let v = parseInt($(this).val()); if(isNaN(v) || v <= 0) transValid = false; });
        $('input[name="transport_days[]"]').each(function(){ let v = parseInt($(this).val()); if(isNaN(v) || v <= 0) transValid = false; });
        
        $('input[name="transport_custom_rate[]"]').each(function(){ 
            if($(this).val() !== '') { let v = parseFloat($(this).val()); if(isNaN(v) || v < 0) transValid = false; }
        });
        $('input[name="transport_custom_total[]"]').each(function(){ 
            if($(this).val() !== '') { let v = parseFloat($(this).val()); if(isNaN(v) || v < 0) transValid = false; }
        });
        if(!transValid) return "Select City, Vehicle, Qty, Days, and valid Rate/Total.";

        let addonValid = true;
        $('.addon-name').each(function(){ if(!$(this).val().trim()) addonValid = false; });
        $('.addon-price').each(function(){ let v = parseFloat($(this).val()); if(isNaN(v) || v < 0) addonValid = false; });
        if(!addonValid) return "Enter valid Add-on name and cost.";

        return null; 
    }

    let liveCalcTimeout;
    $('#tcc-calc-form').on('input change', 'input, select', function(e) {
        if(e.target.id === 'tcc_calculate_btn' || e.target.id === 'client_name' || e.target.id === 'client_phone' || e.target.id === 'client_email' || e.target.id === 'calc_edit_quote_select' || e.target.id === 'calc_quote_search') return;
        if(e.target.id !== 'total_pax' && e.target.id !== 'child_pax' && e.target.id !== 'child_6_12_pax' && e.target.id !== 'calc_pickup') {
            triggerLiveCalculation();
        }
    });

    window.tccLiveSummaryData = null; 

    function triggerLiveCalculation() {
        $('#live_error').hide();
        $('#tcc_link_wrapper').slideUp(); 
        window.tccLiveSummaryData = null; 
        
        let validationError = validateCalculator();
        if (validationError) {
            $('#live_pp_base, #live_pp_inc, #live_profit, #live_discount, #live_total, #live_gst, #live_actual_cost, #live_total_hotel, #live_total_trans, #live_total_base').text('₹0.00').css('opacity', '1');
            $('#live_addons_row').hide();
            $('.tcc-trans-cost, .tcc-hotel-cost').text('');
            $('#live_error').html(validationError).show();
            return;
        }

        $('#live_pp_base, #live_pp_inc, #live_profit, #live_discount, #live_total, #live_gst').css('opacity', '0.5'); 
        clearTimeout(liveCalcTimeout);
        liveCalcTimeout = setTimeout(runLiveCalculation, 400);
    }

    function runLiveCalculation() {
        $('.stay_hotel_dropdown').each(function() {
            let vals = $(this).val();
            $(this).siblings('.stay_hotel_hidden').val(vals ? (Array.isArray(vals) ? vals.join(',') : vals) : '');
        });

        let formData = $('#tcc-calc-form').serialize();
        $.ajax({
            url: tcc_ajax_obj.ajax_url, type: 'POST',
            data: formData + '&action=tcc_calculate_trip',
            success: function(res) {
                $('#live_pp_base, #live_pp_inc, #live_profit, #live_discount, #live_total, #live_gst').css('opacity', '1');
                if(res.success) {
                    let d = res.data;
                    window.tccLiveSummaryData = d.summary_data; 
                    
                    $('#live_pp_base').text(formatINR(d.summary_data.per_person_excl_gst));
                    $('#live_pp_inc').text(formatINR(d.summary_data.per_person_with_gst));
                    $('#live_total_base').text(formatINR(d.summary_data.total_base_price));
                    
                    $('#live_pt_label').text(`PT (${d.summary_data.pt_pct}%):`);
                    $('#live_pg_label').text(`PG (${d.summary_data.pg_pct}%):`);
                    $('#live_gst_label').text(`GST (${d.summary_data.gst_pct}%):`);

                    $('#live_total_hotel').text(formatINR(d.summary_data.total_hotel_cost));
                    $('#live_total_trans').text(formatINR(d.summary_data.total_trans_cost));
                    
                    if (d.summary_data.total_addon_cost > 0) {
                        $('#live_addons_row').css('display', 'flex');
                        $('#live_total_addons').text(formatINR(d.summary_data.total_addon_cost));
                    } else {
                        $('#live_addons_row').hide();
                    }

                    $('#live_actual_cost').text(formatINR(d.summary_data.actual_cost));

                    $('#live_profit').text(formatINR(d.summary_data.net_profit));
                    $('#live_gross_profit').text(formatINR(d.summary_data.final_profit));
                    $('#live_pt').text("-" + formatINR(d.summary_data.prof_tax));
                    $('#live_pg').text("-" + formatINR(d.summary_data.pg_charge));

                    $('#live_discount').text("-" + formatINR(d.summary_data.discount_amount));
                    $('#live_gst').text(formatINR(d.gst));
                    $('#live_total').text(formatINR(d.grand_total));
                    
                    $('#live_profit').removeClass('tcc-preview-loss tcc-preview-profit').addClass(d.summary_data.net_profit < 0 ? 'tcc-preview-loss' : 'tcc-preview-profit');
                    
                    if(d.summary_data.surcharge_applied > 0) {
                        $('#live_surcharge_info').text(`*Season Surcharge applied: +${d.summary_data.surcharge_applied}%`).show();
                    } else {
                        $('#live_surcharge_info').hide();
                    }

                    $('.transport-row').each(function(index) {
                        if(d.summary_data.transport_row_costs[index] !== undefined) {
                            $(this).find('.tcc-trans-cost').text('Cost: ' + formatINR(d.summary_data.transport_row_costs[index]));
                        }
                    });

                    $('.night-stay-row').each(function(index) {
                        if(d.summary_data.hotel_row_costs[index] !== undefined) {
                            $(this).find('.tcc-hotel-cost').text('Cost: ' + formatINR(d.summary_data.hotel_row_costs[index]));
                        }
                    });

                    $('#live_error').hide();
                } else {
                    $('#live_pp_base, #live_pp_inc, #live_profit, #live_gross_profit, #live_pt, #live_pg, #live_discount, #live_total, #live_gst, #live_total_base').text('₹0.00');
                    $('#live_total_hotel, #live_total_trans, #live_actual_cost').text('₹0.00');
                    $('#live_addons_row').hide();
                    $('.tcc-trans-cost, .tcc-hotel-cost').text('');
                    $('#live_error').html(res.data.errors).show();
                }
            }
        });
    }

    window.tccLastQuoteData = null;

    $('#tcc-calc-form').on('submit', function(e) {
        e.preventDefault();
        $('#live_error').hide();
        $('#tcc_link_wrapper').hide();
        
        let validationError = validateCalculator();
        if(validationError) { $('#live_error').html(validationError).show(); return; }

        $('.stay_hotel_dropdown').each(function() {
            let vals = $(this).val();
            $(this).siblings('.stay_hotel_hidden').val(vals ? (Array.isArray(vals) ? vals.join(',') : vals) : '');
        });

        let btn = $('#tcc_calculate_btn');
        btn.text('Processing...').prop('disabled', true);

        $.ajax({
            url: tcc_ajax_obj.ajax_url, type: 'POST',
            data: $(this).serialize() + '&action=tcc_calculate_trip&generate_link=1',
            success: function(response) {
                btn.text($('#edit_quote_id').val() ? 'Update Quote Link' : 'Generate Quote Link').prop('disabled', false);
                if(response.success) {
                    window.tccLastQuoteData = response.data;
                    $('#tcc_generated_link').val(response.data.permalink);
                    $('#tcc_open_btn').attr('href', response.data.permalink);
                    $('#tcc_link_wrapper').slideDown();
                    loadPaymentQuotes(); 
                } else {
                    $('#live_error').html(response.data.errors).show();
                }
            }
        });
    });

    function sanitizeCopyText(text) {
        if (!text) return "";
        let parts = text.split(/(https?:\/\/[^\s\]]+)/g);
        for(let i=0; i<parts.length; i++) {
            if (!parts[i].startsWith('http')) {
                parts[i] = parts[i].replace(/&/g, 'and');
            }
        }
        return parts.join('');
    }

    function copyToClipboardStr(text, $btn) {
        text = sanitizeCopyText(text);

        let copyTextarea = document.createElement("textarea");
        document.body.appendChild(copyTextarea);
        copyTextarea.value = text;
        copyTextarea.select();
        document.execCommand("copy");
        document.body.removeChild(copyTextarea);

        let originalText = $btn.text();
        $btn.text("Copied!");
        setTimeout(function() { $btn.text(originalText); }, 2000);
    }

    function formatBullets(text, bullet) {
        if(!text) return "None specified.";
        return text.split(/\r\n|\n|\r/).filter(line => line.trim() !== '').map(line => `${bullet} ${line.trim()}`).join('\n');
    }

    function buildHotelsText(stays) {
        let msg = `*Hotels Selected*\n`;
        if (stays && stays.length > 0) {
            stays.forEach(stay => {
                if(stay.place === 'No Hotel Required') {
                    msg += `No Hotel Required (${stay.nights}N)\n`;
                } else {
                    let cat = stay.category || '';
                    let stars = cat.toLowerCase().includes('4') ? '⭐⭐⭐⭐' : (cat.toLowerCase().includes('5') ? '⭐⭐⭐⭐⭐' : '⭐⭐⭐');
                    msg += `${stars} ${stay.place} (${stay.nights}N): [${cat}]\n`;
                    if (stay.options && stay.options.length > 0) {
                        stay.options.forEach(opt => {
                            let linkStr = opt.link ? ` [${opt.link}]` : '';
                            msg += `${opt.name}${linkStr}\n`;
                        });
                    } else {
                        msg += `None selected\n`;
                    }
                }
            });
        } else {
            msg += `No hotels selected.\n`;
        }
        return msg;
    }

    // GATHER DATA IF UNCALCULATED
    function getCopyDataUncalculated() {
        if (window.tccLiveSummaryData) return window.tccLiveSummaryData;

        let dest = $('#calc_destination').val();
        let master = (typeof tccMasterData !== 'undefined' && tccMasterData[dest]) ? tccMasterData[dest] : {};

        let itinerary = [];
        let itinerary_stay_places = [];
        $('#day-wise-wrapper .tcc-day-row').each(function() {
            let text = $(this).find('.tcc-day-input').val();
            let stay = $(this).find('.tcc-day-stay-place').val();
            if(text && text.trim() !== '') {
                itinerary.push(text.trim());
                itinerary_stay_places.push(stay || '');
            }
        });

        let stays = [];
        $('.night-stay-row').each(function() {
            let place = $(this).find('.stay_place_dropdown').val();
            let cat = $(this).find('.stay_cat_dropdown').val();
            let nights = $(this).find('.stay_nights').val();
            
            let opts = [];
            $(this).find('.stay_hotel_dropdown option:selected').each(function() {
                opts.push({ name: $(this).val(), link: $(this).data('link') || '' });
            });

            if (place && nights) {
                if(place === 'No Hotel') place = 'No Hotel Required';
                stays.push({place: place, category: cat, nights: nights, options: opts});
            }
        });

        let inclusions = master.inclusions || '';
        let addonsText = [];
        $('#addons-wrapper .addon-row').each(function() {
            let name = $(this).find('.addon-name').val();
            if(name && name.trim() !== '') addonsText.push(name.trim());
        });
        if(addonsText.length > 0) {
            if (inclusions !== '') inclusions += '\n';
            inclusions += addonsText.join('\n');
        }

        return {
            inclusions: inclusions,
            exclusions: master.exclusions || '',
            payment_terms: master.payment_terms || '',
            itinerary: itinerary,
            itinerary_stay_places: itinerary_stay_places,
            stays: stays
        };
    }

    // --- QUICK COPY ACTION BUTTONS ---

    $('#tcc_quick_wa_btn').on('click', function() {
        if (!window.tccLiveSummaryData) return alert("Please calculate trip first to copy the Summary with Prices.");
        let d = window.tccLiveSummaryData;

        let totalValue = $('#live_total').text();
        let gstPct = (typeof tccGlobalSettings !== 'undefined' && tccGlobalSettings.gst) ? tccGlobalSettings.gst : 5;

        let msg = `Soulful Tour & Travels (Soulful Pathfinder)\nGSTIN: 19AXIPD7432L1Z5\n\n`;
        msg += `Travelers: ${d.pax} Adults, ${d.child_6_12 || 0} Child (6-12), ${d.child} Infant\n\n`;
        msg += `Inclusions:\n`;
        msg += `🏨 Room: ${d.rooms} Room${d.rooms > 1 ? 's' : ''} + ${d.extra_beds} Extra Bed${d.extra_beds > 1 ? 's' : ''}\n`;
        msg += `🚗 Vehicle: ${d.transport_string}\n\n`;
        msg += `Grand Total: ${totalValue} (Total Value)\n`;
        msg += `✓ Inclusive of ${gstPct}% GST\n\n`;
        msg += `Visit-https://soulfultourtravels.in/`;

        copyToClipboardStr(msg, $(this));
    });

    $('#tcc_copy_itinerary_btn').on('click', function() {
        let d = getCopyDataUncalculated();
        if(!d.itinerary || d.itinerary.length === 0) return alert("Please fill up the Itinerary properly first.");
        
        let msg = `*Itinerary Details*\n`;
        d.itinerary.forEach((day_text, idx) => {
            let stay = (d.itinerary_stay_places && d.itinerary_stay_places[idx]) ? d.itinerary_stay_places[idx] : '';
            let stay_suffix = (stay && stay !== 'No Hotel') ? ` n/s-${stay}` : '';
            msg += `👉Day ${idx + 1}: ${day_text}${stay_suffix}\n`;
        });

        msg += `\n`;
        msg += buildHotelsText(d.stays);

        msg += `\n*Included in Package*\n`;
        msg += formatBullets(d.inclusions, '✅') + `\n`;

        copyToClipboardStr(msg, $(this));
    });

    $('#tcc_copy_hotels_btn').on('click', function() {
        let d = getCopyDataUncalculated();
        copyToClipboardStr(buildHotelsText(d.stays), $(this));
    });

    $('#tcc_copy_inclusions_btn').on('click', function() {
        let d = getCopyDataUncalculated();
        copyToClipboardStr(`*Included in Package*\n` + formatBullets(d.inclusions, '✅'), $(this));
    });

    $('#tcc_copy_exclusions_btn').on('click', function() {
        let d = getCopyDataUncalculated();
        copyToClipboardStr(`*Excluded from Package*\n` + formatBullets(d.exclusions, '❌'), $(this));
    });

    $('#tcc_copy_payment_btn').on('click', function() {
        let d = getCopyDataUncalculated();
        copyToClipboardStr(`*Payment Terms*\n` + formatBullets(d.payment_terms, '💳'), $(this));
    });

    // --- SUCCESS COPY ACTIONS (Using full quote data) ---
    $('#tcc_copy_btn').on('click', function() {
        let copyText = document.getElementById("tcc_generated_link");
        copyText.select();
        copyText.setSelectionRange(0, 99999); 
        document.execCommand("copy");
        let $btn = $(this);
        let originalText = $btn.text();
        $btn.text("Copied!");
        setTimeout(function(){ $btn.text(originalText); }, 2000);
    });

    $('#tcc_copy_wa_btn').on('click', function() {
        if(!window.tccLastQuoteData) return;
        
        let d = window.tccLastQuoteData.summary_data;
        let pp_gst = formatINR(d.per_person_with_gst || (window.tccLastQuoteData.grand_total / Math.max(1, d.pax)));
        
        let pp_base = formatINR(d.per_person_excl_gst);
        let gst = formatINR(window.tccLastQuoteData.gst);
        let discount = d.discount_amount > 0 ? formatINR(d.discount_amount) : 0;
        let total = formatINR(window.tccLastQuoteData.grand_total);
        let link = window.tccLastQuoteData.permalink;
        let gst_pct = d.gst_pct !== undefined ? d.gst_pct : 5;
        let drop_txt = d.drop !== undefined ? d.drop : d.pickup;
        let pax_count = Math.max(1, d.pax);
        let total_base = formatINR(d.total_base_price);
        
        function formatDate(dateStr) {
            if(!dateStr) return '';
            let options = { day: 'numeric', month: 'short', year: 'numeric' };
            return new Date(dateStr).toLocaleDateString('en-GB', options);
        }

        let msg = `*SOULFUL TOUR & TRAVELS*\n`;
        msg += `GSTIN: 19AXIPD7432L1Z5\n\n`;
        if(d.client_name) msg += `*Client Name:* ${d.client_name}\n`;
        msg += `*Your Quotation for ${d.destination}*\n`;
        if (d.start_date) {
            msg += `*Trip Date:* ${formatDate(d.start_date)} to ${formatDate(d.end_date)}\n`;
        }
        msg += `*Duration:* ${d.days} Days / ${d.days > 0 ? d.days - 1 : 0} Nights\n`;
        msg += `*Travelers:* ${d.pax} Adults, ${d.child_6_12 || 0} Child (6-12), ${d.child} Infant\n`;
        msg += `*Rooms & Beds:* ${d.rooms} Rooms / ${d.extra_beds} Extra Beds\n`;
        msg += `*Transport:* ${d.transport_string} (Pickup: ${d.pickup} | Drop: ${drop_txt})\n\n`;
        
        if(d.itinerary && d.itinerary.length > 0 && d.itinerary.some(day => day.trim() !== '')) {
            msg += `*Itinerary Details:*\n`;
            d.itinerary.forEach(function(day_text, index) {
                if(day_text.trim() !== '') {
                    msg += `*Day ${index + 1}:* ${day_text}\n`;
                }
            });
            msg += `\n`;
        }

        msg += buildHotelsText(d.stays) + `\n`;
        
        msg += `*Included in Package:*\n`;
        msg += `${formatBullets(d.inclusions, '✓')}\n`;
        
        msg += `\n*Pricing Summary:*\n`;
        msg += `Per Person (Excl. GST): ${pp_base}\n`;
        msg += `Total Base (${pax_count} Pax): ${total_base}\n`;
        msg += `GST (${gst_pct}%): ${gst}\n`;
        if(d.discount_amount > 0) msg += `(Note: A discount of ${discount} was applied to your base price)\n`;
        msg += `\n*Total Quote:* ${total}\n`;
        msg += `*Per Person (Inc. GST):* ${pp_gst}\n\n`;

        msg += `*View Detailed Itinerary, Receipts & Quote here:*\n${link}`;

        copyToClipboardStr(msg, $(this));
    });

    // --- DAY WISE LOGIC WITH AUTO STAY-SYNC --- //
    
    let tccSortableInstance = null;

    function initSortable() {
        let el = document.getElementById('day-wise-wrapper');
        if(el && typeof Sortable !== 'undefined') {
            if(tccSortableInstance) {
                tccSortableInstance.destroy();
            }
            tccSortableInstance = Sortable.create(el, { 
                handle: '.drag-handle', animation: 150, ghostClass: 'tcc-sortable-ghost', 
                onEnd: function () { 
                    generateDayInputs(); 
                    syncDayWiseToRouting(); 
                    triggerLiveCalculation(); 
                } 
            });
        }
    }

    function syncDayWiseToRouting() {
        let stays = [];
        $('#day-wise-wrapper .tcc-day-stay-place').each(function() {
            stays.push($(this).val() || ''); 
        });

        if(stays.length === 0) return;

        let grouped = [];
        let currentStay = stays[0];
        let count = 1;
        for(let i = 1; i < stays.length; i++) {
            if(stays[i] === currentStay) {
                count++;
            } else {
                grouped.push({ place: currentStay, nights: count });
                currentStay = stays[i];
                count = 1;
            }
        }
        grouped.push({ place: currentStay, nights: count });

        $('#night-stay-wrapper').empty();
        let defaultCat = $('#calc_hotel_cat').val();

        grouped.forEach(g => { addStayRow(g.place, defaultCat, '', g.nights); });
        triggerLiveCalculation();
    }

    $(document).on('change', '.tcc-day-stay-place', syncDayWiseToRouting);

    function generateDayInputs() {
        let days = parseInt($('#total_days').val()) || 0;
        let wrapper = $('#day-wise-wrapper');
        let currentValues = [];
        let currentStays = [];
        
        wrapper.find('.tcc-day-row').each(function() { 
            currentValues.push($(this).find('.tcc-day-input').val()); 
            let sp = $(this).find('.tcc-day-stay-place').val();
            currentStays.push(sp !== undefined ? sp : '');
        });
        wrapper.empty();

        let dest = $('#calc_destination').val();
        let stayOptions = '<option value="">-- Night Stay --</option>';
        stayOptions += '<option value="No Hotel">No Hotel Required</option>';
        if(typeof tccMasterData !== 'undefined' && tccMasterData[dest]) {
            let stays = tccMasterData[dest].stay_places;
            if(typeof stays === 'string') stays = stays.split(',').map(s=>s.trim());
            if(Array.isArray(stays)) {
                $.each(stays, function(i, val) { stayOptions += `<option value="${val}">${val}</option>`; });
            }
        }

        for(let i = 1; i <= days; i++) {
            let val = currentValues[i-1] ? currentValues[i-1] : '';
            let spVal = currentStays[i-1] ? currentStays[i-1] : '';

            let stayDropdownHtml = '';
            if (i < days) {
                stayDropdownHtml = `<select name="itinerary_stay_place[]" class="tcc-day-stay-place" style="flex:0.6; border-radius:0; margin-bottom:0; font-size:11px; padding:2px; border-left:none;">${stayOptions}</select>`;
            } else {
                stayDropdownHtml = `<input type="hidden" name="itinerary_stay_place[]" value=""><div style="flex:0.6; background:#f1f5f9; border:1px solid #ccc; border-left:none; display:flex; align-items:center; justify-content:center; font-size:10px; color:#94a3b8;">Trip Ends</div>`;
            }

            let row = `
            <div class="tcc-day-row" style="display:flex; align-items:stretch; margin-bottom:5px; background:#fff; border-radius:3px;">
                <div class="drag-handle" style="cursor:grab; padding:8px 10px; background:#f1f5f9; color:#94a3b8; border:1px solid #ccc; border-right:none; border-radius:3px 0 0 3px; display:flex; align-items:center;">&#9776;</div>
                <div class="tcc-day-label" style="background:#e2e8f0; padding:8px 8px; font-size:11px; font-weight:bold; border:1px solid #ccc; border-right:none; border-left:none; width:55px; text-align:center; display:flex; align-items:center; justify-content:center;">Day ${i}</div>
                <input type="text" name="itinerary_day[]" class="tcc-day-input" value="${val}" placeholder="E.g. Arrival at Srinagar" style="border-radius:0; margin-bottom:0; flex:1;" required>
                ${stayDropdownHtml}
                <button type="button" class="tcc-remove-day tcc-btn-del" style="border-radius:0 3px 3px 0; border-left:none; padding:4px 10px !important;" title="Remove Day">X</button>
            </div>`;
            
            let $newRow = $(row);
            if(i < days && spVal) $newRow.find('.tcc-day-stay-place').val(spVal);
            wrapper.append($newRow);
        }
        if (typeof Sortable === 'undefined') { $.getScript('https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js', initSortable); } else { initSortable(); }
    }

    // --- ADD / REMOVE DAY LOGIC ---
    $('#tcc_add_day_btn').on('click', function() {
        let days = parseInt($('#total_days').val()) || 0;
        $('#total_days').val(days + 1).trigger('input');
    });

    $(document).on('click', '.tcc-remove-day', function() {
        $(this).closest('.tcc-day-row').remove();
        let days = parseInt($('#total_days').val()) || 0;
        if (days > 1) {
            $('#total_days').val(days - 1).trigger('input');
        } else {
            triggerLiveCalculation();
        }
    });

    $('#total_days').on('input', generateDayInputs);
    
    $('#calc_destination').on('change', function() { 
        updateCalculatorDropdowns(); 
        loadPresets(); 
        loadAddonPresets();
        generateDayInputs(); 
        triggerLiveCalculation(); 
    });
    
    setTimeout(() => {
        generateDayInputs();
        loadAddonPresets();
    }, 500);

    $('#itinerary_preset_select').on('change', function() {
        let presetName = $(this).val();
        if(!presetName) { 
            $('#delete_itinerary_preset').hide();
            $('#new_preset_name').val('');
            $('#save_itinerary_preset').text('Save as Preset');
            return; 
        }
        $('#delete_itinerary_preset').show();
        $('#new_preset_name').val(presetName);
        $('#save_itinerary_preset').text('Update Preset');
        
        let presets = $(this).data('presets');
        if(presets && presets[presetName]) {
            let presetData = presets[presetName];
            
            let daysArr = [];
            let staysArr = [];

            if (Array.isArray(presetData)) {
                daysArr = presetData;
            } else if (presetData && presetData.itinerary !== undefined) {
                if(Array.isArray(presetData.itinerary)) {
                    daysArr = presetData.itinerary;
                } else if(typeof presetData.itinerary === 'object' && presetData.itinerary !== null) {
                    daysArr = Object.values(presetData.itinerary);
                }

                if(presetData.stay_places) {
                    if(Array.isArray(presetData.stay_places)) {
                        staysArr = presetData.stay_places;
                    } else if(typeof presetData.stay_places === 'object' && presetData.stay_places !== null) {
                        staysArr = Object.values(presetData.stay_places);
                    }
                }
            } else if (presetData && typeof presetData === 'object') {
                daysArr = Object.values(presetData);
            }

            $('#total_days').val(daysArr.length).trigger('input'); 
            setTimeout(function() {
                $('#day-wise-wrapper .tcc-day-row').each(function(index) { 
                    if(daysArr[index]) $(this).find('.tcc-day-input').val(daysArr[index]); 
                    if(staysArr && staysArr[index]) {
                        $(this).find('.tcc-day-stay-place').val(staysArr[index]);
                    } else {
                        $(this).find('.tcc-day-stay-place').val('');
                    }
                });
                
                syncDayWiseToRouting();
            }, 100);
        }
    });

    function loadPresets() {
        let dest = $('#calc_destination').val();
        let $select = $('#itinerary_preset_select');
        
        $select.html('<option value="">-- Load Preset --</option>');
        $('#delete_itinerary_preset').hide();
        
        if(!dest) return;
        
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_itinerary_presets', destination: dest }, function(res) {
            try {
                if(res.success && res.data) {
                    let pData = res.data;
                    if(typeof pData === 'string') { pData = JSON.parse(pData); }
                    
                    // FIX: Double clear to guarantee no duplicates
                    $select.html('<option value="">-- Load Preset --</option>');

                    if(Object.keys(pData).length > 0) {
                        $select.data('presets', pData);
                        $.each(pData, function(presetName, dataObj) {
                            let daysLen = 0;
                            if (Array.isArray(dataObj)) {
                                daysLen = dataObj.length;
                            } else if (dataObj && dataObj.itinerary !== undefined) {
                                if(Array.isArray(dataObj.itinerary)) {
                                    daysLen = dataObj.itinerary.length;
                                } else if(typeof dataObj.itinerary === 'object' && dataObj.itinerary !== null) {
                                    daysLen = Object.keys(dataObj.itinerary).length;
                                }
                            } else if (dataObj && typeof dataObj === 'object') {
                                daysLen = Object.keys(dataObj).length;
                            }
                            $select.append(`<option value="${presetName}">${presetName} (${daysLen} Days)</option>`);
                        });
                    }
                }
            } catch(e) { console.error("Error loading presets: ", e); }
        });
    }

    $('#new_preset_name').on('input', function() {
        if($(this).val() !== $('#itinerary_preset_select').val()) $('#save_itinerary_preset').text('Save as Preset');
        else $('#save_itinerary_preset').text('Update Preset');
    });

    $('#save_itinerary_preset').on('click', function() {
        let presetName = $('#new_preset_name').val();
        let btn = $(this);
        if(!presetName) { alert("Please enter a Preset Name"); return; }
        let formDataArray = $('#tcc-calc-form').serializeArray();
        formDataArray.push({ name: 'action', value: 'tcc_save_itinerary_preset' });
        formDataArray.push({ name: 'preset_name', value: presetName });
        let formData = $.param(formDataArray);
        btn.text('Saving...');
        $.post(tcc_ajax_obj.ajax_url, formData, function(res) {
            btn.text('Save/Update Preset');
            if(res.success) {
                $('#preset_msg').text('Saved successfully!').css('color', '#16a34a').fadeIn().delay(2000).fadeOut();
                loadPresets(); 
                setTimeout(function(){ $('#itinerary_preset_select').val(presetName).trigger('change'); }, 500); 
            } else {
                $('#preset_msg').text('Error saving').css('color', 'red').fadeIn().delay(3000).fadeOut();
            }
        });
    });

    $('#delete_itinerary_preset').on('click', function() {
        let presetName = $('#itinerary_preset_select').val();
        let dest = $('#calc_destination').val();
        if(!presetName || !dest) return;
        if(!confirm("Are you sure you want to permanently delete the preset '" + presetName + "'?")) return;

        $.post(tcc_ajax_obj.ajax_url, {
            action: 'tcc_delete_itinerary_preset',
            presetName: presetName,
            destination: dest
        }, function(res) {
            if(res.success) {
                $('#preset_msg').text('Deleted successfully!').css('color', '#dc2626').fadeIn().delay(2000).fadeOut();
                loadPresets();
                $('#new_preset_name').val('');
            } else {
                alert("Error deleting preset.");
            }
        });
    });

    // --- BOOKING & PAYMENTS MANAGER LOGIC ---
    window.tccAllQuotes = [];

    function renderQuotesDropdown(filterText = '', filterType = 'both') {
        let term = filterText.toLowerCase();
        
        if (filterType === 'both' || filterType === 'pmt') {
            let $selPmt = $('#pmt_quote_select');
            if($selPmt.length > 0) {
                $selPmt.empty().append('<option value="">-- Select Quote/Booking --</option>');
                $.each(window.tccAllQuotes, function(i, q) {
                    let searchStr = `${q.title} ${q.c_name} ${q.c_phone} ${q.c_email}`.toLowerCase();
                    if(term === '' || searchStr.includes(term)) {
                        let details = [];
                        if(q.c_name) details.push(q.c_name);
                        if(q.c_phone) details.push(q.c_phone);
                        let label = q.title + (details.length ? ' [' + details.join(' | ') + ']' : ' [No Client Details]');
                        $selPmt.append($('<option></option>').val(q.id).text(label));
                    }
                });
            }
        }

        if (filterType === 'both' || filterType === 'calc') {
            let $selCalc = $('#calc_edit_quote_select');
            if($selCalc.length > 0) {
                $selCalc.empty().append('<option value="">-- Create New Quote --</option>');
                $.each(window.tccAllQuotes, function(i, q) {
                    let searchStr = `${q.title} ${q.c_name} ${q.c_phone} ${q.c_email}`.toLowerCase();
                    if(term === '' || searchStr.includes(term)) {
                        let details = [];
                        if(q.c_name) details.push(q.c_name);
                        if(q.c_phone) details.push(q.c_phone);
                        let label = q.title + (details.length ? ' [' + details.join(' | ') + ']' : '');
                        $selCalc.append($('<option></option>').val(q.id).text(label));
                    }
                });
            }
        }
    }

    function loadPaymentQuotes() {
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_quotes_list' }, function(res) {
            if(res.success) {
                window.tccAllQuotes = res.data;
                renderQuotesDropdown($('#pmt_quote_search').length ? $('#pmt_quote_search').val() : '', 'pmt');
                renderQuotesDropdown($('#calc_quote_search').length ? $('#calc_quote_search').val() : '', 'calc');
            }
        });
    }

    $('#calc_quote_search').on('input', function() {
        renderQuotesDropdown($(this).val(), 'calc');
    });

    $('#calc_edit_quote_select').on('change', function() {
        let qid = $(this).val();
        if(!qid) {
            $('#tcc-calc-form')[0].reset();
            $('#edit_quote_id').val('');
            $('#tcc_calculate_btn').text('Generate Quote Link');
            $('#transport-wrapper').empty();
            $('#night-stay-wrapper').empty();
            $('#addons-wrapper').empty();
            
            addStayRow(); 
            addTransportRow(); 
            
            $('#day-wise-wrapper').empty();
            $('#live_error').hide();
            $('#tcc_link_wrapper').hide();
            setTimeout(triggerLiveCalculation, 100);
            return;
        }

        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_get_full_quote_data', quote_id: qid }, function(res) {
            if(res.success) {
                let d = res.data.summary;
                let r = res.data.raw; 
                
                $('#edit_quote_id').val(qid);
                $('#tcc_calculate_btn').text('Update Quote Link');

                $('#client_name').val(d.client_name || '');
                $('#client_phone').val(d.client_phone || '');
                $('#client_email').val(d.client_email || '');
                $('#calc_destination').val(d.destination).trigger('change');
                
                setTimeout(() => {
                    $('#start_date').val(d.start_date);
                    $('#total_pax').val(d.pax);
                    $('#child_pax').val(d.child);
                    $('#child_6_12_pax').val(d.child_6_12 || 0);
                    $('#total_days').val(d.days).data('prev-days', d.days);
                    $('#no_of_rooms').val(d.rooms);
                    $('#extra_beds').val(d.extra_beds);
                    
                    $('#calc_pickup').val(d.pickup);
                    $('#calc_pickup_custom').val(r.pickup_custom || '');
                    $('#calc_drop_custom').val(r.drop_custom || '');
                    $('#calc_drop').val(d.drop || d.pickup);
                    
                    $('#calc_hotel_cat').val(d.hotel_cat).trigger('change');
                    
                    $('#transport-wrapper').empty();
                    if(r && r.transports) {
                        r.transports.forEach((veh, i) => { 
                            let tDays = (r.trans_days && r.trans_days[i]) ? r.trans_days[i] : d.days;
                            let tPick = (r.trans_pickups && r.trans_pickups[i]) ? r.trans_pickups[i] : d.pickup;
                            let customRate = (r.trans_custom_rates && r.trans_custom_rates[i] !== undefined) ? r.trans_custom_rates[i] : '';
                            let customTotal = (r.trans_custom_totals && r.trans_custom_totals[i] !== undefined) ? r.trans_custom_totals[i] : '';
                            addTransportRow(veh, r.trans_qtys[i], tDays, tPick, customRate, customTotal); 
                        });
                    }
                    
                    $('#night-stay-wrapper').empty();
                    if(r && r.stay_places) {
                        r.stay_places.forEach((place, i) => { 
                            let cat = (r.stay_categories && r.stay_categories[i]) ? r.stay_categories[i] : d.hotel_cat;
                            addStayRow(place, cat, r.stay_hotels[i], r.stay_nights[i]); 
                        });
                    } else if (d.stays) {
                        d.stays.forEach((stay) => { addStayRow(stay.place, stay.category, '', stay.nights); }); 
                    }

                    $('#addons-wrapper').empty();
                    if(r && r.addon_names && r.addon_names.length > 0) {
                        r.addon_names.forEach((name, i) => { 
                            let price = (r.addon_prices && r.addon_prices[i]) ? r.addon_prices[i] : 0;
                            let type = (r.addon_types && r.addon_types[i]) ? r.addon_types[i] : 'flat';
                            addAddonRow(name, price, type); 
                        });
                    }

                    if(d.itinerary) {
                        $('#total_days').trigger('input');
                        setTimeout(() => {
                            $('#day-wise-wrapper .tcc-day-row').each(function(i) {
                                if(d.itinerary[i]) $(this).find('.tcc-day-input').val(d.itinerary[i]);
                                if(r.itinerary_stay_place && r.itinerary_stay_place[i]) {
                                    $(this).find('.tcc-day-stay-place').val(r.itinerary_stay_place[i]);
                                }
                            });
                        }, 500);
                    }

                    if(r) {
                        $('#calc_override_profit').val(r.override_profit || '');
                        $('#calc_manual_pp_override').val(r.manual_pp_override || '');
                        $('select[name="discount_1_type"]').val(r.d1_type || 'none');
                        $('input[name="discount_1_value"]').val(r.d1_val || 0);
                        $('select[name="discount_2_type"]').val(r.d2_type || 'none');
                        $('input[name="discount_2_value"]').val(r.d2_val || 0);
                    }

                    triggerLiveCalculation();
                }, 500); 
            }
        });
    });

    $('#pmt_quote_search').on('input', function() {
        renderQuotesDropdown($(this).val(), 'pmt');
        $('#pmt_dashboard, #pmt_quote_actions, #pmt_edit_client_wrapper').hide();
    });

    $('#pmt_quote_select').on('change', function() {
        let qid = $(this).val();
        $('#pmt_edit_client_wrapper').hide();

        if(!qid) { 
            $('#pmt_dashboard').slideUp(); 
            $('#pmt_quote_actions').hide();
            return; 
        }
        
        $('#pmt_quote_actions').css('display', 'flex');
        
        let quoteData = window.tccAllQuotes.find(q => q.id == qid);
        
        $('#pmt_email_quote_btn').show();
        
        if(!quoteData.c_name || !quoteData.c_phone) {
            $('#tcc-add-payment-wrapper').hide();
            $('#pmt_missing_client_msg').show();
        } else {
            $('#tcc-add-payment-wrapper').show();
            $('#pmt_missing_client_msg').hide();
        }
        
        refreshPaymentDashboard();
    });

    $('#pmt_view_quote_btn').on('click', function() {
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;
        let quoteData = window.tccAllQuotes.find(q => q.id == qid);
        if(quoteData && quoteData.link) {
            window.open(quoteData.link, '_blank');
        }
    });

    $('#pmt_copy_quote_btn').on('click', function() {
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;
        let quoteData = window.tccAllQuotes.find(q => q.id == qid);
        if(quoteData && quoteData.link) {
            copyToClipboardStr(quoteData.link, $(this));
        }
    });

    // EMAIL PROMPT LOGIC FOR SETTINGS DASHBOARD
    $('#pmt_email_quote_btn').on('click', function() {
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;
        
        let quoteData = window.tccAllQuotes.find(q => q.id == qid);
        let $btn = $(this);
        let originalText = $btn.text();
        
        if (!quoteData.c_email || quoteData.c_email.trim() === '') {
            let newEmail = prompt("⚠️ Client email is missing.\n\nPlease enter the client's email address to instantly save it and send the quotation:");
            if (!newEmail || newEmail.trim() === '') {
                return; 
            }
            
            $btn.text('Saving...').prop('disabled', true);
            
            $.post(tcc_ajax_obj.ajax_url, {
                action: 'tcc_update_quote_client',
                quote_id: qid,
                c_name: quoteData.c_name || '',
                c_phone: quoteData.c_phone || '',
                c_email: newEmail.trim()
            }, function(res) {
                if(res.success) {
                    quoteData.c_email = newEmail.trim(); 
                    sendEmailAJAX(qid, $btn, originalText); 
                } else {
                    $btn.text(originalText).prop('disabled', false);
                    alert("Failed to save the new email address.");
                }
            });
        } else {
            if(confirm('Send quotation email to ' + quoteData.c_email + ' (BCC sent to Admin)?')) {
                sendEmailAJAX(qid, $btn, originalText);
            }
        }
    });

    function sendEmailAJAX(quoteId, $btn, originalText) {
        $btn.text('Sending...').prop('disabled', true);
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_send_quote_email', quote_id: quoteId }, function(res) {
            $btn.text(originalText).prop('disabled', false);
            if(res.success) {
                alert(res.data);
            } else {
                alert("Error: " + res.data);
            }
        }).fail(function() {
            $btn.text(originalText).prop('disabled', false);
            alert("Server error occurred while sending email.");
        });
    }

    $('#pmt_edit_quote_btn').on('click', function() {
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;
        let quoteData = window.tccAllQuotes.find(q => q.id == qid);
        
        $('#edit_c_name').val(quoteData.c_name);
        $('#edit_c_phone').val(quoteData.c_phone);
        $('#edit_c_email').val(quoteData.c_email);
        $('#pmt_edit_client_wrapper').slideDown();
    });

    $('#pmt_full_edit_btn').on('click', function() {
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;
        
        if($('#calc_edit_quote_select').length > 0) {
            $('#calc_edit_quote_select').val(qid).trigger('change');
            $('html, body').animate({ scrollTop: $('#calc_edit_quote_select').offset().top - 50 }, 500);
        } else {
            alert("Please open the Calculator page and use the 'Load Quote to Edit' dropdown at the top.");
        }
    });

    $('#cancel_edit_client_btn').on('click', function() {
        $('#pmt_edit_client_wrapper').slideUp();
    });

    $('#save_edit_client_btn').on('click', function() {
        let qid = $('#pmt_quote_select').val();
        let btn = $(this);
        let data = {
            action: 'tcc_update_quote_client',
            quote_id: qid,
            c_name: $('#edit_c_name').val(),
            c_phone: $('#edit_c_phone').val(),
            c_email: $('#edit_c_email').val()
        };

        btn.text('Saving...').prop('disabled', true);
        $.post(tcc_ajax_obj.ajax_url, data, function(res) {
            btn.text('Save Details').prop('disabled', false);
            if(res.success) {
                $('#pmt_edit_client_wrapper').slideUp();
                $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_quotes_list' }, function(res2) {
                    if(res2.success) {
                        window.tccAllQuotes = res2.data;
                        renderQuotesDropdown($('#pmt_quote_search').length ? $('#pmt_quote_search').val() : '', 'pmt');
                        renderQuotesDropdown($('#calc_quote_search').length ? $('#calc_quote_search').val() : '', 'calc');
                        $('#pmt_quote_select').val(qid).trigger('change');
                    }
                });
            }
        });
    });

    $('#pmt_delete_quote_btn').on('click', function() {
        if(!confirm("Are you sure you want to permanently delete this quote/booking? This cannot be undone.")) return;
        let qid = $('#pmt_quote_select').val();
        
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_delete_quote', quote_id: qid }, function(res) {
            if(res.success) {
                $('#pmt_dashboard, #pmt_quote_actions, #pmt_edit_client_wrapper').hide();
                loadPaymentQuotes();
            }
        });
    });

    $('#pmt_method').on('change', function() {
        let btn = $('#tcc-add-payment-form button[type="submit"]');
        if($(this).val() === 'Refund') {
            btn.text('Process Refund').css({'background': '#dc2626', 'border-color': '#b91c1c'});
        } else {
            btn.text('Add Record').removeAttr('style');
            btn.css({'margin': '0', 'flex': '1', 'min-width': '100px'});
        }
    });

    function refreshPaymentDashboard() {
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;
        
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_quote_payments', quote_id: qid }, function(res) {
            if(res.success) {
                
                if(res.data.is_cancelled) {
                    $('#pmt_total_val').html(`<span style="text-decoration:line-through; opacity:0.5;">${formatINR(res.data.grand_total)}</span> <br><span style="font-size:10px; color:#dc2626; font-weight:bold;">CANCELLED</span>`);
                    $('#pmt_received_val').parent().find('div:first').text('TOTAL REFUNDED');
                    $('#pmt_received_val').text(formatINR(res.data.total_refunded)).css('color', '#dc2626');
                    $('#pmt_balance_val').parent().find('div:first').text('CANCELLATION INCOME');
                    $('#pmt_balance_val').text(formatINR(res.data.retained_income)).css('color', '#16a34a');
                } else {
                    $('#pmt_total_val').text(formatINR(res.data.grand_total));
                    $('#pmt_received_val').parent().find('div:first').text('TOTAL RECEIVED');
                    $('#pmt_received_val').text(formatINR(res.data.total_paid)).css('color', '#16a34a');
                    $('#pmt_balance_val').parent().find('div:first').text('BALANCE DUE');
                    $('#pmt_balance_val').text(formatINR(res.data.balance)).css('color', '#dc2626');
                }

                let phtml = '';
                if(res.data.payments.length > 0) {
                    phtml += `<table style="width:100%; border-collapse:collapse; font-size:12px; text-align:left;">
                        <tr style="background:#f1f5f9; border-bottom:1px solid #e2e8f0;">
                            <th style="padding:8px;">Date</th><th style="padding:8px;">Amount</th><th style="padding:8px;">Method</th><th style="padding:8px;">Ref</th><th style="padding:8px;">Action</th>
                        </tr>`;
                    $.each(res.data.payments, function(i, p) {
                        let isRefund = (p.method === 'Refund');
                        let amtColor = isRefund ? '#dc2626' : '#16a34a';
                        let amtPrefix = isRefund ? '-' : '';

                        phtml += `<tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:8px;">${p.date}</td>
                            <td style="padding:8px; font-weight:bold; color:${amtColor};">${amtPrefix}${formatINR(p.amount)}</td>
                            <td style="padding:8px;">${p.method}</td>
                            <td style="padding:8px; color:#64748b;">${p.ref}</td>
                            <td style="padding:8px;"><button type="button" class="del-payment-btn" data-id="${p.id}" style="background:none; border:none; color:#dc2626; cursor:pointer; text-decoration:underline;">Delete</button></td>
                        </tr>`;
                    });
                    phtml += `</table>`;
                } else {
                    phtml = `<div style="padding:15px; text-align:center; color:#64748b; font-size:13px;">No payments/refunds recorded yet.</div>`;
                }
                $('#pmt_history_table').html(phtml);
                $('#pmt_dashboard').slideDown();
            }
        });
    }

    $('#tcc-add-payment-form').on('submit', function(e) {
        e.preventDefault();
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;

        let btn = $(this).find('button[type="submit"]');
        let originalText = btn.text();
        btn.prop('disabled', true).text('Saving...');

        let data = {
            action: 'tcc_add_payment',
            quote_id: qid,
            amount: $('#pmt_amount').val(),
            date: $('#pmt_date').val(),
            method: $('#pmt_method').val(),
            ref: $('#pmt_ref').val()
        };

        $.post(tcc_ajax_obj.ajax_url, data, function(res) {
            btn.prop('disabled', false).text(originalText);
            if(res.success) {
                $('#pmt_amount, #pmt_ref').val('');
                refreshPaymentDashboard();
            }
        });
    });

    $(document).on('click', '.del-payment-btn', function() {
        if(!confirm('Delete this record?')) return;
        let pmt_id = $(this).data('id');
        let qid = $('#pmt_quote_select').val();
        
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_delete_payment', quote_id: qid, pmt_id: pmt_id }, function(res) {
            if(res.success) refreshPaymentDashboard();
        });
    });

    // --- SETTINGS DASHBOARD DYNAMICS ---

    $(document).on('click', '.tcc-rename-master', function(e) {
        e.preventDefault();
        let type = $(this).data('type');
        let dropdownId = $(this).data('dropdown');
        let oldVal = $(dropdownId).val();
        
        let dest = '';
        if(type === 'destination') {
            dest = oldVal;
        } else if(type === 'stay_place' || type === 'hotel_cat') {
            dest = $('#set_hotel_dest').val();
        } else if(type === 'pickup' || type === 'vehicle') {
            dest = $('#set_trans_dest').val();
        }
        
        if(!oldVal || oldVal === 'ADD_NEW') return alert("Please select an item from the dropdown first to rename it.");
        
        let newVal = prompt("Rename '" + oldVal + "' to:", oldVal);
        if(newVal && newVal.trim() !== '' && newVal !== oldVal) {
            $.post(tcc_ajax_obj.ajax_url, {
                action: 'tcc_rename_master_element',
                element_type: type,
                target_dest: dest,
                old_name: oldVal,
                new_name: newVal.trim()
            }, function(res) {
                if(res.success) {
                    tccMasterData = res.data.new_master;
                    rebuildDestinationDropdowns();
                    updateSettingsDropdowns();
                    updateCalculatorDropdowns();
                    showSettingsMessage(res.data.message);
                    $(dropdownId).val(newVal.trim()).trigger('change');
                } else {
                    showSettingsMessage(res.data.message || "Error renaming.");
                }
            });
        }
    });

    $('#tcc-global-settings-form').on('submit', function(e) {
        e.preventDefault();
        let btn = $(this).find('button[type="submit"]');
        let originalText = btn.text();
        btn.prop('disabled', true).text('Saving...');

        $.post(tcc_ajax_obj.ajax_url, $(this).serialize() + '&action=tcc_save_global_settings', function(res) {
            btn.prop('disabled', false).text(originalText);
            if(res.success) { 
                showSettingsMessage(res.data.message); 
                tccGlobalSettings.gst = parseFloat($('#global_gst').val());
                tccGlobalSettings.pt = parseFloat($('#global_pt').val());
                tccGlobalSettings.pg = parseFloat($('#global_pg').val());
                triggerLiveCalculation();
            }
        });
    });

    $('#add_season_btn').on('click', function() {
        let row = `
        <div class="season-row tcc-repeater-row tcc-fade-in">
            <input type="date" name="season_start[]" required style="flex:1;">
            <input type="date" name="season_end[]" required style="flex:1;">
            <input type="number" name="season_percent[]" placeholder="%" step="0.01" required style="flex:0.5;">
            <button type="button" class="remove_season tcc-btn-del">X</button>
        </div>`;
        $('#season-settings-wrapper').append(row);
    });
    $(document).on('click', '.remove_season', function() { $(this).closest('.season-row').remove(); });

    function updateSettingsDropdowns() {
        if(typeof tccMasterData === 'undefined') return;
        
        let h_dest = $('#set_hotel_dest').val();
        if(h_dest && tccMasterData[h_dest]) {
            populateDropdown('#set_hotel_stay', tccMasterData[h_dest].stay_places);
            populateDropdown('#set_hotel_cat', tccMasterData[h_dest].hotel_categories);
        } else {
            $('#set_hotel_stay, #set_hotel_cat').empty().append('<option value="">N/A</option>');
        }

        let t_dest = $('#set_trans_dest').val();
        if(t_dest && tccMasterData[t_dest]) {
            populateDropdown('#set_trans_pickup', tccMasterData[t_dest].pickups);
            populateDropdown('#set_trans_vehicle', tccMasterData[t_dest].vehicles);
        } else {
            $('#set_trans_pickup, #set_trans_vehicle').empty().append('<option value="">N/A</option>');
        }
    }

    function fetchHotelNamesList() {
        let dest = $('#set_hotel_dest').val();
        let place = $('#set_hotel_stay').val();
        let cat = $('#set_hotel_cat').val();

        if(dest && place && cat) {
            $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_fetch_hotel_names', dest: dest, place: place, cat: cat }, function(res) {
                let $dd = $('#hotel_name_dropdown');
                $dd.empty().append('<option value="">-- Add/Select --</option>');
                if(res.success && res.data.length > 0) {
                    $.each(res.data, function(i, item) { 
                        let hName = typeof item === 'object' ? item.hotel_name : item;
                        $dd.append(`<option value="${hName}">${hName}</option>`); 
                    });
                }
                $dd.append('<option value="ADD_NEW" style="font-weight:bold; color:#108043;">+ ADD NEW</option>');
                $dd.val('').trigger('change');
            });
        }
    }

    $('#hotel_name_dropdown').on('change', function() {
        let val = $(this).val();
        let $input = $('#set_hotel_name_input');
        
        if(val === 'ADD_NEW') {
            $input.val('').show().focus();
            $('#set_hotel_website, #set_room_price, #set_extra_bed, #set_child_price').val('');
            $('#hotel_fetch_status').text('(New)');
            $('#edit_hotel_name_btn').hide();
            $('#delete_hotel_btn').hide();
        } else if (val !== '') {
            $input.val(val).hide(); 
            fetchHotelPricingSpecific(val);
            $('#edit_hotel_name_btn').show();
            $('#delete_hotel_btn').show();
        } else {
            $input.val('').hide();
            $('#set_hotel_website, #set_room_price, #set_extra_bed, #set_child_price').val('');
            $('#hotel_fetch_status').text('');
            $('#edit_hotel_name_btn').hide();
            $('#delete_hotel_btn').hide();
        }
    });

    function fetchHotelPricingSpecific(hotel_name) {
        let dest = $('#set_hotel_dest').val();
        let place = $('#set_hotel_stay').val();
        let cat = $('#set_hotel_cat').val();
        $('#hotel_fetch_status').text('...');
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_fetch_hotel_rate', destination: dest, place: place, category: cat, hotel_name: hotel_name }, function(res) {
            if(res.success) {
                $('#hotel_fetch_status').text('(Edit)');
                $('#set_hotel_website').val(res.data.hotel_website);
                $('#set_room_price').val(res.data.room_price);
                $('#set_extra_bed').val(res.data.extra_bed_price);
                $('#set_child_price').val(res.data.child_price);
            }
        });
    }

    $('#edit_hotel_name_btn').on('click', function() {
        let currentName = $('#hotel_name_dropdown').val();
        if(!currentName || currentName === 'ADD_NEW') return;

        let newName = prompt("Enter new name for " + currentName + ":", currentName);
        if(newName && newName.trim() !== '' && newName !== currentName) {
            let dest = $('#set_hotel_dest').val();
            let place = $('#set_hotel_stay').val();
            let cat = $('#set_hotel_cat').val();

            $.post(tcc_ajax_obj.ajax_url, {
                action: 'tcc_rename_hotel_rate',
                set_destination: dest,
                set_night_stay_place: place,
                set_hotel_cat: cat,
                old_hotel_name: currentName,
                new_hotel_name: newName
            }, function(res) {
                if(res.success) {
                    showSettingsMessage(res.data.message);
                    fetchHotelNamesList(); 
                } else {
                    showSettingsMessage("Error renaming hotel.");
                }
            });
        }
    });

    $('#delete_hotel_btn').on('click', function() {
        let dest = $('#set_hotel_dest').val();
        let place = $('#set_hotel_stay').val();
        let cat = $('#set_hotel_cat').val();
        let hotel = $('#hotel_name_dropdown').val();

        if(!hotel || hotel === 'ADD_NEW') return;

        if(confirm('Are you sure you want to permanently delete this hotel?')) {
            $.post(tcc_ajax_obj.ajax_url, {
                action: 'tcc_delete_hotel_rate',
                set_destination: dest,
                set_night_stay_place: place,
                set_hotel_cat: cat,
                set_hotel_name: hotel
            }, function(res) {
                if(res.success) {
                    showSettingsMessage(res.data.message);
                    fetchHotelNamesList(); 
                    $('#edit_hotel_name_btn').hide();
                    $('#delete_hotel_btn').hide();
                } else {
                    showSettingsMessage("Error deleting hotel.");
                }
            });
        }
    });

    $('#delete_transport_btn').on('click', function() {
        let dest = $('#set_trans_dest').val();
        let pickup = $('#set_trans_pickup').val();
        let vehicle = $('#set_trans_vehicle').val();

        if(!dest || !pickup || !vehicle) return;

        if(confirm('Are you sure you want to permanently delete this transport rate?')) {
            $.post(tcc_ajax_obj.ajax_url, {
                action: 'tcc_delete_transport_rate',
                set_destination: dest,
                set_pickup_loc: pickup,
                set_vehicle: vehicle
            }, function(res) {
                if(res.success) {
                    showSettingsMessage(res.data.message);
                    fetchTransportPricing(); 
                } else {
                    showSettingsMessage("Error deleting transport.");
                }
            });
        }
    });

    $('#set_hotel_dest, #set_hotel_stay, #set_hotel_cat').on('change', function() {
        if($(this).attr('id') === 'set_hotel_dest') updateSettingsDropdowns();
        fetchHotelNamesList();
    });

    $('#set_trans_dest').on('change', function() { updateSettingsDropdowns(); fetchTransportPricing(); });

    function fetchTransportPricing() {
        let dest = $('#set_trans_dest').val();
        let pickup = $('#set_trans_pickup').val();
        let vehicle = $('#set_trans_vehicle').val();

        if(dest && pickup && vehicle) {
            $('#trans_fetch_status').text('...');
            $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_fetch_transport_rate', destination: dest, pickup: pickup, vehicle: vehicle }, function(res) {
                if(res.success) {
                    $('#trans_fetch_status').text('(Edit)');
                    $('#set_capacity').val(res.data.capacity);
                    $('#set_transport_price').val(res.data.price_per_day);
                    $('#delete_transport_btn').show();
                } else {
                    $('#trans_fetch_status').text('(New)');
                    $('#set_transport_price').val('');
                    $('#delete_transport_btn').hide();
                }
            });
        }
    }
    $('#set_trans_pickup, #set_trans_vehicle').on('change', fetchTransportPricing);
    
    function populateMasterForm(val) {
        $('#season-settings-wrapper').empty(); 

        if(val && tccMasterData[val]) {
            $('#master_dest_name').val(val);
            $('#master_profit').val(tccMasterData[val].profit_per_person || 0);
            
            let p = tccMasterData[val].pickups;
            $('#master_pickups').val(Array.isArray(p) ? p.join(', ') : p);
            
            let s = tccMasterData[val].stay_places;
            $('#master_stays').val(Array.isArray(s) ? s.join(', ') : s);
            
            let v = tccMasterData[val].vehicles;
            $('#master_vehicles').val(Array.isArray(v) ? v.join(', ') : v);
            
            let c = tccMasterData[val].hotel_categories;
            $('#master_hotel_cats').val(Array.isArray(c) ? c.join(', ') : c);

            $('#master_inclusions').val(tccMasterData[val].inclusions || '');
            $('#master_exclusions').val(tccMasterData[val].exclusions || '');
            $('#master_payment_terms').val(tccMasterData[val].payment_terms || '');

            if(tccMasterData[val].seasons && tccMasterData[val].seasons.length > 0) {
                $.each(tccMasterData[val].seasons, function(i, season) {
                    let row = `
                    <div class="season-row tcc-repeater-row tcc-fade-in">
                        <input type="date" name="season_start[]" value="${season.start}" required style="flex:1;">
                        <input type="date" name="season_end[]" value="${season.end}" required style="flex:1;">
                        <input type="number" name="season_percent[]" value="${season.percent}" placeholder="%" step="0.01" required style="flex:0.5;">
                        <button type="button" class="remove_season tcc-btn-del">X</button>
                    </div>`;
                    $('#season-settings-wrapper').append(row);
                });
            }
        } else {
            $('#tcc-master-settings-form')[0].reset();
            $('#master_dest_name').val('');
        }
    }

    $('#master_dest_select').on('change', function() { populateMasterForm($(this).val()); });

    setTimeout(() => { 
        rebuildDestinationDropdowns();
        populateMasterForm($('#master_dest_select').val());
        updateSettingsDropdowns();
        updateCalculatorDropdowns();
        fetchHotelNamesList(); 
        fetchTransportPricing(); 
        
        loadPresets(); 
        loadAddonPresets(); // Load custom addons

        if($('#night-stay-wrapper').children().length === 0) {
            addStayRow();
        }
        
        // This ensures the first Transport row is generated perfectly on fresh load!
        if($('#transport-wrapper').children().length === 0) {
            addTransportRow();
        }

        loadPaymentQuotes(); 
    }, 200);

    function showSettingsMessage(msg) { $('#tcc_settings_msg').text(msg).fadeIn().delay(3000).fadeOut(); }

    $('#tcc-master-settings-form').on('submit', function(e) {
        e.preventDefault();
        $.post(tcc_ajax_obj.ajax_url, $(this).serialize() + '&action=tcc_save_master_settings', function(res) {
            if(res.success) { 
                showSettingsMessage(res.data.message); 
                tccMasterData = res.data.new_master; 
                rebuildDestinationDropdowns(); 
                updateSettingsDropdowns();
                updateCalculatorDropdowns();
            }
        });
    });

    $('#tcc-settings-form').on('submit', function(e) {
        e.preventDefault();
        $.post(tcc_ajax_obj.ajax_url, $(this).serialize() + '&action=tcc_save_pricing_settings', function(res) {
            if(res.success) { showSettingsMessage(res.data.message); fetchHotelNamesList(); }
        });
    });

    $('#tcc-transport-settings-form').on('submit', function(e) {
        e.preventDefault();
        $.post(tcc_ajax_obj.ajax_url, $(this).serialize() + '&action=tcc_save_transport_settings', function(res) {
            if(res.success) showSettingsMessage(res.data.message);
        });
    });

    // --- BACKUP & RESTORE LOGIC ---
    
    $('#tcc_export_btn').on('click', function() {
        let btn = $(this);
        let originalText = btn.text();
        btn.text('Exporting...').prop('disabled', true);
        
        $.ajax({
            url: tcc_ajax_obj.ajax_url,
            type: 'POST',
            data: { action: 'tcc_export_backup' },
            success: function(res) {
                btn.text(originalText).prop('disabled', false);
                if(res.success) {
                    try {
                        let dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(res.data));
                        let downloadAnchorNode = document.createElement('a');
                        downloadAnchorNode.setAttribute("href", dataStr);
                        
                        let dateStr = new Date().toISOString().split('T')[0];
                        downloadAnchorNode.setAttribute("download", "tcc_backup_" + dateStr + ".json");
                        
                        document.body.appendChild(downloadAnchorNode); 
                        downloadAnchorNode.click();
                        downloadAnchorNode.remove();
                        
                        showSettingsMessage("Backup downloaded successfully!");
                    } catch (e) {
                        alert("Error generating file. The data might be too large.");
                        console.error(e);
                    }
                } else {
                    alert("Export failed: " + (res.data || "You might not have permission."));
                }
            },
            error: function(xhr, status, error) {
                btn.text(originalText).prop('disabled', false);
                alert("A server error occurred. Please check your browser console (F12) for details.");
                console.error("Export Server Error:", status, error);
                console.error("Response Text:", xhr.responseText);
            }
        });
    });

    $('#tcc_import_btn').on('click', function() {
        let fileInput = $('#tcc_import_file')[0];
        let file = fileInput.files[0];
        
        if(!file) {
            alert("Please select a .json backup file first.");
            return;
        }
        
        if(!confirm("CRITICAL WARNING:\n\nRestoring a backup will instantly wipe all current Master Settings, Quotes, Rates, and Clients, replacing them with the uploaded file.\n\nAre you absolutely sure you want to proceed?")) {
            return;
        }
        
        let btn = $(this);
        let originalText = btn.text();
        btn.text('Restoring Data...').prop('disabled', true);

        // Read file contents via HTML5 FileReader
        let reader = new FileReader();
        reader.onload = function(e) {
            $.ajax({
                url: tcc_ajax_obj.ajax_url + '?action=tcc_import_backup',
                type: 'POST',
                contentType: 'application/json',
                data: e.target.result,
                success: function(res) {
                    if(res.success) {
                        alert(res.data);
                        location.reload(); // Refresh the page to load new settings
                    } else {
                        btn.text(originalText).prop('disabled', false);
                        alert("Restore failed: " + (res.data || 'Invalid file format.'));
                    }
                },
                error: function() {
                    btn.text(originalText).prop('disabled', false);
                    alert("A server error occurred during the restore process.");
                }
            });
        };
        reader.readAsText(file);
    });

    // --- QUICK NOTES LOGIC ---
    $('#tcc-notes-toggle, #tcc-notes-close').on('click', function() {
        let $modal = $('#tcc-notes-modal');
        if($modal.is(':visible')) {
            $modal.fadeOut(200);
        } else {
            $modal.css('display', 'flex').hide().fadeIn(200);
            loadNotes();
        }
    });

    $('#tcc-notes-modal').on('click', function(e) {
        if(e.target === this) {
            $(this).fadeOut(200);
        }
    });

    $('#tcc-notes-filter').on('change', function() {
        if(window.tccGlobalNotes) {
            renderNotes(window.tccGlobalNotes);
        }
    });

    function loadNotes() {
        $('#tcc-notes-list').html('<div style="text-align:center; color:#64748b;">Loading notes...</div>');
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_notes' }, function(res) {
            if(res.success) {
                window.tccGlobalNotes = res.data; 
                renderNotes(res.data);
            }
        });
    }

    function renderNotes(notes) {
        let filterGroup = $('#tcc-notes-filter').val() || 'All';
        let html = '';
        let groups = new Set();
        
        if(!notes || notes.length === 0) {
            html = '<div style="text-align:center; color:#94a3b8; font-size:13px; padding:20px;">No notes saved yet. Type below to add one!</div>';
        } else {
            $.each(notes, function(i, note) {
                let grp = note.group || 'General';
                groups.add(grp);
                
                if(filterGroup !== 'All' && filterGroup !== grp) return;

                let escapedText = $('<div>').text(note.text).html().replace(/\n/g, '<br>');
                html += `
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:4px; padding:12px; margin-bottom:12px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span style="background:#e2e8f0; color:#334155; padding:2px 6px; border-radius:3px; font-size:10px; font-weight:bold; text-transform:uppercase;">${grp}</span>
                    </div>
                    <div style="font-size:13px; color:#334155; margin-bottom:12px; line-height:1.5;">${escapedText}</div>
                    <div style="display:flex; gap:6px; justify-content:flex-end;">
                        <button type="button" class="tcc-copy-note tcc-btn-secondary" data-text="${encodeURIComponent(note.text)}" style="margin:0; padding:4px 10px; font-size:10px; background:#e0e7ff; color:#2563eb; border-color:#bfdbfe;">📋 Copy</button>
                        <button type="button" class="tcc-edit-note tcc-btn-secondary" data-id="${note.id}" data-text="${encodeURIComponent(note.text)}" data-group="${encodeURIComponent(grp)}" style="margin:0; padding:4px 10px; font-size:10px;">✏️ Edit</button>
                        <button type="button" class="tcc-delete-note tcc-btn-del" data-id="${note.id}" style="margin:0; padding:4px 10px; font-size:10px; background:#fee2e2; color:#dc2626; border-color:#fecaca;">🗑️ Del</button>
                    </div>
                </div>`;
            });
            if(html === '') html = '<div style="text-align:center; color:#94a3b8; font-size:13px; padding:20px;">No notes found in this group.</div>';
        }
        $('#tcc-notes-list').html(html);

        let currentFilter = $('#tcc-notes-filter').val();
        let filterOptions = '<option value="All">All Groups</option>';
        let datalistOptions = '';
        
        let sortedGroups = Array.from(groups).sort();
        sortedGroups.forEach(g => {
            let sel = (currentFilter === g) ? 'selected' : '';
            filterOptions += `<option value="${g}" ${sel}>${g}</option>`;
            datalistOptions += `<option value="${g}">`;
        });
        
        $('#tcc-notes-filter').html(filterOptions);
        $('#tcc-note-groups-list').html(datalistOptions);
    }

    $('#tcc-save-note-btn').on('click', function() {
        let text = $('#tcc-new-note-text').val();
        let group = $('#tcc-new-note-group').val() || 'General';
        let id = $('#tcc-edit-note-id').val();
        let btn = $(this);
        if(!text.trim()) return;

        btn.text('Saving...').prop('disabled', true);
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_save_note', note_text: text, note_group: group, note_id: id }, function(res) {
            btn.text('Save Note').prop('disabled', false);
            if(res.success) {
                $('#tcc-new-note-text').val('');
                $('#tcc-edit-note-id').val('');
                $('#tcc-cancel-edit-note').hide();
                window.tccGlobalNotes = res.data; 
                renderNotes(res.data);
            }
        });
    });

    $(document).on('click', '.tcc-edit-note', function() {
        let text = decodeURIComponent($(this).data('text'));
        let group = decodeURIComponent($(this).data('group'));
        let id = $(this).data('id');
        
        $('#tcc-new-note-text').val(text);
        $('#tcc-new-note-group').val(group);
        $('#tcc-edit-note-id').val(id);
        
        $('#tcc-cancel-edit-note').show();
        $('#tcc-save-note-btn').text('Update Note');
    });

    $('#tcc-cancel-edit-note').on('click', function() {
        $('#tcc-new-note-text').val('');
        $('#tcc-new-note-group').val('General');
        $('#tcc-edit-note-id').val('');
        $(this).hide();
        $('#tcc-save-note-btn').text('Save Note');
    });

    $(document).on('click', '.tcc-delete-note', function() {
        if(!confirm('Are you sure you want to delete this note?')) return;
        let id = $(this).data('id');
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_delete_note', note_id: id }, function(res) {
            if(res.success) {
                window.tccGlobalNotes = res.data; 
                renderNotes(res.data);
            }
        });
    });

    $(document).on('click', '.tcc-copy-note', function() {
        let text = decodeURIComponent($(this).data('text'));
        copyToClipboardStr(text, $(this));
    });

});