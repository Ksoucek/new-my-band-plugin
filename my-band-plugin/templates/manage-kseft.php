<?php
// Přidání heslové ochrany stránky pomocí cookie
$correct_password = get_option('my_team_plugin_manage_kseft_password', 'heslo123'); // Heslo nastavené v nastavení pluginu s výchozí hodnotou 'heslo123'
if (!isset($_COOKIE['manage_kseft_access']) || $_COOKIE['manage_kseft_access'] !== md5($correct_password)) {
    if (isset($_POST['manage_kseft_password'])) {
        $user_password = trim($_POST['manage_kseft_password']);
        $expected_password = trim($correct_password);
        error_log('User password: ' . $user_password); // ladící záznam
        error_log('Expected password: ' . $expected_password); // ladící záznam
        if ($user_password === $expected_password) {
            setcookie('manage_kseft_access', md5($expected_password), time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            echo '<p style="color:red;">Nesprávné heslo.</p>';
        }
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="cs">
        <head>
            <meta charset="UTF-8">
            <title>Přihlášení</title>
            <style>
                body {
                    background-color: #f0f0f0;
                    font-family: Arial, sans-serif;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                }
                .login-container {
                    background: #fff;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    text-align: center;
                }
                .login-container h2 {
                    margin-bottom: 20px;
                    color: #0073aa;
                }
                .login-container input[type="password"] {
                    width: 80%;
                    padding: 10px;
                    margin-bottom: 15px;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    font-size: 16px;
                }
                .login-container input[type="submit"] {
                    padding: 10px 20px;
                    background-color: #0073aa;
                    color: #fff;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 16px;
                }
                .login-container input[type="submit"]:hover {
                    background-color: #005177;
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <?php
                if (isset($_POST['manage_kseft_password'])) {
                    echo '<p style="color:red;">Nesprávné heslo.</p>';
                }
                ?>
                <h2>Prosím zadejte heslo</h2>
                <form method="post">
                    <input type="password" name="manage_kseft_password" placeholder="Heslo">
                    <br>
                    <input type="submit" value="Přihlásit">
                </form>
            </div>
        </body>
        </html>
        <?php
    }
    exit;
}
// Nonce kontrola
if (isset($_POST['submit_kseft'])) {
    if (!isset($_POST['kseft_nonce']) || !wp_verify_nonce($_POST['kseft_nonce'], 'save_kseft')) {
        wp_die(__('Neplatný bezpečnostní token.', 'my-band-plugin'));
    }
    // Sanitizace vstupů
    $kseft_name = sanitize_text_field($_POST['kseft_name']);
    $kseft_location = sanitize_text_field($_POST['kseft_location']);
    $kseft_meeting_time = sanitize_text_field($_POST['kseft_meeting_time']);
    $kseft_event_date = sanitize_text_field($_POST['kseft_event_date']);
    $kseft_performance_start = sanitize_text_field($_POST['kseft_performance_start']); // Přidání pole pro začátek vystoupení
    $kseft_performance_end = sanitize_text_field($_POST['kseft_performance_end']); // Přidání pole pro konec vystoupení
    $kseft_obsazeni_template = sanitize_text_field($_POST['kseft_obsazeni_template']);
    $kseft_status = sanitize_text_field($_POST['kseft_status']); // Přidání pole pro stav
    $kseft_clothing = sanitize_text_field($_POST['kseft_clothing']); // Přidání pole pro oblečení
    $kseft_description = sanitize_textarea_field($_POST['kseft_description']); // Přidání pole pro popis
    $kseft_responsible_for_drinks = sanitize_text_field($_POST['kseft_responsible_for_drinks']); // Přidání pole pro odpovědného za pitný režim
}

/* Template Name: Manage Kseft */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kseft_id = isset($_POST['kseft_id']) ? intval($_POST['kseft_id']) : 0;
    $kseft_name = sanitize_text_field($_POST['kseft_name']);
    $kseft_location = sanitize_text_field($_POST['kseft_location']);
    $kseft_meeting_time = sanitize_text_field($_POST['kseft_meeting_time']);
    $kseft_event_date = sanitize_text_field($_POST['kseft_event_date']);
    $kseft_performance_start = sanitize_text_field($_POST['kseft_performance_start']); // Přidání pole pro začátek vystoupení
    $kseft_performance_end = sanitize_text_field($_POST['kseft_performance_end']); // Přidání pole pro konec vystoupení
    $kseft_obsazeni_template = sanitize_text_field($_POST['kseft_obsazeni_template']);
    $kseft_status = sanitize_text_field($_POST['kseft_status']); // Přidání pole pro stav
    $kseft_clothing = sanitize_text_field($_POST['kseft_clothing']); // Přidání pole pro oblečení
    $kseft_description = sanitize_textarea_field($_POST['kseft_description']); // Přidání pole pro popis
    $kseft_responsible_for_drinks = sanitize_text_field($_POST['kseft_responsible_for_drinks']); // Přidání pole pro odpovědného za pitný režim

    $kseft_data = array(
        'post_title' => $kseft_name,
        'post_type' => 'kseft',
        'post_status' => 'publish'
    );

    if ($kseft_id) {
        $kseft_data['ID'] = $kseft_id;
        $kseft_id = wp_update_post($kseft_data);
    } else {
        $kseft_id = wp_insert_post($kseft_data);
    }

    if ($kseft_id) {
        update_post_meta($kseft_id, 'kseft_location', $kseft_location);
        update_post_meta($kseft_id, 'kseft_meeting_time', $kseft_meeting_time);
        update_post_meta($kseft_id, 'kseft_event_date', $kseft_event_date);
        update_post_meta($kseft_id, 'kseft_performance_start', $kseft_performance_start); // Uložení pole pro začátek vystoupení
        update_post_meta($kseft_id, 'kseft_performance_end', $kseft_performance_end); // Uložení pole pro konec vystoupení
        update_post_meta($kseft_id, 'kseft_obsazeni_template', $kseft_obsazeni_template);
        update_post_meta($kseft_id, 'kseft_status', $kseft_status); // Uložení pole pro stav
        update_post_meta($kseft_id, 'kseft_clothing', $kseft_clothing); // Uložení pole pro oblečení
        update_post_meta($kseft_id, 'kseft_description', $kseft_description); // Uložení pole pro popis
        update_post_meta($kseft_id, 'kseft_responsible_for_drinks', $kseft_responsible_for_drinks); // Uložení pole pro odpovědného za pitný režim

        $roles = get_posts(array('post_type' => 'role', 'numberposts' => -1));
        foreach ($roles as $role) {
            $role_id = $role->ID;
            $role_status = isset($_POST['role_status_' . $role_id]) ? sanitize_text_field($_POST['role_status_' . $role_id]) : get_post_meta($kseft_id, 'role_status_' . $role_id, true);
            $role_substitute = isset($_POST['role_substitute_' . $role_id]) ? sanitize_text_field($_POST['role_substitute_' . $role_id]) : get_post_meta($kseft_id, 'role_substitute_' . $role_id, true);
            $pickup_location = isset($_POST['pickup_location_' . $role_id]) ? sanitize_text_field($_POST['pickup_location_' . $role_id]) : get_post_meta($kseft_id, 'pickup_location_' . $role_id, true);
            $pickup_time = isset($_POST['pickup_time_' . $role_id]) ? sanitize_text_field($_POST['pickup_time_' . $role_id]) : get_post_meta($kseft_id, 'pickup_time_' . $role_id, true);
            update_post_meta($kseft_id, 'role_status_' . $role_id, $role_status);
            update_post_meta($kseft_id, 'role_substitute_' . $role_id, $role_substitute);
            update_post_meta($kseft_id, 'pickup_location_' . $role_id, $pickup_location);
            update_post_meta($kseft_id, 'pickup_time_' . $role_id, $pickup_time);
        }

        // Přidání nebo aktualizace Google Kalendář události
        $eventDetails = array(
            'summary' => $kseft_name,
            'location' => $kseft_location,
            'description' => $kseft_description,
            'start' => array(
                'dateTime' => $kseft_event_date . 'T' . ($kseft_performance_start ? $kseft_performance_start : '00:00') . ':00',
                'timeZone' => 'Europe/Prague',
            ),
            'end' => array(
                'dateTime' => $kseft_event_date . 'T' . ($kseft_performance_end ? $kseft_performance_end : '23:59') . ':00',
                'timeZone' => 'Europe/Prague',
            ),
        );

        $google_event_id = get_post_meta($kseft_id, 'google_calendar_event_id', true);
        if ($google_event_id) {
            updateGoogleCalendar($google_event_id, $eventDetails);
        } else {
            createGoogleCalendarEvent($kseft_id, $eventDetails);
        }

        echo '<p>Akce byla úspěšně ' . ($kseft_id ? 'upraven' : 'vytvořen') . '.</p>';
        echo '<script>window.location.href = "' . get_permalink($kseft_id) . '";</script>'; // Přesměrování na kartu kšeftu
    } else {
        echo '<p>Došlo k chybě při ' . ($kseft_id ? 'úpravě' : 'vytváření') . ' Akce.</p>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete_kseft_id'])) {
    $delete_kseft_id = intval($_GET['delete_kseft_id']);
    if (wp_delete_post($delete_kseft_id)) {
        echo '<p>Akce byla úspěšně smazána.</p>';
        echo '<script>window.location.href = "' . site_url('/manage-kseft') . '";</script>'; // Přesměrování na stránku Manage Kseft
    } else {
        echo '<p>Došlo k chybě při mazání Akce.</p>';
    }
}

