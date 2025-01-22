jQuery(document).ready(function ($) {
    console.log('JavaScript file loaded');

    // === Function Definitions ===
    const filterKseftByRole = (roleId) => {
        $.post(myTeamPlugin.ajax_url, {
            action: 'get_ksefty_by_role',
            role_id: roleId
        }, function (response) {
            if (response.success) {
                const kseftIds = response.data;
                $('#kseft-overview-table tbody tr').each(function () {
                    const kseftId = $(this).data('kseft-id');
                    if (kseftIds.includes(kseftId)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
                updateRoleButtonColors();
            } else {
                console.error('Error fetching ksefty by role:', response.error);
            }
        });
    };

    const showModal = (modalId) => {
        if ($(modalId).length) {
            $(modalId).css('display', 'block');
            if (modalId === '#role-confirmation-modal') {
                initializeMap();
            }
        } else {
            console.error('Modal not found:', modalId);
        }
    };

    const closeModal = (modalId) => {
        $(modalId).css('display', 'none');
    };

    const initializeMap = () => {
        const mapElement = document.getElementById('map');
        if (mapElement) {
            const map = new google.maps.Map(mapElement, {
                center: { lat: -34.397, lng: 150.644 },
                zoom: 8
            });
            const input = document.getElementById('pickup_location');
            const autocomplete = new google.maps.places.Autocomplete(input);
            autocomplete.bindTo('bounds', map);
        }
    };

    // === Modal Handling ===
    $('#close-modal').on('click', function () {
        closeModal('#role-confirmation-modal');
    });

    // Show role selection modal on page load if no role is selected
    if (!sessionStorage.getItem('selectedRoleId')) {
        showModal('#role-selection-modal');
    } else {
        const selectedRoleId = sessionStorage.getItem('selectedRoleId');
        const selectedRoleText = sessionStorage.getItem('selectedRoleText');
        $('#role_id').val(selectedRoleId);
        $('#selected-role-display').text(`Zvolená role: ${selectedRoleText}`);
        filterKseftByRole(selectedRoleId);
    }

    // Allow changing role by clicking on the selected role display
    $('#selected-role-display').on('click', function () {
        showModal('#role-selection-modal');
    });

    // === Form Handling ===
    const handleRoleSelection = () => {
        $('#role-selection-form').on('submit', function (e) {
            e.preventDefault();
            const selectedRoleId = $('#initial_role_id').val();
            const selectedRoleText = $('#initial_role_id option:selected').text();
            if (selectedRoleId) {
                $('#role_id').val(selectedRoleId);
                $('#selected-role-display').text(`Zvolená role: ${selectedRoleText}`);
                sessionStorage.setItem('selectedRoleId', selectedRoleId);
                sessionStorage.setItem('selectedRoleText', selectedRoleText);
                closeModal('#role-selection-modal');
                location.reload(); // Refresh the page to update the data
            } else {
                alert('Prosím vyberte roli.');
            }
        });
    };

    const handleRoleConfirmation = () => {
        $('.confirm-role-button').on('click', function () {
            const kseftId = $(this).data('kseft-id');
            const roleId = $('#role_id').val();
            if (!roleId) {
                alert('Prosím vyberte roli.');
                return;
            }
            $('#kseft_id').val(kseftId);
            $('#role_id').val(roleId);
            $('#role_status').val('Nepotvrzeno');
            $('#role_substitute').val('');
            $('#pickup_location').val('');
            $.post(myTeamPlugin.ajax_url, {
                action: 'get_role_details',
                role_id: roleId
            }, function (response) {
                if (response.success) {
                    const roleDetails = response.data;
                    $('#default_player').val(roleDetails.default_player);
                    $('#pickup_location').val(roleDetails.default_pickup_location);
                } else {
                    console.error('Error fetching role details:', response.error);
                }
            });
            showModal('#role-confirmation-modal');
        });
    };

    const handleRoleStatusChange = () => {
        $('#role_status').on('change', function () {
            const status = $(this).val();
            if (status === 'Záskok') {
                $('#substitute-field, #pickup-location-field').show();
                $('#default-player-field').hide();
            } else if (status === 'Jdu') {
                $('#substitute-field').hide();
                $('#pickup-location-field').show();
                $('#default-player-field').show();
                const defaultPlayer = $('#default_player').val();
                $('#default_player').val(defaultPlayer);
            } else {
                $('#substitute-field, #pickup-location-field, #default-player-field').hide();
            }
        });
    };

    const handleRoleConfirmationSubmit = () => {
        $('#role-confirmation-form').on('submit', function (e) {
            e.preventDefault();
            const roleStatus = $('#role_status').val();
            const pickupLocation = $('#pickup_location').val();
            if ((roleStatus === 'Jdu' || roleStatus === 'Záskok') && !pickupLocation) {
                alert('Prosím vyplňte Místo vyzvednutí.');
                return;
            }
            const data = {
                action: 'save_role_confirmation',
                post_id: $('#kseft_id').val(),
                role_id: $('#role_id').val(),
                role_status: roleStatus,
                role_substitute: $('#role_substitute').val(),
                pickup_location: pickupLocation,
                default_player: $('#default_player').val()
            };
            $.post(myTeamPlugin.ajax_url, data, function (response) {
                closeModal('#role-confirmation-modal');
                location.reload();
            });
        });
    };

    const updateRoleButtonColors = () => {
        $('.confirm-role-button').each(function () {
            const kseftId = $(this).data('kseft-id');
            const roleId = $('#role_id').val();
            $.post(myTeamPlugin.ajax_url, {
                action: 'get_role_status',
                kseft_id: kseftId,
                role_id: roleId
            }, function (response) {
                if (response.success) {
                    const roleStatus = response.data.role_status;
                    if (roleStatus === 'Jdu') {
                        $(this).addClass('role-confirmation-jdu');
                    } else if (roleStatus === 'Záskok') {
                        $(this).addClass('role-confirmation-zaskok');
                    } else {
                        $(this).addClass('role-confirmation-nepotvrzeno');
                    }
                } else {
                    console.error('Error fetching role status:', response.error);
                }
            }.bind(this));
        });
    };

    // Close modal on clicking the close button
    $('#role-confirmation-modal .button').on('click', function () {
        closeModal('#role-confirmation-modal');
    });

    // === Initialize ===
    handleRoleSelection();
    handleRoleConfirmation();
    handleRoleStatusChange();
    handleRoleConfirmationSubmit();
    updateRoleButtonColors();
});

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const fromParam = urlParams.get('from');
    console.log('fromParam:', fromParam); // Výpis parametru from do konzole
    const backLink = document.querySelector('.kseft-navigation a.button:nth-child(2)');
    const editButton = document.querySelector('a.button[href*="manage-kseft"]');
    const addToCalendarButton = document.getElementById('add-to-calendar-button');

    if (fromParam === 'moje-ksefty') {
        backLink.href = myTeamPlugin.site_url + '/moje-ksefty';
        if (editButton) {
            editButton.style.display = 'none';
        }
        if (addToCalendarButton) {
            addToCalendarButton.style.display = 'none';
        }
    } else {
        backLink.href = myTeamPlugin.site_url + '/ksefty';
    }
});