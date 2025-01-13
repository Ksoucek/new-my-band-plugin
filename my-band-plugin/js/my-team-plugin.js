jQuery(document).ready(function($) {
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
});
