jQuery(document).ready(function($) {
    $('.confirm-role-button').on('click', function() {
        var kseftId = $(this).data('kseft-id');
        var roleId = $(this).data('role-id');
        $('#kseft_id').val(kseftId);
        $('#role_id').val(roleId);
        $('#role-confirmation-modal').show();
    });

    $('#role-confirmation-form').on('submit', function(e) {
        e.preventDefault();
        var data = $(this).serialize();
        $.post(myTeamPlugin.ajax_url, data, function(response) {
            if (response.success) {
                alert('Účast byla potvrzena.');
                $('#role-confirmation-modal').hide();
            } else {
                alert('Došlo k chybě při potvrzení účasti.');
            }
        });
    });

    $('#close-modal').on('click', function() {
        $('#role-confirmation-modal').hide();
    });
});