$kseft_id = isset($_GET['kseft_id']) ? intval($_GET['kseft_id']) : 0;
$copy_kseft_id = isset($_GET['copy_kseft_id']) ? intval($_GET['copy_kseft_id']) : 0;

if ($copy_kseft_id) {
    $kseft = get_post($copy_kseft_id);
    if ($kseft && $kseft->post_type === 'kseft') {
        $kseft_name = $kseft->post_title . ' (Kopie)'; // Nastavení názvu akce
        $kseft_location = get_post_meta($copy_kseft_id, 'kseft_location', true);
        $kseft_meeting_time = get_post_meta($copy_kseft_id, 'kseft_meeting_time', true);
        $kseft_event_date = date('Y-m-d'); // Nastavení data na dnešek
        $kseft_performance_start = get_post_meta($copy_kseft_id, 'kseft_performance_start', true);
        $kseft_performance_end = get_post_meta($copy_kseft_id, 'kseft_performance_end', true);
        $kseft_status = get_post_meta($copy_kseft_id, 'kseft_status', true);
        $kseft_clothing = get_post_meta($copy_kseft_id, 'kseft_clothing', true);
        $kseft_description = get_post_meta($copy_kseft_id, 'kseft_description', true);
        $kseft_responsible_for_drinks = get_post_meta($copy_kseft_id, 'kseft_responsible_for_drinks', true);
        $kseft_obsazeni_template = get_post_meta($copy_kseft_id, 'kseft_obsazeni_template', true);
        $kseft_id = 0; // Nastavení ID na 0, aby se vytvořil nový kšeft
    }
} else {
    $kseft = $kseft_id ? get_post($kseft_id) : null;
    $kseft_name = $kseft ? $kseft->post_title : '';
    $kseft_location = $kseft ? get_post_meta($kseft_id, 'kseft_location', true) : '';
    $kseft_meeting_time = $kseft ? get_post_meta($kseft_id, 'kseft_meeting_time', true) : '';
    $kseft_event_date = $kseft ? get_post_meta($kseft_id, 'kseft_event_date', true) : '';
    $kseft_performance_start = $kseft ? get_post_meta($kseft_id, 'kseft_performance_start', true) : '';
    $kseft_performance_end = $kseft ? get_post_meta($kseft_id, 'kseft_performance_end', true) : '';
    $kseft_obsazeni_template = $kseft ? get_post_meta($kseft_id, 'kseft_obsazeni_template', true) : '';
    $kseft_status = $kseft ? get_post_meta($kseft_id, 'kseft_status', true) : '';
    $kseft_clothing = $kseft ? get_post_meta($kseft_id, 'kseft_clothing', true) : '';
    $google_event_id = get_post_meta($kseft_id, 'google_calendar_event_id', true);
    $kseft_description = $kseft ? get_post_meta($kseft_id, 'kseft_description', true) : '';
    $kseft_responsible_for_drinks = $kseft ? get_post_meta($kseft_id, 'kseft_responsible_for_drinks', true) : '';
}

