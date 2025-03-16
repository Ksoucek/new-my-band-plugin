jQuery(document).ready(function($) {
    console.log('role-selection.js file loaded');

    // Definice proměnné kseftId
    var kseftId = $('#kseft_id').val() || null;

    function showRoleSelectionModal() {
        $('#role-selection-modal').show();
    }

    function hideRoleSelectionModal() {
        $('#role-selection-modal').hide();
    }

    function setCurrentRole(roleId, roleText) {
        $('#selected-role-display').text(`Zvolená role: ${decodeURIComponent(roleText)}`);
        document.cookie = `selectedRoleId=${roleId}; path=/`;
        document.cookie = `selectedRoleText=${encodeURIComponent(roleText)}; path=/`;
        filterKseftByRole(roleId);
        updateConfirmButtonsForMyKsefty(); // Přidání volání updateConfirmButtonsForMyKsefty
    }

    function getCurrentRole() {
        const cookies = document.cookie.split(';').reduce((acc, cookie) => {
            const [key, value] = cookie.split('=').map(c => c.trim());
            acc[key] = value;
            return acc;
        }, {});
        return {
            roleId: (cookies.selectedRoleId && cookies.selectedRoleId !== "undefined") ? cookies.selectedRoleId : null,
            roleText: (cookies.selectedRoleText && cookies.selectedRoleText !== "undefined") ? decodeURIComponent(cookies.selectedRoleText) : null
        };
    }

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

    function loadRoleConfirmations(kseftId) {
        if (!kseftId) {
            console.error('kseftId is not defined');
            return;
        }
        $('.role-confirmation').each(function() {
            var roleId = $(this).data('role-id');
            $.post(myTeamPlugin.ajax_url, {
                action: 'get_role_confirmation',
                kseft_id: kseftId,
                role_id: roleId
            }, function(response) {
                if (response.success) {
                    var data = response.data;
                    updateRoleButton(kseftId, roleId, data.role_status, data.role_substitute, data.pickup_location);
                } else {
                    console.error('Error fetching role confirmation:', response.error);
                }
            });
        });
    }

    function updateKseftStatus(kseftId) {
        var allConfirmed = true;
        var hasSubstitute = false;
        $(`.role-confirmation[data-kseft-id="${kseftId}"]`).each(function() {
            var roleStatus = $(this).hasClass('role-confirmation-jdu') ? 'Jdu' : ($(this).hasClass('role-confirmation-zaskok') ? 'Záskok' : 'Nepotvrzeno');
            if (roleStatus === 'Záskok') {
                hasSubstitute = true;
            }
            if (roleStatus !== 'Jdu' && roleStatus !== 'Záskok') {
                allConfirmed = false;
            }
        });
        var kseftButton = $(`.kseft-status-button[data-kseft-id="${kseftId}"]`);
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
            action: 'get_role_confirmation',
            kseft_id: kseftId,
            role_id: roleId
        }, function(response) {
            if (response.success) {
                var data = response.data;
                var button = $(`.confirm-role-button[data-kseft-id="${kseftId}"]`);
                button.removeClass('role-confirmation-nepotvrzeno role-confirmation-jdu role-confirmation-zaskok');
                if (data.role_status === 'Jdu') {
                    button.addClass('role-confirmation-jdu');
                    button.text('Jdu');
                } else if (data.role_status === 'Záskok') {
                    button.addClass('role-confirmation-zaskok');
                    button.text('Záskok');
                } else {
                    button.addClass('role-confirmation-nepotvrzeno');
                    button.text('Nepotvrzeno');
                }
            } else {
                console.error('Error fetching role confirmation:', response.error);
            }
        });
    }

    // Přidání funkce pro potvrzení účasti za zvolenou roli
    $('.confirm-role-button, .role-confirmation').on('click', function() {
        const kseftId = $(this).data('kseft-id');
        let confirmingRoleId;
        let confirmingRoleText;

        // Rozlišení, zda potvrzujete z přehledu nebo z karty kšeftu
        if ($(this).hasClass('confirm-role-button')) {
            const currentRole = getCurrentRole(); // Získání aktuální zvolené role
            confirmingRoleId = currentRole.roleId; // Použití aktuální zvolené role
            confirmingRoleText = currentRole.roleText; // Použití textu aktuální zvolené role
        } else {
            confirmingRoleId = $(this).data('role-id'); // Získání role z karty kšeftu
            confirmingRoleText = $(this).closest('tr').find('td:nth-child(2)').text(); // Získání textu role z karty kšeftu
        }

        // Výpis do konzole
        console.log('confirmingRoleId:', confirmingRoleId);

        $.post(myTeamPlugin.ajax_url, {
            action: 'get_role_details',
            role_id: confirmingRoleId
        }, function(response) {
            if (response.success) {
                const roleDetails = response.data;
                $('#kseft_id').val(kseftId); // Ensure kseft_id is set correctly
                $('#role_id').val(confirmingRoleId);
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
        var data = {
            action: 'save_role_confirmation',
            kseft_id: $('#kseft_id').val(), // Use the correct selector
            role_id: $('#role_id').val(),
            role_status: $('#role_status').val(),
            role_substitute: $('#role_substitute').val(),
            pickup_location: $('#pickup_location').val(),
            nonce: myTeamPlugin.nonce // Přidání nonce pro zabezpečení
        };
        console.log(`Saving role confirmation: kseft_id=${data.kseft_id}, role_id=${data.role_id}, role_status=${data.role_status}, role_substitute=${data.role_substitute}, pickup_location=${data.pickup_location}`);
        $.post(myTeamPlugin.ajax_url, data, function(response) {
            if (response.success) {
                console.log(`jsRole confirmation saved: kseft_id=${data.kseft_id}, role_id=${data.role_id}, role_status=${data.role_status}`);
                $('#role-confirmation-modal').hide();
                updateRoleButton(data.kseft_id, data.role_id, data.role_status, data.role_substitute, data.pickup_location);
                updateKseftStatus(data.kseft_id);
                updateConfirmButton(data.kseft_id, data.role_id);
            } else {
                console.error('Error saving role confirmation:', response.error);
            }
        }).fail(function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            console.error('AJAX response:', xhr.responseText);
        });
    });

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
        var kseftId = $('#kseft_id').val() || null; // Přidání kontroly, zda je kseftId definováno
        if (selectedRoleId) {
            $('#kseft-overview-table tbody tr').each(function() {
                var roleIds = $(this).data('role-ids');
                if (roleIds && roleIds.includes(parseInt(selectedRoleId))) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            updateConfirmButtonsForMyKsefty(); // Přidání volání updateConfirmButtonsForMyKsefty
        } else {
            $('#kseft-overview-table tbody tr').show();
        }
    });

    // Aktualizace tlačítek na přehledu "moje-ksefty"
    function updateConfirmButtonsForMyKsefty() {
        $('#kseft-overview-table tbody tr').each(function() {
            var kseftId = $(this).data('kseft-id');
            var currentRoleId = getCurrentRole().roleId;
            if (currentRoleId) {
                console.log(`Fetching role confirmation for kseft_id=${kseftId}, role_id=${currentRoleId}`);
                $.post(myTeamPlugin.ajax_url, {
                    action: 'get_role_confirmation',
                    kseft_id: kseftId,
                    role_id: currentRoleId
                }, function(response) {
                    if (response.success) {
                        var data = response.data;
                        var button = $(`.confirm-role-button[data-kseft-id="${kseftId}"]`);
                        button.removeClass('role-confirmation-nepotvrzeno role-confirmation-jdu role-confirmation-zaskok');
                        if (data.role_status === 'Jdu') {
                            button.addClass('role-confirmation-jdu');
                            button.text('Jdu');
                        } else if (data.role_status === 'Záskok') {
                            button.addClass('role-confirmation-zaskok');
                            button.text('Záskok');
                        } else {
                            button.addClass('role-confirmation-nepotvrzeno');
                            button.text('Nepotvrzeno');
                        }
                    } else {
                        console.error('Error fetching role confirmation:', response.error);
                    }
                });
            }
        });
    }

    updateConfirmButtonsForMyKsefty();

    // Předvyplnění hodnoty v roli na přehledu "moje kšefty"
    $('.confirm-role-button').each(function() {
        const currentRole = getCurrentRole();
        if (currentRole.roleId) {
            $(this).data('role-id', currentRole.roleId);
            $(this).data('role-text', currentRole.roleText); // Přidání textu role
        }
    });

    if (kseftId) {
        loadRoleConfirmations(kseftId);
    } else {
        console.error('kseftId is not defined');
    }
});
