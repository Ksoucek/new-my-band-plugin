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

    $('.role-confirmation').on('click', function() {
        var roleId = $(this).data('role-id');
        var defaultPlayer = $(this).data('default-player');
        var defaultPickupLocation = $(this).data('default-pickup-location');
        $('#role_id').val(roleId);
        $('#role_status').val('Nepotvrzeno');
        $('#role_substitute').val('');
        $('#default_player').val(defaultPlayer);
        $('#pickup_location').val('');
        $('#pickup_location').data('default-pickup-location', defaultPickupLocation);
        $('#substitute-field').hide();
        $('#pickup-location-field').hide();
        $('#default-player-field').hide();
        $('#default-pickup-location-field').show(); // Přidání pole "Výchozí místo vyzvednutí"
        $('#role-confirmation-modal').show();
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

    $('#pickup_location').on('change', function() {
        var pickupLocations = [];
        $('.pickup-location').each(function() {
            pickupLocations.push($(this).val());
        });
        var destination = 'Cílová destinace'; // Zde zadejte cílovou destinaci

        console.log('Pickup locations:', pickupLocations);
        console.log('Destination:', destination);

        $.post(myTeamPlugin.ajax_url, {
            action: 'optimize_transport',
            pickup_locations: pickupLocations,
            destination: destination
        }, function(response) {
            console.log('Optimized transport:', response);
            $('#transport').val(response);
        });
    });

    $('#optimize-transport-button').on('click', function() {
        var pickupLocations = [];
        var addresses = [];
        $('.pickup-location').each(function() {
            var address = $(this).val();
            if (address) {
                addresses.push(address);
            }
        });
        var destination = $('#kseft_location').val(); // Získání cílové destinace z detailu Kšeftu
        if (destination) {
            addresses.push(destination);
        }

        var addressCount = addresses.length;
        var processedCount = 0;

        addresses.forEach(function(address, index) {
            getCoordinates(address, function(error, coords) {
                if (error) {
                    console.error('Error getting coordinates for address:', address, error);
                    return;
                }
                pickupLocations[index] = [coords[1], coords[0]]; // [longitude, latitude]
                processedCount++;
                if (processedCount === addressCount) {
                    console.log('Pickup locations:', pickupLocations);
                    console.log('Destination:', destination);
                    console.log('Post ID:', myTeamPlugin.post_id);

                    $.post(myTeamPlugin.ajax_url, {
                        action: 'optimize_transport',
                        post_id: myTeamPlugin.post_id,
                        locations: pickupLocations
                    }, function(response) {
                        console.log('AJAX response:', response);
                        if (response.success) {
                            $('.transport-select').each(function() {
                                $(this).val(response.data.route);
                            });
                            $('.pickup-time').each(function(index) {
                                $(this).text(response.data.pickup_times[index]);
                            });
                            alert('Optimalizace dopravy byla dokončena.');
                        } else {
                            console.error('Error:', response.error);
                            console.log('Raw response:', response.raw_response);
                            alert('Chyba při optimalizaci dopravy: ' + response.error);
                        }
                    }).fail(function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        console.error('AJAX response:', xhr.responseText);
                    });
                }
            });
        });
    });

    $('#test-api-button').on('click', function() {
        $.post(myTeamPlugin.ajax_url, {
            action: 'test_openai_api'
        }, function(response) {
            console.log('API Test response:', response);
            if (response.success) {
                console.log('API Test data:', response.response);
                alert('API Test proběhl úspěšně. Výsledek je v konzoli.');
            } else {
                console.error('API Test error:', response.error);
                alert('Chyba při testování API: ' + response.error);
            }
        }).fail(function(xhr, status, error) {
            console.error('API Test error:', status, error);
            console.error('API Test response:', xhr.responseText);
            alert('Chyba při testování API: ' + error);
        });
    });

    $('#close-modal').on('click', function() {
        $('#role-confirmation-modal').hide();
    });

    $('#role-confirmation-form').on('submit', function(e) {
        e.preventDefault();
        var roleStatus = $('#role_status').val();
        var pickupLocation = $('#pickup_location').val();
        if ((roleStatus === 'Jdu' || roleStatus === 'Záskok') && !pickupLocation) {
            alert('Prosím vyplňte Místo vyzvednutí.');
            return;
        }
        var data = {
            action: 'save_role_confirmation',
            post_id: myTeamPlugin.post_id,
            role_id: $('#role_id').val(),
            role_status: roleStatus,
            role_substitute: $('#role_substitute').val(),
            pickup_location: pickupLocation,
            default_player: $('#default_player').val(),
            transport: $('select[name="transport_' + $('#role_id').val() + '"]').val()
        };
        $.post(myTeamPlugin.ajax_url, data, function(response) {
            $('#role-confirmation-modal').hide();
            updateRoleButton(data.role_id, data.role_status, data.default_player, data.role_substitute, data.pickup_location, data.transport);
            updateKseftStatus();
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

    function updateRoleButton(roleId, roleStatus, defaultPlayer, roleSubstitute, pickupLocation, transport) {
        var button = $('.role-confirmation[data-role-id="' + roleId + '"]');
        button.removeClass('role-confirmation-nepotvrzeno role-confirmation-jdu role-confirmation-zaskok');
        var confirmationText = roleStatus;
        if (roleStatus === 'Jdu') {
            button.addClass('role-confirmation-jdu');
            confirmationText = defaultPlayer;
        } else if (roleStatus === 'Záskok') {
            button.addClass('role-confirmation-zaskok');
            confirmationText = 'Záskok: ' + roleSubstitute;
        } else {
            button.addClass('role-confirmation-nepotvrzeno');
        }
        button.text(roleStatus);
        $('#role_status').val(roleStatus);
        button.closest('tr').find('td:nth-child(2)').text(confirmationText);
        button.closest('tr').find('td:nth-child(3)').text(pickupLocation);
        button.closest('tr').find('td:nth-child(4)').find('select').val(transport);
    }

    function updateKseftStatus() {
        var allConfirmed = true;
        var hasSubstitute = false;
        $('.role-confirmation').each(function() {
            var roleStatus = $(this).hasClass('role-confirmation-jdu') ? 'Jdu' : ($(this).hasClass('role-confirmation-zaskok') ? 'Záskok' : 'Nepotvrzeno');
            if (roleStatus === 'Záskok') {
                hasSubstitute = true;
            }
            if (roleStatus !== 'Jdu' && roleStatus !== 'Záskok') {
                allConfirmed = false;
            }
        });
        var kseftButton = $('.kseft-status-button');
        if (allConfirmed) {
            kseftButton.removeClass('neobsazeno').addClass('obsazeno');
            kseftButton.text(hasSubstitute ? 'Obsazeno se záskokem' : 'Obsazeno');
        } else {
            kseftButton.removeClass('obsazeno').addClass('neobsazeno');
            kseftButton.text('Neobsazeno');
        }
    }

    function getCoordinates(address, callback) {
        $.post(myTeamPlugin.ajax_url, {
            action: 'get_coordinates',
            address: address
        }, function(response) {
            if (response.success) {
                callback(null, response.data);
            } else {
                callback(response.data);
            }
        }).fail(function(xhr, status, error) {
            callback(error);
        });
    }

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
    if (document.getElementById('kseft_location')) {
        initializeAutocomplete('kseft_location', 'map');
    }
    if (document.getElementById('kseft_location_wp')) {
        initializeAutocomplete('kseft_location_wp', 'map-kseft-wp');
    }

    $('#optimize-transport-button').on('click', function() {
        var locations = [
            // Přidejte zde lokace ve formátu [longitude, latitude]
        ];

        $.ajax({
            url: myTeamPlugin.ajax_url,
            method: 'POST',
            data: {
                action: 'optimize_transport',
                locations: locations
            },
            success: function(response) {
                var data = JSON.parse(response);
                console.log('Optimalizace jízdy:', data);
                // Zpracujte data a aktualizujte UI podle potřeby
            },
            error: function(error) {
                console.error('Chyba při optimalizaci jízdy:', error);
            }
        });
    });

    function handleGoogleCalendarEvent(postId, eventDetails, googleEventId) {
        if (!postId) {
            console.error('Missing post_id');
            alert('Chyba: post_id není k dispozici.');
            return;
        }

        var action = googleEventId ? 'update_google_calendar_event' : 'add_google_calendar_event';
        var ajaxUrl = googleEventId ? myTeamPlugin.ajax_url : myTeamPlugin.rest_url;

        console.log('Sending AJAX request to ' + (googleEventId ? 'update' : 'add') + ' Google Calendar event:', eventDetails);

        var data = {
            action: action,
            post_id: postId,
            event_details: eventDetails
        };

        if (googleEventId) {
            data.event_id = googleEventId;
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            beforeSend: function(xhr) {
                if (!googleEventId) {
                    xhr.setRequestHeader('X-WP-Nonce', myTeamPlugin.nonce);
                }
            },
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    console.log('Google Calendar event ' + (googleEventId ? 'updated' : 'added') + ' successfully:', response.event_id);
                    alert('Událost byla úspěšně ' + (googleEventId ? 'aktualizována' : 'přidána') + ' do Google Kalendáře.');

                    if (!googleEventId) {
                        // Uložení Google Calendar event ID ke kartě kšeftu
                        $.post(myTeamPlugin.ajax_url, {
                            action: 'save_google_event_id',
                            post_id: postId,
                            google_event_id: response.event_id
                        }).done(function(saveResponse) {
                            if (saveResponse.success) {
                                console.log('Google Calendar event ID saved successfully.');
                            } else {
                                console.error('Error saving Google Calendar event ID:', saveResponse.error);
                            }
                        }).fail(function(xhr, status, error) {
                            console.error('AJAX error:', status, error);
                            console.error('AJAX response:', xhr.responseText);
                        });
                    }
                } else {
                    console.error('Error ' + (googleEventId ? 'updating' : 'adding') + ' event to Google Calendar:', response.error);
                    alert('Chyba při ' + (googleEventId ? 'aktualizaci' : 'přidávání') + ' události do Google Kalendáře.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                console.error('Response:', xhr.responseText);
                alert('Chyba při komunikaci se serverem.');
            }
        });
    }

    $('#add-to-calendar-button').on('click', function() {
        console.log('Add to Calendar button clicked');
        var postId = myTeamPlugin.post_id;

        if (!postId) {
            console.error('Missing post_id');
            alert('Chyba: post_id není k dispozici.');
            return;
        }

        $.post(myTeamPlugin.ajax_url, {
            action: 'get_event_details',
            post_id: postId
        }, function(response) {
            if (response.success) {
                var eventDate = response.data.event_date;
                var meetingTime = response.data.meeting_time;
                var kseftName = response.data.kseft_name;
                var kseftLocation = response.data.kseft_location;
                var kseft_duration = response.data.kseft_duration || 2; // Předpokládaná délka v hodinách, výchozí hodnota je 2 hodiny

                var eventDetails = {
                    summary: kseftName,
                    location: kseftLocation,
                    start: {},
                    end: {}
                };

                if (meetingTime) {
                    // Formátování datumu a času
                    var startDateTime = new Date(eventDate + 'T' + meetingTime + ':00').toISOString();
                    var endDateTime = new Date(new Date(eventDate + 'T' + meetingTime + ':00').getTime() + kseft_duration * 60 * 60 * 1000).toISOString();

                    eventDetails.start.dateTime = startDateTime;
                    eventDetails.end.dateTime = endDateTime;
                    eventDetails.start.timeZone = 'Europe/Prague';
                    eventDetails.end.timeZone = 'Europe/Prague';
                } else {
                    // Nastavení akce jako celodenní
                    eventDetails.start.date = eventDate;
                    eventDetails.end.date = eventDate;
                }

                console.log('JS Event details:', eventDetails);

                var googleEventId = response.data.google_event_id || null;
                handleGoogleCalendarEvent(postId, eventDetails, googleEventId);
            } else {
                console.error('Error fetching event details:', response.data);
                alert('Chyba při získávání detailů události.');
            }
        });
    });

    $('#manage-kseft-form').on('submit', function(e) {
        e.preventDefault();

        var kseftId = $('input[name="kseft_id"]').val();
        var kseftName = $('input[name="kseft_name"]').val();
        var kseftLocation = $('input[name="kseft_location"]').val();
        var kseftMeetingTime = $('input[name="kseft_meeting_time"]').val();
        var kseftEventDate = $('input[name="kseft_event_date"]').val();
        var kseftDuration = $('input[name="kseft_duration"]').val();
        var kseftStatus = $('select[name="kseft_status"]').val();

        if (!kseftId) {
            console.error('Missing kseft_id');
            alert('Chyba: kseft_id není k dispozici.');
            return;
        }

        var startTime = kseftMeetingTime ? kseftEventDate + 'T' + kseftMeetingTime + ':00' : kseftEventDate + 'T00:00:00';
        var endTime = kseftMeetingTime ? new Date(new Date(startTime).getTime() + (kseftDuration ? kseftDuration : 24) * 3600 * 1000).toISOString() : kseftEventDate + 'T23:59:59';

        if (isNaN(Date.parse(startTime)) || isNaN(Date.parse(endTime))) {
            endTime = kseftEventDate + 'T23:59:59';
        }

        var eventDetails = {
            summary: kseftName,
            location: kseftLocation,
            description: kseftStatus,
            start: startTime,
            end: endTime
        };

        var googleEventId = $('input[name="google_calendar_event_id"]').val();
        handleGoogleCalendarEvent(kseftId, eventDetails, googleEventId);

        this.submit();
    });

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

});
