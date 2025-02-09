jQuery(document).ready(function($) {
    console.log('role-selection.js file loaded');

    // Log the kseft_id to the console
    var kseftId = $('#kseft_id').val();
    console.log('kseft_id:', kseftId);

    function showRoleSelectionModal() {
        $('#role-selection-modal').show();
    }

    function hideRoleSelectionModal() {
        $('#role-selection-modal').hide();
    }

    function setCurrentRole(roleId, roleText) {
        $('#selected-role-display').text(`Zvolená role: ${roleText}`);
        document.cookie = `selectedRoleId=${roleId}; path=/`;
        document.cookie = `selectedRoleText=${roleText}; path=/`;
        filterKseftByRole(roleId);
    }

    function getCurrentRole() {
        const cookies = document.cookie.split(';').reduce((acc, cookie) => {
            const [key, value] = cookie.split('=').map(c => c.trim());
            acc[key] = value;
            return acc;
        }, {});
        return {
            roleId: cookies.selectedRoleId,
            roleText: cookies.selectedRoleText
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
            action: 'get_role_status',
            kseft_id: kseftId,
            role_id: roleId
        }, function(response) {
            if (response.success) {
                var roleStatus = response.data.role_status;
                var button = $(`.confirm-role-button[data-kseft-id="${kseftId}"][data-role-id="${roleId}"]`);
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
            roleId: getCurrentRole().roleId,
            roleText: getCurrentRole().roleText
        };

        const confirmingRoleId = $(this).data('role-id'); // Oprava pro získání role z konkrétního řádku
        const confirmingRoleText = $(this).closest('tr').find('td:first').text(); // Oprava pro získání textu role

        // Výpis do konzole
        console.log('currentRole.roleId:', currentRole.roleId);
        console.log('confirmingRoleId:', confirmingRoleId);

        // Upravená podmínka pro zobrazení hlášky
        if (currentRole.roleId && currentRole.roleId !== 'undefined' && currentRole.roleId != confirmingRoleId) {
            if (!confirm(`Potvrzujete účast za roli "${confirmingRoleText}", ale jste zalogovaný za roli "${currentRole.roleText}". Chcete pokračovat?`)) {
                return;
            }
        }

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
            pickup_location: $('#pickup_location').val()
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
            alert('Prosím vyberte roli. ROLE CONFIRMATION');
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

    // Aktualizace tlačítek na přehledu "moje-ksefty"
    $('#kseft-overview-table tbody tr').each(function() {
        var kseftId = $(this).data('kseft-id');
        var roleIds = $(this).data('role-ids');
        var currentRoleId = window.sessionStorage.getItem('selectedRoleId');
        if (roleIds && roleIds.includes(parseInt(currentRoleId))) {
            updateConfirmButton(kseftId, currentRoleId);
        }
    });

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
    }
});