$roles = get_posts(array('post_type' => 'role', 'numberposts' => -1));
$unique_players = array();
foreach ($roles as $role) {
    $default_player = get_post_meta($role->ID, 'role_default_player', true);
    if (!in_array($default_player, $unique_players)) {
        $unique_players[] = $default_player;
    }
}

$meta = get_post_meta($kseft_id);
foreach ($roles as $role) {
    $role_id = $role->ID;
    $role_status_key = 'role_status_' . $role_id;
    $role_substitute_key = 'role_substitute_' . $role_id;
    $pickup_location_key = 'pickup_location_' . $role_id;
    $pickup_time_key = 'pickup_time_' . $role_id;

    $role_status = isset($meta[$role_status_key]) ? $meta[$role_status_key][0] : '';
    $role_substitute = isset($meta[$role_substitute_key]) ? $meta[$role_substitute_key][0] : '';
    $pickup_location = isset($meta[$pickup_location_key]) ? $meta[$pickup_location_key][0] : '';
    $pickup_time = isset($meta[$pickup_time_key]) ? $meta[$pickup_time_key][0] : '';
}

if (!$kseft_id) {
    $google_event_id = ''; // Nastavení prázdného ID pro nové kšefty
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $kseft_id ? 'Upravit Akci' : 'Vytvořit Akci'; ?></title>
    <?php wp_head(); ?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #0073aa;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .form-group-inline {
            display: flex;
            justify-content: space-between;
        }
        .form-group-inline .form-group {
            flex: 1;
            margin-right: 10px;
        }
        .form-group-inline .form-group:last-child {
            margin-right: 0;
        }
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        .form-actions .button {
            padding: 10px 20px;
            text-decoration: none;
            color: white;
            background-color: #0073aa;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .form-actions .button:hover {
            background-color: #005177;
        }
        .form-actions .button.delete {
            background-color: #d9534f;
        }
        .form-actions .button.delete:hover {
            background-color: #c9302c;
        }
        #map-kseft {
            width: 100%;
            height: 400px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $kseft_id ? 'Upravit Akci' : 'Vytvořit Akci'; ?></h1>
        <form method="POST" id="manage-kseft-form">
            <input type="hidden" name="kseft_id" id="kseft_id" value="<?php echo esc_attr($kseft_id); ?>">
            <input type="hidden" name="google_calendar_event_id" value="<?php echo esc_attr($google_event_id); ?>">
            <div class="form-group-inline">
                <div class="form-group">
                    <label for="kseft_name">Název Akce:</label>
                    <input type="text" name="kseft_name" id="kseft_name" value="<?php echo esc_attr($kseft_name); ?>" required>
                </div>
                <div class="form-group">
                    <label for="kseft_status">Stav Akce:</label>
                    <select name="kseft_status" id="kseft_status">
                        <option value="Rezervace termínu" <?php selected($kseft_status, 'Rezervace termínu'); ?>>Rezervace termínu</option>
                        <option value="Podepsaná smlouva" <?php selected($kseft_status, 'Podepsaná smlouva'); ?>>Podepsaná smlouva</option>
                    </select>
                </div>
            </div>
            <div class="form-group-inline">
                <div class="form-group">
                    <label for="kseft_event_date">Datum Akce:</label>
                    <input type="date" name="kseft_event_date" id="kseft_event_date" value="<?php echo esc_attr($kseft_event_date); ?>" required>
                </div>
                <div class="form-group">
                    <label for="kseft_performance_start">Začátek vystoupení:</label>
                    <input type="time" name="kseft_performance_start" id="kseft_performance_start" value="<?php echo esc_attr($kseft_performance_start); ?>" required> <!-- Přidání pole pro začátek vystoupení -->
                </div>
                <div class="form-group">
                    <label for="kseft_performance_end">Konec vystoupení:</label>
                    <input type="time" name="kseft_performance_end" id="kseft_performance_end" value="<?php echo esc_attr($kseft_performance_end); ?>" required> <!-- Přidání pole pro konec vystoupení -->
                </div>
            </div>
            <div class="form-group-inline">
                <div class="form-group">
                    <label for="kseft_meeting_time">Čas srazu:</label>
                    <input type="text" name="kseft_meeting_time" id="kseft_meeting_time" value="<?php echo esc_attr($kseft_meeting_time); ?>">
                </div>
                <div class="form-group">
                    <label for="kseft_obsazeni_template">Šablona obsazení:</label>
                    <select name="kseft_obsazeni_template" id="kseft_obsazeni_template">
                        <option value="">-- Vyberte šablonu --</option>
                        <?php
                        $templates = get_posts(array('post_type' => 'obsazeni_template', 'numberposts' => -1));
                        foreach ($templates as $template) {
                            echo '<option value="' . $template->ID . '"' . selected($kseft_obsazeni_template, $template->ID, false) . '>' . $template->post_title . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="form-group-inline">
                <div class="form-group">
                    <label for="kseft_location">Lokace (Google Maps URL):</label>
                    <input type="text" name="kseft_location" id="kseft_location" value="<?php echo esc_attr($kseft_location); ?>" required>
                </div>
                <div class="form-group">
                    <label for="kseft_clothing">Oblečení:</label>
                    <select name="kseft_clothing" id="kseft_clothing">
                        <option value="krojová košile" <?php selected($kseft_clothing, 'krojová košile'); ?>>Krojová košile</option>
                        <option value="společenská košile" <?php selected($kseft_clothing, 'společenská košile'); ?>>Společenská košile</option>
                        <option value="Tmavý civil" <?php selected($kseft_clothing, 'Tmavý civil'); ?>>Tmavý civil</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="kseft_description">Popis <span title="Poznámky - např. doprava, instrukce atd.">(?)</span>:</label>
                <textarea name="kseft_description" id="kseft_description" rows="4"><?php echo esc_textarea($kseft_description); ?></textarea>
            </div>
            <div class="form-group">
                <label for="kseft_responsible_for_drinks">Odpovědný za pitný režim:</label>
                <select name="kseft_responsible_for_drinks" id="kseft_responsible_for_drinks">
                    <?php foreach ($unique_players as $player) : ?>
                        <option value="<?php echo esc_attr($player); ?>" <?php selected($kseft_responsible_for_drinks, $player); ?>><?php echo esc_html($player); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="map" style="width: 100%; height: 400px;"></div> <!-- Přidání ID "map" -->
            <div class="form-actions">
                <button type="submit" class="button" id="submit-kseft"><?php echo ($kseft_id && !$copy_kseft_id) ? 'Upravit akci' : 'Vytvořit akci'; ?></button>
                <a href="<?php echo site_url('/ksefty'); ?>" class="button">Zpět na seznam Akcí</a>
                <?php if ($kseft_id && !$copy_kseft_id) : ?>
                    <a href="<?php echo add_query_arg('delete_kseft_id', $kseft_id, site_url('/manage-kseft')); ?>" class="button delete" onclick="return confirm('Opravdu chcete smazat tuto akci?');">Smazat Akci</a>
                <?php endif; ?>
                <button type="button" class="button" id="add-transport-to-description">Přidat dopravu do popisu</button> <!-- Přidání tlačítka pro přidání dopravy do popisu -->
            </div>
        </form>
        <?php if ($google_event_id) : ?>
            <p>Google Calendar Event ID: <?php echo $google_event_id; ?></p>
        <?php endif; ?>
    </div>
    <?php wp_footer(); ?>
    <script>
        function initializeAutocomplete(inputId, mapId) {
            var input = document.getElementById(inputId);
            var autocomplete = new google.maps.places.Autocomplete(input);
            var map = new google.maps.Map(document.getElementById(mapId), {
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

                if (place.geometry.viewport) {
                    map.fitBounds(place.geometry.viewport);
                } else {
                    map.setCenter(place.geometry.location);
                    map.setZoom(17);
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

                jQuery('#' + inputId).val(address); // Změna $ na jQuery
            });
        }

        jQuery(document).ready(function($) {
            initializeAutocomplete('kseft_location', 'map'); // Změna ID mapy na "map"

            $('#update-google-event').on('click', function() {
                var kseftId = $('input[name="kseft_id"]').val();
                var kseftName = $('input[name="kseft_name"]').val();
                var kseftLocation = $('input[name="kseft_location"]').val();
                var kseftMeetingTime = $('input[name="kseft_meeting_time"]').val();
                var kseftEventDate = $('input[name="kseft_event_date"]').val();
                var kseftStartTime = $('input[name="kseft_performance_start"]').val(); // Přidání pole pro začátek vystoupení
                var kseftEndTime = $('input[name="kseft_performance_end"]').val(); // Přidání pole pro konec vystoupení
                var kseftStatus = $('select[name="kseft_status"]').val();
                var kseftDescription = $('textarea[name="kseft_description"]').val();

                var eventDetails = {
                    summary: kseftName,
                    location: kseftLocation,
                    description: kseftDescription,
                    start: {
                        dateTime: kseftEventDate + 'T' + kseftStartTime + ':00',
                        timeZone: 'Europe/Prague'
                    },
                    end: {
                        dateTime: kseftEventDate + 'T' + kseftEndTime + ':00',
                        timeZone: 'Europe/Prague'
                    }
                };

                var googleEventId = $('input[name="google_calendar_event_id"]').val();
                if (googleEventId) {
                    $.post(myTeamPlugin.ajax_url, {
                        action: 'update_google_calendar_event',
                        event_id: googleEventId,
                        event_details: eventDetails,
                        kseft_id: kseftId // Přidání parametru kseft_id
                    }, function(response) {
                        if (response.success) {
                            alert('Google Calendar event updated successfully.');
                        } else {
                            alert('Error updating Google Calendar event: ' + response.error);
                        }
                    }).fail(function(xhr, status, error) {
                        alert('AJAX error: ' + status + ' ' + error);
                    });
                } else {
                    alert('Google Calendar event ID not found.');
                }
            });

            $('#submit-kseft').on('click', function(e) {
                e.preventDefault();
                var kseftId = $('#kseft_id').val();
                if (kseftId == 0) {
                    $('#submit-kseft').text('Vytvořit akci');
                }
                $('#manage-kseft-form').submit();
                $('#update-google-event').click();
            });

            $('#add-transport-to-description').on('click', function() {
                var transportText = 'Doprava:\n';
                var transportMap = {};

                <?php foreach ($roles as $role) : ?>
                    var roleId = <?php echo $role->ID; ?>;
                    var transport = '<?php echo get_post_meta($kseft_id, 'transport_' . $role->ID, true); ?>';
                    var playerName = '<?php echo get_post_meta($role->ID, 'role_default_player', true); ?>';
                    var pickupTime = '<?php echo get_post_meta($kseft_id, 'pickup_time_' . $role->ID, true); ?>';
                    var pickupLocation = '<?php echo get_post_meta($kseft_id, 'pickup_location_' . $role->ID, true); ?>';

                    if (transport) {
                        if (!transportMap[transport]) {
                            transportMap[transport] = [];
                        }
                        var playerInfo = playerName;
                        if (pickupTime || pickupLocation) {
                            playerInfo += ' (';
                            if (pickupTime) {
                                playerInfo += pickupTime;
                            }
                            if (pickupTime && pickupLocation) {
                                playerInfo += ', ';
                            }
                            if (pickupLocation) {
                                playerInfo += pickupLocation;
                            }
                            playerInfo += ')';
                        }
                        transportMap[transport].push(playerInfo);
                    }
                <?php endforeach; ?>

                for (var transport in transportMap) {
                    if (transportMap[transport].length > 0) {
                        transportText += transport + ' - ' + transportMap[transport].join(', ') + '\n';
                    }
                }

                var currentDescription = $('#kseft_description').val();
                $('#kseft_description').val(currentDescription + '\n' + transportText);
            });
        });
    </script>
</body>
</html>
