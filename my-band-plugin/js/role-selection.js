jQuery(document).ready(function($) {
    console.log('role-selection.js file loaded');

    function showRoleSelectionModal() {
        $('#role-selection-modal').show();
    }

    function hideRoleSelectionModal() {
        $('#role-selection-modal').hide();
    }

    function setCurrentRole(roleId, roleText) {
        $('#selected-role-display').text(`Zvolená role: ${roleText}`);
        sessionStorage.setItem('selectedRoleId', roleId);
        sessionStorage.setItem('selectedRoleText', roleText);
        filterKseftByRole(roleId);
    }

    function getCurrentRole() {
        return {
            roleId: sessionStorage.getItem('selectedRoleId'),
            roleText: sessionStorage.getItem('selectedRoleText')
        };
    }

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

    function filterKseftByRole(roleId) {
        $.post(myTeamPlugin.ajax_url, {
            action: 'get_ksefty_by_role',
            role_id: roleId
        }, function(response) {
            if (response.success) {
                const kseftIds = response.data;
                $('#kseft-overview-table tbody tr').each(function() {
                    const kseftId = $(this).data('kseft-id');
                    if (kseftIds.includes(kseftId)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            } else {
                console.error('Error fetching ksefty by role:', response.error);
            }
        });
    }

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

    // Přidání funkce pro potvrzení účasti za zvolenou roli
    $('.confirm-role-button, .role-confirmation').on('click', function() {
        const kseftId = $(this).data('kseft-id');
        const currentRole = {
            roleId: sessionStorage.getItem('selectedRoleId'),
            roleText: sessionStorage.getItem('selectedRoleText')
        };
        if (!currentRole.roleId) {
            alert('Prosím vyberte roli.');
            return;
        }

        $.post(myTeamPlugin.ajax_url, {
            action: 'get_role_details',
            role_id: currentRole.roleId
        }, function(response) {
            if (response.success) {
                const roleDetails = response.data;
                $('#kseft_id').val(kseftId);
                $('#role_id').val(currentRole.roleId);
                $('#role_status').val('Nepotvrzeno');
                $('#role_substitute').val('');
                $('#pickup_location').val(roleDetails.default_pickup_location);
                $('#default_player').val(roleDetails.default_player);
                $('#substitute-field').hide();
                $('#pickup-location-field').hide();
                $('#default-player-field').hide();
                $('#role-confirmation-modal').show();
            } else {
                console.error('Error fetching role details:', response.error);
            }
        });
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
            post_id: $('#kseft_id').val(),
            role_id: $('#role_id').val(),
            role_status: roleStatus,
            role_substitute: $('#role_substitute').val(),
            pickup_location: pickupLocation,
            default_player: $('#default_player').val()
        };
        $.post(myTeamPlugin.ajax_url, data, function(response) {
            $('#role-confirmation-modal').hide();
            updateRoleButton(data.role_id, data.role_status, data.default_player, data.role_substitute, data.pickup_location);
            updateKseftStatus();
            updateConfirmButton(data.post_id, data.role_id);
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

    $('#role-selection-form').on('submit', function(e) {
        e.preventDefault();
        const selectedRoleId = $('#initial_role_id').val();
        const selectedRoleText = $('#initial_role_id option:selected').text();
        if (selectedRoleId) {
            setCurrentRole(selectedRoleId, selectedRoleText);
            hideRoleSelectionModal();
        } else {
            alert('Prosím vyberte roli.');
        }
    });

    $('#selected-role-display').on('click', function() {
        showRoleSelectionModal();
    });

    const currentRole = getCurrentRole();
    if (!currentRole.roleId) {
        showRoleSelectionModal();
    } else {
        setCurrentRole(currentRole.roleId, currentRole.roleText);
    }

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

    // Aktualizace tlačítek na přehledu "moje-ksefty"
    $('#kseft-overview-table tbody tr').each(function() {
        var kseftId = $(this).data('kseft-id');
        var roleIds = $(this).data('role-ids');
        var currentRoleId = sessionStorage.getItem('selectedRoleId');
        if (roleIds && roleIds.includes(parseInt(currentRoleId))) {
            updateConfirmButton(kseftId, currentRoleId);
        }
    });
});
