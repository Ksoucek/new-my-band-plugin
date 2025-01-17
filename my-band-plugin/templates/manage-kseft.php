<?php
/* Template Name: Manage Kseft */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kseft_id = isset($_POST['kseft_id']) ? intval($_POST['kseft_id']) : 0;
    $kseft_name = sanitize_text_field($_POST['kseft_name']);
    $kseft_location = sanitize_text_field($_POST['kseft_location']);
    $kseft_meeting_time = sanitize_text_field($_POST['kseft_meeting_time']);
    $kseft_event_date = sanitize_text_field($_POST['kseft_event_date']);
    $kseft_duration = intval($_POST['kseft_duration']); // Přidání pole pro předpokládanou délku
    $kseft_obsazeni_template = sanitize_text_field($_POST['kseft_obsazeni_template']);
    $kseft_status = sanitize_text_field($_POST['kseft_status']); // Přidání pole pro stav
    $kseft_clothing = sanitize_text_field($_POST['kseft_clothing']); // Přidání pole pro oblečení

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
        update_post_meta($kseft_id, 'kseft_duration', $kseft_duration); // Uložení pole pro předpokládanou délku
        update_post_meta($kseft_id, 'kseft_obsazeni_template', $kseft_obsazeni_template);
        update_post_meta($kseft_id, 'kseft_status', $kseft_status); // Uložení pole pro stav
        update_post_meta($kseft_id, 'kseft_clothing', $kseft_clothing); // Uložení pole pro oblečení
        echo '<p>Kšeft byl úspěšně ' . ($kseft_id ? 'upraven' : 'vytvořen') . '.</p>';
    } else {
        echo '<p>Došlo k chybě při ' . ($kseft_id ? 'úpravě' : 'vytváření') . ' kšeftu.</p>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete_kseft_id'])) {
    $delete_kseft_id = intval($_GET['delete_kseft_id']);
    if (wp_delete_post($delete_kseft_id)) {
        echo '<p>Kšeft byl úspěšně smazán.</p>';
    } else {
        echo '<p>Došlo k chybě při mazání kšeftu.</p>';
    }
}

$kseft_id = isset($_GET['kseft_id']) ? intval($_GET['kseft_id']) : 0;
$kseft = $kseft_id ? get_post($kseft_id) : null;
$kseft_name = $kseft ? $kseft->post_title : '';
$kseft_location = $kseft ? get_post_meta($kseft_id, 'kseft_location', true) : '';
$kseft_meeting_time = $kseft ? get_post_meta($kseft_id, 'kseft_meeting_time', true) : '';
$kseft_event_date = $kseft ? get_post_meta($kseft_id, 'kseft_event_date', true) : '';
$kseft_duration = $kseft ? get_post_meta($kseft_id, 'kseft_duration', true) : ''; // Načtení pole pro předpokládanou délku
$kseft_obsazeni_template = $kseft ? get_post_meta($kseft_id, 'kseft_obsazeni_template', true) : '';
$kseft_status = $kseft ? get_post_meta($kseft_id, 'kseft_status', true) : ''; // Načtení pole pro stav
$kseft_clothing = $kseft ? get_post_meta($kseft_id, 'kseft_clothing', true) : ''; // Načtení pole pro oblečení
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $kseft_id ? 'Upravit Kšeft' : 'Vytvořit Kšeft'; ?></title>
    <?php wp_head(); ?>
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
        .form-group input, .form-group select {
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
        <h1><?php echo $kseft_id ? 'Upravit Kšeft' : 'Vytvořit Kšeft'; ?></h1>
        <form method="POST">
            <input type="hidden" name="kseft_id" value="<?php echo esc_attr($kseft_id); ?>">
            <div class="form-group-inline">
                <div class="form-group">
                    <label for="kseft_name">Název kšeftu:</label>
                    <input type="text" name="kseft_name" id="kseft_name" value="<?php echo esc_attr($kseft_name); ?>" required>
                </div>
                <div class="form-group">
                    <label for="kseft_status">Stav kšeftu:</label>
                    <select name="kseft_status" id="kseft_status">
                        <option value="Rezervace termínu" <?php selected($kseft_status, 'Rezervace termínu'); ?>>Rezervace termínu</option>
                        <option value="Podepsaná smlouva" <?php selected($kseft_status, 'Podepsaná smlouva'); ?>>Podepsaná smlouva</option>
                    </select>
                </div>
            </div>
            <div class="form-group-inline">
                <div class="form-group">
                    <label for="kseft_event_date">Datum kšeftu:</label>
                    <input type="date" name="kseft_event_date" id="kseft_event_date" value="<?php echo esc_attr($kseft_event_date); ?>" required>
                </div>
                <div class="form-group">
                    <label for="kseft_duration">Předpokládaná délka (v hodinách):</label>
                    <input type="number" name="kseft_duration" id="kseft_duration" value="<?php echo esc_attr($kseft_duration); ?>" required>
                </div>
            </div>
            <div class="form-group-inline">
                <div class="form-group">
                    <label for="kseft_meeting_time">Čas srazu:</label>
                    <input type="text" name="kseft_meeting_time" id="kseft_meeting_time" value="<?php echo esc_attr($kseft_meeting_time); ?>" required>
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
            <div id="map-kseft"></div>
            <div class="form-actions">
                <button type="submit" class="button"><?php echo $kseft_id ? 'Upravit Kšeft' : 'Vytvořit Kšeft'; ?></button>
                <a href="<?php echo site_url('/ksefty'); ?>" class="button">Zpět na seznam kšeftů</a>
                <?php if ($kseft_id) : ?>
                    <a href="<?php echo add_query_arg('delete_kseft_id', $kseft_id, site_url('/manage-kseft')); ?>" class="button delete" onclick="return confirm('Opravdu chcete smazat tento kšeft?');">Smazat Kšeft</a>
                <?php endif; ?>
            </div>
        </form>
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

                $('#' + inputId).val(address);
            });
        }

        jQuery(document).ready(function($) {
            initializeAutocomplete('kseft_location', 'map-kseft');
        });
    </script>
</body>
</html>
