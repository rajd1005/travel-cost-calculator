jQuery(document).ready(function($) {

    // === SECURITY NONCE INJECTION ===
    $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
        let token = typeof tcc_ajax_obj !== 'undefined' ? tcc_ajax_obj.nonce : (typeof tcc_exp_obj !== 'undefined' ? tcc_exp_obj.nonce : '');
        if (options.data) {
            if (typeof options.data === 'string') {
                options.data += '&security=' + encodeURIComponent(token);
            } else {
                options.data.security = token;
            }
        } else {
            options.data = $.param({ security: token });
        }
    });

    // === CRITICAL FIX: Bypass native HTML5 validation on hidden tabs ===
    $('#tcc-calc-form').attr('novalidate', 'novalidate');

    // === REUSABLE TOAST NOTIFICATION SYSTEM ===
    window.tccShowToast = function(message, isError = false) {
        let bg = isError ? '#dc2626' : '#16a34a'; 
        let icon = isError ? '⚠️ ' : '✅ ';
        let toast = $(`<div style="position:fixed; bottom:25px; right:25px; background:${bg}; color:#fff; padding:12px 24px; border-radius:6px; z-index:99999; box-shadow:0 10px 15px rgba(0,0,0,0.2); font-size:14px; font-weight:bold; opacity:0; transform:translateY(20px); transition:all 0.3s ease;">${icon}${message}</div>`);
        
        $('body').append(toast);
        
        setTimeout(() => toast.css({opacity: 1, transform: 'translateY(0)'}), 10);
        
        setTimeout(() => {
            toast.css({opacity: 0, transform: 'translateY(20px)'});
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    };

    // === INTERCONNECTIVITY EVENT LISTENERS ===
    $(document).on('tcc_data_updated', function(e, source) {
        if (source === 'tcc-script') return; 
        
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_get_sync_data' }, function(res) {
            if (res.success) {
                window.tccMasterData = res.data.master;
                window.tccGlobalSettings = res.data.global;
                rebuildDestinationDropdowns();
                updateSettingsDropdowns();
                updateCalculatorDropdowns();
                updateQuoteTerms(); 
                triggerLiveCalculation(); 
            }
        });
    });

    $(document).on('tcc_finances_updated', function(e, source) {
        if (source === 'tcc-script') return;
        loadPaymentQuotes(); 
        if ($('#pmt_quote_select').val()) {
            refreshPaymentDashboard(); 
        }
    });

    
// --- FIXED DEPARTURE (GROUP TOURS) LOGIC ---

    // Calculator Toggle
    $('input[name="calc_mode"]').on('change', function() {
        if($(this).val() === 'fixed') {
            $('#tcc-custom-quote-wrapper').hide();
            $('#tcc-fixed-departure-wrapper').show();
            loadFixedTours();
        } else {
            $('#tcc-fixed-departure-wrapper').hide();
            $('#tcc-custom-quote-wrapper').show();
            if (typeof triggerLiveCalculation === 'function') triggerLiveCalculation();
        }
    });

    window.tccActiveFixedTours = [];
    function loadFixedTours() {
        let $select = $('#fd_select_tour');
        let oldVal = $select.val();
        $select.html('<option value="">-- Loading Tours... --</option>');
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_get_fixed_tours' }, function(res) {
            if(res.success && res.data.length > 0) {
                window.tccActiveFixedTours = res.data;
                $select.empty().append('<option value="">-- Select Group Tour --</option>');
                res.data.forEach(tour => {
                    $select.append(`<option value="${tour.id}">${tour.name} (${tour.dates.length} Upcoming Dates)</option>`);
                });
                if(oldVal) $select.val(oldVal);
                calcFixedTourTotal();
            } else {
                $select.html('<option value="">-- No Tours Available --</option>');
            }
        });
    }

    $('#fd_select_tour').on('change', function() {
        let tid = $(this).val();
        $('#fd_selected_start_date, #fd_selected_end_date').val('');
        
        if(!tid) {
            $('#fd_date_selection_wrapper').hide();
            calcFixedTourTotal();
            return;
        }

        let tour = window.tccActiveFixedTours.find(t => t.id == tid);
        if(tour && tour.dates && tour.dates.length > 0) {
            let btnsHtml = '';
            tour.dates.forEach(d => {
                let dObj = new Date(d.start_date);
                let formatted = dObj.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'});
                if (formatted === 'Invalid Date') formatted = d.start_date;

let isSoldOut = d.available <= 0;
let disabledAttr = isSoldOut ? 'disabled' : '';
let availClass = isSoldOut ? 'fd-seat-sold' : 'fd-seat-avail';
let availText = isSoldOut ? 'Sold Out' : `${d.available}/${d.capacity} Seats`;

btnsHtml += `<button type="button" class="fd-date-btn-modern tcc-btn-secondary" data-start="${d.start_date}" data-end="${d.end_date}" ${disabledAttr}>
    <div class="fd-date-check">✓</div>
    <span class="fd-date-main-text">🗓️ ${formatted}</span>
    <span class="fd-date-sub-text ${availClass}">${availText}</span>
</button>`;
            });
            $('#fd_date_buttons').html(btnsHtml);
            $('#fd_date_selection_wrapper').show();
        } else {
            $('#fd_date_selection_wrapper').hide();
        }
        calcFixedTourTotal();
    });

// --- 1. FIXED DATE BUTTON CLICK & COLOR HIGHLIGHTING ---
$(document).on('click', '.fd-date-btn-modern', function() {
    if($(this).prop('disabled')) return; 
    
    // Toggle active class (styling is handled in CSS)
    $('.fd-date-btn-modern').removeClass('selected');
    $(this).addClass('selected');
    
    $('#fd_selected_start_date').val($(this).data('start'));
    $('#fd_selected_end_date').val($(this).data('end'));
    
    calcFixedTourTotal();
});

    // --- 2. THE TOTAL CALCULATOR ---
    function calcFixedTourTotal() {
        let tid = $('#fd_select_tour').val();
        let selectedDate = $('#fd_selected_start_date').val();

        if(!tid || !selectedDate) {
            $('#fd_live_total').text(!selectedDate && tid ? 'Select Date First' : '₹0.00');
            if(!selectedDate && tid) $('#fd_live_total').css('font-size', '16px'); // Adjust size for text
            else $('#fd_live_total').css('font-size', '24px');
            return;
        }
        
        $('#fd_live_total').css('font-size', '24px'); // Reset font size

        let tour = window.tccActiveFixedTours.find(t => t.id == tid);
        if(!tour) return;

        let paxDb = parseInt($('#fd_pax_double').val()) || 0;
        let paxTr = parseInt($('#fd_pax_triple').val()) || 0;
        let paxSg = parseInt($('#fd_pax_single').val()) || 0;
        let paxCh = parseInt($('#fd_pax_child').val()) || 0;
        let paxIn = parseInt($('#fd_pax_infant').val()) || 0;

        // Find the pricing for the exact selected date
        let datePrices = tour.prices; // Fallback
        if (tour.dates && tour.dates.length > 0) {
            let matchingDate = tour.dates.find(d => d.start_date === selectedDate);
            if (matchingDate && matchingDate.prices) {
                datePrices = matchingDate.prices;
            }
        }

        let total = 0;
        total += paxDb * parseFloat(datePrices.double || 0);
        total += paxTr * parseFloat(datePrices.triple || 0);
        total += paxSg * parseFloat(datePrices.single || 0);
        total += paxCh * parseFloat(datePrices.child || 0);
        total += paxIn * parseFloat(datePrices.infant || 0);

        let discType = $('#fd_discount_type').val();
        let discVal = parseFloat($('#fd_discount_val').val()) || 0;

        if (discType === 'flat') total -= discVal;
        else if (discType === 'percent') total -= (total * (discVal / 100));

        if(total < 0) total = 0;

        $('#fd_live_total').text(typeof formatINR === 'function' ? formatINR(total) : '₹' + total.toFixed(2));
    }

    // --- 3. AUTO-UPDATE TRIGGER (Crucial: Connects the +/- buttons to the calculator) ---
$('#fd_pax_double, #fd_pax_triple, #fd_pax_single, #fd_pax_child, #fd_pax_infant, #fd_discount_type, #fd_discount_val').off('input change').on('input change', function() {
    calcFixedTourTotal();
});

