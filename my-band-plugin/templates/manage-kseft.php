<?php
/* Template Name: Manage Kseft */

get_header();

if (!is_user_logged_in()) {
    echo '<p>Musíte být přihlášeni, abyste mohli spravovat kšefty.</p>';
    get_footer();
    exit;
}

?>

<div class="wrap">
    <h1>Správa Kšeftů</h1>

    <h2>Přidat nový kšeft</h2>
    <form id="add-kseft-form">
        <label for="kseft_name">Název kšeftu:</label>
        <input type="text" id="kseft_name" name="kseft_name" required>
        <label for="kseft_event_date">Datum kšeftu:</label>
        <input type="date" id="kseft_event_date" name="kseft_event_date" required>
        <label for="kseft_meeting_time">Čas srazu:</label>
        <input type="time" id="kseft_meeting_time" name="kseft_meeting_time" required>
        <label for="kseft_location">Lokace:</label>
        <input type="text" id="kseft_location" name="kseft_location" required>
        <button type="submit" class="button">Přidat kšeft</button>
    </form>

    <h2>Existující kšefty</h2>
    <div id="ksefty-list">
        <?php echo do_shortcode('[display_ksefty]'); ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#add-kseft-form').on('submit', function(e) {
        e.preventDefault();
        var kseftName = $('#kseft_name').val();
        var kseftEventDate = $('#kseft_event_date').val();
        var kseftMeetingTime = $('#kseft_meeting_time').val();
        var kseftLocation = $('#kseft_location').val();

        console.log('Kseft Name:', kseftName);
        console.log('Kseft Event Date:', kseftEventDate);
        console.log('Kseft Meeting Time:', kseftMeetingTime);
        console.log('Kseft Location:', kseftLocation);

        $.post(myTeamPlugin.ajax_url, {
            action: 'my_team_plugin_create_kseft',
            kseft_name: kseftName,
            kseft_event_date: kseftEventDate,
            kseft_meeting_time: kseftMeetingTime,
            kseft_location: kseftLocation
        }, function(response) {
            if (response) {
                alert('Kšeft byl úspěšně přidán.');
                $('#ksefty-list').html(response);
            } else {
                alert('Chyba při přidávání kšeftu.');
            }
        });
    });
});
</script>

<?php
get_footer();
?>
