jQuery(document).ready(function($) {
    $('#upload_logo_button').on('click', function(e) {
        e.preventDefault();
        var mediaUploader = wp.media({
            title: 'Vyberte logo',
            button: { text: 'Použít logo' },
            multiple: false
        }).on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#invoice_generator_logo').val(attachment.url);
            $('#logo_preview').html('<img src="' + attachment.url + '" alt="Logo" style="max-width: 200px;">');
        }).open();
    });
});