// --- QTY BTNS ---
    $(document).on('click', '.tcc-qty-btn', function() {
        let $wrapper = $(this).closest('.tcc-qty-wrapper');
        let $input = $wrapper.find('input[type="number"]');
        let val = parseInt($input.val());
        let min = parseInt($input.attr('min'));
        
        // 1. Determine step dynamically (Fallback to element IDs for specific Fixed Departure inputs)
        let step = parseInt($input.attr('step')) || 1;
        let inputId = $input.attr('id');
        
        if (inputId === 'fd_pax_double') step = 2;
        else if (inputId === 'fd_pax_triple') step = 3;

        if (isNaN(min)) min = 0;
        if (isNaN(val)) val = min > 0 ? min - step : 0; 

        // 2. Self-correct manual typing (If someone types 1 in Double, make + go to 2, and - go to 0)
        let remainder = val % step;

        if ($(this).hasClass('plus')) {
            if (remainder !== 0) {
                $input.val(val + (step - remainder)); // Round up to next multiple
            } else {
                $input.val(val + step);
            }
        } else if ($(this).hasClass('minus')) {
            if (remainder !== 0) {
                $input.val(val - remainder); // Round down to previous multiple
            } else {
                if (val - step >= min) {
                    $input.val(val - step);
                } else {
                    $input.val(min);
                }
            }
        }
        $input.trigger('input'); 
    });

    // --- STEP NAVIGATION FIX ---
    $('#tcc_next_step').on('click', function() {
        $('#tcc-step-1').hide().removeClass('active');
        $('#tcc-step-2').fadeIn().addClass('active');
        $('#tcc_step_indicator').text('Step 2 of 2');
        $('html, body').animate({ scrollTop: $('#tcc-step-2').offset().top - 30 }, 300);
    });

    $('#tcc_prev_step').on('click', function() {
        $('#tcc-step-2').hide().removeClass('active');
        $('#tcc-step-1').fadeIn().addClass('active');
        $('#tcc_step_indicator').text('Step 1 of 2');
        $('html, body').animate({ scrollTop: $('#tcc-step-1').offset().top - 30 }, 300);
    });
    // ---------------------------

    $('.tcc-step-accordion-header').on('click', function() {
        let $body = $(this).next('.tcc-step-accordion-body');
        let $icon = $(this).find('.tcc-acc-icon');
        
        if ($body.is(':visible')) {
            $body.slideUp(150);
            $icon.text('▼'); 
        } else {
            $('.tcc-step-accordion-body').slideUp(150);
            $('.tcc-step-accordion-header .tcc-acc-icon').text('▼');
            $body.slideDown(150);
            $icon.text('▲');
        }
    });

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
            let isSpecial = $el.is('#calc_pickup, #calc_drop, .transport_dropdown, .transport_pickup_dropdown, #calc_short_itinerary');
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

    function updateQuoteTerms() {
        let isEditing = $('#edit_quote_id').val();
        if(isEditing) return; 
        
        let dest = $('#calc_destination').val();
        if(typeof tccMasterData !== 'undefined' && tccMasterData[dest]) {
            function setQVal(id, val) {
                $('#' + id).val(val);
                let edNode = document.getElementById(id + '_editor');
                if(edNode) {
                    let $wrapper = $(edNode).closest('.tcc-quill-wrapper');
                    let $textarea = $wrapper.find('textarea');
                    $textarea.val(val);
                    
                    if (typeof Quill !== 'undefined') {
                        let quillInstance = Quill.find(edNode);
                        if (quillInstance) {
                            quillInstance.clipboard.dangerouslyPasteHTML(val);
                            return;
                        }
                    }
                    let $ed = $(edNode);
                    if($ed.find('.ql-editor').length) $ed.find('.ql-editor').html(val);
                    else $ed.html(val);
                }
            }
            setQVal('quote_inclusions', tccMasterData[dest].inclusions || '');
            setQVal('quote_exclusions', tccMasterData[dest].exclusions || '');
            setQVal('quote_payment_terms', tccMasterData[dest].payment_terms || '');
            setQVal('quote_important_note', tccMasterData[dest].important_note || '');
            setQVal('quote_why_choose_us', tccMasterData[dest].why_choose_us || '');
            setQVal('quote_essential_guidelines', tccMasterData[dest].essential_guidelines || '');

            $('#quote_head_office').val(tccMasterData[dest].head_office || 'Lake City Plaza, Karan Nagar Rd, Karan Nagar, Srinagar, Jammu and Kashmir 190010');
        } else {
            function clearQVal(id) {
                $('#' + id).val('');
                let edNode = document.getElementById(id + '_editor');
                if(edNode) {
                    let $wrapper = $(edNode).closest('.tcc-quill-wrapper');
                    let $textarea = $wrapper.find('textarea');
                    $textarea.val('');
                    
                    if (typeof Quill !== 'undefined') {
                        let quillInstance = Quill.find(edNode);
                        if (quillInstance) {
                            quillInstance.clipboard.dangerouslyPasteHTML('');
                            return;
                        }
                    }
                    let $ed = $(edNode);
                    if($ed.find('.ql-editor').length) $ed.find('.ql-editor').html('');
                    else $ed.html('');
                }
            }
            clearQVal('quote_inclusions');
            clearQVal('quote_exclusions');
            clearQVal('quote_payment_terms');
            clearQVal('quote_important_note');
            clearQVal('quote_why_choose_us');
            clearQVal('quote_essential_guidelines');
            $('#quote_head_office').val('');
        }
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
            populateDropdown('#calc_short_itinerary', tccMasterData[dest].short_itineraries);
            $('.transport_dropdown').each(function() { populateDropdown(this, tccMasterData[dest].vehicles); });
            $('.transport_pickup_dropdown').each(function() { populateDropdown(this, tccMasterData[dest].pickups); });
            
            setTimeout(function() { $('.night-stay-row').each(function() { updateRowHotels($(this)); }); }, 100);
        }
    }

    function updateRowHotels($row, forceSelectAll = false) {
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
            $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_fetch_hotel_names', dest: dest, place: place, cat: cat, is_settings: 1 }, function(res) {
                $hotelDrop.empty();
                if(res.success && res.data.length > 0) {
                    let allHotelNames = []; 
                    let fetchedCategory = cat; 
                    let $roomTypeDrop = $row.find('.stay_room_type_dropdown');
                    let preRoomType = $roomTypeDrop.data('pre') || $roomTypeDrop.val();
                    let uniqueRoomTypes = new Set();

                    $.each(res.data, function(i, item) { 
                        let hName = typeof item === 'object' ? item.hotel_name : item;
                        let hLink = typeof item === 'object' ? (item.hotel_website || '') : '';
                        
                        if (i === 0 && typeof item === 'object' && item.hotel_category) {
                            fetchedCategory = item.hotel_category;
                        }

                        if (typeof item === 'object' && item.room_types) {
                            try {
                                let rts = JSON.parse(item.room_types);
                                if (Array.isArray(rts)) rts.forEach(rt => uniqueRoomTypes.add(rt.name));
                            } catch(e) {}
                        }

                        $hotelDrop.append(`<option value="${hName}" data-link="${hLink}">${hName}</option>`); 
                        allHotelNames.push(hName); 
                    });
                    
                    $roomTypeDrop.empty().append('<option value="Default">Deluxe (Def)</option>');
                    uniqueRoomTypes.forEach(rtName => {
                        $roomTypeDrop.append(`<option value="${rtName}">${rtName}</option>`);
                    });

                    if (preRoomType && preRoomType !== 'Default') {
                        if ($roomTypeDrop.find(`option[value="${preRoomType}"]`).length > 0) {
                            $roomTypeDrop.val(preRoomType);
                        } else {
                            $roomTypeDrop.val('Default').css({'background-color': '#fca5a5', 'transition': '0.4s'});
                            setTimeout(() => { $roomTypeDrop.css('background-color', ''); }, 1500);
                        }
                    }
                    $roomTypeDrop.data('pre', '');

                    if (fetchedCategory !== cat) {
                        $catDrop.val(fetchedCategory);
                        $catDrop.css({'background-color': '#fef08a', 'transition': 'background-color 0.4s ease'});
                        setTimeout(() => { $catDrop.css('background-color', ''); }, 1500);
                    }

                    let preVals = (preValue && preValue !== 'None') ? preValue.split(',') : [];
                    let validPreVals = preVals.filter(v => allHotelNames.includes(v));
                    
                    if (forceSelectAll) {
                        $hotelDrop.val(allHotelNames);
                    } else if (validPreVals.length > 0 && fetchedCategory === cat) {
                        $hotelDrop.val(validPreVals);
                    } else {
                        $hotelDrop.val(allHotelNames); 
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

    $(document).on('change', '.stay_place_dropdown, .stay_cat_dropdown', function() { 
        updateRowHotels($(this).closest('.night-stay-row'), true); 
    });
    
    $('#calc_hotel_cat').on('change', function() { 
        let newVal = $(this).val();
        $('.stay_cat_dropdown').val(newVal); 
        $('.night-stay-row').each(function() { updateRowHotels($(this), true); }); 
    });

    $(document).on('change', '.stay_room_type_dropdown', function() { triggerLiveCalculation(); });

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

    function addTransportRow(vehicleName = '', qty = '1', tDays = null, pickupName = '', customRate = '', customTotal = '') {
        if (tDays === null) tDays = $('#total_days').val() || 1;
        if (qty === '' || qty === null) qty = '1';

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
            $('#addon_preset_select').val('').trigger('change');
        }
    });

    $(document).on('click', '.save_addon_row', function() {
        let row = $(this).closest('.addon-row');
        let name = row.find('.addon-name').val().trim();
        let type = row.find('.addon-type').val();
        let price = row.find('.addon-price').val();
        let dest = $('#calc_destination').val();
        
        if(!name || !dest) { tccShowToast("Enter Add-on Name and select Destination first.", true); return; }
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
                tccShowToast("Error saving Add-on.", true);
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
                tccShowToast("Error deleting preset.", true);
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

    function addStayRow(placeName = '', catVal = '', hotelVal = '', nights = 1, roomTypeVal = 'Default') {
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
        
        let roomTypeHtml = '';
        if (roomTypeVal !== 'Default') {
            roomTypeHtml = `<option value="${roomTypeVal}" selected>${roomTypeVal}</option>`;
        }
        
        let row = `
        <div class="night-stay-row tcc-repeater-row tcc-fade-in" style="flex-wrap:wrap; gap:5px;">
            <select name="stay_place[]" class="stay_place_dropdown" required style="flex:1.5;">
                <option value="">-- Select --</option>
                ${placeOptionsHtml}
            </select>
            <select name="stay_category[]" class="stay_cat_dropdown" required style="flex:1;">${catOptionsHtml}</select>
            <select name="stay_room_type[]" class="stay_room_type_dropdown" data-pre="${roomTypeVal}" style="flex:1; border:1px solid #ccc; border-radius:3px; padding:2px; font-size:12px;">
                <option value="Default">Deluxe (Def)</option>
                ${roomTypeHtml}
            </select>
            <div style="flex:2.5;">
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
    $('#tcc-calc-form').on('input change', 'input:not(.tcc-day-desc), select, textarea', function(e) {
        if(e.target.id === 'tcc_calculate_btn' || e.target.id === 'client_name' || e.target.id === 'client_phone' || e.target.id === 'client_email') return;
        if(e.target.id !== 'total_pax' && e.target.id !== 'child_pax' && e.target.id !== 'child_6_12_pax' && e.target.id !== 'calc_pickup') {
            triggerLiveCalculation();
        }
    });

    // --- QUILL.JS EDITOR ENGINE ---
    
    // Custom Blot for Horizontal Line & Inline Style Enforcers
    if (typeof Quill !== 'undefined') {
        let BlockEmbed = Quill.import('blots/block/embed');
        class DividerBlot extends BlockEmbed { }
        DividerBlot.blotName = 'divider';
        DividerBlot.tagName = 'hr';
        Quill.register(DividerBlot);

        // Force Quill to use inline HTML styles instead of CSS classes (survives wp_kses_post)
        let SizeStyle = Quill.import('attributors/style/size');
        SizeStyle.whitelist = null; // ALLOW ANY SIZE (px, em, small, large)
        
        let AlignStyle = Quill.import('attributors/style/align');
        AlignStyle.whitelist = null; 
        
        let ColorStyle = Quill.import('attributors/style/color');
        ColorStyle.whitelist = null; // ALLOW ANY COLOR (hex, rgb, etc.)
        
        let BackgroundStyle = Quill.import('attributors/style/background');
        BackgroundStyle.whitelist = null; // ALLOW ANY BACKGROUND COLOR
        
        Quill.register(SizeStyle, true);
        Quill.register(AlignStyle, true);
        Quill.register(ColorStyle, true);
        Quill.register(BackgroundStyle, true);
    }

    function initQuillEditors() {
        $('.tcc-quill-wrapper').each(function() {
            if ($(this).hasClass('quill-ready')) return;
            $(this).addClass('quill-ready');

            let $editorDiv = $(this).find('.tcc-quill-editor');
            let $hiddenTextarea = $(this).find('textarea');

            if ($hiddenTextarea.val() && $.trim($editorDiv.html()) === '') {
                $editorDiv.html($hiddenTextarea.val());
            }

            let quill = new Quill($editorDiv[0], {
                theme: 'snow',
                modules: {
                    toolbar: {
                        container: [
                            ['bold', 'italic', 'underline', 'strike'],
                            ['blockquote'],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'header': [3, false] }],
                            [{ 'size': ['small', false, 'large', 'huge'] }],
                            [{ 'color': [] }, { 'background': [] }],
                            [{ 'align': [] }],
                            ['clean'],
                            ['insert_hr', 'insert_special', 'html_toggle'] 
                        ],
                        handlers: {
                            'insert_hr': function() {
                                let range = this.quill.getSelection(true);
                                this.quill.insertEmbed(range.index, 'divider', true, Quill.sources.USER);
                                this.quill.setSelection(range.index + 1, Quill.sources.SILENT);
                            },
                            'insert_special': function() {
                                let char = prompt("Enter a special character or emoji (e.g. ★, ✈️, 🏨, ₹, ✓, ✔):");
                                if (char) {
                                    let range = this.quill.getSelection(true);
                                    this.quill.insertText(range.index, char, Quill.sources.USER);
                                }
                            },
                            'html_toggle': function() {
                                let wrapper = this.quill.container.closest('.tcc-quill-wrapper');
                                let $textarea = jQuery(wrapper).find('textarea');
                                let $editor = jQuery(wrapper).find('.ql-container');
                                let $btn = jQuery(wrapper).find('.ql-html_toggle');
                                
                                if ($textarea.is(':visible')) {
                                    this.quill.clipboard.dangerouslyPasteHTML($textarea.val());
                                    $textarea.hide();
                                    $editor.show();
                                    $btn.css('background', '');
                                } else {
                                    $textarea.val(this.quill.root.innerHTML);
                                    $editor.hide();
                                    $textarea.css({display: 'block', width: '100%', minHeight: '150px', padding: '10px', fontFamily: 'monospace', borderTop: 'none', border: '1px solid #cbd5e1', outline: 'none'});
                                    $btn.css('background', '#e2e8f0');
                                }
                            }
                        }
                    }
                }
            });

            // Add visual labels to the new custom buttons
            $editorDiv.siblings('.ql-toolbar').find('.ql-insert_hr').html('<span style="font-weight:bold; font-size:16px; line-height:1;" title="Insert Horizontal Line">―</span>');
            $editorDiv.siblings('.ql-toolbar').find('.ql-insert_special').html('<span style="font-weight:bold; font-size:16px; line-height:1;" title="Insert Special Character">★</span>');
            $editorDiv.siblings('.ql-toolbar').find('.ql-html_toggle').html('<span style="font-weight:bold; font-size:14px; line-height:1; color:#b93b59;" title="Raw HTML Mode">&lt;/&gt;</span>');

            quill.on('text-change', function() {
                if(!$hiddenTextarea.is(':visible')) {
                    $hiddenTextarea.val(quill.root.innerHTML);
                    triggerLiveCalculation();
                }
            });
            
            $hiddenTextarea.on('input', function() {
                if($(this).is(':visible')) {
                    triggerLiveCalculation();
                }
            });
        });
    }

    window.tccLiveSummaryData = null; 

    function triggerLiveCalculation() {
        if ($('input[name="calc_mode"]:checked').val() === 'fixed') return;

        // Sync all Quill editors right before any calculation IF in visual mode
        $('.tcc-quill-wrapper').each(function() {
            let qlEditor = $(this).find('.ql-editor');
            let $textarea = $(this).find('textarea');
            if(!$textarea.is(':visible') && qlEditor.length) {
                $textarea.val(qlEditor.html());
            }
        });

        $('#live_error').hide();
        $('#tcc_link_wrapper').slideUp(); 
        window.tccLiveSummaryData = null; 
        
        let validationError = validateCalculator();
        if (validationError) {
            $('#live_pp_base, #live_pp_inc, #live_profit, #live_discount, #live_total, #live_gst, #live_actual_cost, #live_total_hotel, #live_total_trans, #live_total_base').text('₹0.00').css('opacity', '1');
            $('#live_addons_row').hide();
            $('.tcc-trans-cost, .tcc-hotel-cost').text('');
            $('#live_actual_cost_breakdown, #live_pp_base_breakdown, #live_pp_inc_breakdown').hide(); 
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
                    
                    let baseBreakdown = `Adult: ${formatINR(d.summary_data.adult_base_pp)}`;
                    if (d.summary_data.child_6_12 > 0) baseBreakdown += ` | Child: ${formatINR(d.summary_data.child_base_pp)}`;
                    if (d.summary_data.child > 0) baseBreakdown += ` | Infant: ₹0.00`;
                    $('#live_pp_base_breakdown').text(baseBreakdown).show();

                    $('#live_pp_inc').text(formatINR(d.summary_data.per_person_with_gst));

                    let incBreakdown = `Adult: ${formatINR(d.summary_data.adult_inc_gst_pp)}`;
                    if (d.summary_data.child_6_12 > 0) incBreakdown += ` | Child: ${formatINR(d.summary_data.child_inc_gst_pp)}`;
                    if (d.summary_data.child > 0) incBreakdown += ` | Infant: ₹0.00`;
                    $('#live_pp_inc_breakdown').text(incBreakdown).show();
                    
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

                    let actualBreakdown = `Adult: ${formatINR(d.summary_data.adult_actual_pp)}`;
                    if (d.summary_data.child_6_12 > 0) actualBreakdown += ` | Child: ${formatINR(d.summary_data.child_actual_pp)}`;
                    if (d.summary_data.child > 0) actualBreakdown += ` | Infant: ₹0.00`;
                    $('#live_actual_cost_breakdown').text(actualBreakdown).show();

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
                    $('#live_actual_cost_breakdown, #live_pp_base_breakdown, #live_pp_inc_breakdown').hide(); 
                    $('#live_error').html(res.data.errors).show();
                }
            }
        });
    }

    window.tccLastQuoteData = null;

    $('#tcc-calc-form').on('submit', function(e) {
        e.preventDefault();

        // Handle Group Tour Submit
        if ($('input[name="calc_mode"]:checked').val() === 'fixed') {
            let tourId = $('#fd_select_tour').val();
            let cName = $('#fd_client_name').val();
            let cPhone = $('#fd_client_phone').val();
            
            if (!tourId) { tccShowToast("Please select a Group Tour from the list.", true); return; }
            // Note: Client Name and WhatsApp Number are now optional, restriction removed.

            let btn = $('#tcc_calculate_btn');
            btn.text('Processing...').prop('disabled', true);
            
            // Explicitly map the data since the Fixed Departure inputs rely on IDs, not names
            let startDate = $('#fd_selected_start_date').val();
            if (!startDate) { tccShowToast("Please select a specific departure date.", true); return; }

            let fixedData = {
                action: 'tcc_generate_fixed_booking',
                tour_id: tourId,
                selected_start_date: startDate,
                selected_end_date: $('#fd_selected_end_date').val(),
                client_name: cName,
                client_phone: cPhone,
                client_email: $('#fd_client_email').val(),
                pax_double: $('#fd_pax_double').val(),
                pax_triple: $('#fd_pax_triple').val(),
                pax_single: $('#fd_pax_single').val(),
                pax_child: $('#fd_pax_child').val(),
                pax_infant: $('#fd_pax_infant').val(),
                discount_type: $('#fd_discount_type').val(),
                discount_val: $('#fd_discount_val').val()
            };

                $.ajax({
                url: tcc_ajax_obj.ajax_url, type: 'POST',
                data: fixedData,
                success: function(response) {
                    btn.text('Generate Quote Link').prop('disabled', false);
                    if(response.success) {
                        $(document).trigger('tcc_finances_updated', ['tcc-script']);
                        tccShowToast("Group Tour Booking Generated!", false);
                        loadFixedTours(); // Refresh live seat counts
                        
                        window.tccLastQuoteData = response.data;

                        // --- NEW: Reset Group Form fields ---
                        $('#fd_client_name, #fd_client_phone, #fd_client_email').val('');
                        $('#fd_pax_double, #fd_pax_triple, #fd_pax_single, #fd_pax_child, #fd_pax_infant').val(0);
                        $('#fd_discount_val').val('');
                        $('#fd_select_tour').val('').trigger('change');
                        calcFixedTourTotal();
                        // ------------------------------------

                        $('#tcc_generated_link').val(response.data.permalink);
                        $('#tcc_open_btn').attr('href', response.data.permalink);
                        tccSetupQuoteLinkButtons(response.data.permalink, response.data.quote_id || response.data.post_id || 0);
                        $('#tcc_link_wrapper').slideDown();
                    } else {
                        $('#live_error').html(response.data.errors || "Error generating booking").show();
                    }
                },
                error: function() {
                    btn.text('Generate Quote Link').prop('disabled', false);
                    tccShowToast("Server error connecting to backend.", true);
                }
            });
            return;
        }

        // Standard Custom FIT Submit
        $('#live_error').hide();
        $('#tcc_link_wrapper').hide();
        
        let validationError = validateCalculator();
        if(validationError) { $('#live_error').html(validationError).show(); return; }

        $('.stay_hotel_dropdown').each(function() {
            let vals = $(this).val();
            $(this).siblings('.stay_hotel_hidden').val(vals ? (Array.isArray(vals) ? vals.join(',') : vals) : '');
        });
        
        // Final sync of RTEs before generate
        $('.tcc-quill-wrapper').each(function() {
            let qlEditor = $(this).find('.ql-editor');
            let $textarea = $(this).find('textarea');
            if(!$textarea.is(':visible') && qlEditor.length) {
                $textarea.val(qlEditor.html());
            }
        });

        let btn = $('#tcc_calculate_btn');
        btn.text('Processing...').prop('disabled', true);

$.ajax({
            url: tcc_ajax_obj.ajax_url, type: 'POST',
            data: $(this).serialize() + '&action=tcc_calculate_trip&generate_link=1',
            success: function(response) {
                btn.text('Generate Quote Link').prop('disabled', false);
                if(response.success) {
                    $(document).trigger('tcc_finances_updated', ['tcc-script']); // ADDED
                    window.tccLastQuoteData = response.data;
                    
                    // --- NEW: Reset Custom FIT Form Safely ---
                    $('#client_name, #client_phone, #client_email').val('');
                    $('#start_date, #end_date').val('');
                    $('#total_pax, #no_of_rooms, #total_days').val(1);
                    $('#child_6_12_pax, #child_pax, #extra_beds').val(0);
                    
                    $('#tcc-calc-form')[0].reset(); 
                    $('#calc_destination').val('').trigger('change'); // Resets repeaters and UI automatically
                    
                    // Clear Live Preview Pricing
                    $('#live_pp_base, #live_pp_inc, #live_profit, #live_discount, #live_total, #live_gst, #live_actual_cost, #live_total_hotel, #live_total_trans, #live_total_base').text('₹0.00');
                    $('#live_addons_row').hide();
                    $('.tcc-trans-cost, .tcc-hotel-cost').text('');
                    $('#live_actual_cost_breakdown, #live_pp_base_breakdown, #live_pp_inc_breakdown').hide();
                    // -----------------------------------------

                    $('#tcc_generated_link').val(response.data.permalink);
                    $('#tcc_open_btn').attr('href', response.data.permalink);
                    tccSetupQuoteLinkButtons(response.data.permalink, response.data.post_id || response.data.quote_id || 0);
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

    // Safely transforms HTML from the RTE into plain text for WhatsApp
    function stripHtmlToWA(html, bullet = "•") {
        if(!html) return "";
        let text = html.replace(/<br\s*[\/]?>/gi, "\n");
        text = text.replace(/<\/p>/gi, "\n\n");
        text = text.replace(/<li[^>]*>/gi, "\n" + bullet + " ");
        let tmp = document.createElement("DIV");
        tmp.innerHTML = text;
        text = tmp.textContent || tmp.innerText || "";
        text = text.replace(/\n{3,}/g, "\n\n");
        return text.trim();
    }

    function formatBullets(text, bullet) {
        if(!text) return "None specified.";
        if (text.indexOf('<li') !== -1 || text.indexOf('<p') !== -1 || text.indexOf('<div') !== -1 || text.indexOf('<br') !== -1) {
            return stripHtmlToWA(text, bullet);
        }
        return text.split(/\r\n|\n|\r/).filter(line => line.trim() !== '').map(line => `${bullet} ${line.trim()}`).join('\n');
    }

    function buildHotelsText(stays) {
        let msg = `*Hotels Selected*\n`;
        if (stays && stays.length > 0) {
            stays.forEach(stay => {
                if(stay.place === 'No Hotel Required') {
                    msg += `- No Hotel Required (${stay.nights}N)\n`;
                } else {
                    let cat = stay.category || '';
                    let catText = cat && cat !== '-' ? `[${cat}] ` : '';
                    let roomTxt = (stay.room_type && stay.room_type !== 'Default') ? stay.room_type : 'Deluxe Room';
                    
                    let opts = [];
                    if (stay.options && stay.options.length > 0) {
                        stay.options.forEach(opt => {
                            let linkStr = opt.link ? ` (${opt.link})` : '';
                            opts.push(`${opt.name}${linkStr}`);
                        });
                        msg += `- ${stay.place} (${stay.nights}N): ${catText}${opts.join(' OR ')} (${roomTxt}) / Similar\n`;
                    } else {
                        msg += `- ${stay.place} (${stay.nights}N): ${catText}None selected\n`;
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
        let dest = $('#calc_destination').val();
        let master = (typeof tccMasterData !== 'undefined' && tccMasterData[dest]) ? tccMasterData[dest] : {};

        let short_itinerary = $('#calc_short_itinerary').val() || '';

        let itinerary = [];
        let itinerary_stay_places = [];
        let itinerary_routes = [];
        let itinerary_desc = [];
        $('#day-wise-wrapper .tcc-day-row').each(function() {
            let text = $(this).find('.tcc-day-input').val();
            let stay = $(this).find('.tcc-day-stay-place').val();
            let route = $(this).find('.tcc-day-route-dropdown').val();
            let $textarea = $(this).find('.tcc-day-desc');
            let desc = $textarea.is(':visible') ? $textarea.val() : ($(this).find('.ql-editor').html() || $textarea.val());
            
            if(text && text.trim() !== '') {
                itinerary.push(text.trim());
                itinerary_stay_places.push(stay || '');
                itinerary_routes.push(route || '');
                itinerary_desc.push(desc || '');
            }
        });

        let dynamic_route_arr = itinerary_routes.filter(r => r.trim() !== '');
        let dynamic_route = dynamic_route_arr.length > 0 ? dynamic_route_arr.join(' → ') : short_itinerary;

        let stays = [];
        $('.night-stay-row').each(function() {
            let place = $(this).find('.stay_place_dropdown').val();
            let cat = $(this).find('.stay_cat_dropdown').val();
            let nights = $(this).find('.stay_nights').val();
            let roomType = $(this).find('.stay_room_type_dropdown').val() || 'Default';
            
            let opts = [];
            $(this).find('.stay_hotel_dropdown option:selected').each(function() {
                opts.push({ name: $(this).val(), link: $(this).data('link') || '' });
            });

            if (place && nights) {
                if(place === 'No Hotel') place = 'No Hotel Required';
                stays.push({place: place, category: cat, nights: nights, options: opts, room_type: roomType});
            }
        });

        let $inc_ta = $('#quote_inclusions');
        let inclusions = $inc_ta.is(':visible') ? $inc_ta.val() : ($('#quote_inclusions_editor .ql-editor').html() || $inc_ta.val());
        if (inclusions === undefined || inclusions === '') inclusions = master.inclusions || '';

        let $exc_ta = $('#quote_exclusions');
        let exclusions = $exc_ta.is(':visible') ? $exc_ta.val() : ($('#quote_exclusions_editor .ql-editor').html() || $exc_ta.val());
        if (exclusions === undefined || exclusions === '') exclusions = master.exclusions || '';

        let $pay_ta = $('#quote_payment_terms');
        let payment_terms = $pay_ta.is(':visible') ? $pay_ta.val() : ($('#quote_payment_terms_editor .ql-editor').html() || $pay_ta.val());
        if (payment_terms === undefined || payment_terms === '') payment_terms = master.payment_terms || '';

        let $guide_ta = $('#quote_essential_guidelines');
        let essential_guidelines = $guide_ta.is(':visible') ? $guide_ta.val() : ($('#quote_essential_guidelines_editor .ql-editor').html() || $guide_ta.val());
        if (essential_guidelines === undefined || essential_guidelines === '') essential_guidelines = master.essential_guidelines || '';

        let addonsText = [];
        $('#addons-wrapper .addon-row').each(function() {
            let name = $(this).find('.addon-name').val();
            if(name && name.trim() !== '') addonsText.push(name.trim());
        });
        
        if(addonsText.length > 0) {
            let addonsHtml = '<ul>';
            addonsText.forEach(a => addonsHtml += '<li>' + a + '</li>');
            addonsHtml += '</ul>';
            inclusions += addonsHtml;
        }

        return {
            short_itinerary: short_itinerary,
            dynamic_route: dynamic_route,
            inclusions: inclusions,
            exclusions: exclusions,
            payment_terms: payment_terms,
            essential_guidelines: essential_guidelines,
            itinerary: itinerary,
            itinerary_stay_places: itinerary_stay_places,
            itinerary_desc: itinerary_desc,
            stays: stays
        };
    }

    // --- QUICK COPY ACTION BUTTONS ---

    $('#tcc_quick_wa_btn').on('click', function() {
        if (!window.tccLiveSummaryData) { tccShowToast("Please calculate trip first to copy the Summary with Prices.", true); return; }
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
        if(!d.itinerary || d.itinerary.length === 0) { tccShowToast("Please fill up the Itinerary properly first.", true); return; }
        
        let msg = `*Itinerary Details*\n`;
        if (d.dynamic_route) {
            msg += `Route: ${d.dynamic_route}\n\n`;
        }
        d.itinerary.forEach((day_text, idx) => {
            msg += `👉Day ${idx + 1}: ${day_text}\n`;
            if (d.itinerary_desc && d.itinerary_desc[idx]) {
                msg += `${stripHtmlToWA(d.itinerary_desc[idx], '•')}\n`;
            }
            if (d.itinerary_stay_places && d.itinerary_stay_places[idx] && d.itinerary_stay_places[idx] !== 'No Hotel') {
                msg += `Night Stay: ${d.itinerary_stay_places[idx]}\n`;
            }
            msg += `\n`;
        });

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

// ─────────────────────────────────────────────────────────────────────────────
// QUOTE LINK BUTTONS — Copy Link / Download PDF / Share PDF / Open
// Called after BOTH Custom-FIT and Group-Tour (FD) quote generation succeeds.
// ─────────────────────────────────────────────────────────────────────────────

    window.tccCurrentQuoteUrl  = '';
    window.tccCurrentQuoteId   = 0;
    window.tccSharedPdfFile    = null;

    function tccSetupQuoteLinkButtons(url, postId) {
        window.tccCurrentQuoteUrl = url  || '';
        window.tccCurrentQuoteId  = parseInt(postId, 10) || 0;
        window.tccSharedPdfFile   = null;

        // Reset Download button
        $('#tcc_pdf_download').prop('disabled', false).text('📥 Download PDF');

        // Reset & potentially pre-fetch PDF for Share
        $('#tcc_pdf_share').hide().prop('disabled', true).text('⏳ Preparing share...').css('opacity', '0.7');

        if (window.tccCurrentQuoteId) {
            try {
                let canFileShare = !!(navigator.canShare && navigator.canShare({
                    files: [new File([new Blob([''], { type: 'application/pdf' })], 'test.pdf', { type: 'application/pdf' })]
                }));
                if (canFileShare) {
                    let $sb = $('#tcc_pdf_share').show();
                    let fd  = new FormData();
                    fd.append('action',   'tcc_front_generate_pdf');
                    fd.append('quote_id', window.tccCurrentQuoteId);
                    fd.append('security', tcc_ajax_obj.nonce);

                    fetch(tcc_ajax_obj.ajax_url, { method: 'POST', body: fd })
                        .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.blob(); })
                        .then(function(blob) {
                            if (!blob || blob.size < 200) throw new Error('Empty PDF');
                            let file = new File([blob], 'Quotation.pdf', { type: 'application/pdf' });
                            if (!navigator.canShare({ files: [file] })) throw new Error('Not shareable');
                            window.tccSharedPdfFile = file;
                            $sb.prop('disabled', false).text('📤 Share PDF').css('opacity', '1');
                        })
                        .catch(function(err) { console.warn('PDF prefetch for share:', err); $sb.hide(); });
                }
            } catch (e) { /* canShare not available */ }
        }
    }

    // ── 1. Copy Link ──────────────────────────────────────────────────────────
    $('#tcc_copy_btn').on('click', function() {
        let url  = window.tccCurrentQuoteUrl;
        let $btn = $(this);
        if (!url) return;

        let done = function() { $btn.text('✅ Copied!'); setTimeout(function() { $btn.text('📋 Copy Link'); }, 2000); };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(done).catch(function() {
                tccFallbackCopy(url); done();
            });
        } else {
            tccFallbackCopy(url); done();
        }
    });

    function tccFallbackCopy(text) {
        let ta = document.createElement('textarea');
        ta.value = text; ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;';
        document.body.appendChild(ta); ta.focus(); ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);
    }

    // ── 2. Download PDF ───────────────────────────────────────────────────────
    $('#tcc_pdf_download').on('click', function() {
        let qId  = window.tccCurrentQuoteId || 0;
        let $btn = $(this);
        if (!qId) { alert('Quote not found. Please regenerate the quote.'); return; }

        $btn.prop('disabled', true).text('⏳ Generating...');

        let form = document.createElement('form');
        form.method = 'POST'; form.action = tcc_ajax_obj.ajax_url;
        form.target = '_self'; form.style.display = 'none';
        [['action', 'tcc_front_generate_pdf'], ['quote_id', qId], ['security', tcc_ajax_obj.nonce]].forEach(function(pair) {
            let inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = pair[0]; inp.value = pair[1];
            form.appendChild(inp);
        });
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);

        setTimeout(function() { $btn.prop('disabled', false).text('📥 Download PDF'); }, 3500);
    });

    // ── 3. Share PDF (synchronous — PDF pre-fetched in tccSetupQuoteLinkButtons) ──
    $('#tcc_pdf_share').on('click', function() {
        let file = window.tccSharedPdfFile;
        if (!file) { alert('PDF is still being prepared. Please wait a moment and try again.'); return; }
        if (!navigator.canShare || !navigator.canShare({ files: [file] })) {
            alert("Your browser doesn't support sharing PDF files. Please use Download instead.");
            return;
        }
        navigator.share({ files: [file], title: 'Tour Quotation', text: 'Here is your tour quotation.' })
            .catch(function(err) {
                if (err && err.name !== 'AbortError') {
                    console.error('Share PDF error:', err);
                    alert('Could not share: ' + (err.message || err));
                }
            });
    });

    // --- DAY WISE LOGIC WITH AUTO STAY-SYNC --- //
    
    let tccSortableInstance = null;
    window.tccTempDayData = null;
    window.tccGlobalDayPresets = {};

    function loadSingleDayPresets() {
        let dest = $('#calc_destination').val();
        if(!dest) return;
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_single_day_presets', destination: dest }, function(res) {
            if(res.success) {
                window.tccGlobalDayPresets = res.data;
                let opts = '<option value="">Load Day Preset</option>';
                $.each(res.data, function(name, d) { opts += `<option value="${name}">${name}</option>`; });
                $('.tcc-day-preset-dropdown').html(opts);
            }
        });
    }

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

        // Capture existing rows to preserve selections (category, specific hotels, room type)
        let existingRows = [];
        $('#night-stay-wrapper .night-stay-row').each(function() {
            existingRows.push({
                place: $(this).find('.stay_place_dropdown').val(),
                cat: $(this).find('.stay_cat_dropdown').val(),
                hotels: $(this).find('.stay_hotel_hidden').val(),
                room: $(this).find('.stay_room_type_dropdown').val()
            });
        });

        $('#night-stay-wrapper').empty();
        let defaultCat = $('#calc_hotel_cat').val();

        grouped.forEach(g => {
            // Find existing row config for this place
            let matchIdx = existingRows.findIndex(r => r.place === g.place);
            let cat = defaultCat;
            let hotels = '';
            let room = 'Default';

            if (matchIdx !== -1) {
                cat = existingRows[matchIdx].cat;
                hotels = existingRows[matchIdx].hotels;
                room = existingRows[matchIdx].room;
                existingRows.splice(matchIdx, 1); // Remove to avoid re-matching the same row for a 2nd visit
            }
            
            addStayRow(g.place, cat, hotels, g.nights, room); 
        });
        triggerLiveCalculation();
    }

    $(document).on('change', '.tcc-day-stay-place', syncDayWiseToRouting);

    // Media uploader logic
    $(document).on('click', '.tcc-upload-img-btn', function(e) {
        e.preventDefault();
        let btn = $(this);
        let wrapper = btn.closest('div');
        let hiddenInput = wrapper.find('.tcc-day-image');
        let imgPreview = wrapper.find('.tcc-day-img-preview');
        
        let mediaUploader = wp.media({
            title: 'Select Day Image',
            button: { text: 'Use this image' },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            let attachment = mediaUploader.state().get('selection').first().toJSON();
            hiddenInput.val(attachment.url);
            imgPreview.attr('src', attachment.url).show();
            btn.text('Change Image');
            wrapper.find('.tcc-remove-img-btn').show(); // Show remove button
            triggerLiveCalculation();
        });
        
        mediaUploader.open();
    });

    // --- REMOVE IMAGE LOGIC ---
    $(document).on('click', '.tcc-remove-img-btn', function() {
        let wrapper = $(this).closest('div');
        wrapper.find('.tcc-day-image').val('');
        wrapper.find('.tcc-day-img-preview').attr('src', '').hide();
        wrapper.find('.tcc-upload-img-btn').text('🖼️ Add Image');
        $(this).hide();
        triggerLiveCalculation();
    });

    $(document).on('click', '.tcc-insert-day-btn', function() {
        let $row = $(this).closest('.tcc-day-row');
        let index = $row.index();
        
        let currentValues = [];
        let currentStays = [];
        let currentRoutes = [];
        let currentDescs = [];
        let currentImages = [];
        
        $('#day-wise-wrapper .tcc-day-row').each(function() { 
            currentValues.push($(this).find('.tcc-day-input').val()); 
            let sp = $(this).find('.tcc-day-stay-place').val();
            currentStays.push(sp !== undefined ? sp : '');
            
            let rt = $(this).find('.tcc-day-route-dropdown').val();
            currentRoutes.push(rt !== undefined ? rt : '');

            let $textarea = $(this).find('.tcc-day-desc');
            let desc = $textarea.is(':visible') ? $textarea.val() : ($(this).find('.ql-editor').html() || $textarea.val());
            currentDescs.push(desc);

            currentImages.push($(this).find('.tcc-day-image').val());
        });

        // Splice a blank day completely safely into arrays directly after this index
        currentValues.splice(index + 1, 0, '');
        currentStays.splice(index + 1, 0, '');
        currentRoutes.splice(index + 1, 0, '');
        currentDescs.splice(index + 1, 0, '');
        currentImages.splice(index + 1, 0, '');

        window.tccTempDayData = { val: currentValues, stay: currentStays, route: currentRoutes, desc: currentDescs, img: currentImages };

        let days = parseInt($('#total_days').val()) || 0;
        $('#total_days').val(days + 1).trigger('input'); // triggers generateDayInputs
    });

    // Handle day preset dropdown selection
    $(document).on('change', '.tcc-day-preset-dropdown', function() {
        let presetName = $(this).val();
        if(!presetName || !window.tccGlobalDayPresets[presetName]) return;
        let p = window.tccGlobalDayPresets[presetName];
        let $row = $(this).closest('.tcc-day-row');
        
        $row.find('.tcc-day-input').val(p.title || '');
        
        let $textarea = $row.find('.tcc-day-desc');
        $textarea.val(p.desc || '');
        
        let edNode = $row.find('.tcc-quill-editor')[0];
        if (edNode && typeof Quill !== 'undefined') {
            let quillInstance = Quill.find(edNode);
            if (quillInstance && !$textarea.is(':visible')) {
                quillInstance.clipboard.dangerouslyPasteHTML(p.desc || '');
            } else if (!quillInstance) {
                if($row.find('.ql-editor').length) $row.find('.ql-editor').html(p.desc || '');
                else $(edNode).html(p.desc || '');
            }
        }
        
        if(p.stay) $row.find('.tcc-day-stay-place').val(p.stay);
        
        if(p.image) {
            $row.find('.tcc-day-image').val(p.image);
            $row.find('.tcc-day-img-preview').attr('src', p.image).show();
            $row.find('.tcc-upload-img-btn').text('Change Image');
            $row.find('.tcc-remove-img-btn').show(); // Show remove button
        } else {
            $row.find('.tcc-day-image').val('');
            $row.find('.tcc-day-img-preview').hide();
            $row.find('.tcc-upload-img-btn').text('🖼️ Add Image');
            $row.find('.tcc-remove-img-btn').hide(); // Hide remove button
        }
        triggerLiveCalculation();
        syncDayWiseToRouting();
    });

    // Delete Single Day Preset
    $(document).on('click', '.tcc-delete-day-preset', function() {
        let $row = $(this).closest('.tcc-day-row');
        let presetName = $row.find('.tcc-day-preset-dropdown').val();
        let dest = $('#calc_destination').val();

        if(!presetName) {
            tccShowToast("Please select a Day Preset from the dropdown to delete.", true);
            return;
        }
        if(!dest) return;

        if(!confirm(`Are you sure you want to permanently delete the Day Preset: "${presetName}"?`)) return;

        let btn = $(this);
        let oldText = btn.text();
        btn.text('⏳');

        $.post(tcc_ajax_obj.ajax_url, {
            action: 'tcc_delete_single_day_preset',
            destination: dest,
            preset_name: presetName
        }, function(res) {
            btn.text(oldText);
            if(res.success) {
                $('#preset_msg').text("Day Preset Deleted!").css('color', '#dc2626').show().delay(2000).fadeOut();
                $row.find('.tcc-day-preset-dropdown').val('');
                loadSingleDayPresets();
            } else {
                tccShowToast(res.data || "Error deleting preset.", true);
            }
        });
    });

    // Save/Update individual day preset
    $(document).on('click', '.tcc-save-day-preset', function() {
        let $row = $(this).closest('.tcc-day-row');
        let dest = $('#calc_destination').val();
        let title = $row.find('.tcc-day-input').val().trim();
        let selectedPreset = $row.find('.tcc-day-preset-dropdown').val();
        
        let $textarea = $row.find('.tcc-day-desc');
        let qlHtml = $textarea.is(':visible') ? $textarea.val() : ($row.find('.ql-editor').html() || $textarea.val());
        $textarea.val(qlHtml);
        let desc = $textarea.val();
        
        let stay = $row.find('.tcc-day-stay-place').val();
        let img = $row.find('.tcc-day-image').val();

        if(!dest) { tccShowToast("Select destination first.", true); return; }
        if(!title) { tccShowToast("Enter a title for the day first to save it.", true); return; }

        let defaultName = selectedPreset ? selectedPreset : title;
        let presetName = prompt("Enter a name for this Individual Day Preset (use an existing name to Update):", defaultName);
        if(!presetName) return;

        let btn = $(this);
        let oldText = btn.text();
        btn.text('⏳');

        $.post(tcc_ajax_obj.ajax_url, {
            action: 'tcc_save_single_day_preset',
            destination: dest,
            preset_name: presetName,
            title: title,
            desc: desc,
            stay: stay,
            image: img
        }, function(res) {
            btn.text(oldText);
            if(res.success) {
                $('#preset_msg').text("Day Preset Saved!").css('color', '#16a34a').show().delay(2000).fadeOut();
                loadSingleDayPresets();
                setTimeout(function() {
                    $row.find('.tcc-day-preset-dropdown').val(presetName);
                }, 500);
            } else {
                tccShowToast("Error saving preset.", true);
            }
        });
    });

    function generateDayInputs() {
        let days = parseInt($('#total_days').val()) || 0;
        let wrapper = $('#day-wise-wrapper');
        
        let currentValues = [];
        let currentStays = [];
        let currentRoutes = [];
        let currentDescs = [];
        let currentImages = [];

        if (window.tccTempDayData) {
            currentValues = window.tccTempDayData.val;
            currentStays = window.tccTempDayData.stay;
            currentRoutes = window.tccTempDayData.route || [];
            currentDescs = window.tccTempDayData.desc;
            currentImages = window.tccTempDayData.img;
            window.tccTempDayData = null; // Reset it immediately
        } else {
            wrapper.find('.tcc-day-row').each(function() { 
                currentValues.push($(this).find('.tcc-day-input').val()); 
                let sp = $(this).find('.tcc-day-stay-place').val();
                currentStays.push(sp !== undefined ? sp : '');
                
                let rt = $(this).find('.tcc-day-route-dropdown').val();
                currentRoutes.push(rt !== undefined ? rt : '');
                
                let $textarea = $(this).find('.tcc-day-desc');
                let desc = $textarea.is(':visible') ? $textarea.val() : ($(this).find('.ql-editor').html() || $textarea.val());
                currentDescs.push(desc);
                
                currentImages.push($(this).find('.tcc-day-image').val());
            });
        }
        
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

        let routeOptions = '<option value="">-- Route --</option>';
        if(typeof tccMasterData !== 'undefined' && tccMasterData[dest] && tccMasterData[dest].daily_routes) {
            let rts = tccMasterData[dest].daily_routes;
            if(typeof rts === 'string') rts = rts.split(',').map(s=>s.trim());
            if(Array.isArray(rts)) {
                $.each(rts, function(i, val) { routeOptions += `<option value="${val}">${val}</option>`; });
            }
        }

        let dayPresetOptions = '<option value="">Load Day Preset</option>';
        if(window.tccGlobalDayPresets) {
            $.each(window.tccGlobalDayPresets, function(name, d) { dayPresetOptions += `<option value="${name}">${name}</option>`; });
        }

        for(let i = 1; i <= days; i++) {
            let rawVal = currentValues[i-1] ? currentValues[i-1] : '';
            let val = rawVal.replace(/"/g, '&quot;');
            let spVal = currentStays[i-1] ? currentStays[i-1] : '';
            let rtVal = currentRoutes[i-1] ? currentRoutes[i-1] : '';
            let descVal = currentDescs[i-1] ? currentDescs[i-1] : '';
            let imgVal = currentImages[i-1] ? currentImages[i-1] : '';

            let stayDropdownHtml = '';
            let routeDropdownHtml = '';

            if (i < days) {
                stayDropdownHtml = `<select name="itinerary_stay_place[]" class="tcc-day-stay-place" style="flex:1; border-radius:3px; margin:0; font-size:11px; padding:4px;">${stayOptions}</select>`;
            } else {
                stayDropdownHtml = `<input type="hidden" name="itinerary_stay_place[]" value=""><div style="flex:1; background:#f1f5f9; border:1px solid #ccc; border-radius:3px; display:flex; align-items:center; justify-content:center; font-size:10px; color:#94a3b8; margin:0; padding:4px;">Trip Ends</div>`;
            }

            routeDropdownHtml = `<select name="itinerary_route[]" class="tcc-day-route-dropdown" style="flex:1; border-radius:3px; margin:0; font-size:11px; padding:4px;">${routeOptions}</select>`;

            let row = `
            <div class="tcc-day-row" style="margin-bottom:12px; background:#fff; border-radius:6px; border:1px solid #cbd5e1; padding:12px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
                    <div class="drag-handle" style="cursor:grab; padding:6px 8px; background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; border-radius:4px; display:flex; align-items:center; height:32px;" title="Drag to Reorder">&#9776;</div>
                    <div class="tcc-day-label" style="background:#b93b59; color:#fff; padding:0 10px; font-size:12px; font-weight:bold; border-radius:4px; text-align:center; height:32px; line-height:32px;">Day ${i}</div>
                    <input type="text" name="itinerary_day[]" class="tcc-day-input" value="${val}" placeholder="Day Title (e.g. Arrival at Srinagar)" style="margin:0; flex:1; font-size:14px; font-weight:600; padding:0 10px; height:32px; border-radius:4px;" required>
                    <button type="button" class="tcc-remove-day tcc-btn-del" style="height:32px; width:32px; padding:0; margin:0; font-size:14px; display:flex; align-items:center; justify-content:center; border-radius:4px;" title="Remove Day">✖</button>
                </div>

                <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px; flex-wrap:wrap; background:#f8fafc; padding:8px; border-radius:4px; border:1px dashed #cbd5e1;">
                    <div style="flex:1; min-width:150px; display:flex; align-items:center; gap:6px;">
                        <span style="font-size:11px; font-weight:bold; color:#475569; white-space:nowrap;">Night Stay:</span>
                        ${stayDropdownHtml}
                    </div>
                    <div style="flex:1; min-width:150px; display:flex; align-items:center; gap:6px;">
                        <span style="font-size:11px; font-weight:bold; color:#475569; white-space:nowrap;">Route:</span>
                        ${routeDropdownHtml}
                    </div>
                    <div style="flex:2; display:flex; gap:6px; align-items:center; flex-wrap:wrap; justify-content:flex-end;">
                        <select class="tcc-day-preset-dropdown" style="width:140px; font-size:11px; padding:0 6px; margin:0; height:28px; border-radius:3px;">${dayPresetOptions}</select>
                        <button type="button" class="tcc-save-day-preset tcc-btn-secondary" style="padding:0 10px; margin:0; height:28px; font-size:11px;" title="Save/Update Day Preset">💾 Save</button>
                        <button type="button" class="tcc-delete-day-preset tcc-btn-del" style="padding:0 10px; margin:0; height:28px; font-size:11px; background:#fee2e2; border-color:#fca5a5;" title="Delete Day Preset">🗑️ Del</button>
                        <button type="button" class="tcc-insert-day-btn tcc-btn-secondary" style="padding:0 10px; margin:0; height:28px; background:#10b981; border-color:#059669; color:#fff; font-size:11px; margin-left:auto;" title="Insert Blank Day Below">➕ Insert Day</button>
                    </div>
                </div>

                <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                    
                    <div class="tcc-quill-wrapper" style="flex:1; min-width:250px; display:flex; flex-direction:column; border:1px solid #cbd5e1; border-radius:4px; background:#fff; overflow:hidden;">
                        <div class="tcc-quill-editor" style="min-height:100px; font-size:13px;">${descVal}</div>
                        <textarea name="itinerary_desc[]" class="tcc-day-desc" style="display:none;">${descVal}</textarea>
                    </div>
                    
                    <div style="width:180px; display:flex; flex-direction:column; gap:8px; background:#f8fafc; padding:8px; border:1px dashed #cbd5e1; border-radius:4px; flex-shrink:0;">
                        <input type="hidden" name="itinerary_image[]" class="tcc-day-image" value="${imgVal}">
                        <img src="${imgVal}" class="tcc-day-img-preview" style="width:100%; height:100px; object-fit:cover; display:${imgVal ? 'block' : 'none'}; border-radius:3px; border:1px solid #e2e8f0; background:#fff;">
                        <button type="button" class="tcc-btn-secondary tcc-upload-img-btn" style="font-size:11px; padding:6px; margin:0; width:100%; font-weight:600;">${imgVal ? 'Change Image' : '🖼️ Add Image'}</button>
                        <button type="button" class="tcc-btn-del tcc-remove-img-btn" style="font-size:11px; padding:6px; margin:0; width:100%; font-weight:600; display:${imgVal ? 'block' : 'none'};">🗑️ Remove Image</button>
                    </div>
                </div>
            </div>`;
            
            let $newRow = $(row);
            if(i < days && spVal) $newRow.find('.tcc-day-stay-place').val(spVal);
            if(rtVal) $newRow.find('.tcc-day-route-dropdown').val(rtVal);
            wrapper.append($newRow);
        }
        if (typeof Sortable === 'undefined') { $.getScript('https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js', initSortable); } else { initSortable(); }
        initQuillEditors();
    }

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
        updateQuoteTerms();
        loadPresets(); 
        loadAddonPresets();
        loadSingleDayPresets();
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
            let routesArr = [];
            let descsArr = [];
            let imagesArr = [];

            if (Array.isArray(presetData)) {
                daysArr = presetData;
            } else if (presetData && presetData.itinerary !== undefined) {
                if(Array.isArray(presetData.itinerary)) {
                    daysArr = presetData.itinerary;
                } else if(typeof presetData.itinerary === 'object' && presetData.itinerary !== null) {
                    daysArr = Object.values(presetData.itinerary);
                }

                if(presetData.stay_places) {
                    staysArr = Array.isArray(presetData.stay_places) ? presetData.stay_places : Object.values(presetData.stay_places);
                }
                
                if(presetData.itinerary_routes) {
                    routesArr = Array.isArray(presetData.itinerary_routes) ? presetData.itinerary_routes : Object.values(presetData.itinerary_routes);
                }

                if(presetData.itinerary_desc) {
                    descsArr = Array.isArray(presetData.itinerary_desc) ? presetData.itinerary_desc : Object.values(presetData.itinerary_desc);
                }
                if(presetData.itinerary_image) {
                    imagesArr = Array.isArray(presetData.itinerary_image) ? presetData.itinerary_image : Object.values(presetData.itinerary_image);
                }
            } else if (presetData && typeof presetData === 'object') {
                daysArr = Object.values(presetData);
            }

            $('#total_days').val(daysArr.length).trigger('input'); 
            
            if(presetData.short_itinerary) {
                $('#calc_short_itinerary').val(presetData.short_itinerary);
            } else {
                $('#calc_short_itinerary').val('');
            }
            
            if(presetData.pickup) {
                $('#calc_pickup').val(presetData.pickup).trigger('change');
                if(presetData.pickup_custom) $('#calc_pickup_custom').val(presetData.pickup_custom);
            }
            if(presetData.drop) {
                $('#calc_drop').val(presetData.drop).trigger('change');
                if(presetData.drop_custom) $('#calc_drop_custom').val(presetData.drop_custom);
            }

            // --- LOAD NEW FIELDS ---
            // 1. Overall Hotel Category
            if(presetData.hotel_category) {
                $('#calc_hotel_cat').val(presetData.hotel_category).trigger('change');
            }

            // 2. Transport Repeater
            $('#transport-wrapper').empty();
            if (presetData.transports && presetData.transports.length > 0) {
                presetData.transports.forEach((veh, i) => {
                    let qty = (presetData.trans_qtys && presetData.trans_qtys[i]) ? presetData.trans_qtys[i] : 1;
                    let days = (presetData.trans_days && presetData.trans_days[i]) ? presetData.trans_days[i] : $('#total_days').val();
                    let pickup = (presetData.trans_pickups && presetData.trans_pickups[i]) ? presetData.trans_pickups[i] : '';
                    let rate = (presetData.trans_rates && presetData.trans_rates[i]) ? presetData.trans_rates[i] : '';
                    let total = (presetData.trans_totals && presetData.trans_totals[i]) ? presetData.trans_totals[i] : '';
                    
                    addTransportRow(veh, qty, days, pickup, rate, total);
                });
            } else {
                addTransportRow(); // Ensures at least one empty row exists
            }

            // 3. Add-ons Repeater
            $('#addons-wrapper').empty();
            if (presetData.addon_names && presetData.addon_names.length > 0) {
                presetData.addon_names.forEach((name, i) => {
                    let price = (presetData.addon_prices && presetData.addon_prices[i]) ? presetData.addon_prices[i] : '';
                    let type = (presetData.addon_types && presetData.addon_types[i]) ? presetData.addon_types[i] : 'flat';
                    
                    addAddonRow(name, price, type);
                });
            }
            // ------------------------

           setTimeout(function() {
                $('#day-wise-wrapper .tcc-day-row').each(function(index) { 
                    if(daysArr[index]) $(this).find('.tcc-day-input').val(daysArr[index]); 
                    
                    if(staysArr && staysArr[index]) $(this).find('.tcc-day-stay-place').val(staysArr[index]);
                    else $(this).find('.tcc-day-stay-place').val('');

                    if(routesArr && routesArr[index]) $(this).find('.tcc-day-route-dropdown').val(routesArr[index]);
                    else $(this).find('.tcc-day-route-dropdown').val('');

                    if(descsArr && descsArr[index]) {
                        let $textarea = $(this).find('.tcc-day-desc');
                        $textarea.val(descsArr[index]);
                        
                        let edNode = $(this).find('.tcc-quill-editor')[0];
                        if (edNode && typeof Quill !== 'undefined') {
                            let quillInstance = Quill.find(edNode);
                            if (quillInstance && !$textarea.is(':visible')) {
                                quillInstance.clipboard.dangerouslyPasteHTML(descsArr[index]);
                            } else if (!quillInstance) {
                                if($(this).find('.ql-editor').length) $(this).find('.ql-editor').html(descsArr[index]);
                                else $(edNode).html(descsArr[index]);
                            }
                        }
                    } else {
                        let $textarea = $(this).find('.tcc-day-desc');
                        $textarea.val('');
                        
                        let edNode = $(this).find('.tcc-quill-editor')[0];
                        if (edNode && typeof Quill !== 'undefined') {
                            let quillInstance = Quill.find(edNode);
                            if (quillInstance && !$textarea.is(':visible')) {
                                quillInstance.clipboard.dangerouslyPasteHTML('');
                            } else if (!quillInstance) {
                                if($(this).find('.ql-editor').length) $(this).find('.ql-editor').html('');
                                else $(edNode).html('');
                            }
                        }
                    }

                    if(imagesArr && imagesArr[index]) {
                        $(this).find('.tcc-day-image').val(imagesArr[index]);
                        $(this).find('.tcc-day-img-preview').attr('src', imagesArr[index]).show();
                        $(this).find('.tcc-upload-img-btn').text('Change Image');
                        $(this).find('.tcc-remove-img-btn').show(); 
                    } else {
                        $(this).find('.tcc-day-image').val('');
                        $(this).find('.tcc-day-img-preview').hide();
                        $(this).find('.tcc-upload-img-btn').text('🖼️ Add Image');
                        $(this).find('.tcc-remove-img-btn').hide(); 
                    }
                });
                
                // --- ADD THIS BLOCK TO EXPLICITLY LOAD SAVED HOTELS ---
                if (presetData.routing_stay_places && presetData.routing_stay_places.length > 0) {
                    $('#night-stay-wrapper').empty();
                    presetData.routing_stay_places.forEach((place, i) => {
                        let cat = (presetData.routing_stay_categories && presetData.routing_stay_categories[i]) ? presetData.routing_stay_categories[i] : $('#calc_hotel_cat').val();
                        let hotels = (presetData.routing_stay_hotels && presetData.routing_stay_hotels[i]) ? presetData.routing_stay_hotels[i] : '';
                        let nights = (presetData.routing_stay_nights && presetData.routing_stay_nights[i]) ? presetData.routing_stay_nights[i] : 1;
                        let room = (presetData.routing_stay_room_types && presetData.routing_stay_room_types[i]) ? presetData.routing_stay_room_types[i] : 'Default';
                        addStayRow(place, cat, hotels, nights, room);
                    });
                }
                
                syncDayWiseToRouting();
            }, 100);
         }
    });

    $('#new_preset_name').on('input', function() {
        if($(this).val() !== $('#itinerary_preset_select').val()) $('#save_itinerary_preset').text('Save as Preset');
        else $('#save_itinerary_preset').text('Update Preset');
    });

    $('#save_itinerary_preset').on('click', function() {
        let presetName = $('#new_preset_name').val();
        let btn = $(this);
        if(!presetName) { tccShowToast("Please enter a Preset Name", true); return; }
        
        // Sync before serializing
        $('.tcc-quill-wrapper').each(function() {
            let qlEditor = $(this).find('.ql-editor');
            let $textarea = $(this).find('textarea');
            if(!$textarea.is(':visible') && qlEditor.length) {
                $textarea.val(qlEditor.html());
            }
        });

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
            preset_name: presetName,
            destination: dest
        }, function(res) {
            if(res.success) {
                $('#preset_msg').text('Deleted successfully!').css('color', '#dc2626').fadeIn().delay(2000).fadeOut();
                loadPresets();
                $('#new_preset_name').val('');
            } else {
                tccShowToast("Error deleting preset.", true);
            }
        });
    });

    // --- BOOKING & PAYMENTS MANAGER LOGIC ---
    window.tccAllQuotes = [];

    function renderQuotesDropdown(filterText = '') {
        let term = filterText.toLowerCase();
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

    function loadPaymentQuotes() {
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_quotes_list' }, function(res) {
            if(res.success) {
                window.tccAllQuotes = res.data;
                renderQuotesDropdown($('#pmt_quote_search').length ? $('#pmt_quote_search').val() : '');
            }
        });
    }

    $('#pmt_quote_search').on('input', function() {
        renderQuotesDropdown($(this).val());
        $('#pmt_dashboard, #pmt_quote_actions, #pmt_edit_client_wrapper').hide();
    });

    $('#pmt_quote_select').on('change', function() {
        let qid = $(this).val();
        $('#pmt_edit_client_wrapper').hide();
        $('#pmt_cancel_edit_btn').trigger('click');

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

    // EMAIL PROMPT LOGIC
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
                    $(document).trigger('tcc_finances_updated', ['tcc-script']); // ADDED
                    quoteData.c_email = newEmail.trim(); 
                    sendEmailAJAX(qid, $btn, originalText); 
                } else {
                    $btn.text(originalText).prop('disabled', false);
                    tccShowToast("Failed to save the new email address.", true);
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
                tccShowToast(res.data, false);
            } else {
                tccShowToast("Error: " + res.data, true);
            }
        }).fail(function() {
            $btn.text(originalText).prop('disabled', false);
            tccShowToast("Server error occurred while sending email.", true);
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
                $(document).trigger('tcc_finances_updated', ['tcc-script']); // ADDED
                $('#pmt_edit_client_wrapper').slideUp();
                $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_quotes_list' }, function(res2) {
                    if(res2.success) {
                        window.tccAllQuotes = res2.data;
                        renderQuotesDropdown($('#pmt_quote_search').length ? $('#pmt_quote_search').val() : '');
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
                $(document).trigger('tcc_finances_updated', ['tcc-script']); // ADDED
                $('#pmt_dashboard, #pmt_quote_actions, #pmt_edit_client_wrapper').hide();
                loadPaymentQuotes();
            }
        });
    });

    // DUPLICATE QUOTE LOGIC
    $('#pmt_duplicate_quote_btn').on('click', function() {
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;
        if(!confirm("Are you sure you want to duplicate this quote? It will create a fresh copy with a new Link.")) return;

        let btn = $(this);
        let oldText = btn.text();
        btn.text('Duplicating...').prop('disabled', true);

        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_duplicate_quote', quote_id: qid }, function(res) {
            btn.text(oldText).prop('disabled', false);
            if(res.success) {
                $(document).trigger('tcc_finances_updated', ['tcc-script']); // ADDED
                tccShowToast(res.data.message, false);
                
                // Refresh the quote list and automatically select the new duplicate!
                $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_quotes_list' }, function(res2) {
                    if(res2.success) {
                        window.tccAllQuotes = res2.data;
                        renderQuotesDropdown($('#pmt_quote_search').length ? $('#pmt_quote_search').val() : '');
                        $('#pmt_quote_select').val(res.data.new_id).trigger('change');
                    }
                });
            } else {
                tccShowToast("Error: " + res.data, true);
            }
        }).fail(function() {
            btn.text(oldText).prop('disabled', false);
            tccShowToast("Server error. Could not duplicate.", true);
        });
    });

    // LOAD SAVED QUOTE TO CALCULATOR LOGIC
    $('#pmt_load_edit_btn').on('click', function() {
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;

        if(!confirm("This will clear your current calculator form and load the selected quote. Continue?")) return;

        let btn = $(this);
        let oldText = btn.text();
        btn.text('Loading...').prop('disabled', true);

        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_get_full_quote_data', quote_id: qid }, function(res) {
            btn.text(oldText).prop('disabled', false);
            if(res.success) {
                let d = res.data.summary;
                let r = res.data.raw;

                // 1. Set the internal Edit ID so saving UPDATES this quote
                $('#edit_quote_id').val(qid);

                // 2. Client & Basics
                $('#client_name').val(d.client_name || '');
                $('#client_phone').val(d.client_phone || '');
                $('#client_email').val(d.client_email || '');
                $('#calc_destination').val(d.destination).trigger('change');

                // Wait 800ms for Destination dropdowns (Hotels/Vehicles) to load via AJAX
                setTimeout(() => {
                    $('#start_date').val(d.start_date || '');
                    $('#total_pax').val(d.pax || 1);
                    $('#child_6_12_pax').val(d.child_6_12 || 0);
                    $('#child_pax').val(d.child || 0);
                    $('#total_days').val(d.days || 1);
                    $('#no_of_rooms').val(d.rooms || 1);
                    $('#extra_beds').val(d.extra_beds || 0);
                    $('#calc_hotel_cat').val(d.hotel_cat || '');
                    $('#calc_short_itinerary').val(d.short_itinerary || '');

                    // Extract raw pickup/drop locations from strings like "Airport (Srinagar)"
                    let p_base = (d.pickup && d.pickup.includes('(')) ? d.pickup.split('(')[1].replace(')','') : (d.pickup || '');
                    let d_base = (d.drop && d.drop.includes('(')) ? d.drop.split('(')[1].replace(')','') : (d.drop || '');
                    $('#calc_pickup').val(p_base.trim());
                    $('#calc_drop').val(d_base.trim());
                    $('#calc_pickup_custom').val(r.pickup_custom || '');
                    $('#calc_drop_custom').val(r.drop_custom || '');

                    // 3. Clear Repeaters safely
                    $('#day-wise-wrapper, #night-stay-wrapper, #transport-wrapper, #addons-wrapper').empty();

                    // 4. Populate Itinerary Days
                    if (d.itinerary) {
                        window.tccTempDayData = {
                            val: d.itinerary,
                            stay: d.itinerary_stay_places || r.itinerary_stay_place || [],
                            route: r.itinerary_route || [],
                            desc: d.itinerary_desc || [],
                            img: d.itinerary_image || []
                        };
                        $('#total_days').trigger('input'); // Generates the day rows dynamically
                    }

                    // 5. Populate Stays
                    if (r.stay_places && r.stay_places.length > 0) {
                        r.stay_places.forEach((place, i) => {
                            let rt = (r.stay_room_type && r.stay_room_type[i]) ? r.stay_room_type[i] : 'Default';
                            addStayRow(place, r.stay_categories[i], r.stay_hotels[i], r.stay_nights[i], rt);
                        });
                    } else {
                        addStayRow();
                    }

                    // 6. Populate Transports
                    if (r.transports && r.transports.length > 0) {
                        r.transports.forEach((veh, i) => {
                            addTransportRow(veh, r.trans_qtys[i], r.trans_days[i], r.trans_pickups[i], r.trans_custom_rates[i], r.trans_custom_totals[i]);
                        });
                    } else {
                        addTransportRow();
                    }

                    // 7. Populate Add-ons
                    if (r.addon_names && r.addon_names.length > 0) {
                        r.addon_names.forEach((name, i) => {
                            addAddonRow(name, r.addon_prices[i], r.addon_types[i]);
                        });
                    }

                    // 8. Populate Terms (RTEs)
                    function setQValLoad(id, val) {
                        $('#' + id).val(val);
                        let edNode = document.getElementById(id + '_editor');
                        if(edNode) {
                            let $wrapper = $(edNode).closest('.tcc-quill-wrapper');
                            let $textarea = $wrapper.find('textarea');
                            $textarea.val(val);
                            
                            if (typeof Quill !== 'undefined') {
                                let quillInstance = Quill.find(edNode);
                                if (quillInstance && !$textarea.is(':visible')) {
                                    quillInstance.clipboard.dangerouslyPasteHTML(val);
                                    return;
                                }
                            }
                            let $ed = $(edNode);
                            if($ed.find('.ql-editor').length) $ed.find('.ql-editor').html(val);
                            else $ed.html(val);
                        }
                    }
                    setQValLoad('quote_inclusions', r.quote_inclusions || '');
                    setQValLoad('quote_exclusions', r.quote_exclusions || '');
                    setQValLoad('quote_payment_terms', r.quote_payment_terms || '');
                    setQValLoad('quote_important_note', r.quote_important_note || '');
                    setQValLoad('quote_why_choose_us', r.quote_why_choose_us || '');
                    setQValLoad('quote_essential_guidelines', r.quote_essential_guidelines || '');
                    
                    $('#quote_head_office').val(r.quote_head_office || '');

                    // 9. Populate Final Adjustments
                    $('#share_cab_cost').prop('checked', r.share_cab_cost == 1); // Check share state
                    $('#calc_override_profit').val(r.override_profit || '');
                    $('#calc_manual_pp_override').val(r.manual_pp_override || '');
                    $('select[name="discount_1_type"]').val(r.d1_type || 'none');
                    $('input[name="discount_1_value"]').val(r.d1_val || 0);
                    $('select[name="discount_2_type"]').val(r.d2_type || 'none');
                    $('input[name="discount_2_value"]').val(r.d2_val || 0);

                    // Change button look so you know you are updating!
                    $('#tcc_calculate_btn').text('Update Quote Data').css('background-color', '#d97706');

                    // Calculate live pricing and scroll smoothly to Step 1
                    triggerLiveCalculation();
                    $('html, body').animate({ scrollTop: $('#tcc-step-1').offset().top - 30 }, 500);

                }, 800); // Wait allows master dropdowns to load
            } else {
                tccShowToast("Error loading quote data.", true);
            }
        });
    });

    $('#pmt_method').on('change', function() {
        let btn = $('#tcc-add-payment-form button[type="submit"]');
        if($(this).val() === 'Refund') {
            btn.text('Process Refund').css({'background': '#dc2626', 'border-color': '#b91c1c'});
        } else {
            let btnText = $('#pmt_edit_id').val() ? 'Update Record' : 'Add Record';
            btn.text(btnText).removeAttr('style');
            btn.css({'margin': '0', 'flex': '1', 'min-width': '100px'});
        }
    });

    // SAVE POST QUOTE DISCOUNT
    $('#pmt_save_discount_btn').on('click', function() {
        let qid = $('#pmt_quote_select').val();
        let discount = $('#pmt_post_discount').val();
        if(!qid) return;

        let btn = $(this);
        let origText = btn.text();
        btn.text('...').prop('disabled', true);

        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_save_post_discount', quote_id: qid, discount: discount }, function(res) {
            btn.text(origText).prop('disabled', false);
            if(res.success) {
                $(document).trigger('tcc_finances_updated', ['tcc-script']); // ADDED
                refreshPaymentDashboard();
            } else {
                tccShowToast("Failed to save discount.", true);
            }
        });
    });

    function refreshPaymentDashboard() {
        let qid = $('#pmt_quote_select').val();
        if(!qid) return;
        
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_load_quote_payments', quote_id: qid }, function(res) {
            if(res.success) {
                // Populate the discount input box
                $('#pmt_post_discount').val(res.data.post_discount || 0);

                // NEW: Populate Expected Net Profit Box (Profit - Post Discount)
                let expectedProfit = (res.data.net_profit || 0) - (res.data.post_discount || 0);
                $('#pmt_profit_val').text(formatINR(expectedProfit)).css('color', expectedProfit < 0 ? '#dc2626' : '#0ea5e9');

                if(res.data.is_cancelled) {
                    let totalHtml = `<span style="text-decoration:line-through; opacity:0.5;">${formatINR(res.data.grand_total)}</span>`;
                    if(res.data.post_discount > 0) {
                        totalHtml += `<br><span style="font-size:11px; color:#dc2626; text-decoration:line-through; opacity:0.5;">Discount: -${formatINR(res.data.post_discount)}</span>`;
                    }
                    totalHtml += `<br><span style="font-size:10px; color:#dc2626; font-weight:bold;">CANCELLED</span>`;
                    $('#pmt_total_val').html(totalHtml);
                    
                    $('#pmt_received_val').parent().find('div:first').text('TOTAL REFUNDED');
                    $('#pmt_received_val').text(formatINR(res.data.total_refunded)).css('color', '#dc2626');
                    $('#pmt_balance_val').parent().find('div:first').text('CANCELLATION INCOME');
                    $('#pmt_balance_val').text(formatINR(res.data.retained_income)).css('color', '#16a34a');
                } else {
                    let totalHtml = formatINR(res.data.grand_total);
                    if(res.data.post_discount > 0) {
                        totalHtml += `<br><span style="font-size:11px; color:#dc2626; font-weight:normal;">Discount: -${formatINR(res.data.post_discount)}</span>`;
                    }
                    $('#pmt_total_val').html(totalHtml);

                    $('#pmt_received_val').parent().find('div:first').text('AGENCY RECEIVED');
                    $('#pmt_received_val').html(`${formatINR(res.data.total_paid)}<br><span style="font-size:11px; color:#64748b; font-weight:normal;">Net in Bank: ${formatINR(res.data.net_in_bank)}</span>`).css('color', '#16a34a');
                    $('#pmt_balance_val').parent().find('div:first').text('BALANCE DUE');
                    
                    let balanceHtml = formatINR(res.data.balance);
                    if (res.data.vendor_paid > 0) {
                        balanceHtml += `<br><span style="font-size:10px; color:#d97706; font-weight:normal; line-height:1.2; display:inline-block; margin-top:4px;">Vendor Direct: ${formatINR(res.data.vendor_paid)}<br>Tax Waiver: -${formatINR(res.data.tax_waiver)}</span>`;
                    }
                    $('#pmt_balance_val').html(balanceHtml).css('color', '#dc2626');
                }

                let phtml = '';
                if(res.data.payments.length > 0) {
                    phtml += `<table style="width:100%; min-width:700px; border-collapse:collapse; font-size:12px; text-align:left;">
                        <tr style="background:#f1f5f9; border-bottom:1px solid #e2e8f0;">
                            <th style="padding:8px;">Date</th><th style="padding:8px;">Gross Amt</th><th style="padding:8px; color:#dc2626;">PG Cut</th><th style="padding:8px; color:#16a34a;">Net Bank</th><th style="padding:8px;">Method</th><th style="padding:8px;">Ref</th><th style="padding:8px; text-align:right;">Action</th>
                        </tr>`;
                    $.each(res.data.payments, function(i, p) {
                        let isRefund = (p.method === 'Refund');
                        let isVendor = (p.method === 'Direct to Vendor');
                        let amtColor = isRefund ? '#dc2626' : (isVendor ? '#d97706' : '#16a34a');
                        let amtPrefix = isRefund ? '-' : '';
                        
                        let pgFee = p.pg_fee ? parseFloat(p.pg_fee) : 0;
                        let netBank = parseFloat(p.amount) - pgFee;
                        
                        let methodCell = p.method;
                        if (isVendor) {
                            methodCell = `Direct to Vendor<br><span style="font-size:10px; color:#16a34a; font-weight:bold;">+ Tax Waiver: ${formatINR(p.waiver_calculated)}</span>`;
                        }

                        phtml += `<tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:8px;">${p.date}</td>
                            <td style="padding:8px; font-weight:bold; color:${amtColor};">${amtPrefix}${formatINR(p.amount)}</td>
                            <td style="padding:8px; color:#dc2626;">${pgFee > 0 ? '-' + formatINR(pgFee) : '-'}</td>
                            <td style="padding:8px; font-weight:bold; color:#16a34a;">${isVendor ? '-' : amtPrefix + formatINR(netBank)}</td>
                            <td style="padding:8px;">${methodCell}</td>
                            <td style="padding:8px; color:#64748b;">${p.ref}</td>
                            <td style="padding:8px; text-align:right;">
                                <button type="button" class="edit-payment-btn" data-id="${p.id}" data-date="${p.date}" data-amt="${p.amount}" data-pg="${pgFee}" data-method="${p.method}" data-ref="${p.ref}" style="background:none; border:none; color:#0284c7; cursor:pointer; text-decoration:underline; margin-right:8px;">Edit</button>
                                <button type="button" class="del-payment-btn" data-id="${p.id}" style="background:none; border:none; color:#dc2626; cursor:pointer; text-decoration:underline;">Delete</button>
                            </td>
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

    // Load payment into Edit form
    $(document).on('click', '.edit-payment-btn', function() {
        let p = $(this).data();
        $('#pmt_edit_id').val(p.id);
        $('#pmt_date').val(p.date);
        $('#pmt_amount').val(p.amt);
        $('#pmt_pg_fee').val(p.pg);
        $('#pmt_method').val(p.method).trigger('change');
        $('#pmt_ref').val(p.ref);
        
        let btn = $('#tcc-add-payment-form button[type="submit"]');
        if(p.method !== 'Refund') {
            btn.text('Update Record');
        }
        $('#pmt_cancel_edit_btn').show();
        $('html, body').animate({ scrollTop: $('#tcc-add-payment-wrapper').offset().top - 50 }, 300);
    });

    $('#pmt_cancel_edit_btn').on('click', function() {
        $('#tcc-add-payment-form')[0].reset();
        $('#pmt_edit_id').val('');
        let btn = $('#tcc-add-payment-form button[type="submit"]');
        btn.text('Add Record').removeAttr('style').css({'margin': '0', 'flex': '1', 'min-width': '100px'});
        $(this).hide();
    });

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
            pmt_id: $('#pmt_edit_id').val(),
            amount: $('#pmt_amount').val(),
            pg_fee: $('#pmt_pg_fee').val(),
            date: $('#pmt_date').val(),
            method: $('#pmt_method').val(),
            ref: $('#pmt_ref').val()
        };

        $.post(tcc_ajax_obj.ajax_url, data, function(res) {
            btn.prop('disabled', false).text(originalText);
            if(res.success) {
                $(document).trigger('tcc_finances_updated', ['tcc-script']); // ADDED
                $('#pmt_cancel_edit_btn').trigger('click'); // Reset everything perfectly
                refreshPaymentDashboard();
            }
        });
    });

    $(document).on('click', '.del-payment-btn', function() {
        if(!confirm('Delete this record?')) return;
        let pmt_id = $(this).data('id');
        let qid = $('#pmt_quote_select').val();
        
        $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_delete_payment', quote_id: qid, pmt_id: pmt_id }, function(res) {
            if(res.success) {
                $(document).trigger('tcc_finances_updated', ['tcc-script']); // ADDED
                $('#pmt_cancel_edit_btn').trigger('click');
                refreshPaymentDashboard();
            }
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
        
        if(!oldVal || oldVal === 'ADD_NEW') { tccShowToast("Please select an item from the dropdown first to rename it.", true); return; }
        
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
                    $(document).trigger('tcc_data_updated', ['tcc-script']); // ADDED
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

    $('#master_profit_type').on('change', function() {
        if ($(this).val() === 'percent') {
            $('#master_profit_flat_wrapper').hide();
            $('#master_profit_tier_wrapper').show();
        } else {
            $('#master_profit_flat_wrapper').show();
            $('#master_profit_tier_wrapper').hide();
        }
    });

    $('#add_profit_tier_btn').on('click', function() {
        let row = `
        <div class="profit-tier-row tcc-repeater-row tcc-fade-in" style="display:flex; gap:10px; margin-bottom:5px;">
            <input type="number" name="tier_min_pax[]" placeholder="Min Pax" min="1" required style="flex:1;">
            <input type="number" name="tier_max_pax[]" placeholder="Max Pax" min="1" required style="flex:1;">
            <input type="number" name="tier_percent[]" placeholder="Profit %" step="0.01" required style="flex:1;">
            <button type="button" class="remove_profit_tier tcc-btn-del">X</button>
        </div>`;
        $('#profit-tiers-wrapper').append(row);
    });
    $(document).on('click', '.remove_profit_tier', function() { $(this).closest('.profit-tier-row').remove(); });

    $('#tcc-global-settings-form').on('submit', function(e) {
        e.preventDefault();
        let btn = $(this).find('button[type="submit"]');
        let originalText = btn.text();
        btn.prop('disabled', true).text('Saving...');

        $.post(tcc_ajax_obj.ajax_url, $(this).serialize() + '&action=tcc_save_global_settings', function(res) {
            btn.prop('disabled', false).text(originalText);
            if(res.success) { 
                $(document).trigger('tcc_data_updated', ['tcc-script']); // ADDED
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
                
                $('#hotel-room-types-wrapper').empty();
                if(res.data.room_types) {
                    try {
                        let rts = JSON.parse(res.data.room_types);
                        if(Array.isArray(rts)) {
                            rts.forEach(rt => {
                                addHotelRoomTypeRow(rt.name, rt.room, rt.eb, rt.child);
                            });
                        }
                    } catch(e) {}
                }
            }
        });
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
            $('#hotel-room-types-wrapper').empty();
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
            $('#hotel-room-types-wrapper').empty();
        }
    });

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
                    $(document).trigger('tcc_data_updated', ['tcc-script']); // ADDED
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
                    $(document).trigger('tcc_data_updated', ['tcc-script']); // ADDED
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
                    $(document).trigger('tcc_data_updated', ['tcc-script']); // ADDED
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
        $('#profit-tiers-wrapper').empty();

        if(val && tccMasterData[val]) {
            $('#master_dest_name').val(val);
            
            let pType = tccMasterData[val].profit_type || 'flat';
            $('#master_profit_type').val(pType).trigger('change');
            $('#master_profit').val(tccMasterData[val].profit_per_person || 0);
            
            if (tccMasterData[val].profit_tiers && tccMasterData[val].profit_tiers.length > 0) {
                $.each(tccMasterData[val].profit_tiers, function(i, tier) {
                    let row = `
                    <div class="profit-tier-row tcc-repeater-row tcc-fade-in" style="display:flex; gap:10px; margin-bottom:5px;">
                        <input type="number" name="tier_min_pax[]" value="${tier.min}" placeholder="Min Pax" min="1" required style="flex:1;">
                        <input type="number" name="tier_max_pax[]" value="${tier.max}" placeholder="Max Pax" min="1" required style="flex:1;">
                        <input type="number" name="tier_percent[]" value="${tier.percent}" placeholder="Profit %" step="0.01" required style="flex:1;">
                        <button type="button" class="remove_profit_tier tcc-btn-del">X</button>
                    </div>`;
                    $('#profit-tiers-wrapper').append(row);
                });
            }

            let p = tccMasterData[val].pickups;
            $('#master_pickups').val(Array.isArray(p) ? p.join(', ') : p);
            
            let si = tccMasterData[val].short_itineraries;
            $('#master_short_itineraries').val(Array.isArray(si) ? si.join(', ') : si);
            
            let dr = tccMasterData[val].daily_routes;
            $('#master_daily_routes').val(Array.isArray(dr) ? dr.join(', ') : dr);
            
            let s = tccMasterData[val].stay_places;
            $('#master_stays').val(Array.isArray(s) ? s.join(', ') : s);
            
            let v = tccMasterData[val].vehicles;
            $('#master_vehicles').val(Array.isArray(v) ? v.join(', ') : v);
            
            let c = tccMasterData[val].hotel_categories;
            $('#master_hotel_cats').val(Array.isArray(c) ? c.join(', ') : c);

            function setQValMaster(id, valStr) {
                $('#' + id).val(valStr);
                let edNode = document.getElementById(id + '_editor');
                if(edNode) {
                    let $wrapper = $(edNode).closest('.tcc-quill-wrapper');
                    let $textarea = $wrapper.find('textarea');
                    $textarea.val(valStr);
                    
                    if (typeof Quill !== 'undefined') {
                        let quillInstance = Quill.find(edNode);
                        if (quillInstance && !$textarea.is(':visible')) {
                            quillInstance.clipboard.dangerouslyPasteHTML(valStr);
                            return;
                        }
                    }
                    let $ed = $(edNode);
                    if($ed.find('.ql-editor').length) $ed.find('.ql-editor').html(valStr);
                    else $ed.html(valStr);
                }
            }

            setQValMaster('master_inclusions', tccMasterData[val].inclusions || '');
            setQValMaster('master_exclusions', tccMasterData[val].exclusions || '');
            setQValMaster('master_payment_terms', tccMasterData[val].payment_terms || '');
            setQValMaster('master_important_note', tccMasterData[val].important_note || '');
            setQValMaster('master_why_choose_us', tccMasterData[val].why_choose_us || '');
            setQValMaster('master_essential_guidelines', tccMasterData[val].essential_guidelines || '');

            let head = tccMasterData[val].head_office || 'Lake City Plaza, Karan Nagar Rd, Karan Nagar, Srinagar, Jammu and Kashmir 190010';
            $('#master_head_office').val(head);

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
            $('#master_profit_type').val('flat').trigger('change');
            
            function clearQValMaster(id) {
                $('#' + id).val('');
                let edNode = document.getElementById(id + '_editor');
                if(edNode) {
                    let $wrapper = $(edNode).closest('.tcc-quill-wrapper');
                    let $textarea = $wrapper.find('textarea');
                    $textarea.val('');
                    
                    if (typeof Quill !== 'undefined') {
                        let quillInstance = Quill.find(edNode);
                        if (quillInstance && !$textarea.is(':visible')) {
                            quillInstance.clipboard.dangerouslyPasteHTML('');
                            return;
                        }
                    }
                    let $ed = $(edNode);
                    if($ed.find('.ql-editor').length) $ed.find('.ql-editor').html('');
                    else $ed.html('');
                }
            }
            clearQValMaster('master_inclusions');
            clearQValMaster('master_exclusions');
            clearQValMaster('master_payment_terms');
            clearQValMaster('master_important_note');
            clearQValMaster('master_why_choose_us');
            clearQValMaster('master_essential_guidelines');
        }
    }

    $('#master_dest_select').on('change', function() { populateMasterForm($(this).val()); });

    setTimeout(() => { 
        rebuildDestinationDropdowns();
        populateMasterForm($('#master_dest_select').val());
        updateSettingsDropdowns();
        updateCalculatorDropdowns();
        updateQuoteTerms(); 
        fetchHotelNamesList(); 
        fetchTransportPricing(); 
        
        loadPresets(); 
        loadAddonPresets();
        loadSingleDayPresets();

        if($('#night-stay-wrapper').children().length === 0) {
            addStayRow();
        }
        
        if($('#transport-wrapper').children().length === 0) {
            addTransportRow();
        }

        loadPaymentQuotes(); 
        initQuillEditors();
    }, 200);

    function showSettingsMessage(msg) { $('#tcc_settings_msg').text(msg).fadeIn().delay(3000).fadeOut(); }

    $('#tcc-master-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        $('.tcc-quill-wrapper').each(function() {
            let qlEditor = $(this).find('.ql-editor');
            let $textarea = $(this).find('textarea');
            if(!$textarea.is(':visible') && qlEditor.length) {
                $textarea.val(qlEditor.html());
            }
        });

        $.post(tcc_ajax_obj.ajax_url, $(this).serialize() + '&action=tcc_save_master_settings', function(res) {
            if(res.success) { 
                $(document).trigger('tcc_data_updated', ['tcc-script']); // ADDED
                showSettingsMessage(res.data.message); 
                tccMasterData = res.data.new_master; 
                rebuildDestinationDropdowns(); 
                updateSettingsDropdowns();
                updateCalculatorDropdowns();
                updateQuoteTerms(); // Added: Syncs to Section 6 immediately!
            }
        });
    });

    $('#tcc-settings-form').on('submit', function(e) {
        e.preventDefault();
        $.post(tcc_ajax_obj.ajax_url, $(this).serialize() + '&action=tcc_save_pricing_settings', function(res) {
            if(res.success) { 
                $(document).trigger('tcc_data_updated', ['tcc-script']); // ADDED
                showSettingsMessage(res.data.message); fetchHotelNamesList(); 
            }
        });
    });

    $('#tcc-transport-settings-form').on('submit', function(e) {
        e.preventDefault();
        $.post(tcc_ajax_obj.ajax_url, $(this).serialize() + '&action=tcc_save_transport_settings', function(res) {
            if(res.success) {
                $(document).trigger('tcc_data_updated', ['tcc-script']); // ADDED
                showSettingsMessage(res.data.message);
            }
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
                        tccShowToast("Error generating file. The data might be too large.", true);
                        console.error(e);
                    }
                } else {
                    tccShowToast("Export failed: " + (res.data || "You might not have permission."), true);
                }
            },
            error: function(xhr, status, error) {
                btn.text(originalText).prop('disabled', false);
                tccShowToast("A server error occurred. Please check your browser console (F12) for details.", true);
                console.error("Export Server Error:", status, error);
                console.error("Response Text:", xhr.responseText);
            }
        });
    });

    $('#tcc_import_btn').on('click', function() {
        let fileInput = $('#tcc_import_file')[0];
        let file = fileInput.files[0];
        
        if(!file) {
            tccShowToast("Please select a .json backup file first.", true);
            return;
        }
        
        if(!confirm("CRITICAL WARNING:\n\nRestoring a backup will instantly wipe all current Master Settings, Quotes, Rates, and Clients, replacing them with the uploaded file.\n\nAre you absolutely sure you want to proceed?")) {
            return;
        }
        
        let btn = $(this);
        let originalText = btn.text();
        btn.text('Restoring Data...').prop('disabled', true);

        let reader = new FileReader();
        reader.onload = function(e) {
            $.ajax({
                url: tcc_ajax_obj.ajax_url + '?action=tcc_import_backup',
                type: 'POST',
                contentType: 'application/json',
                data: e.target.result,
                success: function(res) {
                    if(res.success) {
                        tccShowToast(res.data, false);
                        location.reload(); 
                    } else {
                        btn.text(originalText).prop('disabled', false);
                        tccShowToast("Restore failed: " + (res.data || 'Invalid file format.'), true);
                    }
                },
                error: function() {
                    btn.text(originalText).prop('disabled', false);
                    tccShowToast("A server error occurred during the restore process.", true);
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

    // --- ADD ROOM CATEGORY SETTINGS REPEATER ---
    function addHotelRoomTypeRow(name='', room='', eb='', child='') {
        let row = `
        <div class="hotel-rt-row tcc-repeater-row" style="display:flex; gap:5px; margin-bottom:5px;">
            <input type="text" name="rt_name[]" value="${name}" placeholder="Room Name" style="flex:2;" required>
            <input type="number" name="rt_room[]" value="${room}" placeholder="Room ₹" step="0.01" style="flex:1;" required>
            <input type="number" name="rt_eb[]" value="${eb}" placeholder="Ex Bed ₹" step="0.01" style="flex:1;" required>
            <input type="number" name="rt_child[]" value="${child}" placeholder="Child ₹" step="0.01" style="flex:1;" required>
            <button type="button" class="remove-hotel-rt tcc-btn-del">X</button>
        </div>`;
        $('#hotel-room-types-wrapper').append(row);
    }
    $('#add_hotel_room_type_btn').on('click', function() { addHotelRoomTypeRow(); });
    $(document).on('click', '.remove-hotel-rt', function() { $(this).closest('.hotel-rt-row').remove(); });

// ═══════════════════════════════════════════════════════════════════════════
// PACKAGE PUBLISHING SYSTEM — replace the entire block in tcc-script.js
// ═══════════════════════════════════════════════════════════════════════════

// ── Open publish modal ──────────────────────────────────────────────────────
$('#publish_as_package_btn').on('click', function() {
    let presetName = $('#itinerary_preset_select').val();
    let dest       = $('#calc_destination').val();

    if (!presetName) {
        tccShowToast("Please load or save a preset first, then click Publish.", true);
        return;
    }
    if (!dest) {
        tccShowToast("Please select a destination first.", true);
        return;
    }

    $('#pkg_modal_name').val(presetName);
    $('#pkg_modal_preset_name').val(presetName);
    $('#pkg_modal_destination').val(dest);
    $('#pkg_modal_existing_id').val('');
    $('#pkg_modal_msg').hide();

    $('#pkg_modal_tour_link').html('<option value="">Loading…</option>');
    $.post(tcc_ajax_obj.ajax_url, {
        action: 'tcc_get_tours_for_pkg_link',
        security: tcc_ajax_obj.nonce
    }, function(res) {
        let $sel = $('#pkg_modal_tour_link');
        $sel.html('<option value="">— No Group Tour Linked —</option>');
        if (res.success && res.data.length) {
            res.data.forEach(function(t) {
                $sel.append('<option value="' + t.id + '">' + t.title + '</option>');
            });
        }
    });

    $('#tcc-pkg-modal-wrap').css('display', 'flex');
});

// ── Close modal ─────────────────────────────────────────────────────────────
$('#pkg_modal_cancel').on('click', function() {
    $('#tcc-pkg-modal-wrap').hide();
});

// Click backdrop to close
$('#tcc-pkg-modal-wrap').on('click', function(e) {
    if ($(e.target).is('#tcc-pkg-modal-wrap')) {
        $(this).hide();
    }
});

// ── Submit publish ──────────────────────────────────────────────────────────
$('#pkg_modal_submit').on('click', function() {
    let displayName = $('#pkg_modal_name').val().trim();
    let presetName  = $('#pkg_modal_preset_name').val();
    let dest        = $('#pkg_modal_destination').val();
    let tourId      = $('#pkg_modal_tour_link').val();
    let existingId  = $('#pkg_modal_existing_id').val();

    if (!displayName) {
        tccShowToast("Please enter a public name for this package.", true);
        return;
    }

    let btn = $(this);
    let origText = btn.text();
    btn.text('Publishing…').prop('disabled', true);

    $.post(tcc_ajax_obj.ajax_url, {
        action:         'tcc_publish_preset_package',
        security:       tcc_ajax_obj.nonce,
        destination:    dest,
        preset_name:    presetName,
        display_name:   displayName,
        linked_tour_id: tourId,
        existing_id:    existingId
    }, function(res) {
        btn.text(origText).prop('disabled', false);
        if (res.success) {
            $('#tcc-pkg-modal-wrap').hide();
            tccShowToast(res.data.message, false);
            setTimeout(function() {
                if (confirm('Package published!\n\nOpen the live page in a new tab?')) {
                    window.open(res.data.permalink, '_blank');
                }
            }, 300);
            loadPublishedPackages();
        } else {
            let $msg = $('#pkg_modal_msg');
            $msg.text('Error: ' + (res.data || 'Unknown error')).css('color', '#dc2626').show();
        }
    }).fail(function() {
        btn.text(origText).prop('disabled', false);
        tccShowToast('Server error. Please try again.', true);
    });
});

// ── Load & render packages table in the settings panel ──────────────────────
// NOTE: shortcode examples are shown in PHP panel only — NOT in this JS —
// to prevent WordPress from executing [tcc_packages] inside AJAX HTML.
function loadPublishedPackages() {
    let $el = $('#tcc_packages_list');
    if (!$el.length) return;

    $el.html('<div style="padding:15px;text-align:center;color:#64748b;">Loading…</div>');

    $.post(tcc_ajax_obj.ajax_url, {
        action: 'tcc_list_all_packages',
        security: tcc_ajax_obj.nonce
    }, function(res) {
        if (!res.success) return;

        if (res.data.length === 0) {
            $el.html('<div style="padding:20px;text-align:center;color:#64748b;">No packages published yet.<br><br>Load a preset in the <strong>Calculator</strong> tab, then click <strong style="color:#10b981;">🌐 Publish</strong>.</div>');
            return;
        }

        let html = '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            + '<tr style="background:#f1f5f9;border-bottom:1px solid #cbd5e1;">'
            + '<th style="padding:8px;text-align:left;">Package Name</th>'
            + '<th style="padding:8px;text-align:center;">Destination</th>'
            + '<th style="padding:8px;text-align:center;">Days</th>'
            + '<th style="padding:8px;text-align:center;">Status</th>'
            + '<th style="padding:8px;text-align:right;">Actions</th>'
            + '</tr>';

        res.data.forEach(function(p) {
            let badge = p.status === 'publish'
                ? '<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:10px;font-weight:700;font-size:10px;">🟢 Live</span>'
                : '<span style="background:#f1f5f9;color:#64748b;padding:2px 8px;border-radius:10px;font-weight:700;font-size:10px;">⚫ Draft</span>';

            html += '<tr style="border-bottom:1px solid #f8fafc;">'
                + '<td style="padding:8px;font-weight:bold;color:#0f172a;">' + p.title + '</td>'
                + '<td style="padding:8px;text-align:center;color:#64748b;">' + (p.destination || '—') + '</td>'
                + '<td style="padding:8px;text-align:center;">' + (p.days || '—') + 'D</td>'
                + '<td style="padding:8px;text-align:center;">' + badge + '</td>'
                + '<td style="padding:8px;text-align:right;white-space:nowrap;">'
                + '<a href="' + p.permalink + '" target="_blank" style="color:#2563eb;font-size:11px;margin-right:8px;text-decoration:underline;">View →</a>'
                + '<button type="button" class="sync-pkg-btn tcc-btn-secondary" data-id="' + p.id + '" data-preset="' + p.preset_name + '" data-dest="' + (p.destination || '') + '" style="font-size:10px;padding:2px 8px;margin:0 4px 0 0;">🔄 Sync</button>'
                + '<button type="button" class="toggle-pkg-btn" data-id="' + p.id + '" data-status="' + p.status + '" style="background:none;border:none;color:#d97706;cursor:pointer;font-size:11px;text-decoration:underline;margin-right:6px;">'
                + (p.status === 'publish' ? 'Unpublish' : 'Publish')
                + '</button>'
                + '<button type="button" class="del-pkg-btn" data-id="' + p.id + '" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:11px;text-decoration:underline;">Del</button>'
                + '</td></tr>';
        });

        html += '</table>';

        // ── No shortcode display here on purpose ──
        // Shortcode examples are rendered in PHP (render_settings_panel) with
        // HTML-entity-escaped brackets &#91; &#93; so WordPress cannot execute
        // them. Putting raw [tcc_packages] in JS-injected HTML risks execution
        // by page builders / caching plugins.

        $el.html(html);
    });
}

// ── Sync (re-publish) a package from its saved preset ───────────────────────
$(document).on('click', '.sync-pkg-btn', function() {
    let id         = $(this).data('id');
    let presetName = $(this).data('preset');
    let dest       = $(this).data('dest');
    let title      = $(this).closest('tr').find('td:first').text().trim();

    $('#pkg_modal_name').val(title);
    $('#pkg_modal_preset_name').val(presetName);
    $('#pkg_modal_destination').val(dest);
    $('#pkg_modal_existing_id').val(id);
    $('#pkg_modal_msg').hide();

    $('#pkg_modal_tour_link').html('<option value="">Loading…</option>');
    $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_get_tours_for_pkg_link', security: tcc_ajax_obj.nonce }, function(r) {
        let $sel = $('#pkg_modal_tour_link');
        $sel.html('<option value="">— No Group Tour Linked —</option>');
        if (r.success && r.data.length) {
            r.data.forEach(function(t) {
                $sel.append('<option value="' + t.id + '">' + t.title + '</option>');
            });
        }
        $('#tcc-pkg-modal-wrap').css('display', 'flex');
    });
});

// ── Toggle publish / draft ───────────────────────────────────────────────────
$(document).on('click', '.toggle-pkg-btn', function() {
    let id = $(this).data('id');
    $.post(tcc_ajax_obj.ajax_url, {
        action: 'tcc_toggle_package_status',
        security: tcc_ajax_obj.nonce,
        post_id: id
    }, function(res) {
        if (res.success) { tccShowToast('Status updated.', false); loadPublishedPackages(); }
        else tccShowToast('Error updating status.', true);
    });
});

// ── Delete package ───────────────────────────────────────────────────────────
$(document).on('click', '.del-pkg-btn', function() {
    if (!confirm('Permanently delete this package page?\nThis cannot be undone.')) return;
    let id = $(this).data('id');
    $.post(tcc_ajax_obj.ajax_url, {
        action: 'tcc_delete_package_post',
        security: tcc_ajax_obj.nonce,
        post_id: id
    }, function(res) {
        if (res.success) { tccShowToast('Package deleted.', false); loadPublishedPackages(); }
        else tccShowToast('Error deleting package.', true);
    });
});

// ── Auto-load packages list when the accordion is opened ────────────────────
$(document).on('click', '.tcc-accordion-header', function() {
    if ($(this).next('.tcc-accordion-body').find('#tcc_packages_list').length) {
        setTimeout(loadPublishedPackages, 200);
    }
});
});