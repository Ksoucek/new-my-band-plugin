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
        var pickupLocation = $(this).data('pickup-location');
        $('#role_id').val(roleId);
        $('#role_status').val('Nepotvrzeno');
        $('#role_substitute').val('');
        $('#default_player').val(defaultPlayer);
        $('#pickup_location').val(pickupLocation);
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
        } else if ($(this).val() === 'Jdu') {
            $('#substitute-field').hide();
            $('#pickup-location-field').show();
            $('#default-player-field').show();
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
            post_id: myTeamPlugin.post_id,
            role_id: $('#role_id').val(),
            role_status: roleStatus,
            role_substitute: $('#role_substitute').val(),
            pickup_location: pickupLocation,
            default_player: $('#default_player').val()
        };
        $.post(myTeamPlugin.ajax_url, data, function(response) {
            $('#role-confirmation-modal').hide();
            updateRoleButton(data.role_id, data.role_status);
            updateKseftStatus();
        });
    });

    function updateRoleButton(roleId, roleStatus) {
        var button = $('.role-confirmation[data-role-id="' + roleId + '"]');
        button.removeClass('role-confirmation-nepotvrzeno role-confirmation-jdu role-confirmation-zaskok');
        if (roleStatus === 'Jdu') {
            button.addClass('role-confirmation-jdu');
        } else if (roleStatus === 'Záskok') {
            button.addClass('role-confirmation-zaskok');
        } else {
            button.addClass('role-confirmation-nepotvrzeno');
        }
        button.text(roleStatus);
        $('#role_status').val(roleStatus);
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
});
