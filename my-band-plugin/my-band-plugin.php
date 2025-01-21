<?php
/*
Plugin Name: Muzikantské kšefty
Description: Umožňuje uživatelům vytvářet a spravovat týmy, přidávat členy a plánovat události.
Version: 1.0
Author: Vaše Jméno
*/

error_log('Muzikantské kšefty plugin loaded');

$autoload_path = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path; // Načtení knihovny Google API PHP Client
}

require_once plugin_dir_path(__FILE__) . 'includes/transport-optimization.php';
require_once plugin_dir_path(__FILE__) . 'includes/google-calendar.php'; // Načtení souboru pro Google Kalendář

function my_team_plugin_enqueue_scripts() {
    wp_enqueue_script('my-team-plugin-script', plugins_url('/js/my-team-plugin.js', __FILE__), array('jquery'), '1.0', true);
    wp_localize_script('my-team-plugin-script', 'myTeamPlugin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'post_id' => get_the_ID(), // Přidání post_id do lokalizovaného skriptu
        'api_key' => get_option('my_team_plugin_openrouteservice_api_key'), // Přidání API klíče do lokalizovaného skriptu
        'rest_url' => rest_url('google-calendar/v1/add-to-calendar'), // Přidání REST URL do lokalizovaného skriptu
        'nonce' => wp_create_nonce('wp_rest') // Přidání nonce do lokalizovaného skriptu
     ));
    wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . get_option('my_team_plugin_google_maps_api_key') . '&libraries=places', null, null, true);
    wp_enqueue_style('my-team-plugin-style', plugins_url('/css/my-team-plugin.css', __FILE__));
    wp_enqueue_style('my-team-plugin-responsive-style', plugins_url('/css/my-team-plugin-responsive.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'my_team_plugin_enqueue_scripts');

function my_team_plugin_create_kseft() {
    $kseft_name = sanitize_text_field($_POST['kseft_name']);
    $kseft_id = wp_insert_post(array(
        'post_title' => $kseft_name,
        'post_type' => 'kseft',
        'post_status' => 'publish'
    ));
    echo $kseft_id;
    wp_die();
}
add_action('wp_ajax_my_team_plugin_create_kseft', 'my_team_plugin_create_kseft');
add_action('wp_ajax_nopriv_my_team_plugin_create_kseft', 'my_team_plugin_create_kseft');

function my_team_plugin_add_member() {
    $kseft_id = intval($_POST['kseft_id']);
    $member_name = sanitize_text_field($_POST['member_name']);
    add_post_meta($kseft_id, 'kseft_member', $member_name);
    echo 'Member added';
    wp_die();
}
add_action('wp_ajax_my_team_plugin_add_member', 'my_team_plugin_add_member');
add_action('wp_ajax_nopriv_my_team_plugin_add_member', 'my_team_plugin_add_member');

function my_team_plugin_schedule_event() {
    $kseft_id = intval($_POST['kseft_id']);
    $event_name = sanitize_text_field($_POST['event_name']);
    $event_date = sanitize_text_field($_POST['event_date']);
    add_post_meta($kseft_id, 'kseft_event', array('name' => $event_name, 'date' => $event_date));
    echo 'Event scheduled';
    wp_die();
}
add_action('wp_ajax_my_team_plugin_schedule_event', 'my_team_plugin_schedule_event');
add_action('wp_ajax_nopriv_my_team_plugin_schedule_event', 'my_team_plugin_schedule_event');

function my_team_plugin_register_post_types() {
    // Registrace post typu 'kseft'
    register_post_type('kseft', array(
        'labels' => array(
            'name' => __('Kšefty', 'textdomain'),
            'singular_name' => __('Kšeft', 'textdomain'),
            'menu_name' => __('Kšefty', 'textdomain'),
            'name_admin_bar' => __('Kšeft', 'textdomain'),
            'add_new' => __('Přidat nový', 'textdomain'),
            'add_new_item' => __('Přidat nový kšeft', 'textdomain'),
            'new_item' => __('Nový kšeft', 'textdomain'),
            'edit_item' => __('Upravit kšeft', 'textdomain'),
            'view_item' => __('Zobrazit kšeft', 'textdomain'),
            'all_items' => __('Všechny kšefty', 'textdomain'),
            'search_items' => __('Hledat kšefty', 'textdomain'),
            'parent_item_colon' => __('Nadřazený kšeft:', 'textdomain'),
            'not_found' => __('Žádné kšefty nenalezeny.', 'textdomain'),
            'not_found_in_trash' => __('Žádné kšefty v koši.', 'textdomain')
        ),
        'public' => true,
        'has_archive' => true,
        'show_in_menu' => true,
        'show_ui' => true,
        'rewrite' => array('slug' => 'kseft'),
        'supports' => array('title', 'editor'),
        'menu_icon' => 'dashicons-calendar-alt' // Přidání ikony pro 'kseft'
    ));

    // Registrace post typu 'obsazeni_template'
    register_post_type('obsazeni_template', array(
        'labels' => array(
            'name' => __('Šablony obsazení', 'textdomain'),
            'singular_name' => __('Šablona obsazení', 'textdomain'),
            'menu_name' => __('Šablony obsazení', 'textdomain'),
            'name_admin_bar' => __('Šablona obsazení', 'textdomain'),
            'add_new' => __('Přidat novou', 'textdomain'),
            'add_new_item' => __('Přidat novou šablonu obsazení', 'textdomain'),
            'new_item' => __('Nová šablona obsazení', 'textdomain'),
            'edit_item' => __('Upravit šablonu obsazení', 'textdomain'),
            'view_item' => __('Zobrazit šablonu obsazení', 'textdomain'),
            'all_items' => __('Všechny šablony obsazení', 'textdomain'),
            'search_items' => __('Hledat šablony obsazení', 'textdomain'),
            'parent_item_colon' => __('Nadřazená šablona obsazení:', 'textdomain'),
            'not_found' => __('Žádné šablony obsazení nenalezeny.', 'textdomain'),
            'not_found_in_trash' => __('Žádné šablony obsazení v koši.', 'textdomain')
        ),
        'public' => true,
        'has_archive' => true,
        'show_in_menu' => true,
        'show_ui' => true,
        'rewrite' => array('slug' => 'obsazeni-template'),
        'supports' => array('title', 'editor'),
        'menu_icon' => 'dashicons-clipboard' // Přidání ikony pro 'obsazeni_template'
    ));

    // Registrace post typu 'role'
    register_post_type('role', array(
        'labels' => array(
            'name' => __('Role', 'textdomain'),
            'singular_name' => __('Role', 'textdomain'),
            'menu_name' => __('Role', 'textdomain'),
            'name_admin_bar' => __('Role', 'textdomain'),
            'add_new' => __('Přidat novou', 'textdomain'),
            'add_new_item' => __('Přidat novou roli', 'textdomain'),
            'new_item' => __('Nová role', 'textdomain'),
            'edit_item' => __('Upravit roli', 'textdomain'),
            'view_item' => __('Zobrazit roli', 'textdomain'),
            'all_items' => __('Všechny role', 'textdomain'),
            'search_items' => __('Hledat role', 'textdomain'),
            'parent_item_colon' => __('Nadřazená role:', 'textdomain'),
            'not_found' => __('Žádné role nenalezeny.', 'textdomain'),
            'not_found_in_trash' => __('Žádné role v koši.', 'textdomain')
        ),
        'public' => true,
        'has_archive' => true,
        'show_in_menu' => true,
        'show_ui' => true,
        'rewrite' => array('slug' => 'role'),
        'supports' => array('title', 'editor'),
        'menu_icon' => 'dashicons-groups' // Přidání ikony pro 'role'
    ));

    // Registrace post typu 'auta'
    register_post_type('auta', array(
        'labels' => array(
            'name' => __('Auta', 'textdomain'),
            'singular_name' => __('Auto', 'textdomain'),
            'menu_name' => __('Auta', 'textdomain'),
            'name_admin_bar' => __('Auto', 'textdomain'),
            'add_new' => __('Přidat nové', 'textdomain'),
            'add_new_item' => __('Přidat nové auto', 'textdomain'),
            'new_item' => __('Nové auto', 'textdomain'),
            'edit_item' => __('Upravit auto', 'textdomain'),
            'view_item' => __('Zobrazit auto', 'textdomain'),
            'all_items' => __('Všechna auta', 'textdomain'),
            'search_items' => __('Hledat auta', 'textdomain'),
            'parent_item_colon' => __('Nadřazené auto:', 'textdomain'),
            'not_found' => __('Žádná auta nenalezena.', 'textdomain'),
            'not_found_in_trash' => __('Žádná auta v koši.', 'textdomain')
        ),
        'public' => true,
        'has_archive' => true,
        'show_in_menu' => true,
        'show_ui' => true,
        'rewrite' => array('slug' => 'auta'),
        'supports' => array('title', 'editor'),
        'menu_icon' => 'dashicons-car' // Přidání ikony pro 'auta'
    ));
}
add_action('init', 'my_team_plugin_register_post_types');

// Přidání metaboxu pro výchozího hráče a výchozí místo vyzvednutí při tvorbě role
function my_team_plugin_add_role_meta_boxes() {
    add_meta_box('role_default_player', 'Výchozí hráč', 'my_team_plugin_render_role_default_player_meta_box', 'role', 'normal', 'high');
    add_meta_box('role_default_pickup_location', 'Výchozí místo vyzvednutí', 'my_team_plugin_render_role_default_pickup_location_meta_box', 'role', 'normal', 'high');
}
add_action('add_meta_boxes', 'my_team_plugin_add_role_meta_boxes');

function my_team_plugin_render_role_default_player_meta_box($post) {
    $default_player = get_post_meta($post->ID, 'role_default_player', true);
    ?>
    <label for="role_default_player">Výchozí hráč:</label>
    <input type="text" name="role_default_player" id="role_default_player" value="<?php echo esc_attr($default_player); ?>" size="25" />
    <?php
}

function my_team_plugin_render_role_default_pickup_location_meta_box($post) {
    $default_pickup_location = get_post_meta($post->ID, 'role_default_pickup_location', true);
    ?>
    <label for="role_default_pickup_location">Výchozí místo vyzvednutí:</label>
    <input type="text" name="role_default_pickup_location" id="role_default_pickup_location" value="<?php echo esc_attr($default_pickup_location); ?>" size="25" />
    <?php
}

function my_team_plugin_save_role_meta_box_data($post_id) {
    if (array_key_exists('role_default_player', $_POST)) {
        update_post_meta($post_id, 'role_default_player', sanitize_text_field($_POST['role_default_player']));
    }
    if (array_key_exists('role_default_pickup_location', $_POST)) {
        update_post_meta($post_id, 'role_default_pickup_location', sanitize_text_field($_POST['role_default_pickup_location']));
    }
}
add_action('save_post', 'my_team_plugin_save_role_meta_box_data');

function my_team_plugin_display_ksefty() {
    error_log('my_team_plugin_display_ksefty function called');
    $args = array(
        'post_type' => 'kseft',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_key' => 'kseft_event_date',
        'orderby' => 'meta_value',
        'order' => 'ASC'
    );
    $ksefty = new WP_Query($args);
    error_log('Query executed: ' . print_r($args, true));
    $output = '<div class="business-overview">';
    $output .= '<a href="' . site_url('/manage-kseft') . '" class="button">Vytvořit nový kšeft</a>'; // Přesunutí tlačítka nahoru
    if ($ksefty->have_posts()) {
        $output .= '<table>';
        $output .= '<thead><tr><th>Termín</th><th>Název</th><th>Umístění</th><th>Stav obsazení</th><th>Stav</th></thead>';
        $output .= '<tbody>';
        while ($ksefty->have_posts()) {
            $ksefty->the_post();
            $event_date = get_post_meta(get_the_ID(), 'kseft_event_date', true);
            $location = get_post_meta(get_the_ID(), 'kseft_location', true);
            $status = get_post_meta(get_the_ID(), 'kseft_status', true);
            $obsazeni_template_id = get_post_meta(get_the_ID(), 'kseft_obsazeni_template', true);
            $roles = get_post_meta($obsazeni_template_id, 'obsazeni_template_roles', true);
            $all_confirmed = true;
            $has_substitute = false;
            if ($roles) {
                foreach ($roles as $role_id) {
                    $role_status = get_post_meta(get_the_ID(), 'role_status_' . $role_id, true);
                    if ($role_status === 'Záskok') {
                        $has_substitute = true;
                    }
                    if ($role_status !== 'Jdu' && $role_status !== 'Záskok') {
                        $all_confirmed = false;
                        break;
                    }
                }
            } else {
                $all_confirmed = false;
            }
            if ($all_confirmed) {
                $obsazeni_class = 'obsazeno';
                $obsazeni_text = $has_substitute ? 'Obsazeno se záskokem' : 'Obsazeno';
            } else {
                $obsazeni_class = 'neobsazeno';
                $obsazeni_text = 'Neobsazeno';
            }
            $formatted_date = date_i18n('D d.m.Y', strtotime($event_date));
            $output .= '<tr>';
            $output .= '<td><a href="' . get_permalink() . '">' . esc_html($formatted_date) . '</a></td>'; // Přidání odkazu na termín
            $output .= '<td><a href="' . get_permalink() . '">' . get_the_title() . '</a></td>';
            $output .= '<td><a href="' . get_permalink() . '">' . esc_html($location) . '</a></td>';
            $output .= '<td><a href="' . get_permalink() . '" class="button kseft-status-button ' . esc_attr($obsazeni_class) . '">' . esc_html($obsazeni_text) . '</a></td>';
            $output .= '<td><a href="' . get_permalink() . '">' . esc_html($status) . '</a></td>'; // Přidání odkazu na stav
            $output .= '</tr>';
        }
        $output .= '</tbody>';
        $output .= '</table>';
        wp_reset_postdata();
    } else {
        $output .= '<p>Žádné kšefty nejsou k dispozici.</p>';
    }
    $output .= '</div>';
    error_log('Output: ' . $output);
    return $output;
}
add_shortcode('display_ksefty', 'my_team_plugin_display_ksefty');

function my_team_plugin_test_shortcode() {
    return 'Test shortcode works!';
}
add_shortcode('test_shortcode', 'my_team_plugin_test_shortcode');

function my_team_plugin_add_meta_boxes() {
    add_meta_box('kseft_details', 'Kšeft Details', 'my_team_plugin_render_meta_box', 'kseft', 'normal', 'high');
}
add_action('add_meta_boxes', 'my_team_plugin_add_meta_boxes');

function my_team_plugin_render_meta_box($post) {
    $location = get_post_meta($post->ID, 'kseft_location', true);
    $meeting_time = get_post_meta($post->ID, 'kseft_meeting_time', true);
    $event_date = get_post_meta($post->ID, 'kseft_event_date', true);
    $kseft_duration = get_post_meta($post->ID, 'kseft_duration', true); // Přidání pole pro předpokládanou délku
    $status = get_post_meta($post->ID, 'kseft_status', true); // Přidání pole pro stav
    $clothing = get_post_meta($post->ID, 'kseft_clothing', true); // Přidání pole pro oblečení
    ?>
    <label for="kseft_location">Lokace (Google Maps URL):</label>
    <input type="text" name="kseft_location" id="kseft_location_wp" value="<?php echo esc_attr($location); ?>" size="25" />
    <div id="map-kseft-wp"></div> <!-- Změna ID elementu mapy -->
    <br><br>
    <label for="kseft_meeting_time">Čas srazu:</label>
    <input type="text" name="kseft_meeting_time" id="kseft_meeting_time" value="<?php echo esc_attr($meeting_time); ?>" size="25" />
    <br><br>
    <div style="display: flex; justify-content: space-between;">
        <div style="flex: 1; margin-right: 10px;">
            <label for="kseft_event_date">Datum kšeftu:</label>
            <input type="date" name="kseft_event_date" id="kseft_event_date" value="<?php echo esc_attr($event_date); ?>" size="25" />
        </div>
        <div style="flex: 1;">
            <label for="kseft_duration">Předpokládaná délka (v hodinách):</label>
            <input type="number" name="kseft_duration" id="kseft_duration" value="<?php echo esc_attr($kseft_duration); ?>" size="25" />
        </div>
    </div>
    <br><br>
    <label for="kseft_status">Stav kšeftu:</label>
    <select name="kseft_status" id="kseft_status">
        <option value="Rezervace termínu" <?php selected($status, 'Rezervace termínu'); ?>>Rezervace termínu</option>
        <option value="Podepsaná smlouva" <?php selected($status, 'Podepsaná smlouva'); ?>>Podepsaná smlouva</option>
    </select>
    <br><br>
    <label for="kseft_clothing">Oblečení:</label>
    <select name="kseft_clothing" id="kseft_clothing">
        <option value="krojová košile" <?php selected($clothing, 'krojová košile'); ?>>Krojová košile</option>
        <option value="společenská košile" <?php selected($clothing, 'společenská košile'); ?>>Společenská košile</option>
        <option value="Tmavý civil" <?php selected($clothing, 'Tmavý civil'); ?>>Tmavý civil</option>
    </select>
    <?php
}

function my_team_plugin_save_meta_box_data($post_id) {
    if (array_key_exists('kseft_location', $_POST)) {
        update_post_meta($post_id, 'kseft_location', sanitize_text_field($_POST['kseft_location']));
    }
    if (array_key_exists('kseft_meeting_time', $_POST)) {
        update_post_meta($post_id, 'kseft_meeting_time', sanitize_text_field($_POST['kseft_meeting_time']));
    }
    if (array_key_exists('kseft_event_date', $_POST)) {
        update_post_meta($post_id, 'kseft_event_date', sanitize_text_field($_POST['kseft_event_date']));
    }
    if (array_key_exists('kseft_duration', $_POST)) { // Uložení pole pro předpokládanou délku
        update_post_meta($post_id, 'kseft_duration', intval($_POST['kseft_duration']));
    }
    if (array_key_exists('kseft_status', $_POST)) { // Uložení pole pro stav
        update_post_meta($post_id, 'kseft_status', sanitize_text_field($_POST['kseft_status']));
    }
    if (array_key_exists('kseft_clothing', $_POST)) { // Uložení pole pro oblečení
        update_post_meta($post_id, 'kseft_clothing', sanitize_text_field($_POST['kseft_clothing']));
    }
}

add_action('save_post', 'my_team_plugin_save_meta_box_data');

function my_team_plugin_add_kseft_meta_boxes() {
    add_meta_box('kseft_obsazeni_template', 'Šablona obsazení', 'my_team_plugin_render_kseft_obsazeni_template_meta_box', 'kseft', 'side', 'default');
    add_meta_box('kseft_clothing', 'Oblečení', 'my_team_plugin_render_kseft_clothing_meta_box', 'kseft', 'side', 'default');
}
add_action('add_meta_boxes', 'my_team_plugin_add_kseft_meta_boxes');

function my_team_plugin_render_kseft_obsazeni_template_meta_box($post) {
    $selected_template = get_post_meta($post->ID, 'kseft_obsazeni_template', true);
    $templates = get_posts(array('post_type' => 'obsazeni_template', 'numberposts' => -1));
    ?>
    <label for="kseft_obsazeni_template">Vyberte šablonu obsazení:</label>
    <select name="kseft_obsazeni_template" id="kseft_obsazeni_template">
        <option value="">-- Vyberte šablonu --</option>
        <?php foreach ($templates as $template) : ?>
            <option value="<?php echo $template->ID; ?>" <?php selected($selected_template, $template->ID); ?>><?php echo $template->post_title; ?></option>
        <?php endforeach; ?>
    </select>
    <?php
}

function my_team_plugin_render_kseft_clothing_meta_box($post) {
    $selected_clothing = get_post_meta($post->ID, 'kseft_clothing', true);
    ?>
    <label for="kseft_clothing">Vyberte oblečení:</label>
    <select name="kseft_clothing" id="kseft_clothing">
        <option value="krojová košile" <?php selected($selected_clothing, 'krojová košile'); ?>>Krojová košile</option>
        <option value="společenská košile" <?php selected($selected_clothing, 'společenská košile'); ?>>Společenská košile</option>
        <option value="Tmavý civil" <?php selected($selected_clothing, 'Tmavý civil'); ?>>Tmavý civil</option>
    </select>
    <?php
}

function my_team_plugin_save_kseft_meta_box_data($post_id) {
    if (array_key_exists('kseft_obsazeni_template', $_POST)) {
        update_post_meta($post_id, 'kseft_obsazeni_template', sanitize_text_field($_POST['kseft_obsazeni_template']));
    }
    if (array_key_exists('kseft_clothing', $_POST)) {
        update_post_meta($post_id, 'kseft_clothing', sanitize_text_field($_POST['kseft_clothing']));
    }
}
add_action('save_post', 'my_team_plugin_save_kseft_meta_box_data');

function my_team_plugin_display_kseft_details($content) {
    if (is_singular('kseft')) {
        $kseft_id = get_the_ID();
        $location = get_post_meta($kseft_id, 'kseft_location', true);
        $meeting_time = get_post_meta($kseft_id, 'kseft_meeting_time', true);
        $event_date = get_post_meta($kseft_id, 'kseft_event_date', true);
        $kseft_duration = get_post_meta($kseft_id, 'kseft_duration', true); // Přidání pole pro předpokládanou délku
        $status = get_post_meta($kseft_id, 'kseft_status', true);
        $clothing = get_post_meta($kseft_id, 'kseft_clothing', true);
        $description = get_post_meta($kseft_id, 'kseft_description', true); // Přidání pole pro popis
        $obsazeni_template_id = get_post_meta($kseft_id, 'kseft_obsazeni_template', true);
        $obsazeni_template = get_post($obsazeni_template_id);

        // Přidání tlačítek pro přechod na další nebo předchozí kšeft
        $prev_kseft = my_team_plugin_get_adjacent_kseft($event_date, 'prev');
        $next_kseft = my_team_plugin_get_adjacent_kseft($event_date, 'next');
        $custom_content = '<div class="kseft-navigation" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">';
        if ($prev_kseft) {
            $custom_content .= '<a href="' . get_permalink($prev_kseft->ID) . '" class="button" style="flex: 1; text-align: left;">Předchozí kšeft</a>';
        } else {
            $custom_content .= '<span style="flex: 1;"></span>';
        }
        $custom_content .= '<a href="' . site_url('/ksefty') . '" class="button" style="flex: 1; text-align: center;">Zpět na přehled kšeftů</a>';
        if ($next_kseft) {
            $custom_content .= '<a href="' . get_permalink($next_kseft->ID) . '" class="button" style="flex: 1; text-align: right;">Další kšeft</a>';
        } else {
            $custom_content .= '<span style="flex: 1;"></span>';
        }
        $custom_content .= '</div>';

        // Přidání tlačítek pro úpravu kšeftu a přidání do Google Kalendáře
        $custom_content .= '<a href="' . add_query_arg('kseft_id', $kseft_id, site_url('/manage-kseft')) . '" class="button">Upravit Kšeft</a>';
        $custom_content .= '<button id="add-to-calendar-button" class="button">Přidat do Google Kalendáře</button>';

        $custom_content .= '<h3>Detaily Kšeftu</h3>';
        $custom_content .= '<p><strong>Lokace:</strong> ' . esc_html($location) . '</p>';
        $custom_content .= '<p><strong>Čas srazu:</strong> ' . esc_html($meeting_time) . '</p>';
        $formatted_date = date_i18n('D d.m.Y', strtotime($event_date));
        $custom_content .= '<p><strong>Datum kšeftu:</strong> ' . esc_html($formatted_date) . '</p>';
        $custom_content .= '<p><strong>Předpokládaná délka:</strong> ' . esc_html($kseft_duration) . ' hodin</p>'; // Přidání délky kšeftu
        $custom_content .= '<p><strong>Status:</strong> ' . esc_html($status) . '</p>';
        $custom_content .= '<p><strong>Oblečení:</strong> ' . esc_html($clothing) . '</p>';
        $custom_content .= '<p><strong>Popis:</strong> ' . esc_html($description) . '</p>'; // Přidání popisu
        if ($obsazeni_template) {
            $custom_content .= '<h4>Obsazení:</h4>';
            $roles = get_post_meta($obsazeni_template_id, 'obsazeni_template_roles', true);
            if ($roles) {
                $custom_content .= '<table id="obsazeni-table">';
                $custom_content .= '<thead><tr><th>Název role</th><th>Potvrzení</th><th>Místo vyzvednutí</th><th>Čas vyzvednutí</th><th class="sortable">Doprava</th><th class="sortable">Akce</th></tr></thead>';
                $custom_content .= '<tbody>';
                foreach ($roles as $role_id) {
                    $role = get_post($role_id);
                    if ($role) {
                        $role_status = get_post_meta($kseft_id, 'role_status_' . $role_id, true);
                        $role_substitute = get_post_meta($kseft_id, 'role_substitute_' . $role_id, true);
                        $default_player = get_post_meta($role_id, 'role_default_player', true);
                        $default_pickup_location = get_post_meta($role_id, 'role_default_pickup_location', true);
                        $pickup_location = get_post_meta($kseft_id, 'pickup_location_' . $role_id, true);
                        $pickup_time = get_post_meta($kseft_id, 'pickup_time_' . $role_id, true); // Přidání pole pro čas vyzvednutí
                        $transport = get_post_meta($kseft_id, 'transport_' . $role_id, true);
                        $button_class = 'role-confirmation';
                        $button_text = $role_status ?: 'Nepotvrzeno';
                        if ($role_status === 'Jdu') {
                            $button_class .= ' role-confirmation-jdu';
                        } elseif ($role_status === 'Záskok') {
                            $button_class .= ' role-confirmation-zaskok';
                        } else {
                            $button_class .= ' role-confirmation-nepotvrzeno';
                        }
                        $confirmation_text = $role_status === 'Záskok' ? 'Záskok: ' . esc_html($role_substitute) : esc_html($default_player);
                        $custom_content .= '<tr>';
                        $custom_content .= '<td>' . esc_html($role->post_title) . '</td>';
                        $custom_content .= '<td>' . $confirmation_text . '</td>';
                        $custom_content .= '<td>' . esc_html($pickup_location) . '</td>';
                        $custom_content .= '<td class="pickup-time"><input type="text" name="pickup_time_' . esc_attr($role_id) . '" value="' . esc_attr($pickup_time) . '" class="pickup-time-input" data-role-id="' . esc_attr($role_id) . '"></td>'; // Přidání editovatelného pole
                        $custom_content .= '<td>
                            <select name="transport_' . esc_attr($role_id) . '" class="transport-select" data-role-id="' . esc_attr($role_id) . '">
                                <option value="">-- Vyberte auto --</option>';
                        $auta = get_posts(array('post_type' => 'auta', 'numberposts' => -1));
                        foreach ($auta as $auto) {
                            $auto_title = get_the_title($auto->ID);
                            $seats = get_post_meta($auto->ID, 'doprava_seats', true);
                            $driver = get_post_meta($auto->ID, 'doprava_driver', true);
                            $custom_content .= '<option value="' . esc_attr($auto_title) . '" data-seats="' . esc_attr($seats) . '" ' . selected($transport, $auto_title, false) . '>' . esc_html($auto_title) . ' (' . esc_html($seats) . ' míst, řidič: ' . esc_html($driver) . ')</option>';
                        }
                        $custom_content .= '</select>
                        </td>';
                        $custom_content .= '<td><button class="button ' . esc_attr($button_class) . '" data-role-id="' . esc_attr($role_id) . '" data-default-player="' . esc_attr($default_player) . '" data-pickup-location="' . esc_attr($pickup_location) . '" data-default-pickup-location="' . esc_attr($default_pickup_location) . '">' . esc_html($button_text) . '</button></td>';
                        $custom_content .= '</tr>';
                    }
                }
                $custom_content .= '</tbody>';
                $custom_content .= '</table>';
            }
        }

        // Přidání modálního okna pro potvrzení účasti
        $custom_content .= '<div id="role-confirmation-modal" style="display: none;">
            <div class="modal-content">
                <h3>Potvrdit účast</h3>
                <form id="role-confirmation-form">
                    <input type="hidden" name="role_id" id="role_id" value="">
                    <label for="role_status">Stav účasti:</label>
                    <select name="role_status" id="role_status">
                        <option value="Nepotvrzeno">Nepotvrzeno</option>
                        <option value="Jdu">Jdu</option>
                        <option value="Záskok">Záskok</option>
                    </select>
                    <div id="substitute-field" style="display: none;">
                        <label for="role_substitute">Záskok:</label>
                        <input type="text" name="role_substitute" id="role_substitute" value="">
                    </div>
                    <div id="pickup-location-field" style="display: none;">
                        <label for="pickup_location">Místo vyzvednutí:</label>
                        <input type="text" name="pickup_location" id="pickup_location" value="">
                    </div>
                    <div id="default-player-field" style="display: none;">
                        <label for="default_player">Jméno hráče:</label>
                        <input type="text" name="default_player" id="default_player" value="">
                    </div>
                    <button type="submit" class="button">Uložit</button>
                    <button type="button" class="button" id="close-modal">Zavřít</button>
                </form>
            </div>
        </div>';

        $content .= $custom_content;
    }
    return $content;
}
add_filter('the_content', 'my_team_plugin_display_kseft_details');

function my_team_plugin_save_pickup_time() {
    $post_id = intval($_POST['post_id']);
    $role_id = intval($_POST['role_id']);
    $pickup_time = sanitize_text_field($_POST['pickup_time']);

    // Kontrola formátu času (hh:mm)
    if (!preg_match('/^\d{2}:\d{2}$/', $pickup_time)) {
        echo 'Neplatný formát času. Použijte formát hh:mm.';
        wp_die();
    }

    update_post_meta($post_id, 'pickup_time_' . $role_id, $pickup_time);

    echo 'Čas vyzvednutí byl uložen.';
    wp_die();
}
add_action('wp_ajax_save_pickup_time', 'my_team_plugin_save_pickup_time');
add_action('wp_ajax_nopriv_save_pickup_time', 'my_team_plugin_save_pickup_time');

function my_team_plugin_get_adjacent_kseft($current_date, $direction = 'next') {
    $order = ($direction === 'next') ? 'ASC' : 'DESC';
    $compare = ($direction === 'next') ? '>' : '<';
    $args = array(
        'post_type' => 'kseft',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_key' => 'kseft_event_date',
        'orderby' => 'meta_value',
        'order' => $order,
        'meta_query' => array(
            array(
                'key' => 'kseft_event_date',
                'value' => $current_date,
                'compare' => $compare,
                'type' => 'DATE'
            )
        )
    );
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        return $query->posts[0];
    }
    return false;
}

function my_team_plugin_save_role_confirmation() {
    $post_id = intval($_POST['post_id']);
    $role_id = intval($_POST['role_id']);
    $role_status = sanitize_text_field($_POST['role_status']);
    $role_substitute = sanitize_text_field($_POST['role_substitute']);
    $pickup_location = sanitize_text_field($_POST['pickup_location']);
    $transport = sanitize_text_field($_POST['transport']);

    update_post_meta($post_id, 'role_status_' . $role_id, $role_status);
    update_post_meta($post_id, 'role_substitute_' . $role_id, $role_substitute);
    update_post_meta($post_id, 'pickup_location_' . $role_id, $pickup_location);
    update_post_meta($post_id, 'transport_' . $role_id, $transport);

    echo 'Účast byla potvrzena.';
    wp_die();
}
add_action('wp_ajax_save_role_confirmation', 'my_team_plugin_save_role_confirmation');
add_action('wp_ajax_nopriv_save_role_confirmation', 'my_team_plugin_save_role_confirmation');

function my_team_plugin_save_role_transport() {
    $post_id = intval($_POST['post_id']);
    $role_id = intval($_POST['role_id']);
    $transport = sanitize_text_field($_POST['transport']);

    update_post_meta($post_id, 'transport_' . $role_id, $transport);

    echo 'Doprava byla uložena.';
    wp_die();
}
add_action('wp_ajax_save_role_transport', 'my_team_plugin_save_role_transport');
add_action('wp_ajax_nopriv_save_role_transport', 'my_team_plugin_save_role_transport');

// Přidání metaboxu pro výběr rolí při tvorbě šablony obsazení
function my_team_plugin_add_obsazeni_template_meta_boxes() {
    add_meta_box('obsazeni_template_roles', 'Role v kapele', 'my_team_plugin_render_obsazeni_template_roles_meta_box', 'obsazeni_template', 'normal', 'high');
}
add_action('add_meta_boxes', 'my_team_plugin_add_obsazeni_template_meta_boxes');

function my_team_plugin_render_obsazeni_template_roles_meta_box($post) {
    $selected_roles = get_post_meta($post->ID, 'obsazeni_template_roles', true);
    $roles = get_posts(array('post_type' => 'role', 'numberposts' => -1));
    ?>
    <label for="obsazeni_template_roles">Vyberte role:</label>
    <select name="obsazeni_template_roles[]" id="obsazeni_template_roles" multiple>
        <?php foreach ($roles as $role) : ?>
            <option value="<?php echo $role->ID; ?>" <?php echo (is_array($selected_roles) && in_array($role->ID, $selected_roles)) ? 'selected' : ''; ?>><?php echo $role->post_title; ?></option>
        <?php endforeach; ?>
    </select>
    <?php
}

function my_team_plugin_save_obsazeni_template_meta_box_data($post_id) {
    if (array_key_exists('obsazeni_template_roles', $_POST)) {
        update_post_meta($post_id, 'obsazeni_template_roles', $_POST['obsazeni_template_roles']);
    }
}
add_action('save_post', 'my_team_plugin_save_obsazeni_template_meta_box_data');

// Přidání metaboxu pro stav kšeftu
function my_team_plugin_add_kseft_status_meta_box() {
    add_meta_box('kseft_status', 'Stav kšeftu', 'my_team_plugin_render_kseft_status_meta_box', 'kseft', 'side', 'default');
}
add_action('add_meta_boxes', 'my_team_plugin_add_kseft_status_meta_box');

function my_team_plugin_render_kseft_status_meta_box($post) {
    $status = get_post_meta($post->ID, 'kseft_status', true);
    ?>
    <label for="kseft_status">Stav kšeftu:</label>
    <select name="kseft_status" id="kseft_status">
        <option value="Rezervace termínu" <?php selected($status, 'Rezervace termínu'); ?>>Rezervace termínu</option>
        <option value="Podepsaná smlouva" <?php selected($status, 'Podepsaná smlouva'); ?>>Podepsaná smlouva</option>
    </select>
    <?php
}

function my_team_plugin_save_kseft_status_meta_box_data($post_id) {
    if (array_key_exists('kseft_status', $_POST)) {
        update_post_meta($post_id, 'kseft_status', sanitize_text_field($_POST['kseft_status']));
    }
}
add_action('save_post', 'my_team_plugin_save_kseft_status_meta_box_data');

function my_team_plugin_add_kseft_details_meta_box() {
    add_meta_box('kseft_details', 'Detaily Kšeftu', 'my_team_plugin_render_kseft_details_meta_box', 'kseft', 'normal', 'high');
}
add_action('add_meta_boxes', 'my_team_plugin_add_kseft_details_meta_box');

function my_team_plugin_render_kseft_details_meta_box($post) {
    $location = get_post_meta($post->ID, 'kseft_location', true);
    $meeting_time = get_post_meta($post->ID, 'kseft_meeting_time', true);
    $event_date = get_post_meta($post->ID, 'kseft_event_date', true);
    ?>
    <label for="kseft_location">Lokace (Google Maps URL):</label>
    <input type="text" name="kseft_location" id="kseft_location" value="<?php echo esc_attr($location); ?>" size="25" />
    <br><br>
    <label for="kseft_meeting_time">Čas srazu:</label>
    <input type="text" name="kseft_meeting_time" id="kseft_meeting_time" value="<?php echo esc_attr($meeting_time); ?>" size="25" />
    <br><br>
    <label for="kseft_event_date">Datum kšeftu:</label>
    <input type="date" name="kseft_event_date" id="kseft_event_date" value="<?php echo esc_attr($event_date); ?>" size="25" />
    <?php
}

function my_team_plugin_save_kseft_details_meta_box_data($post_id) {
    if (array_key_exists('kseft_location', $_POST)) {
        update_post_meta($post_id, 'kseft_location', sanitize_text_field($_POST['kseft_location']));
    }
    if (array_key_exists('kseft_meeting_time', $_POST)) {
        update_post_meta($post_id, 'kseft_meeting_time', sanitize_text_field($_POST['kseft_meeting_time']));
    }
    if (array_key_exists('kseft_event_date', $_POST)) {
        update_post_meta($post_id, 'kseft_event_date', sanitize_text_field($_POST['kseft_event_date']));
    }
}
add_action('save_post', 'my_team_plugin_save_kseft_details_meta_box_data');

// Přidání metaboxu pro vlastnosti dopravy
function my_team_plugin_add_doprava_meta_boxes() {
    add_meta_box('doprava_seats', 'Počet míst', 'my_team_plugin_render_doprava_seats_meta_box', 'auta', 'normal', 'high');
    add_meta_box('doprava_driver', 'Řidič', 'my_team_plugin_render_doprava_driver_meta_box', 'auta', 'normal', 'high');
}
add_action('add_meta_boxes', 'my_team_plugin_add_doprava_meta_boxes');

function my_team_plugin_render_doprava_seats_meta_box($post) {
    $seats = get_post_meta($post->ID, 'doprava_seats', true);
    ?>
    <label for="doprava_seats">Počet míst:</label>
    <input type="number" name="doprava_seats" id="doprava_seats" value="<?php echo esc_attr($seats); ?>" size="25" />
    <?php
}

function my_team_plugin_render_doprava_driver_meta_box($post) {
    $driver = get_post_meta($post->ID, 'doprava_driver', true);
    $roles = get_posts(array('post_type' => 'role', 'numberposts' => -1));
    ?>
    <label for="doprava_driver">Řidič:</label>
    <select name="doprava_driver" id="doprava_driver">
        <option value="">-- Vyberte řidiče --</option>
        <?php foreach ($roles as $role) : ?>
    $destination = sanitize_text_field($_GET['destination']);
            <?php $driver_name = get_post_meta($role->ID, 'role_default_player', true); ?>
            <option value="<?php echo esc_attr($driver_name); ?>" <?php selected($driver, $driver_name); ?>><?php echo esc_html($driver_name); ?></option>
        <?php endforeach; ?>
    </select>
    <?php
}

function my_team_plugin_save_doprava_meta_box_data($post_id) {
    if (array_key_exists('doprava_seats', $_POST)) {
        update_post_meta($post_id, 'doprava_seats', intval($_POST['doprava_seats']));
    }
    if (array_key_exists('doprava_driver', $_POST)) {
        update_post_meta($post_id, 'doprava_driver', sanitize_text_field($_POST['doprava_driver']));
    }
}
add_action('save_post', 'my_team_plugin_save_doprava_meta_box_data');

// Přidání stránky nastavení pluginu
function my_team_plugin_add_settings_page() {
    add_options_page(
        'Nastavení Muzikantské kšefty',
        'Muzikantské kšefty',
        'manage_options',
        'my-team-plugin-settings',
        'my_team_plugin_render_settings_page'
    );
}
add_action('admin_menu', 'my_team_plugin_add_settings_page');

function my_team_plugin_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Nastavení Muzikantské kšefty</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('my_team_plugin_settings_group');
            do_settings_sections('my-team-plugin-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function my_team_plugin_register_settings() {
    register_setting('my_team_plugin_settings_group', 'my_team_plugin_google_maps_api_key');
    register_setting('my_team_plugin_settings_group', 'my_team_plugin_openrouteservice_api_key');
    register_setting('my_team_plugin_settings_group', 'my_team_plugin_openai_api_key'); // Přidání OpenAI API klíče
    register_setting('my_team_plugin_settings_group', 'my_team_plugin_google_calendar_api_key'); // Přidání Google Calendar API klíče

    add_settings_section(
        'my_team_plugin_settings_section',
        'Obecná nastavení',
        null,
        'my-team-plugin-settings'
    );

    add_settings_field(
        'my_team_plugin_google_maps_api_key',
        'Google Maps API Key',
        'my_team_plugin_google_maps_api_key_callback',
        'my-team-plugin-settings',
        'my_team_plugin_settings_section'
    );

    add_settings_field(
        'my_team_plugin_openrouteservice_api_key',
        'OpenRouteService API Key',
        'my_team_plugin_openrouteservice_api_key_callback',
        'my-team-plugin-settings',
        'my_team_plugin_settings_section'
    );

    add_settings_field(
        'my_team_plugin_openai_api_key',
        'OpenAI API Key',
        'my_team_plugin_openai_api_key_callback',
        'my-team-plugin-settings',
        'my_team_plugin_settings_section'
    );

    add_settings_field(
        'my_team_plugin_google_calendar_api_key',
        'Google Calendar API Key',
        'my_team_plugin_google_calendar_api_key_callback',
        'my-team-plugin-settings',
        'my_team_plugin_settings_section'
    );
}
add_action('admin_init', 'my_team_plugin_register_settings');

function my_team_plugin_google_maps_api_key_callback() {
    $api_key = get_option('my_team_plugin_google_maps_api_key');
    ?>
    <input type="text" name="my_team_plugin_google_maps_api_key" value="<?php echo esc_attr($api_key); ?>" size="50">
    <?php
}

function my_team_plugin_openrouteservice_api_key_callback() {
    $api_key = get_option('my_team_plugin_openrouteservice_api_key');
    ?>
    <input type="text" name="my_team_plugin_openrouteservice_api_key" value="<?php echo esc_attr($api_key); ?>" size="50">
    <?php
}

function my_team_plugin_openai_api_key_callback() {
    $api_key = get_option('my_team_plugin_openai_api_key');
    ?>
    <input type="text" name="my_team_plugin_openai_api_key" value="<?php echo esc_attr($api_key); ?>" size="50">
    <?php
}

function my_team_plugin_google_calendar_api_key_callback() {
    $api_key = get_option('my_team_plugin_google_calendar_api_key');
    ?>
    <input type="text" name="my_team_plugin_google_calendar_api_key" value="<?php echo esc_attr($api_key); ?>" size="50">
    <?php
}

// Přidání sloupce pro oblečení do přehledu kšeftů
function my_team_plugin_add_clothing_column($columns) {
    $columns['kseft_clothing'] = 'Oblečení';
    return $columns;
}
add_filter('manage_kseft_posts_columns', 'my_team_plugin_add_clothing_column');

function my_team_plugin_display_clothing_column($column, $post_id) {
    if ($column === 'kseft_clothing') {
        $clothing = get_post_meta($post_id, 'kseft_clothing', true);
        echo esc_html($clothing);
    }
}
add_action('manage_kseft_posts_custom_column', 'my_team_plugin_display_clothing_column', 10, 2);

// Registrace vlastních šablon stránek
function my_team_plugin_register_templates($templates) {
    $templates['manage-kseft.php'] = 'Manage Kseft';
    $templates['kseft-details.php'] = 'Kseft Details';
    return $templates;
}
add_filter('theme_page_templates', 'my_team_plugin_register_templates');

function my_team_plugin_load_template($template) {
    if (get_page_template_slug() === 'manage-kseft.php') {
        $template = plugin_dir_path(__FILE__) . 'templates/manage-kseft.php';
    } elseif (get_page_template_slug() === 'kseft-details.php') {
        $template = plugin_dir_path(__FILE__) . 'templates/kseft-details.php';
    }
    return $template;
}
add_filter('template_include', 'my_team_plugin_load_template');

function my_team_plugin_test_openai_api() {
    $result = test_openai_api();

    if (isset($result['error'])) {
        wp_send_json_error(['error' => $result['error']]);
    }

    wp_send_json_success(['response' => $result['response']]);
}
add_action('wp_ajax_test_openai_api', 'my_team_plugin_test_openai_api');
add_action('wp_ajax_nopriv_test_openai_api', 'my_team_plugin_test_openai_api');

function my_team_plugin_get_event_details() {
    $post_id = intval($_POST['post_id']);                 
    $kseft_duration = get_post_meta($post_id, 'kseft_duration', true); // Přidání pole pro předpokládanou délku           
    $event_date = get_post_meta($post_id, 'kseft_event_date', true);
    $meeting_time = get_post_meta($post_id, 'kseft_meeting_time', true);
    $kseft_name = get_the_title($post_id);
    $kseft_location = get_post_meta($post_id, 'kseft_location', true);

    $response = array(
        'event_date' => $event_date,
        'meeting_time' => $meeting_time,
        'kseft_name' => $kseft_name,
        'kseft_location' => $kseft_location,
        'kseft_duration' => $kseft_duration
    );

    wp_send_json_success($response);
}
add_action('wp_ajax_get_event_details', 'my_team_plugin_get_event_details');
add_action('wp_ajax_nopriv_get_event_details', 'my_team_plugin_get_event_details');

function my_team_plugin_update_google_calendar_event() {
    error_log('my_team_plugin_update_google_calendar_event function called');
    $event_id = sanitize_text_field($_POST['event_id']);
    $event_details = $_POST['event_details'];

    error_log('AJAX request received to update Google Calendar event with ID: ' . $event_id);
    error_log('Event details: ' . print_r($event_details, true));

    $result = updateGoogleCalendar($event_id, $event_details);

    if ($result) {
        error_log('Google Calendar event updated successfully.');
        wp_send_json_success();
    } else {
        error_log('Failed to update Google Calendar event.');
        wp_send_json_error(array('error' => 'Failed to update Google Calendar event.'));
    }
}
add_action('wp_ajax_update_google_calendar_event', 'my_team_plugin_update_google_calendar_event');
add_action('wp_ajax_nopriv_update_google_calendar_event', 'my_team_plugin_update_google_calendar_event');

// Přidání funkce pro smazání Google akce
function delete_google_calendar_event($event_id) {
    $credentials_path = plugin_dir_path(__FILE__) . 'includes/credential.json'; // Ujistěte se, že cesta je správná
    if (!file_exists($credentials_path)) {
        return false;
    }

    $client = new Google_Client();
    $client->setAuthConfig($credentials_path);
    $client->addScope(Google_Service_Calendar::CALENDAR);

    $service = new Google_Service_Calendar($client);

    try {
        $calendarId = 'olo0v28necdv27n6mg7psud2dc@group.calendar.google.com';
        $service->events->delete($calendarId, $event_id);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Přidání akce pro smazání Google akce při smazání kšeftu
function my_team_plugin_delete_kseft($post_id) {
    if (get_post_type($post_id) === 'kseft') {
        $google_event_id = get_post_meta($post_id, 'google_calendar_event_id', true);
        if ($google_event_id) {
            delete_google_calendar_event($google_event_id);
        }
    }
}
add_action('before_delete_post', 'my_team_plugin_delete_kseft');

function my_team_plugin_add_kseft_overview_page() {
    add_menu_page(
        'Přehled Kšeftů',
        'Přehled Kšeftů',
        'manage_options',
        'kseft-overview',
        'my_team_plugin_render_kseft_overview_page',
        'dashicons-calendar-alt',
        6
    );
}
add_action('admin_menu', 'my_team_plugin_add_kseft_overview_page');

function my_team_plugin_render_kseft_overview_page() {
    ?>
    <div class="wrap">
        <h1>Přehled Kšeftů</h1>
        <?php echo do_shortcode('[kseft_overview]'); ?>
    </div>
    <?php
}

function my_team_plugin_kseft_overview_shortcode() {
    ob_start();
    ?>
    <div>
        <label for="role_select">Vyberte roli:</label>
        <select id="role_select">
            <option value="">-- Vyberte roli --</option>
            <?php
            $roles = get_posts(array('post_type' => 'role', 'numberposts' => -1));
            foreach ($roles as $role) {
                echo '<option value="' . esc_attr($role->ID) . '">' . esc_html($role->post_title) . '</option>';
            }
            ?>
        </select>
    </div>
    <table id="kseft-overview-table">
        <thead>
            <tr>
                <th>Termín</th>
                <th>Název</th>
                <th>Lokace</th>
                <th>Stav</th>
                <th>Akce</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $args = array(
                'post_type' => 'kseft',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_key' => 'kseft_event_date',
                'orderby' => 'meta_value',
                'order' => 'ASC'
            );
            $ksefty = new WP_Query($args);
            if ($ksefty->have_posts()) {
                while ($ksefty->have_posts()) {
                    $ksefty->the_post();
                    $event_date = get_post_meta(get_the_ID(), 'kseft_event_date', true);
                    $location = get_post_meta(get_the_ID(), 'kseft_location', true);
                    $status = get_post_meta(get_the_ID(), 'kseft_status', true);
                    $obsazeni_template_id = get_post_meta(get_the_ID(), 'kseft_obsazeni_template', true);
                    $roles = get_post_meta($obsazeni_template_id, 'obsazeni_template_roles', true);
                    $formatted_date = date_i18n('D d.m.Y', strtotime($event_date));
                    ?>
                    <tr data-role-ids="<?php echo esc_attr(json_encode($roles)); ?>">
                        <td><a href="<?php echo get_permalink(); ?>"><?php echo esc_html($formatted_date); ?></a></td>
                        <td><a href="<?php echo get_permalink(); ?>"><?php echo get_the_title(); ?></a></td>
                        <td><a href="<?php echo get_permalink(); ?>"><?php echo esc_html($location); ?></a></td>
                        <td><a href="<?php echo get_permalink(); ?>"><?php echo esc_html($status); ?></a></td>
                        <td><button class="button confirm-role-button" data-kseft-id="<?php echo get_the_ID(); ?>">Potvrdit účast</button></td>
                    </tr>
                    <?php
                }
                wp_reset_postdata();
            } else {
                ?>
                <tr>
                    <td colspan="5">Žádné kšefty nejsou k dispozici.</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>

    <?php include plugin_dir_path(__FILE__) . 'templates/role-confirmation-modal.php'; ?>

    <?php
    return ob_get_clean();
}
add_shortcode('kseft_overview', 'my_team_plugin_kseft_overview_shortcode');
?>
