jQuery(document).ready(function($) {
    
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
            $.each(optionsArray, function(i, val) { $el.append($('<option></option>').val(val).text(val)); });
            
            if(currentVal && $el.find("option[value='" + currentVal + "']").length) {
                $el.val(currentVal);
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
            populateDropdown('#calc_hotel_cat', tccMasterData[dest].hotel_categories);
            $('.stay_place_dropdown').each(function() { populateDropdown(this, tccMasterData[dest].stay_places); });
            $('.transport_dropdown').each(function() { populateDropdown(this, tccMasterData[dest].vehicles); });
            setTimeout(function() { $('.night-stay-row').each(function() { updateRowHotels($(this)); }); }, 100);
        }
    }

    $('#calc_destination').on('change', updateCalculatorDropdowns);

    function updateRowHotels($row) {
        let dest = $('#calc_destination').val();
        let cat = $('#calc_hotel_cat').val();
        let place = $row.find('.stay_place_dropdown').val();
        let $hotelDrop = $row.find('.stay_hotel_dropdown');
        let $hiddenInput = $row.find('.stay_hotel_hidden');

        if(dest && cat && place) {
            $hotelDrop.html('<option value="">Loading...</option>');
            $.post(tcc_ajax_obj.ajax_url, { action: 'tcc_fetch_hotel_names', dest: dest, place: place, cat: cat }, function(res) {
                $hotelDrop.empty();
                if(res.success && res.data.length > 0) {
                    $.each(res.data, function(i, name) { $hotelDrop.append(`<option value="${name}">${name}</option>`); });
                    $hotelDrop.val([res.data[0]]); 
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

    $(document).on('change', '.stay_place_dropdown', function() { updateRowHotels($(this).closest('.night-stay-row')); });
    $('#calc_hotel_cat').on('change', function() { $('.night-stay-row').each(function() { updateRowHotels($(this)); }); });

    $('#total_pax, #child_pax').on('input', function() {
        let pax = parseInt($('#total_pax').val()) || 0;
        let child = parseInt($('#child_pax').val()) || 0;

        let rooms = Math.max(Math.ceil(pax / 3), child);
        if(rooms < 1) rooms = 1;
        let baseAdultCap = rooms * 2;
        let extraBeds = pax > baseAdultCap ? pax - baseAdultCap : 0;
        
        $('#no_of_rooms').val(rooms);
        $('#extra_beds').val(extraBeds);

        triggerAutoTransportOptimization();
    });

    $('#calc_pickup').on('change', function() { triggerAutoTransportOptimization(); });

    let transportTimeout;
    function triggerAutoTransportOptimization() {
        clearTimeout(transportTimeout);
        transportTimeout = setTimeout(function() {
            let dest = $('#calc_destination').val();
            let pickup = $('#calc_pickup').val();
            let pax = parseInt($('#total_pax').val()) || 0;
            if(!dest || !pickup || pax <= 0) return;

            $.post(tcc_ajax_obj.ajax_url, {
                action: 'tcc_optimize_transport', destination: dest, pickup_location: pickup, total_pax: pax
            }, function(res) {
                if(res.success && res.data.length > 0) {
                    $('#transport-wrapper .transport-row').remove(); 
                    $.each(res.data, function(i, item) { addTransportRow(item.vehicle, item.qty); });
                    triggerLiveCalculation();
                } else {
                    triggerLiveCalculation();
                }
            });
        }, 400); 
    }

    function addTransportRow(vehicleName = '', qty = 1) {
        let dest = $('#calc_destination').val();
        let optionsHtml = '';
        if(typeof tccMasterData !== 'undefined' && tccMasterData[dest]) {
            let vehs = tccMasterData[dest].vehicles;
            if(typeof vehs === 'string') vehs = vehs.split(',').map(s=>s.trim());
            if(Array.isArray(vehs)) {
                $.each(vehs, function(i, val) {
                    let sel = (val === vehicleName) ? 'selected' : '';
                    optionsHtml += `<option value="${val}" ${sel}>${val}</option>`;
                });
            }
        }
        let row = `
        <div class="transport-row tcc-repeater-row tcc-fade-in" style="flex-wrap:wrap;">
            <select name="transportation[]" class="transport_dropdown" required style="flex:3;">${optionsHtml}</select>
            <input type="number" name="transport_qty[]" value="${qty}" min="1" placeholder="Qty" required style="flex:1;">
            <button type="button" class="remove_transport tcc-btn-del">X</button>
            <div class="tcc-trans-cost" style="flex: 1 1 100%; text-align:right; font-size:11px; color:#108043; font-weight:bold; margin-top:-2px;"></div>
        </div>`;
        $('#transport-wrapper').append(row);
    }

    $('#add_transport').on('click', function() { addTransportRow(); triggerLiveCalculation(); });
    $(document).on('click', '.remove_transport', function() { $(this).closest('.transport-row').remove(); triggerLiveCalculation(); });

    $('#total_days').on('input', function() {
        let days = parseInt($(this).val()) || 0;
        $('#total_nights_display').text(days > 0 ? days - 1 : 0);
        triggerLiveCalculation();
    });

    $('#add_stay_place').on('click', function() {
        let dest = $('#calc_destination').val();
        let optionsHtml = '';
        if(typeof tccMasterData !== 'undefined' && tccMasterData[dest]) {
            let stays = tccMasterData[dest].stay_places;
            if(typeof stays === 'string') stays = stays.split(',').map(s=>s.trim());
            if(Array.isArray(stays)) $.each(stays, function(i, val) { optionsHtml += `<option value="${val}">${val}</option>`; });
        }
        let row = `
        <div class="night-stay-row tcc-repeater-row tcc-fade-in" style="flex-wrap:wrap;">
            <select name="stay_place[]" class="stay_place_dropdown" required style="flex:2;"></select>
            <div style="flex:2;">
                <select class="stay_hotel_dropdown" multiple required style="width:100%; height:55px !important; border:1px solid #ccc; border-radius:3px; padding:2px; font-size:12px; background:#fff; outline:none;"></select>
                <input type="hidden" name="stay_hotel[]" class="stay_hotel_hidden">
            </div>
            <input type="number" name="stay_nights[]" placeholder="Nights" class="stay_nights" min="1" required style="flex:1;">
            <button type="button" class="remove_stay_place tcc-btn-del">X</button>
            <div class="tcc-hotel-cost" style="flex: 1 1 100%; text-align:right; font-size:11px; color:#108043; font-weight:bold; margin-top:-2px;"></div>
        </div>`;
        let $newRow = $(row);
        $('#night-stay-wrapper').append($newRow);
        updateRowHotels($newRow);
    });
    $(document).on('click', '.remove_stay_place', function() { $(this).closest('.night-stay-row').remove(); triggerLiveCalculation(); });

    function validateCalculator() {
        let pax = parseInt($('#total_pax').val()) || 0;
        let child = parseInt($('#child_pax').val()) || 0;
        let rooms = parseInt($('#no_of_rooms').val()) || 0;
        let extra_beds = parseInt($('#extra_beds').val()) || 0;
        let total_days = parseInt($('#total_days').val()) || 0;
        let expected_nights = total_days > 0 ? total_days - 1 : 0;

        if (!$('#start_date').val()) return "Select Start Date.";
        if (extra_beds > rooms) return "Max 1 Extra Bed/room.";
        if (child > rooms) return "Max 1 Child (<6yr)/room.";
        if ((rooms * 2) + extra_beds < pax) return `Not enough beds for ${pax} Adults.`;

        let sum_nights = 0;
        $('.stay_nights').each(function() { sum_nights += parseInt($(this).val()) || 0; });
        if (sum_nights !== expected_nights) return `Nights (${sum_nights}) must equal ${expected_nights}.`;

        return null; 
    }

    let liveCalcTimeout;
    $('#tcc-calc-form').on('input change', 'input, select', function(e) {
        if(e.target.id === 'tcc_calculate_btn') return;
        if(e.target.id !== 'total_pax' && e.target.id !== 'child_pax' && e.target.id !== 'calc_pickup') {
            triggerLiveCalculation();
        }
    });

    function triggerLiveCalculation() {
        $('#live_error').hide();
        $('#tcc_link_wrapper').slideUp(); 
        
        let validationError = validateCalculator();
        if (validationError) {
            $('#live_pp, #live_profit, #live_discount, #live_total, #live_gst').text('₹0.00').css('opacity', '1');
            $('.tcc-trans-cost, .tcc-hotel-cost').text('');
            $('#live_error').html(validationError).show();
            return;
        }

        $('#live_pp, #live_profit, #live_discount, #live_total, #live_gst').css('opacity', '0.5'); 
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
                $('#live_pp, #live_profit, #live_discount, #live_total, #live_gst').css('opacity', '1');
                if(res.success) {
                    let d = res.data;
                    $('#live_pp').text(formatINR(d.per_person));
                    $('#live_profit').text(formatINR(d.summary_data.final_profit));
                    $('#live_discount').text("-" + formatINR(d.summary_data.discount_amount));
                    $('#live_gst').text(formatINR(d.gst));
                    $('#live_total').text(formatINR(d.grand_total));
                    
                    $('#live_profit').removeClass('tcc-preview-loss tcc-preview-profit').addClass(d.summary_data.final_profit < 0 ? 'tcc-preview-loss' : 'tcc-preview-profit');
                    
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

                    if (d.summary_data.corrected_trans_qtys) {
                        $('.transport-row').each(function(index) {
                            let $qtyInput = $(this).find('input[name="transport_qty[]"]');
                            if (d.summary_data.corrected_trans_qtys[index] !== undefined && !$qtyInput.is(':focus')) {
                                $qtyInput.val(d.summary_data.corrected_trans_qtys[index]);
                            }
                        });
                    }

                    $('#live_error').hide();
                } else {
                    $('#live_pp, #live_profit, #live_discount, #live_total, #live_gst').text('₹0.00');
                    $('.tcc-trans-cost, .tcc-hotel-cost').text('');
                    $('#live_error').html(res.data.errors).show();
                }
            }
        });
    }

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
        btn.text('Generating Link...').prop('disabled', true);

        $.ajax({
            url: tcc_ajax_obj.ajax_url, type: 'POST',
            data: $(this).serialize() + '&action=tcc_calculate_trip&generate_link=1',
            success: function(response) {
                btn.text('Generate Quote Link').prop('disabled', false);
                if(response.success) {
                    $('#tcc_generated_link').val(response.data.permalink);
                    $('#tcc_open_btn').attr('href', response.data.permalink);
                    $('#tcc_link_wrapper').slideDown();
                } else {
                    $('#live_error').html(response.data.errors).show();
                }
            }
        });
    });

    $('#tcc_copy_btn').on('click', function() {
        let copyText = document.getElementById("tcc_generated_link");
        copyText.select();
        copyText.setSelectionRange(0, 99999); 
        document.execCommand("copy");
        let $btn = $(this);
        $btn.text("Copied!");
        setTimeout(function(){ $btn.text("Copy"); }, 2000);
    });

    // --- SETTINGS DASHBOARD DYNAMICS ---
    
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
                    $.each(res.data, function(i, name) { $dd.append(`<option value="${name}">${name}</option>`); });
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
        } else if (val !== '') {
            $input.val(val).hide(); 
            fetchHotelPricingSpecific(val);
        } else {
            $input.val('').hide();
            $('#set_hotel_website, #set_room_price, #set_extra_bed, #set_child_price').val('');
            $('#hotel_fetch_status').text('');
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
                } else {
                    $('#trans_fetch_status').text('(New)');
                    $('#set_transport_price').val('');
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
        setTimeout(() => { triggerAutoTransportOptimization(); }, 500); 
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
});