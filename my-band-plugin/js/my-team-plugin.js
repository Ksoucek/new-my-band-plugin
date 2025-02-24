jQuery(document).ready(function($) {
    console.log('JavaScript file loaded');

    $('#add-member-button').click(function() {
        var teamId = $('#team-id').val();
        var memberName = $('#member-name').val();
        $.post(myTeamPlugin.ajax_url, {
            action: 'my_team_plugin_add_member',
            team_id: teamId,
            member_name: memberName
        }, function(response) {
            console.log(response);
        });
    });

    $('#schedule-event-button').click(function() {
        var teamId = $('#team-id').val();
        var eventName = $('#event-name').val();
        var eventDate = $('#event-date').val();
        $.post(myTeamPlugin.ajax_url, {
            action: 'my_team_plugin_schedule_event',
            team_id: teamId,
            event_name: eventName,
            event_date: eventDate
        }, function(response) {
            console.log(response);
        });
    });

    $('#create-kseft-button').click(function() {
        var kseftName = $('#kseft-name').val();
        $.post(myTeamPlugin.ajax_url, {
            action: 'my_team_plugin_create_kseft',
            kseft_name: kseftName,
            nonce: myTeamPlugin.nonce
        }, function(response) {
            if (response.success) {
                window.location.href = response.data.redirect_url; // Přesměrování na kartu kšeftu
            } else {
                alert(response.data.message); // Zobrazení chybové zprávy
            }
        }).fail(function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            console.error('AJAX response:', xhr.responseText);
        });
    });

    $('.transport-select').on('change', function() {
        var roleId = $(this).data('role-id');
        var transport = $(this).val();
        var seats = $(this).find('option:selected').data('seats');
        var selectedCount = 0;

        $('.transport-select').each(function() {
            if ($(this).val() === transport) {
                selectedCount++;
            }
        });

        if (seats > 0 && selectedCount > seats) {
            alert('Nelze vybrat více míst než je kapacita auta.');
            $(this).val('');
            return;
        }

        var data = {
            action: 'save_role_transport',
            post_id: myTeamPlugin.post_id,
            role_id: roleId,
            transport: transport
        };
        $.post(myTeamPlugin.ajax_url, data, function(response) {
            console.log('Transport saved: ' + transport);
        });
    });

    $('.pickup-time-input').on('change', function() {
        var roleId = $(this).data('role-id');
        var pickupTime = $(this).val();

        // Kontrola formátu času (hh:mm)
        if (!/^\d{2}:\d{2}$/.test(pickupTime)) {
            alert('Neplatný formát času. Použijte formát hh:mm.');
            return;
        }

        // Kontrola hodnoty minut
        var timeParts = pickupTime.split(':');
        var hours = parseInt(timeParts[0], 10);
        var minutes = parseInt(timeParts[1], 10);
        if (minutes > 59) {
            alert('Neplatná hodnota minut. Minuty musí být mezi 00 a 59.');
            return;
        }

        $.post(myTeamPlugin.ajax_url, {
            action: 'save_pickup_time',
            post_id: myTeamPlugin.post_id,
            role_id: roleId,
            pickup_time: pickupTime
        }, function(response) {
            console.log('Pickup time saved:', response);
        });
    });

    function initializeAutocomplete(inputId, mapId) {
        var input = document.getElementById(inputId);
        if (!input) {
            console.error('Element with id "' + inputId + '" not found.');
            return;
        }

        var autocomplete = new google.maps.places.Autocomplete(input);
        var mapElement = document.getElementById(mapId);
        if (!mapElement) {
            console.error('Element with id "' + mapId + '" not found.');
            return;
        }

        var map = new google.maps.Map(mapElement, {
            center: { lat: -34.397, lng: 150.644 },
            zoom: 8
        });
        var marker = new google.maps.Marker({
            map: map,
            anchorPoint: new google.maps.Point(0, -29)
        });

        autocomplete.addListener('place_changed', function() {
            marker.setVisible(false);
            var place = autocomplete.getPlace();
            if (!place.geometry) {
                window.alert("No details available for input: '" + place.name + "'");
                return;
            }

            // If the place has a geometry, then present it on a map.
            if (place.geometry.viewport) {
                map.fitBounds(place.geometry.viewport);
            } else {
                map.setCenter(place.geometry.location);
                map.setZoom(17);  // Why 17? Because it looks good.
            }
            marker.setPosition(place.geometry.location);
            marker.setVisible(true);

            var address = '';
            if (place.address_components) {
                address = [
                    (place.address_components[0] && place.address_components[0].short_name || ''),
                    (place.address_components[1] && place.address_components[1].short_name || ''),
                    (place.address_components[2] && place.address_components[2].short_name || '')
                ].join(' ');
            }

            $('#' + inputId).val(address);
        });
    }

    // Initialize autocomplete for all address inputs if they exist
    if (document.getElementById('pickup_location')) {
        initializeAutocomplete('pickup_location', 'map');
    }
    if (document.getElementById('role_default_pickup_location')) {
        initializeAutocomplete('role_default_pickup_location', 'map-role-default');
    }
    if (document.getElementById('kseft_location') && document.getElementById('map')) {
        initializeAutocomplete('kseft_location', 'map'); // Změna ID mapy na "map"
    }
    if (document.getElementById('kseft_location_wp')) {
        initializeAutocomplete('kseft_location_wp', 'map-kseft-wp');
    }

    // Přidání řazení na všech sloupcích
    $('#obsazeni-table thead th').each(function() {
        $(this).addClass('sortable');
    });

    $('#obsazeni-table .sortable').on('click', function() {
        var table = $(this).parents('table').eq(0);
        var rows = table.find('tr:gt(0)').toArray().sort(comparer($(this).index()));
        this.asc = !this.asc;
        if (!this.asc) {
            rows = rows.reverse();
        }
        for (var i = 0; i < rows.length; i++) {
            table.append(rows[i]);
        }
    });

    function comparer(index) {
        return function(a, b) {
            var valA = getCellValue(a, index),
                valB = getCellValue(b, index);
            if (index === 4) { // Kontrola sloupce "Doprava"
                return valA.localeCompare(valB);
            }
            return $.isNumeric(valA) && $.isNumeric(valB) ? valA - valB : valA.localeCompare(valB);
        };
    }

    function getCellValue(row, index) {
        return $(row).children('td').eq(index).text();
    }

    // Přidání filtrování
    $('#obsazeni-table thead th').each(function() {
        var title = $(this).text();
        $(this).append('<br><input type="text" class="column-filter" placeholder="Filtr ' + title + '" />');
    });

    $('.column-filter').on('keyup change', function() {
        var index = $(this).parent().index();
        var filter = $(this).val().toLowerCase();
        $('#obsazeni-table tbody tr').filter(function() {
            $(this).toggle($(this).children('td').eq(index).text().toLowerCase().indexOf(filter) > -1);
        });
    });

    $('#role-confirmation-modal').css({
        'width': '50%', // Zvětšení šířky modálního okna
        'max-width': '600px', // Nastavení maximální šířky
        'margin': '0 auto' // Vycentrování modálního okna
    });

    $('#role-confirmation-modal .modal-content').css({
        'padding': '20px' // Zvýšení vnitřního odsazení
    });

    $('#role_status').on('change', function() {
        if ($(this).val() === 'Záskok') {
            $('#substitute-field').show();
            $('#pickup-location-field').show();
            $('#default-player-field').hide();
            $('#pickup_location').val('');
        } else if ($(this).val() === 'Jdu') {
            $('#substitute-field').hide();
            $('#pickup-location-field').show();
            $('#default-player-field').show();
            var defaultPickupLocation = $('#pickup_location').data('default-pickup-location');
            $('#pickup_location').val(defaultPickupLocation);
        } else {
            $('#substitute-field').hide();
            $('#pickup-location-field').hide();
            $('#default-player-field').hide();
        }
    });

    function updateRoleButton(kseftId, roleId, roleStatus, roleSubstitute, pickupLocation) {
        var button = $(`.role-confirmation[data-kseft-id="${kseftId}"][data-role-id="${roleId}"]`);
        button.removeClass('role-confirmation-nepotvrzeno role-confirmation-jdu role-confirmation-zaskok');
        if (roleStatus === 'Jdu') {
            button.addClass('role-confirmation-jdu');
            button.text('Jdu');
        } else if (roleStatus === 'Záskok') {
            button.addClass('role-confirmation-zaskok');
            button.text('Záskok: ' + roleSubstitute);
        } else {
            button.addClass('role-confirmation-nepotvrzeno');
            button.text('Nepotvrzeno');
        }
        button.closest('tr').find('.pickup-location').text(pickupLocation);
    }

    $('#role-confirmation-form').on('submit', function(e) {
        e.preventDefault();
        var data = {
            action: 'save_role_confirmation',
            post_id: $('#kseft_id').val(),
            role_id: $('#role_id').val(),
            role_status: $('#role_status').val(),
            role_substitute: $('#role_substitute').val(),
            pickup_location: $('#pickup_location').val(),
            nonce: myTeamPlugin.nonce
        };
        $.post(myTeamPlugin.ajax_url, data, function(response) {
            if (response.success) {
                $('#role-confirmation-modal').hide();
                updateRoleButton(data.post_id, data.role_id, data.role_status, data.role_substitute, data.pickup_location);
            } else {
                console.error('Error saving role confirmation:', response.error);
            }
        }).fail(function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            console.error('AJAX response:', xhr.responseText);
        });
    });

    $('#close-modal').on('click', function() {
        $('#role-confirmation-modal').hide();
    });

    $('#role_select').on('change', function() {
        var selectedRoleId = $(this).val();
        if (selectedRoleId) {
            $('#kseft-overview-table tbody tr').each(function() {
                var roleIds = $(this).data('role-ids');
                if (roleIds && roleIds.includes(parseInt(selectedRoleId))) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        } else {
            $('#kseft-overview-table tbody tr').show();
        }
    });
     function updateConfirmButton(kseftId, roleId) {
        $.post(myTeamPlugin.ajax_url, {
            action: 'get_role_status',
            kseft_id: kseftId,
            role_id: roleId
        }, function(response) {
            if (response.success) {
                var roleStatus = response.data.role_status;
                var button = $('.confirm-role-button[data-kseft-id="' + kseftId + '"]');
                button.removeClass('role-confirmation-nepotvrzeno role-confirmation-jdu role-confirmation-zaskok');
                if (roleStatus === 'Jdu') {
                    button.addClass('role-confirmation-jdu');
                    button.text('Jdu');
                } else if (roleStatus === 'Záskok') {
                    button.addClass('role-confirmation-zaskok');
                    button.text('Záskok');
                } else {
                    button.addClass('role-confirmation-nepotvrzeno');
                    button.text('Nepotvrzeno');
                }

                // Aktualizace sloupců Lokace a Čas vyzvednutí
                var pickupLocation = response.data.pickup_location;
                var pickupTime = response.data.pickup_time;
                button.closest('tr').find('td:nth-child(6)').text(pickupLocation);
                button.closest('tr').find('td:nth-child(7)').text(pickupTime);
            } else {
                console.error('Error fetching role status:', response.error);
            }
        });
    }


    // Aktualizace tlačítek na přehledu "moje-ksefty"
    $('#kseft-overview-table tbody tr').each(function() {
        var kseftId = $(this).data('kseft-id');
        var roleIds = $(this).data('role-ids');
        var currentRoleId = window.sessionStorage.getItem('selectedRoleId');
        if (roleIds && roleIds.includes(parseInt(currentRoleId))) {
            updateConfirmButton(kseftId, currentRoleId);
        }
    });
});