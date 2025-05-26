<?php
/*
Plugin Name: Muzikantské kšefty
Description: Umožňuje uživatelům vytvářet a spravovat týmy, přidávat členy a plánovat události.
Version: 1.0
Author: Vaše Jméno
*/


$autoload_path = plugin_dir_path(__FILE__) . 'vendor/autoload.php'; // Cesta k autoload souboru
if (file_exists($autoload_path)) { // Kontrola, zda soubor existuje
    require_once $autoload_path; // Načtení knihovny Google API PHP Client
}

require_once plugin_dir_path(__FILE__) . 'includes/google-calendar.php'; // Načtení souboru pro Google Kalendář
require_once plugin_dir_path(__FILE__) . 'includes/export-roles.php';

function my_team_plugin_enqueue_scripts() {
    wp_enqueue_script('jquery'); // Načtení jQuery
    wp_enqueue_script('my-team-plugin-script', plugins_url('/js/my-team-plugin.js', __FILE__), array('jquery'), '1.0', true); // Načtení hlavního JS souboru
    wp_localize_script('my-team-plugin-script', 'myTeamPlugin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('my_band_plugin_ajax_nonce'), // More specific nonce
        'site_url' => site_url(),
        'post_id' => get_the_ID(),
        'api_key' => get_option('my_team_plugin_openrouteservice_api_key'), // API klíč pro OpenRouteService
        'rest_url' => rest_url('google-calendar/v1/add-to-calendar'), // REST URL pro přidání do kalendáře
        'kseft_id' => isset($_GET['kseft_id']) ? intval($_GET['kseft_id']) : 0 // Přidání kseft_id
     ));
    wp_enqueue_script('role-selection-script', plugins_url('/js/role-selection.js', __FILE__), array('jquery'), '1.0', true); // Načtení JS souboru pro výběr rolí
    wp_enqueue_script('google-calendar-script', plugins_url('/js/google-calendar.js', __FILE__), array('jquery', 'my-team-plugin-script'), '1.0', true); // Načtení JS souboru pro Google Kalendář
    wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . get_option('my_team_plugin_google_maps_api_key') . '&libraries=places', null, null, true); // Načtení Google Maps API
    wp_enqueue_style('my-team-plugin-style', plugins_url('/css/my-team-plugin.css', __FILE__)); // Načtení hlavního CSS souboru
    wp_enqueue_style('my-team-plugin-responsive-style', plugins_url('/css/my-team-plugin-responsive.css', __FILE__)); // Načtení CSS souboru pro responzivní design
}
add_action('wp_enqueue_scripts', 'my_team_plugin_enqueue_scripts');

function my_team_plugin_create_kseft() {
    check_ajax_referer('my_band_plugin_ajax_nonce', 'nonce');
    $kseft_name = isset($_POST['kseft_name']) ? sanitize_text_field(wp_unslash($_POST['kseft_name'])) : '';

    if (empty($kseft_name)) {
        wp_send_json_error(array('message' => __('Název akce nemůže být prázdný.', 'my-band-plugin')));
        // No wp_die() needed after wp_send_json_error() as it includes it.
    }

    $kseft_id = wp_insert_post(array(
        'post_title' => $kseft_name,
        'post_type' => 'kseft',
        'post_status' => 'publish'
    ));

    if (is_wp_error($kseft_id)) {
        wp_send_json_error(array('message' => $kseft_id->get_error_message()));
    } else {
        wp_send_json_success(array('kseft_id' => $kseft_id, 'redirect_url' => get_permalink($kseft_id)));
    }
    // No wp_die() needed after wp_send_json_success() as it includes it.
}
add_action('wp_ajax_my_team_plugin_create_kseft', 'my_team_plugin_create_kseft');
add_action('wp_ajax_nopriv_my_team_plugin_create_kseft', 'my_team_plugin_create_kseft'); // Consider if nopriv is appropriate for creating posts

function my_team_plugin_add_member() {
    check_ajax_referer('my_band_plugin_ajax_nonce', 'nonce');
    $kseft_id = isset($_POST['kseft_id']) ? intval($_POST['kseft_id']) : 0;
    $member_name = isset($_POST['member_name']) ? sanitize_text_field(wp_unslash($_POST['member_name'])) : '';

    if (empty($kseft_id) || empty($member_name)) {
        wp_send_json_error(array('message' => __('Chybí ID akce nebo jméno člena.', 'my-band-plugin')));
    }
    // Consider capability check if needed for 'nopriv' action
    // Example: if (!current_user_can('edit_post', $kseft_id) && get_post_type($kseft_id) == 'kseft' ) { wp_send_json_error(...); }
    add_post_meta($kseft_id, 'kseft_member', $member_name);
    wp_send_json_success(array('message' => __('Člen přidán.', 'my-band-plugin')));
}
add_action('wp_ajax_my_team_plugin_add_member', 'my_team_plugin_add_member');
add_action('wp_ajax_nopriv_my_team_plugin_add_member', 'my_team_plugin_add_member'); // Consider if nopriv is appropriate

function my_team_plugin_schedule_event() {
    check_ajax_referer('my_band_plugin_ajax_nonce', 'nonce');
    $kseft_id = isset($_POST['kseft_id']) ? intval($_POST['kseft_id']) : 0;
    $event_name = isset($_POST['event_name']) ? sanitize_text_field(wp_unslash($_POST['event_name'])) : '';
    $event_date = isset($_POST['event_date']) ? sanitize_text_field(wp_unslash($_POST['event_date'])) : ''; // Basic sanitization

    if (empty($kseft_id) || empty($event_name) || empty($event_date)) {
        wp_send_json_error(array('message' => __('Chybí ID akce, název nebo datum události.', 'my-band-plugin')));
    }
    // Consider date validation for $event_date (e.g., using a regex or DateTime::createFromFormat)
    // Consider capability check if needed
    add_post_meta($kseft_id, 'kseft_event', array('name' => $event_name, 'date' => $event_date));
    wp_send_json_success(array('message' => __('Událost naplánována.', 'my-band-plugin')));
}
add_action('wp_ajax_my_team_plugin_schedule_event', 'my_team_plugin_schedule_event');
add_action('wp_ajax_nopriv_my_team_plugin_schedule_event', 'my_team_plugin_schedule_event');

function my_team_plugin_register_post_types() {
    // Registrace post typu 'kseft'
    register_post_type('kseft', array(
        'labels' => array(
            'name' => __('Kšefty', 'my-band-plugin'),
            'singular_name' => __('Kšeft', 'my-band-plugin'),
            'menu_name' => __('Kšefty', 'my-band-plugin'),
            'name_admin_bar' => __('Kšeft', 'my-band-plugin'),
            'add_new' => __('Přidat nový', 'my-band-plugin'),
            'add_new_item' => __('Přidat nový kšeft', 'my-band-plugin'),
            'new_item' => __('Nový kšeft', 'my-band-plugin'),
            'edit_item' => __('Upravit kšeft', 'my-band-plugin'),
            'view_item' => __('Zobrazit kšeft', 'my-band-plugin'),
            'all_items' => __('Všechny kšefty', 'my-band-plugin'),
            'search_items' => __('Hledat kšefty', 'my-band-plugin'),
            'parent_item_colon' => __('Nadřazený kšeft:', 'my-band-plugin'),
            'not_found' => __('Žádné kšefty nenalezeny.', 'my-band-plugin'),
            'not_found_in_trash' => __('Žádné kšefty v koši.', 'my-band-plugin')
        ),
        'public' => true,
        'has_archive' => true, // Archivace příspěvků
        'show_in_menu' => true, // Zobrazení v menu
        'show_ui' => true, // Zobrazení v uživatelském rozhraní
        'rewrite' => array('slug' => 'kseft'), // Přepis URL
        'supports' => array('title', 'editor'), // Podpora polí
        'menu_icon' => 'dashicons-calendar-alt' // Ikona pro 'kseft'
    ));

    // Registrace post typu 'obsazeni_template'
    register_post_type('obsazeni_template', array(
        'labels' => array(
            'name' => __('Šablony obsazení', 'my-band-plugin'),
            'singular_name' => __('Šablona obsazení', 'my-band-plugin'),
            'menu_name' => __('Šablony obsazení', 'my-band-plugin'),
            'name_admin_bar' => __('Šablona obsazení', 'my-band-plugin'),
            'add_new' => __('Přidat novou', 'my-band-plugin'),
            'add_new_item' => __('Přidat novou šablonu obsazení', 'my-band-plugin'),
            'new_item' => __('Nová šablona obsazení', 'my-band-plugin'),
            'edit_item' => __('Upravit šablonu obsazení', 'my-band-plugin'),
            'view_item' => __('Zobrazit šablonu obsazení', 'my-band-plugin'),
            'all_items' => __('Všechny šablony obsazení', 'my-band-plugin'),
            'search_items' => __('Hledat šablony obsazení', 'my-band-plugin'),
            'parent_item_colon' => __('Nadřazená šablona obsazení:', 'my-band-plugin'),
            'not_found' => __('Žádné šablony obsazení nenalezeny.', 'my-band-plugin'),
            'not_found_in_trash' => __('Žádné šablony obsazení v koši.', 'my-band-plugin')
        ),
        'public' => true,
        'has_archive' => true, // Archivace příspěvků
        'show_in_menu' => true, // Zobrazení v menu
        'show_ui' => true, // Zobrazení v uživatelském rozhraní
        'rewrite' => array('slug' => 'obsazeni-template'), // Přepis URL
        'supports' => array('title', 'editor'), // Podpora polí
        'menu_icon' => 'dashicons-clipboard' // Ikona pro 'obsazeni_template'
    ));

    // Registrace post typu 'role'
    register_post_type('role', array(
        'labels' => array(
            'name' => __('Role', 'my-band-plugin'),
            'singular_name' => __('Role', 'my-band-plugin'),
            'menu_name' => __('Role', 'my-band-plugin'),
            'name_admin_bar' => __('Role', 'my-band-plugin'),
            'add_new' => __('Přidat novou', 'my-band-plugin'),
            'add_new_item' => __('Přidat novou roli', 'my-band-plugin'),
            'new_item' => __('Nová role', 'my-band-plugin'),
            'edit_item' => __('Upravit roli', 'my-band-plugin'),
            'view_item' => __('Zobrazit roli', 'my-band-plugin'),
            'all_items' => __('Všechny role', 'my-band-plugin'),
            'search_items' => __('Hledat role', 'my-band-plugin'),
            'parent_item_colon' => __('Nadřazená role:', 'my-band-plugin'),
            'not_found' => __('Žádné role nenalezeny.', 'my-band-plugin'),
            'not_found_in_trash' => __('Žádné role v koši.', 'my-band-plugin')
        ),
        'public' => true,
        'has_archive' => true, // Archivace příspěvků
        'show_in_menu' => true, // Zobrazení v menu
        'show_ui' => true, // Zobrazení v uživatelském rozhraní
        'rewrite' => array('slug' => 'role'), // Přepis URL
        'supports' => array('title', 'editor'), // Podpora polí
        'menu_icon' => 'dashicons-groups' // Ikona pro 'role'
    ));

    // Registrace post typu 'auta'
    register_post_type('auta', array(
        'labels' => array(
            'name' => __('Auta', 'my-band-plugin'),
            'singular_name' => __('Auto', 'my-band-plugin'),
            'menu_name' => __('Auta', 'my-band-plugin'),
            'name_admin_bar' => __('Auto', 'my-band-plugin'),
            'add_new' => __('Přidat nové', 'my-band-plugin'),
            'add_new_item' => __('Přidat nové auto', 'my-band-plugin'),
            'new_item' => __('Nové auto', 'my-band-plugin'),
            'edit_item' => __('Upravit auto', 'my-band-plugin'),
            'view_item' => __('Zobrazit auto', 'my-band-plugin'),
            'all_items' => __('Všechna auta', 'my-band-plugin'),
            'search_items' => __('Hledat auta', 'my-band-plugin'),
            'parent_item_colon' => __('Nadřazené auto:', 'my-band-plugin'),
            'not_found' => __('Žádná auta nenalezena.', 'my-band-plugin'),
            'not_found_in_trash' => __('Žádná auta v koši.', 'my-band-plugin')
        ),
        'public' => true,
        'has_archive' => true, // Archivace příspěvků
        'show_in_menu' => true, // Zobrazení v menu
        'show_ui' => true, // Zobrazení v uživatelském rozhraní
        'rewrite' => array('slug' => 'auta'), // Přepis URL
        'supports' => array('title', 'editor'), // Podpora polí
        'menu_icon' => 'dashicons-car' // Ikona pro 'auta'
    ));
}
add_action('init', 'my_team_plugin_register_post_types'); // Přidání akce pro registraci typů příspěvků

// Přidání metaboxu pro výchozího hráče, výchozí místo vyzvednutí a heslo role při tvorbě role
function my_team_plugin_add_role_meta_boxes() {
    add_meta_box('role_default_player', 'Výchozí hráč', 'my_team_plugin_render_role_default_player_meta_box', 'role', 'normal', 'high'); // Přidání metaboxu pro výchozího hráče
    add_meta_box('role_default_pickup_location', 'Výchozí místo vyzvednutí', 'my_team_plugin_render_role_default_pickup_location_meta_box', 'role', 'normal', 'high'); // Přidání metaboxu pro výchozí místo vyzvednutí
    add_meta_box('role_password', 'Heslo role', 'my_team_plugin_render_role_password_meta_box', 'role', 'normal', 'high'); // Přidání metaboxu pro heslo role
    add_meta_box('role_alternative', 'Alternativní role', 'my_team_plugin_render_role_alternative_meta_box', 'role', 'normal', 'high'); // Přidání metaboxu pro alternativní roli
}
add_action('add_meta_boxes', 'my_team_plugin_add_role_meta_boxes'); // Přidání akce pro přidání metaboxů

function my_team_plugin_render_role_default_player_meta_box($post) {
    // Add nonce field to the first meta box in the 'role' CPT edit screen
    wp_nonce_field('my_team_plugin_role_meta_nonce', 'role_meta_nonce_field');
    $default_player = get_post_meta($post->ID, 'role_default_player', true);
    ?>
    <p>
    <label for="role_default_player"><?php esc_html_e('Výchozí hráč:', 'my-band-plugin'); ?></label>
    <input type="text" name="role_default_player" id="role_default_player" value="<?php echo esc_attr($default_player); ?>" class="widefat" />
    </p>
    <?php
}

function my_team_plugin_render_role_default_pickup_location_meta_box($post) {
    $default_pickup_location = get_post_meta($post->ID, 'role_default_pickup_location', true);
    ?>
    <p>
    <label for="role_default_pickup_location"><?php esc_html_e('Výchozí místo vyzvednutí:', 'my-band-plugin'); ?></label>
    <input type="text" name="role_default_pickup_location" id="role_default_pickup_location" value="<?php echo esc_attr($default_pickup_location); ?>" class="widefat" />
    </p>
    <?php
}

function my_team_plugin_render_role_password_meta_box($post) {
    $role_password = get_post_meta($post->ID, 'role_password', true);
    ?>
    <p>
    <label for="role_password"><?php esc_html_e('Heslo role:', 'my-band-plugin'); ?></label>
    <input type="password" name="role_password" id="role_password" value="<?php echo esc_attr($role_password); ?>" class="widefat" />
    <span class="description"><?php esc_html_e('Toto heslo se používá pro přístup na stránku "Moje Kšefty" pro tuto roli, pokud uživatel není přihlášen.', 'my-band-plugin'); ?></span>
    </p>
    <?php
}

function my_team_plugin_render_role_alternative_meta_box($post) {
    $alternative_role = get_post_meta($post->ID, 'role_alternative', true);
    $roles_args = array('post_type' => 'role', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'exclude' => $post->ID); // Exclude current post
    $all_roles = get_posts($roles_args);
    ?>
    <p>
    <label for="role_alternative"><?php esc_html_e('Alternativní role:', 'my-band-plugin'); ?></label>
    <select name="role_alternative" id="role_alternative" class="widefat">
        <option value=""><?php esc_html_e('-- Vyberte alternativní roli --', 'my-band-plugin'); ?></option>
        <?php foreach ($all_roles as $role) : ?>
            <option value="<?php echo esc_attr($role->ID); ?>" <?php selected($alternative_role, $role->ID); ?>><?php echo esc_html($role->post_title); ?></option>
        <?php endforeach; ?>
    </select>
    </p>
    <?php
}

function my_team_plugin_save_role_meta_box_data($post_id) {
    // Add nonce check
    if (!isset($_POST['role_meta_nonce_field']) || !wp_verify_nonce(sanitize_key($_POST['role_meta_nonce_field']), 'my_team_plugin_role_meta_nonce')) {
        return;
    }
    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    // Check the user's permissions.
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $fields_to_save = array(
        'role_default_player',
        'role_default_pickup_location',
        'role_password', // Consider hashing this if security needs to be tighter
        'role_alternative'
    );
    foreach ($fields_to_save as $field) {
        if (array_key_exists($field, $_POST)) {
            update_post_meta($post_id, $field, sanitize_text_field(wp_unslash($_POST[$field])));
        }
    }
    // Handle checkbox separately if it's part of this meta box section
    if (isset($_POST['role_confirm_anyone'])) { // This checkbox is rendered by my_team_plugin_render_role_confirm_anyone_meta_box
        update_post_meta($post_id, 'role_confirm_anyone', '1');
    } else {
        delete_post_meta($post_id, 'role_confirm_anyone');
    }
}
add_action('save_post_role', 'my_team_plugin_save_role_meta_box_data'); 

function my_team_plugin_display_ksefty() {
    // error_log('my_team_plugin_display_ksefty function called'); // Can be verbose
    $show_all = isset($_GET['show_all']) && $_GET['show_all'] === '1'; // Kontrola, zda je zaškrtnuto zobrazení všech kšeftů
    $args = array(
        'post_type' => 'kseft', // Typ příspěvku
        'post_status' => 'publish', // Stav příspěvku
        'posts_per_page' => -1, // Počet příspěvků na stránku
        'meta_key' => 'kseft_event_date', // Klíč pro řazení
        'orderby' => 'meta_value', // Řazení podle meta hodnoty
        'order' => 'ASC' // Řazení vzestupně
    );

    if (!$show_all) {
        $args['meta_query'] = array(
            array(
                'key' => 'kseft_event_date',
                'value' => date('Y-m-d'),
                'compare' => '>=',
                'type' => 'DATE'
            )
        );
    }

    $ksefty = new WP_Query($args); // Dotaz na příspěvky
    // error_log('Query executed: ' . print_r($args, true)); // Can be verbose
    $plugin_text_domain = 'my-band-plugin';
    $output = '<div class="business-overview my-band-plugin-centered-content">'; // Use class for styling
    $output .= '<a href="' . esc_url(site_url('/moje-ksefty')) . '" class="button">' . esc_html__('Moje Akce', $plugin_text_domain) . '</a> ';
    $output .= '<a href="' . esc_url(site_url('/manage-kseft')) . '" class="button">' . esc_html__('Vytvořit novou Akci', $plugin_text_domain) . '</a>';
    $current_page_url = get_permalink();
    if (!$current_page_url) $current_page_url = ''; // Fallback if not on a page
    $output .= '<form method="GET" action="' . esc_url($current_page_url) . '" class="my-band-plugin-filter-form"><label><input type="checkbox" name="show_all" value="1" ' . checked($show_all, true, false) . '> ' . esc_html__('Zobrazit všechny akce', $plugin_text_domain) . '</label><button type="submit" class="button">' . esc_html__('Filtrovat', $plugin_text_domain) . '</button></form>';
    if ($ksefty->have_posts()) {
        $output .= '<table class="wp-list-table widefat fixed striped my-band-plugin-table">'; // Added WP classes
        $output .= '<thead><tr><th>Termín</th><th>Název</th><th>Umístění</th><th>Stav obsazení</th><th>Stav</th></thead>';
        $output .= '<tbody>';
        while ($ksefty->have_posts()) {
            $ksefty->the_post();
            $event_date = get_post_meta(get_the_ID(), 'kseft_event_date', true); // Získání data události
            $location = get_post_meta(get_the_ID(), 'kseft_location', true); // Získání lokace
            $status = get_post_meta(get_the_ID(), 'kseft_status', true); // Získání stavu
            $obsazeni_template_id = get_post_meta(get_the_ID(), 'kseft_obsazeni_template', true); // Získání ID šablony obsazení
            $roles = get_post_meta($obsazeni_template_id, 'obsazeni_template_roles', true); // Získání rolí
            $all_confirmed = true; // Předpoklad, že všechny role jsou potvrzeny
            $has_substitute = false; // Předpoklad, že žádná role nemá záskok
            $pickup_location = ''; // Výchozí hodnota pro místo vyzvednutí
            $pickup_time = ''; // Výchozí hodnota pro čas vyzvednutí
            $current_role_id = isset($_COOKIE['selectedRoleId']) ? intval($_COOKIE['selectedRoleId']) : 0; // Získání aktuální role z cookie
            if ($roles) {
                foreach ($roles as $role_id) {
                    $role_status = get_post_meta(get_the_ID(), 'role_status_' . $role_id, true); // Získání stavu role
                    if ($role_status === 'Záskok') {
                        $has_substitute = true; // Pokud je role záskok, nastaví se příznak
                    }
                    if ($role_status !== 'Jdu' && $role_status !== 'Záskok') {
                        $all_confirmed = false; // Pokud role není potvrzena, nastaví se příznak
                        break;
                    }
                    if ($role_id == $current_role_id) {
                        $pickup_location = get_post_meta(get_the_ID(), 'pickup_location_' . $role_id, true); // Získání místa vyzvednutí pro aktuální roli
                        $pickup_time = get_post_meta(get_the_ID(), 'pickup_time_' . $role_id, true); // Získání času vyzvednutí pro aktuální roli
                    }
                }
            } else {
                $all_confirmed = false; // Pokud nejsou žádné role, nastaví se příznak
            }
            if ($all_confirmed) {
                $obsazeni_class = 'obsazeno'; // Pokud jsou všechny role potvrzena, nastaví se třída
                $obsazeni_text = $has_substitute ? 'Obsazeno se záskokem' : 'Obsazeno'; // Pokud je záskok, nastaví se text
            } else {
                $obsazeni_class = 'neobsazeno'; // Pokud nejsou všechny role potvrzena, nastaví se třída
                $obsazeni_text = 'Neobsazeno'; // Nastaví se text
            }
            $formatted_date = date_i18n('D d.m.Y', strtotime($event_date)); // Formátování data
            $output .= '<tr>';
            $output .= '<td><a href="' . esc_url(get_permalink()) . '">' . esc_html($formatted_date) . '</a></td>';
            $output .= '<td><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></td>';
            $output .= '<td><a href="' . esc_url(get_permalink()) . '">' . esc_html($location) . '</a></td>';
            $output .= '<td><a href="' . esc_url(get_permalink()) . '" class="button kseft-status-button ' . esc_attr($obsazeni_class) . '">' . esc_html($obsazeni_text) . '</a></td>';
            $output .= '<td><a href="' . esc_url(get_permalink()) . '">' . esc_html($status) . '</a></td>';
            $output .= '</tr>';
        }
        $output .= '</tbody>';
        $output .= '</table>';
        wp_reset_postdata();
    } else {
        $output .= '<p>' . esc_html__('Žádné akce nejsou k dispozici.', $plugin_text_domain) . '</p>';
    }
    $output .= '</div>';
    // error_log('Output: ' . $output); // Can be verbose
    return $output;
}
add_shortcode('display_ksefty', 'my_team_plugin_display_ksefty');

function my_team_plugin_display_moje_ksefty() {
    // error_log('my_team_plugin_display_moje_ksefty function called'); // Can be verbose
    $show_all = isset($_GET['show_all']) && $_GET['show_all'] === '1'; // Kontrola, zda je zaškrtnuto zobrazení všech kšeftů
    $args = array(
        'post_type' => 'kseft', // Typ příspěvku
        'post_status' => 'publish', // Stav příspěvku
        'posts_per_page' => -1, // Počet příspěvků na stránku
        'meta_key' => 'kseft_event_date', // Klíč pro řazení
        'orderby' => 'meta_value', // Řazení podle meta hodnoty
        'order' => 'ASC' // Řazení vzestupně
    );

    if (!$show_all) {
        $args['meta_query'] = array(
            array(
                'key' => 'kseft_event_date',
                'value' => date('Y-m-d'),
                'compare' => '>=',
                'type' => 'DATE'
            )
        );
    }

    $ksefty = new WP_Query($args); // Dotaz na příspěvky
    // error_log('Query executed: ' . print_r($args, true)); // Can be verbose
    $plugin_text_domain = 'my-band-plugin';
    $output = '<div class="business-overview my-band-plugin-centered-content">';
    $output .= '<a href="' . esc_url(site_url('/moje-ksefty')) . '" class="button">' . esc_html__('Moje akce', $plugin_text_domain) . '</a> ';
    $output .= '<a href="' . esc_url(site_url('/manage-kseft')) . '" class="button">' . esc_html__('Vytvořit novou akci', $plugin_text_domain) . '</a>';
    $current_page_url = get_permalink();
    if (!$current_page_url) $current_page_url = '';
    $output .= '<form method="GET" action="' . esc_url($current_page_url) . '" class="my-band-plugin-filter-form"><label><input type="checkbox" name="show_all" value="1" ' . checked($show_all, true, false) . '> ' . esc_html__('Zobrazit všechny Akce', $plugin_text_domain) . '</label><button type="submit" class="button">' . esc_html__('Filtrovat', $plugin_text_domain) . '</button></form>';
    if ($ksefty->have_posts()) {
        $output .= '<table class="wp-list-table widefat fixed striped my-band-plugin-table">';
        $output .= '<thead><tr><th>Termín</th><th>Umístění</th><th>Název</th><th>Stav obsazení</th><th>Stav</th></thead>';
        $output .= '<tbody>';
        while ($ksefty->have_posts()) {
            $ksefty->the_post();
            $event_date = get_post_meta(get_the_ID(), 'kseft_event_date', true); // Získání data události
            $location = get_post_meta(get_the_ID(), 'kseft_location', true); // Získání lokace
            $status = get_post_meta(get_the_ID(), 'kseft_status', true); // Získání stavu
            $obsazeni_template_id = get_post_meta(get_the_ID(), 'kseft_obsazeni_template', true); // Získání ID šablony obsazení
            $roles = get_post_meta($obsazeni_template_id, 'obsazeni_template_roles', true); // Získání rolí
            $all_confirmed = true; // Předpoklad, že všechny role jsou potvrzeny
            $has_substitute = false; // Předpoklad, že žádná role nemá záskok
            $pickup_location = ''; // Výchozí hodnota pro místo vyzvednutí
            $pickup_time = ''; // Výchozí hodnota pro čas vyzvednutí
            $current_role_id = isset($_COOKIE['selectedRoleId']) ? intval($_COOKIE['selectedRoleId']) : 0; // Získání aktuální role z cookie
            if ($roles) {
                foreach ($roles as $role_id) {
                    $role_status = get_post_meta(get_the_ID(), 'role_status_' . $role_id, true); // Získání stavu role
                    if ($role_status === 'Záskok') {
                        $has_substitute = true; // Pokud je role záskok, nastaví se příznak
                    }
                    if ($role_status !== 'Jdu' && $role_status !== 'Záskok') {
                        $all_confirmed = false; // Pokud role není potvrzena, nastaví se příznak
                        break;
                    }
                    if ($role_id == $current_role_id) {
                        $pickup_location = get_post_meta(get_the_ID(), 'pickup_location_' . $role_id, true); // Získání místa vyzvednutí pro aktuální roli
                        $pickup_time = get_post_meta(get_the_ID(), 'pickup_time_' . $role_id, true); // Získání času vyzvednutí pro aktuální roli
                    }
                }
            } else {
                $all_confirmed = false; // Pokud nejsou žádné role, nastaví se příznak
            }
            if ($all_confirmed) {
                $obsazeni_class = 'obsazeno'; // Pokud jsou všechny role potvrzena, nastaví se třída
                $obsazeni_text = $has_substitute ? 'Obsazeno se záskokem' : 'Obsazeno'; // Pokud je záskok, nastaví se text
            } else {
                $obsazeni_class = 'neobsazeno'; // Pokud nejsou všechny role potvrzena, nastaví se třída
                $obsazeni_text = 'Neobsazeno'; // Nastaví se text
            }
            $formatted_date = date_i18n('D d.m.Y', strtotime($event_date)); // Formátování data
            $output .= '<tr>';
            $output .= '<td><a href="' . esc_url(get_permalink()) . '">' . esc_html($formatted_date) . '</a></td>';
            $output .= '<td><a href="' . esc_url(get_permalink()) . '">' . esc_html($location) . '</a></td>';
            $output .= '<td><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></td>';
            $output .= '<td><a href="' . esc_url(get_permalink()) . '" class="button kseft-status-button ' . esc_attr($obsazeni_class) . '">' . esc_html($obsazeni_text) . '</a></td>';
            $output .= '<td><a href="' . esc_url(get_permalink()) . '">' . esc_html($status) . '</a></td>';
            $output .= '</tr>';
        }
        $output .= '</tbody>';
        $output .= '</table>';
        wp_reset_postdata();
    } else {
        $output .= '<p>' . esc_html__('Žádné Akce nejsou k dispozici.', $plugin_text_domain) . '</p>';
    }
    $output .= '</div>';
    // error_log('Output: ' . $output); // Can be verbose
    return $output;
}
add_shortcode('display_moje_ksefty', 'my_team_plugin_display_moje_ksefty');

// Consolidated meta box registration for 'kseft' CPT
function my_team_plugin_add_kseft_meta_boxes() {
    $plugin_text_domain = 'my-band-plugin';
    add_meta_box('kseft_main_details', __('Detaily Kšeftu', $plugin_text_domain), 'my_team_plugin_render_kseft_main_details_meta_box', 'kseft', 'normal', 'high');
    add_meta_box('kseft_status_meta', __('Stav kšeftu', $plugin_text_domain), 'my_team_plugin_render_kseft_status_meta_box', 'kseft', 'side', 'default');
}
add_action('add_meta_boxes_kseft', 'my_team_plugin_add_kseft_meta_boxes');


// This function seems to be the more complete one for 'kseft' CPT details. Renamed from my_team_plugin_render_meta_box
function my_team_plugin_render_kseft_main_details_meta_box($post) {
    wp_nonce_field('my_team_plugin_save_kseft_details_nonce', 'kseft_details_nonce_field');
    $plugin_text_domain = 'my-band-plugin';
    $location = get_post_meta($post->ID, 'kseft_location', true);
    $meeting_time = get_post_meta($post->ID, 'kseft_meeting_time', true); // Získání času srazu
    $event_date = get_post_meta($post->ID, 'kseft_event_date', true); // Získání data události
    $performance_start = get_post_meta($post->ID, 'kseft_performance_start', true); // Získání začátku vystoupení
    $performance_end = get_post_meta($post->ID, 'kseft_performance_end', true); // Získání konce vystoupení
    $status = get_post_meta($post->ID, 'kseft_status', true); // Získání stavu
    $clothing = get_post_meta($post->ID, 'kseft_clothing', true); // Získání oblečení
    $responsible_for_drinks = get_post_meta($post->ID, 'kseft_responsible_for_drinks', true); // Získání odpovědného za pitný režim
    $roles = get_posts(array('post_type' => 'role', 'numberposts' => -1)); // Získání všech rolí
    ?>
    <label for="kseft_location">Lokace (Google Maps URL):</label>
    <input type="text" name="kseft_location" id="kseft_location_wp" value="<?php echo esc_attr($location); ?>" size="25" /> <!-- Pole pro lokaci -->
    <div id="map-kseft-wp"></div> <!-- Změna ID elementu mapy -->
    <br><br>
    <label for="kseft_meeting_time">Čas srazu:</label>
    <input type="text" name="kseft_meeting_time" id="kseft_meeting_time" value="<?php echo esc_attr($meeting_time); ?>" size="25" /> <!-- Pole pro čas srazu -->
    <br><br>
    <div style="display: flex; justify-content: space_between;">
        <div style="flex: 1; margin-right: 10px;">
            <label for="kseft_event_date">Datum Akce:</label>
            <input type="date" name="kseft_event_date" id="kseft_event_date" value="<?php echo esc_attr($event_date); ?>" size="25" /> <!-- Pole pro datum kšeftu -->
        </div>
        <div style="flex: 1; margin-right: 10px;">
            <label for="kseft_performance_start">Začátek vystoupení:</label>
            <input type="time" name="kseft_performance_start" id="kseft_performance_start" value="<?php echo esc_attr($performance_start); ?>" size="25" /> <!-- Pole pro začátek vystoupení -->
        </div>
        <div style="flex: 1;">
            <label for="kseft_performance_end">Konec vystoupení:</label>
            <input type="time" name="kseft_performance_end" id="kseft_performance_end" value="<?php echo esc_attr($performance_end); ?>" size="25" /> <!-- Pole pro konec vystoupení -->
        </div>
    </div>
    <br><br>
    <label for="kseft_status">Stav Akce:</label>
    <select name="kseft_status" id="kseft_status">
        <option value="Rezervace termínu" <?php selected($status, 'Rezervace termínu'); ?>>Rezervace termínu</option>
        <option value="Podepsaná smlouva" <?php selected($status, 'Podepsaná smlouva'); ?>>Podepsaná smlouva</option>
    </select> <!-- Výběr pro stav kšeftu -->
    <br><br>
    <label for="kseft_clothing">Oblečení:</label>
    <select name="kseft_clothing" id="kseft_clothing">
        <option value="krojová košile" <?php selected($clothing, 'krojová košile'); ?>>Krojová košile</option>
        <option value="společenská košile" <?php selected($clothing, 'společenská košile'); ?>>Společenská košile</option>
        <option value="Tmavý civil" <?php selected($clothing, 'Tmavý civil'); ?>>Tmavý civil</option>
    </select> <!-- Výběr pro oblečení -->
    <br><br>
    <label for="kseft_responsible_for_drinks">Odpovědný za pitný režim:</label>
    <select name="kseft_responsible_for_drinks" id="kseft_responsible_for_drinks">
        <?php foreach ($roles as $role) : ?>
            <?php $default_player = get_post_meta($role->ID, 'role_default_player', true); ?>
            <option value="<?php echo esc_attr($default_player); ?>" <?php selected($responsible_for_drinks, $default_player); ?>><?php echo esc_html($default_player); ?></option>
        <?php endforeach; ?>
    </select> <!-- Výběr pro odpovědného za pitný režim -->
    <br><br>
    <?php
}

function my_team_plugin_save_meta_box_data($post_id) {
    // Check if nonce is set.
    if (!isset($_POST['kseft_details_nonce_field'])) {
        return; // Changed from return $post_id to just return for clarity as $post_id is not always expected by save_post hook
    }
    // Verify that the nonce is valid.
    if (!wp_verify_nonce(sanitize_key($_POST['kseft_details_nonce_field']), 'my_team_plugin_save_kseft_details_nonce')) {
        return;
    }
    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    // Check the user's permissions.
    // Note: 'save_post_kseft' already ensures the post type is 'kseft'.
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $meta_to_save = array(
        'kseft_location', 'kseft_meeting_time', 'kseft_event_date',
        'kseft_performance_start', 'kseft_performance_end',
        'kseft_clothing', 'kseft_responsible_for_drinks'
        // kseft_status is saved in my_team_plugin_save_kseft_status_meta_data
        // kseft_description (if it's a meta field using wp_editor) would be: 'kseft_description_meta_field' => 'wp_kses_post'
    );

    foreach ($meta_to_save as $key) {
        if (isset($_POST[$key])) {
            // Assuming all these fields are simple text fields for now.
            // If any field expects HTML or specific formats, use appropriate sanitization like wp_kses_post or custom validation.
            update_post_meta($post_id, $key, sanitize_text_field(wp_unslash($_POST[$key])));
        }
    }
}
add_action('save_post_kseft', 'my_team_plugin_save_meta_box_data');

function my_team_plugin_display_kseft_details($content) {
    if (is_singular('kseft')) {
        $kseft_id = get_the_ID(); // Získání ID kšeftu
        $location = get_post_meta($kseft_id, 'kseft_location', true); // Získání lokace
        $meeting_time = get_post_meta($kseft_id, 'kseft_meeting_time', true); // Získání času srazu
        $event_date = get_post_meta($kseft_id, 'kseft_event_date', true); // Získání data události
        $performance_start = get_post_meta($kseft_id, 'kseft_performance_start', true); // Získání začátku vystoupení
        $performance_end = get_post_meta($kseft_id, 'kseft_performance_end', true); // Získání konce vystoupení
        $status = get_post_meta($kseft_id, 'kseft_status', true); // Získání stavu
        $clothing = get_post_meta($kseft_id, 'kseft_clothing', true); // Získání oblečení
        $description = get_post_meta($kseft_id, 'kseft_description', true); // Získání popisu
        $responsible_for_drinks = get_post_meta($kseft_id, 'kseft_responsible_for_drinks', true); // Získání odpovědného za pitný režim
        $obsazeni_template_id = get_post_meta($kseft_id, 'kseft_obsazeni_template', true); // Získání ID šablony obsazení
        $obsazeni_template = get_post($obsazeni_template_id); // Získání šablony obsazení

        // Získání počtu kšeftů, které mají aktuálně zvolenou osobu odpovědnou za pitný režim
        $args = array(
            'post_type' => 'kseft',
            'meta_query' => array(
                array(
                    'key' => 'kseft_responsible_for_drinks',
                    'value' => $responsible_for_drinks,
                    'compare' => '='
                )
            )
        );
        $ksefty_query = new WP_Query($args);
        $ksefty_count = $ksefty_query->found_posts;

        // Přidání tlačítek pro přechod na další nebo předchozí kšeft
        $prev_kseft = my_team_plugin_get_adjacent_kseft($event_date, 'prev'); // Získání předchozího kšeftu
        $next_kseft = my_team_plugin_get_adjacent_kseft($event_date, 'next'); // Získání dalšího kšeftu
        $custom_content = '<div class="kseft-navigation" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">';
        if ($prev_kseft) {
            $custom_content .= '<a href="' . get_permalink($prev_kseft->ID) . '" class="button" style="flex: 1; text-align: left;">Předchozí Akce</a>'; // Tlačítko pro předchozí kšeft
        } else {
            $custom_content .= '<span style="flex: 1;"></span>';
        }
        $current_role_id = isset($_COOKIE['selectedRoleId']) ? intval($_COOKIE['selectedRoleId']) : 0; // Získání aktuální role z cookie
        $back_link = $current_role_id ? site_url('/moje-ksefty') : site_url('/ksefty'); // Odkaz zpět na přehled kšeftů
        $custom_content .= '<a href="' . $back_link . '" class="button" style="flex: 1; text-align: center;">Zpět na přehled akcí</a>'; // Tlačítko zpět na přehled kšeftů
        if ($next_kseft) {
            $custom_content .= '<a href="' . get_permalink($next_kseft->ID) . '" class="button" style="flex: 1; text-align: right;">Další Akce</a>'; // Tlačítko pro další kšeft
        } else {
            $custom_content .= '<span style="flex: 1;"></span>';
        }
        $custom_content .= '</div>';

        // Přidání tlačítek pro úpravu kšeftu a přidání do Google Kalendáře, pokud nepřicházíte ze stránky "moje-ksefty"
        if (!isset($_GET['from']) || $_GET['from'] !== 'moje-ksefty') {
            $custom_content .= '<a href="' . add_query_arg('kseft_id', $kseft_id, site_url('/manage-kseft')) . '" class="button">Upravit Akci</a>'; // Tlačítko pro úpravu kšeftu
            $custom_content .= '<button id="add-to-calendar-button" class="button">Přidat do Google Kalendáře</button>'; // Tlačítko pro přidání do Google Kalendáře
        }

        $custom_content .= '<a href="' . add_query_arg('copy_kseft_id', $kseft_id, site_url('/manage-kseft')) . '" class="button">Kopírovat Akci</a>'; // Tlačítko pro kopírování kšeftu

        $custom_content .= '<h3>Detaily Akce</h3>';
        // $custom_content .= '<p><strong>ID Kšeftu:</strong> ' . esc_html($kseft_id) . '</p>'; // Zobrazení ID kšeftu
        $custom_content .= '<input type="hidden" id="kseft_id" value="' . esc_attr($kseft_id) . '">'; // Skryté pole pro kseft_id
        $custom_content .= '<p><strong>Lokace:</strong> ' . esc_html($location) . '</p>'; // Zobrazení lokace
        $formatted_date = date_i18n('D d.m.Y', strtotime($event_date)); // Formátování data
        $custom_content .= '<p><strong>Datum Akce:</strong> ' . esc_html($formatted_date) . '</p>'; // Zobrazení data kšeftu
        $custom_content .= '<p><strong>Čas srazu:</strong> ' . esc_html($meeting_time) . '</p>'; // Zobrazení času srazu
        $custom_content .= '<p><strong>Začátek vystoupení:</strong> ' . esc_html($performance_start) . '</p>'; // Zobrazení začátku vystoupení
        $custom_content .= '<p><strong>Konec vystoupení:</strong> ' . esc_html($performance_end) . '</p>'; // Zobrazení konce vystoupení
        $custom_content .= '<p><strong>Status:</strong> ' . esc_html($status) . '</p>'; // Zobrazení stavu
        $custom_content .= '<p><strong>Oblečení:</strong> ' . esc_html($clothing) . '</p>'; // Zobrazení oblečení
        $custom_content .= '<p><strong>Odpovědný za pitný režim:</strong> ' . esc_html($responsible_for_drinks) . ' (' . $ksefty_count . ' kšeftů)</p>'; // Zobrazení odpovědného za pitný režim s počtem kšeftů
        $custom_content .= '<p><strong>Popis:</strong> ' . wpautop($description) . '</p>'; // Zobrazení popisu s HTML úpravami
        if ($obsazeni_template) {
            $custom_content .= '<h4>Obsazení:</h4>';
            $roles = get_post_meta($obsazeni_template_id, 'obsazeni_template_roles', true); // Získání rolí
            if ($roles) {
                $custom_content .= '<table id="obsazeni-table">';
                $custom_content .= '<thead><tr><th>Název role</th><th>Potvrzení</th><th>Místo vyzvednutí</th><th>Čas vyzvednutí</th><th class="sortable">Doprava</th><th class="sortable">Akce</th></tr></thead>';
                $custom_content .= '<tbody>';
                foreach ($roles as $role_id) {
                    $role = get_post($role_id); // Získání role
                    if ($role) {
                        $role_status = get_post_meta($kseft_id, 'role_status_' . $role_id, true); // Získání stavu role
                        $role_substitute = get_post_meta($kseft_id, 'role_substitute_' . $role_id, true); // Získání záskoku role
                        $default_player = get_post_meta($role_id, 'role_default_player', true); // Získání výchozího hráče
                        $default_pickup_location = get_post_meta($role_id, 'role_default_pickup_location', true); // Získání výchozího místa vyzvednutí
                        $pickup_location = get_post_meta($kseft_id, 'pickup_location_' . $role_id, true); // Získání místa vyzvednutí
                        $pickup_time = get_post_meta($kseft_id, 'pickup_time_' . $role_id, true); // Získání času vyzvednutí
                        $transport = get_post_meta($kseft_id, 'transport_' . $role_id, true); // Získání dopravy
                        $button_class = 'role-confirmation'; // Výchozí třída pro tlačítko
                        $button_text = $role_status ?: 'Nepotvrzeno'; // Výchozí text pro tlačítko
                        if ($role_status === 'Jdu') {
                            $button_class .= ' role-confirmation-jdu'; // Pokud je role potvrzena, nastaví se třída
                        } elseif ($role_status === 'Záskok') {
                            $button_class .= ' role-confirmation-zaskok'; // Pokud je role záskok, nastaví se třída
                        } else {
                            $button_class .= ' role-confirmation-nepotvrzeno'; // Pokud role není potvrzena, nastaví se třída
                        }
                        $confirmation_text = $role_status === 'Záskok' ? 'Záskok: ' . esc_html($role_substitute) : esc_html($default_player); // Text pro potvrzení role
                        $custom_content .= '<tr>';
                        $custom_content .= '<td>' . esc_html($role->post_title) . '</td>'; // Zobrazení názvu role
                        $custom_content .= '<td>' . $confirmation_text . '</td>'; // Zobrazení potvrzení role
                        $custom_content .= '<td>' . esc_html($pickup_location) . '</td>'; // Zobrazení místa vyzvednutí
                        $custom_content .= '<td class="pickup-time"><input type="text" name="pickup_time_' . esc_attr($role_id) . '" value="' . esc_attr($pickup_time) . '" class="pickup-time-input" data-role-id="' . esc_attr($role_id) . '"></td>'; // Zobrazení editovatelného pole pro čas vyzvednutí
                        $custom_content .= '<td>
                            <select name="transport_' . esc_attr($role_id) . '" class="transport-select" data-role-id="' . esc_attr($role_id) . '">
                                <option value="">-- Vyberte auto --</option>';
                        $auta = get_posts(array('post_type' => 'auta', 'numberposts' => -1)); // Získání všech aut
                        foreach ($auta as $auto) {
                            $auto_title = get_the_title($auto->ID); // Získání názvu auta
                            $seats = get_post_meta($auto->ID, 'doprava_seats', true); // Získání počtu míst
                            $driver = get_post_meta($auto->ID, 'doprava_driver', true); // Získání řidiče
                            $custom_content .= '<option value="' . esc_attr($auto_title) . '" data-seats="' . esc_attr($seats) . '" ' . selected($transport, $auto_title, false) . '>' . esc_html($auto_title) . ' (' . esc_html($seats) . ' míst, řidič: ' . esc_html($driver) . ')</option>'; // Výběr pro auto
                        }
                        $custom_content .= '</select>
                        </td>';
                        $custom_content .= '<td><button class="button ' . esc_attr($button_class) . '" data-role-id="' . esc_attr($role_id) . '" data-kseft-id="' . esc_attr($kseft_id) . '" data-default-player="' . esc_attr($default_player) . '" data-pickup-location="' . esc_attr($pickup_location) . '" data-default-pickup-location="' . esc_attr($default_pickup_location) . '">' . esc_html($button_text) . '</button></td>'; // Tlačítko pro potvrzení role
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
                    <input type="hidden" name="kseft_id" id="kseft_id" value="">
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
add_filter('the_content', 'my_team_plugin_display_kseft_details'); // Přidání filtru pro zobrazení detailů kšeftu

function my_team_plugin_save_pickup_time() {
    check_ajax_referer('wp_rest', 'nonce');
    $post_id = intval($_POST['post_id']); // Získání ID příspěvku
    $role_id = intval($_POST['role_id']); // Získání ID role
    $pickup_time = sanitize_text_field($_POST['pickup_time']); // Sanitizace času vyzvednutí

    // Kontrola formátu času (hh:mm) nebo prázdného pole
    if ($pickup_time !== '' && !preg_match('/^\d{2}:\d{2}$/', $pickup_time)) {
        echo 'Neplatný formát času. Použijte formát hh:mm.';
        wp_die();
    }

    if ($pickup_time === '') {
        delete_post_meta($post_id, 'pickup_time_' . $role_id); // Smazání času vyzvednutí
        echo 'Čas vyzvednutí byl smazán.';
    } else {
        update_post_meta($post_id, 'pickup_time_' . $role_id, $pickup_time); // Uložení času vyzvednutí
        echo 'Čas vyzvednutí byl uložen.';
    }

    wp_die();
}
add_action('wp_ajax_save_pickup_time', 'my_team_plugin_save_pickup_time'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_save_pickup_time', 'my_team_plugin_save_pickup_time'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_get_adjacent_kseft($current_date, $direction = 'next') {
    $order = ($direction === 'next') ? 'ASC' : 'DESC'; // Řazení podle směru
    $compare = ($direction === 'next') ? '>' : '<'; // Porovnání podle směru
    $args = array(
        'post_type' => 'kseft', // Typ příspěvku
        'post_status' => 'publish', // Stav příspěvku
        'posts_per_page' => 1, // Počet příspěvků na stránku
        'meta_key' => 'kseft_event_date', // Klíč pro řazení
        'orderby' => 'meta_value', // Řazení podle meta hodnoty
        'order' => $order, // Řazení podle směru
        'meta_query' => array(
            array(
                'key' => 'kseft_event_date', // Klíč pro porovnání
                'value' => $current_date, // Hodnota pro porovnání
                'compare' => $compare, // Porovnání podle směru
                'type' => 'DATE' // Typ hodnoty
            )
        )
    );
    $query = new WP_Query($args); // Dotaz na příspěvky
    if ($query->have_posts()) {
        return $query->posts[0]; // Vrácení prvního příspěvku
    }
    return false; // Pokud nejsou žádné příspěvky, vrátí se false
}

// Přidání checkboxu pro potvrzení účasti za kohokoli
function my_team_plugin_render_role_confirm_anyone_meta_box($post) {
    $confirm_anyone = get_post_meta($post->ID, 'role_confirm_anyone', true); // Získání hodnoty checkboxu
    ?>
    <label for="role_confirm_anyone">
        <input type="checkbox" name="role_confirm_anyone" id="role_confirm_anyone" value="1" <?php checked($confirm_anyone, '1'); ?> />
        Potvrdit účast za kohokoli
    </label>
    <?php
}
add_action('add_meta_boxes', function () {
    add_meta_box('role_confirm_anyone', 'Oprávnění', 'my_team_plugin_render_role_confirm_anyone_meta_box', 'role', 'side', 'default');
});

// Uložení hodnoty checkboxu
function my_team_plugin_save_role_confirm_anyone_meta_box_data($post_id) {
    if (array_key_exists('role_confirm_anyone', $_POST)) {
        update_post_meta($post_id, 'role_confirm_anyone', '1'); // Uložení hodnoty "1" pokud je checkbox zaškrtnut
    } else {
        delete_post_meta($post_id, 'role_confirm_anyone'); // Smazání hodnoty pokud není checkbox zaškrtnut
    }
}
add_action('save_post', 'my_team_plugin_save_role_confirm_anyone_meta_box_data');

// Úprava logiky pro potvrzení účasti
function my_team_plugin_save_role_confirmation() {
    check_ajax_referer('wp_rest', 'nonce');
    $post_id = intval($_POST['kseft_id']); // Získání ID příspěvku
    $role_id = intval($_POST['role_id']); // Získání ID role
    $role_status = sanitize_text_field($_POST['role_status']); // Sanitizace stavu role
    $role_substitute = sanitize_text_field($_POST['role_substitute']); // Sanitizace záskoku role
    $pickup_location = sanitize_text_field($_POST['pickup_location']); // Sanitizace místa vyzvednutí

    // Kontrola, zda uživatel může potvrdit účast za tuto roli
    $current_role_id = isset($_COOKIE['selectedRoleId']) ? intval($_COOKIE['selectedRoleId']) : 0;
    $confirm_anyone = get_post_meta($current_role_id, 'role_confirm_anyone', true);

    if (!$confirm_anyone && $current_role_id !== $role_id) {
        wp_send_json_error('Nemáte oprávnění potvrdit účast za tuto roli.');
        wp_die();
    }

    update_post_meta($post_id, 'role_status_' . $role_id, $role_status); // Uložení stavu role
    update_post_meta($post_id, 'role_substitute_' . $role_id, $role_substitute); // Uložení záskoku role
    update_post_meta($post_id, 'pickup_location_' . $role_id, $pickup_location); // Uložení místa vyzvednutí

    error_log("php Role confirmation saved: kseft_id=$post_id, role_id=$role_id, role_status=$role_status, role_substitute=$role_substitute, pickup_location=$pickup_location"); // Logování uložení potvrzení role

    wp_send_json_success('Účast byla potvrzena.');
    wp_die();
}
add_action('wp_ajax_save_role_confirmation', 'my_team_plugin_save_role_confirmation'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_save_role_confirmation', 'my_team_plugin_save_role_confirmation'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_get_role_confirmation() {
    $post_id = intval($_POST['kseft_id']); // Získání ID příspěvku
    $role_id = intval($_POST['role_id']); // Získání ID role

    $role_status = get_post_meta($post_id, 'role_status_' . $role_id, true); // Získání stavu role
    $role_substitute = get_post_meta($post_id, 'role_substitute_' . $role_id, true); // Získání záskoku role
    $pickup_location = get_post_meta($post_id, 'pickup_location_' . $role_id, true); // Získání místa vyzvednutí

    wp_send_json_success(array(
        'role_status' => $role_status,
        'role_substitute' => $role_substitute,
        'pickup_location' => $pickup_location
    ));
}
add_action('wp_ajax_get_role_confirmation', 'my_team_plugin_get_role_confirmation'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_get_role_confirmation', 'my_team_plugin_get_role_confirmation'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_save_role_transport() {
    check_ajax_referer('wp_rest', 'nonce');
    $post_id = intval($_POST['post_id']); // Získání ID příspěvku
    $role_id = intval($_POST['role_id']); // Získání ID role
    $transport = sanitize_text_field($_POST['transport']); // Sanitizace dopravy

    update_post_meta($post_id, 'transport_' . $role_id, $transport); // Uložení dopravy

    echo 'Doprava byla uložena.';
    wp_die();
}
add_action('wp_ajax_save_role_transport', 'my_team_plugin_save_role_transport'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_save_role_transport', 'my_team_plugin_save_role_transport'); // Přidání AJAX akce pro nepřihlášené uživatele

// Přidání metaboxu pro výběr rolí při tvorbě šablony obsazení
function my_team_plugin_add_obsazeni_template_meta_boxes() {
    add_meta_box('obsazeni_template_roles', 'Role v kapele', 'my_team_plugin_render_obsazeni_template_roles_meta_box', 'obsazeni_template', 'normal', 'high'); // Přidání metaboxu pro role v kapele
}
add_action('add_meta_boxes', 'my_team_plugin_add_obsazeni_template_meta_boxes'); // Přidání akce pro přidání metaboxů

function my_team_plugin_render_obsazeni_template_roles_meta_box($post) {
    $selected_roles = get_post_meta($post->ID, 'obsazeni_template_roles', true); // Získání vybraných rolí
    $roles = get_posts(array('post_type' => 'role', 'numberposts' => -1)); // Získání všech rolí
    ?>
    <label for="obsazeni_template_roles">Vyberte role:</label>
    <select name="obsazeni_template_roles[]" id="obsazeni_template_roles" multiple>
        <?php foreach ($roles as $role) : ?>
            <option value="<?php echo $role->ID; ?>" <?php echo (is_array($selected_roles) && in_array($role->ID, $selected_roles)) ? 'selected' : ''; ?>><?php echo $role->post_title; ?></option>
        <?php endforeach; ?>
    </select> <!-- Výběr pro role -->
    <?php
}

function my_team_plugin_save_obsazeni_template_meta_box_data($post_id) {
    if (array_key_exists('obsazeni_template_roles', $_POST)) {
        update_post_meta($post_id, 'obsazeni_template_roles', $_POST['obsazeni_template_roles']); // Uložení rolí
    }
}
add_action('save_post', 'my_team_plugin_save_obsazeni_template_meta_box_data'); // Přidání akce pro uložení metaboxů

// Přidání metaboxu pro stav kšeftu
function my_team_plugin_add_kseft_status_meta_box() {
    add_meta_box('kseft_status', 'Stav kšeftu', 'my_team_plugin_render_kseft_status_meta_box', 'kseft', 'side', 'default'); // Přidání metaboxu pro stav kšeftu
}
add_action('add_meta_boxes', 'my_team_plugin_add_kseft_status_meta_box'); // Přidání akce pro přidání metaboxů

function my_team_plugin_render_kseft_status_meta_box($post) {
    $status = get_post_meta($post->ID, 'kseft_status', true); // Získání stavu kšeftu
    ?>
    <label for="kseft_status">Stav kšeftu:</label>
    <select name="kseft_status" id="kseft_status">
        <option value="Rezervace termínu" <?php selected($status, 'Rezervace termínu'); ?>>Rezervace termínu</option>
        <option value="Podepsaná smlouva" <?php selected($status, 'Podepsaná smlouva'); ?>>Podepsaná smlouva</option>
    </select> <!-- Výběr pro stav kšeftu -->
    <?php
}

function my_team_plugin_save_kseft_status_meta_box_data($post_id) {
    if (array_key_exists('kseft_status', $_POST)) {
        update_post_meta($post_id, 'kseft_status', sanitize_text_field($_POST['kseft_status'])); // Uložení stavu kšeftu
    }
}
add_action('save_post', 'my_team_plugin_save_kseft_status_meta_box_data'); // Přidání akce pro uložení metaboxů

function my_team_plugin_add_kseft_details_meta_box() {
    add_meta_box('kseft_details', 'Detaily Kšeftu', 'my_team_plugin_render_kseft_details_meta_box', 'kseft', 'normal', 'high'); // Přidání metaboxu pro detaily kšeftu
}
add_action('add_meta_boxes', 'my_team_plugin_add_kseft_details_meta_box'); // Přidání akce pro přidání metaboxů

function my_team_plugin_render_kseft_details_meta_box($post) {
    $location = get_post_meta($post->ID, 'kseft_location', true); // Získání lokace
    $meeting_time = get_post_meta($post->ID, 'kseft_meeting_time', true); // Získání času srazu
    $event_date = get_post_meta($post->ID, 'kseft_event_date', true); // Získání data události
    $responsible_for_drinks = get_post_meta($post->ID, 'kseft_responsible_for_drinks', true); // Získání odpovědného za pitný režim
    $roles = get_posts(array('post_type' => 'role', 'numberposts' => -1)); // Získání všech rolí
    ?>
    <label for="kseft_location">Lokace (Google Maps URL):</label>
    <input type="text" name="kseft_location" id="kseft_location" value="<?php echo esc_attr($location); ?>" size="25" /> <!-- Pole pro lokaci -->
    <br><br>
    <label for="kseft_meeting_time">Čas srazu:</label>
    <input type="text" name="kseft_meeting_time" id="kseft_meeting_time" value="<?php echo esc_attr($meeting_time); ?>" size="25" /> <!-- Pole pro čas srazu -->
    <br><br>
    <label for="kseft_event_date">Datum kšeftu:</label>
    <input type="date" name="kseft_event_date" id="kseft_event_date" value="<?php echo esc_attr($event_date); ?>" size="25" /> <!-- Pole pro datum kšeftu -->
    <br><br>
    <label for="kseft_responsible_for_drinks">Odpovědný za pitný režim:</label>
    <select name="kseft_responsible_for_drinks" id="kseft_responsible_for_drinks">
        <?php foreach ($roles as $role) : ?>
            <?php $default_player = get_post_meta($role->ID, 'role_default_player', true); ?>
            <option value="<?php echo esc_attr($default_player); ?>" <?php selected($responsible_for_drinks, $default_player); ?>><?php echo esc_html($default_player); ?></option>
        <?php endforeach; ?>
    </select> <!-- Výběr pro odpovědného za pitný režim -->
    <?php
}

function my_team_plugin_save_kseft_details_meta_box_data($post_id) {
    if (array_key_exists('kseft_location', $_POST)) {
        update_post_meta($post_id, 'kseft_location', sanitize_text_field($_POST['kseft_location'])); // Uložení lokace
    }
    if (array_key_exists('kseft_meeting_time', $_POST)) {
        update_post_meta($post_id, 'kseft_meeting_time', sanitize_text_field($_POST['kseft_meeting_time'])); // Uložení času srazu
    }
    if (array_key_exists('kseft_event_date', $_POST)) {
        update_post_meta($post_id, 'kseft_event_date', sanitize_text_field($_POST['kseft_event_date'])); // Uložení data události
    }
    if (array_key_exists('kseft_responsible_for_drinks', $_POST)) {
        update_post_meta($post_id, 'kseft_responsible_for_drinks', sanitize_text_field($_POST['kseft_responsible_for_drinks'])); // Uložení odpovědného za pitný režim
    }
}
add_action('save_post', 'my_team_plugin_save_kseft_details_meta_box_data'); // Přidání akce pro uložení metaboxů

// Přidání metaboxu pro vlastnosti dopravy
function my_team_plugin_add_doprava_meta_boxes() {
    add_meta_box('doprava_seats', 'Počet míst', 'my_team_plugin_render_doprava_seats_meta_box', 'auta', 'normal', 'high'); // Přidání metaboxu pro počet míst
    add_meta_box('doprava_driver', 'Řidič', 'my_team_plugin_render_doprava_driver_meta_box', 'auta', 'normal', 'high'); // Přidání metaboxu pro řidiče
}
add_action('add_meta_boxes', 'my_team_plugin_add_doprava_meta_boxes'); // Přidání akce pro přidání metaboxů

function my_team_plugin_render_doprava_seats_meta_box($post) {
    $seats = get_post_meta($post->ID, 'doprava_seats', true); // Získání počtu míst
    ?>
    <label for="doprava_seats">Počet míst:</label>
    <input type="number" name="doprava_seats" id="doprava_seats" value="<?php echo esc_attr($seats); ?>" size="25" /> <!-- Pole pro počet míst -->
    <?php
}

function my_team_plugin_render_doprava_driver_meta_box($post) {
    $driver = get_post_meta($post->ID, 'doprava_driver', true); // Získání řidiče
    $roles = get_posts(array('post_type' => 'role', 'numberposts' => -1)); // Získání všech rolí
    ?>
    <label for="doprava_driver">Řidič:</label>
    <select name="doprava_driver" id="doprava_driver">
        <option value="">-- Vyberte řidiče --</option>
        <?php foreach ($roles as $role) : ?>
            <?php $driver_name = get_post_meta($role->ID, 'role_default_player', true); ?>
            <option value="<?php echo esc_attr($driver_name); ?>" <?php selected($driver, $driver_name); ?>><?php echo esc_html($driver_name); ?></option>
        <?php endforeach; ?>
    </select> <!-- Výběr pro řidiče -->
    <?php
}

function my_team_plugin_save_doprava_meta_box_data($post_id) {
    if (array_key_exists('doprava_seats', $_POST)) {
        update_post_meta($post_id, 'doprava_seats', intval($_POST['doprava_seats'])); // Uložení počtu míst
    }
    if (array_key_exists('doprava_driver', $_POST)) {
        update_post_meta($post_id, 'doprava_driver', sanitize_text_field($_POST['doprava_driver'])); // Uložení řidiče
    }
}
add_action('save_post', 'my_team_plugin_save_doprava_meta_box_data'); // Přidání akce pro uložení metaboxů

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
add_action('admin_menu', 'my_team_plugin_add_settings_page'); // Přidání akce pro přidání stránky nastavení

function my_team_plugin_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Nastavení Muzikantské kšefty</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('my_team_plugin_settings_group'); // Zobrazení polí nastavení
            do_settings_sections('my-team-plugin-settings'); // Zobrazení sekcí nastavení
            submit_button(); // Tlačítko pro uložení nastavení
            ?>
        </form>
    </div>
    <?php
}

function my_team_plugin_register_settings() {
    register_setting('my_team_plugin_settings_group', 'my_team_plugin_google_maps_api_key'); // Registrace nastavení pro Google Maps API klíč
    register_setting('my_team_plugin_settings_group', 'my_team_plugin_openrouteservice_api_key'); // Registrace nastavení pro OpenRouteService API klíč
    register_setting('my_team_plugin_settings_group', 'my_team_plugin_openai_api_key'); // Registrace nastavení pro OpenAI API klíč
    register_setting('my_team_plugin_settings_group', 'my_team_plugin_google_calendar_api_key'); // Registrace nastavení pro Google Calendar API klíč
    register_setting('my_team_plugin_settings_group', 'my_team_plugin_manage_kseft_password'); // Registrace hesla pro správu Kšeftů

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

    add_settings_field(
        'my_team_plugin_manage_kseft_password',
        'Heslo pro správu Kšeftů',
        'my_team_plugin_manage_kseft_password_callback',
        'my-team-plugin-settings',
        'my_team_plugin_settings_section'
    );
}
add_action('admin_init', 'my_team_plugin_register_settings');

function my_team_plugin_manage_kseft_password_callback() {
    $password = get_option('my_team_plugin_manage_kseft_password', 'heslo123');
    ?>
    <input type="text" name="my_team_plugin_manage_kseft_password" value="<?php echo esc_attr($password); ?>" size="50"> <!-- Pole pro heslo pro správu Kšeftů -->
    <?php
}

function my_team_plugin_google_maps_api_key_callback() {
    $api_key = get_option('my_team_plugin_google_maps_api_key'); // Získání Google Maps API klíče
    ?>
    <input type="text" name="my_team_plugin_google_maps_api_key" value="<?php echo esc_attr($api_key); ?>" size="50"> <!-- Pole pro Google Maps API klíč -->
    <?php
}

function my_team_plugin_openrouteservice_api_key_callback() {
    $api_key = get_option('my_team_plugin_openrouteservice_api_key'); // Získání OpenRouteService API klíče
    ?>
    <input type="text" name="my_team_plugin_openrouteservice_api_key" value="<?php echo esc_attr($api_key); ?>" size="50"> <!-- Pole pro OpenRouteService API klíč -->
    <?php
}

function my_team_plugin_openai_api_key_callback() {
    $api_key = get_option('my_team_plugin_openai_api_key'); // Získání OpenAI API klíče
    ?>
    <input type="text" name="my_team_plugin_openai_api_key" value="<?php echo esc_attr($api_key); ?>" size="50"> <!-- Pole pro OpenAI API klíč -->
    <?php
}

function my_team_plugin_google_calendar_api_key_callback() {
    $api_key = get_option('my_team_plugin_google_calendar_api_key'); // Získání Google Calendar API klíče
    ?>
    <input type="text" name="my_team_plugin_google_calendar_api_key" value="<?php echo esc_attr($api_key); ?>" size="50"> <!-- Pole pro Google Calendar API klíč -->
    <?php
}

// Přidání sloupce pro oblečení do přehledu kšeftů
function my_team_plugin_add_clothing_column($columns) {
    $columns['kseft_clothing'] = 'Oblečení'; // Přidání sloupce pro oblečení
    return $columns;
}
add_filter('manage_kseft_posts_columns', 'my_team_plugin_add_clothing_column'); // Přidání filtru pro přidání sloupce

function my_team_plugin_display_clothing_column($column, $post_id) {
    if ($column === 'kseft_clothing') {
        $clothing = get_post_meta($post_id, 'kseft_clothing', true); // Získání oblečení
        echo esc_html($clothing); // Zobrazení oblečení
    }
}
add_action('manage_kseft_posts_custom_column', 'my_team_plugin_display_clothing_column', 10, 2); // Přidání akce pro zobrazení sloupce

// Registrace vlastních šablon stránek
function my_team_plugin_register_templates($templates) {
    $templates['manage-kseft.php'] = 'Manage Kseft'; // Registrace šablony pro správu kšeftu
    $templates['kseft-details.php'] = 'Kseft Details'; // Registrace šablony pro detaily kšeftu
    return $templates;
}
add_filter('theme_page_templates', 'my_team_plugin_register_templates'); // Přidání filtru pro registraci šablon

function my_team_plugin_load_template($template) {
    if (get_page_template_slug() === 'manage-kseft.php') {
        $template = plugin_dir_path(__FILE__) . 'templates/manage-kseft.php'; // Načtení šablony pro správu kšeftu
    } elseif (get_page_template_slug() === 'kseft-details.php') {
        $template = plugin_dir_path(__FILE__) . 'templates/kseft-details.php'; // Načtení šablony pro detaily kšeftu
    }
    return $template;
}
add_filter('template_include', 'my_team_plugin_load_template'); // Přidání filtru pro načtení šablon

function my_team_plugin_test_openai_api() {
    $result = test_openai_api(); // Testování OpenAI API

    if (isset($result['error'])) {
        wp_send_json_error(['error' => $result['error']]); // Odeslání chybové zprávy
    }

    wp_send_json_success(['response' => $result['response']]); // Odeslání úspěšné zprávy
}
add_action('wp_ajax_test_openai_api', 'my_team_plugin_test_openai_api'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_test_openai_api', 'my_team_plugin_test_openai_api'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_get_event_details() {
    $post_id = intval($_POST['kseft_id']); // Získání ID příspěvku
    $event_date = get_post_meta($post_id, 'kseft_event_date', true); // Získání data události
    $meeting_time = get_post_meta($post_id, 'kseft_meeting_time', true); // Získání času srazu
    $kseft_name = get_the_title($post_id); // Získání názvu kšeftu
    $kseft_location = get_post_meta($post_id, 'kseft_location', true); // Získání lokace
    $kseft_description = get_post_meta($post_id, 'kseft_description', true); // Získání description

    // Nová pole pro začátek a konec vystoupení:
    $performance_start = get_post_meta($post_id, 'kseft_performance_start', true);
    $performance_end = get_post_meta($post_id, 'kseft_performance_end', true);
    
    $response = array(
        'event_date' => $event_date,
        'meeting_time' => $meeting_time,
        'kseft_name' => $kseft_name,
        'kseft_location' => $kseft_location,
        'kseft_description' => $kseft_description,
        'performance_start' => $performance_start,
        'performance_end' => $performance_end
    );
    
    wp_send_json_success($response); // Odeslání úspěšné zprávy
}
add_action('wp_ajax_get_event_details', 'my_team_plugin_get_event_details'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_get_event_details', 'my_team_plugin_get_event_details'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_update_google_calendar_event() {
    error_log('my_team_plugin_update_google_calendar_event function called'); // Logování volání funkce
    $event_id = sanitize_text_field($_POST['event_id']); // Sanitizace ID události
    $event_details = $_POST['event_details']; // Získání detailů události

    error_log('AJAX request received to update Google Calendar event with ID: ' . $event_id); // Logování přijetí AJAX požadavku
    error_log('Event details: ' . print_r($event_details, true)); // Logování detailů události

    $result = updateGoogleCalendar($event_id, $event_details); // Aktualizace Google Kalendáře

    if ($result) {
        error_log('Google Calendar event updated successfully.'); // Logování úspěšné aktualizace
        wp_send_json_success(); // Odeslání úspěšné zprávy
    } else {
        error_log('Failed to update Google Calendar event.'); // Logování neúspěšné aktualizace
        wp_send_json_error(array('error' => 'Failed to update Google Calendar event.')); // Odeslání chybové zprávy
    }
}
add_action('wp_ajax_update_google_calendar_event', 'my_team_plugin_update_google_calendar_event'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_update_google_calendar_event', 'my_team_plugin_update_google_calendar_event'); // Přidání AJAX akce pro nepřihlášené uživatele

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
add_action('before_delete_post', 'my_team_plugin_delete_kseft'); // Přidání akce pro smazání Google akce při smazání kšeftu

// Přidání přesměrování po smazání kšeftu
function my_team_plugin_redirect_after_delete() {
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['post']) && get_post_type($_GET['post']) === 'kseft') {
        wp_redirect(site_url('/ksefty'));
        exit;
    }
}
add_action('admin_init', 'my_team_plugin_redirect_after_delete'); // Přidání akce pro přesměrování po smazání kšeftu

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
add_action('admin_menu', 'my_team_plugin_add_kseft_overview_page'); // Přidání akce pro přidání stránky přehledu kšeftů

function my_team_plugin_render_kseft_overview_page() {
    ?>
    <div class="wrap">
        <h1>Přehled Akcí</h1>
        <?php echo do_shortcode('[kseft_overview]'); ?>
    </div>
    <?php
}

function my_team_plugin_kseft_overview_shortcode() {
    ob_start();
    ?>
    <div id="selected-role-display" style="margin-bottom: 20px; font-weight: bold; cursor: pointer;"></div>
    <?php include plugin_dir_path(__FILE__) . 'templates/role-selection-modal.php'; ?>
    <table id="kseft-overview-table">
        <thead>
            <tr>
                <th>Termín</th>
                <th>Lokace</th>
                <th>Název</th>
                <th>Akce</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $args = array(
                'post_type' => 'kseft', // Typ příspěvku
                'post_status' => 'publish', // Stav příspěvku
                'posts_per_page' => -1, // Počet příspěvků na stránku
                'meta_key' => 'kseft_event_date', // Klíč pro řazení
                'orderby' => 'meta_value', // Řazení podle meta hodnoty
                'order' => 'ASC' // Řazení vzestupně
            );
            $ksefty = new WP_Query($args); // Dotaz na příspěvky
            if ($ksefty->have_posts()) {
                while ($ksefty->have_posts()) {
                    $ksefty->the_post();
                    $event_date = get_post_meta(get_the_ID(), 'kseft_event_date', true); // Získání data události
                    $location = get_post_meta(get_the_ID(), 'kseft_location', true); // Získání lokace
                    $status = get_post_meta(get_the_ID(), 'kseft_status', true); // Získání stavu
                    $obsazeni_template_id = get_post_meta(get_the_ID(), 'kseft_obsazeni_template', true); // Získání ID šablony obsazení
                    $roles = get_post_meta($obsazeni_template_id, 'obsazeni_template_roles', true); // Získání rolí
                    $formatted_date = date_i18n('D d.m.Y', strtotime($event_date)); // Formátování data
                    ?>
                    <tr data-kseft-id="<?php echo get_the_ID(); ?>" data-role-ids='<?php echo json_encode($roles); ?>'>
                        <td><a href="<?php echo get_permalink(); ?>"><?php echo esc_html($formatted_date); ?></a></td> <!-- Odkaz na termín -->
                        <td><a href="<?php echo get_permalink(); ?>"><?php echo esc_html($location); ?></a></td> <!-- Odkaz na lokaci -->
                        <td><a href="<?php echo get_permalink(); ?>"><?php echo get_the_title(); ?></a></td> <!-- Odkaz na název -->
                        <td><button class="button confirm-role-button" data-kseft-id="<?php echo get_the_ID(); ?>">Potvrdit účast</button></td> <!-- Tlačítko pro potvrzení účasti -->
                    </tr>
                    <?php
                }
                wp_reset_postdata(); // Resetování dotazu
            } else {
                ?>
                <tr>
                    <td colspan="5">Žádné akce nejsou k dispozici.</td>
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
add_shortcode('kseft_overview', 'my_team_plugin_kseft_overview_shortcode'); // Přidání shortcode pro přehled kšeftů

function my_team_plugin_get_ksefty_by_role() {
    $role_id = intval($_POST['role_id']); // Získání ID role
    $ksefty = get_posts(array(
        'post_type' => 'kseft', // Typ příspěvku
        'numberposts' => -1, // Počet příspěvků
        'meta_query' => array(
            array(
                'key' => 'kseft_obsazeni_template', // Klíč pro porovnání
                'compare' => 'EXISTS' // Porovnání
            )
        )
    ));

    $kseft_ids = array();
    foreach ($ksefty as $kseft) {
        $obsazeni_template_id = get_post_meta($kseft->ID, 'kseft_obsazeni_template', true); // Získání ID šablony obsazení
        $roles = get_post_meta($obsazeni_template_id, 'obsazeni_template_roles', true); // Získání rolí
        if (is_array($roles) && in_array($role_id, $roles)) {
            $kseft_ids[] = $kseft->ID; // Přidání ID kšeftu do pole
        }
    }

    wp_send_json_success($kseft_ids); // Odeslání úspěšné zprávy
}
add_action('wp_ajax_get_ksefty_by_role', 'my_team_plugin_get_ksefty_by_role'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_get_ksefty_by_role', 'my_team_plugin_get_ksefty_by_role'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_get_role_details() {
    $role_id = intval($_POST['role_id']); // Získání ID role
    $default_player = get_post_meta($role_id, 'role_default_player', true); // Získání výchozího hráče
    $default_pickup_location = get_post_meta($role_id, 'role_default_pickup_location', true); // Získání výchozího místa vyzvednutí

    $response = array(
        'default_player' => $default_player,
        'default_pickup_location' => $default_pickup_location
    );

    wp_send_json_success($response); // Odeslání úspěšné zprávy
}
add_action('wp_ajax_get_role_details', 'my_team_plugin_get_role_details'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_get_role_details', 'my_team_plugin_get_role_details'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_get_role_status() {
    $kseft_id = intval($_POST['kseft_id']); // Získání ID kšeftu
    $role_id = intval($_POST['role_id']); // Získání ID role
    $role_status = get_post_meta($kseft_id, 'role_status_' . $role_id, true); // Získání stavu role

    $response = array(
        'role_status' => $role_status
    );

    wp_send_json_success($response); // Odeslání úspěšné zprávy
}
add_action('wp_ajax_get_role_status', 'my_team_plugin_get_role_status'); // Přidání AJAX akce pro přihlášené uživatele
add_action('wp_ajax_nopriv_get_role_status', 'my_team_plugin_get_role_status'); // Přidání AJAX akce pro nepřihlášené uživatele

function my_team_plugin_log_error($message) {
    // if (defined('WP_DEBUG') && WP_DEBUG === true) {
    //     if (is_array($message) || is_object($message)) {
    //         error_log(print_r($message, true));
    //     } else {
    //         error_log($message);
    //     }
    // }
}

/**
 * Generates HTML for a login form.
 * @param string $password_field_name The name attribute for the password input field.
 * @param string $title The title displayed above the form.
 * @param string $nonce_action The action name for the nonce.
 * @param string $nonce_field_name The name attribute for the nonce input field.
 * @return string HTML output for the login form.
 */
function my_team_plugin_get_login_form_html($password_field_name, $title, $nonce_action, $nonce_field_name) {
    $plugin_text_domain = 'my-band-plugin';
    ob_start();
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <title><?php echo esc_html($title); ?></title>
        <style>
            body { background-color: #f0f0f0; font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .login-container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
            .login-container h2 { margin-bottom: 20px; color: #0073aa; }
            .login-container input[type="password"] { width: 80%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; font-size: 16px; }
            .login-container input[type="submit"] { padding: 10px 20px; background-color: #0073aa; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            .login-container input[type="submit"]:hover { background-color: #005177; }
        </style>
        <?php wp_head(); ?>
    </head>
    <body>
        <div class="login-container">
            <h2><?php echo esc_html($title); ?></h2>
            <form method="post" action="<?php echo esc_url(get_permalink()); ?>">
                <input type="password" name="<?php echo esc_attr($password_field_name); ?>" placeholder="<?php esc_attr_e('Heslo', $plugin_text_domain); ?>">
                <br>
                <?php wp_nonce_field($nonce_action, $nonce_field_name); ?>
                <input type="submit" value="<?php esc_attr_e('Přihlásit', $plugin_text_domain); ?>">
            </form>
        </div>
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Generates HTML for a role selection form.
 * @param string $options_html HTML string of select options.
 * @param string $nonce_action The action name for the nonce.
 * @param string $nonce_field_name The name attribute for the nonce input field.
 * @return string HTML output for the role selection form.
 */
function my_team_plugin_get_role_selection_form($options_html, $nonce_action, $nonce_field_name) {
    $plugin_text_domain = 'my-band-plugin';
    ob_start();
    ?>
    <!DOCTYPE html>
     <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <title><?php esc_html_e('Výběr role', $plugin_text_domain); ?></title>
         <style>
            body { background-color: #f0f0f0; font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .login-container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
            .login-container h2 { margin-bottom: 20px; color: #0073aa; }
            .login-container select { width: 80%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; font-size: 16px; }
            .login-container input[type="submit"] { padding: 10px 20px; background-color: #0073aa; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            .login-container input[type="submit"]:hover { background-color: #005177; }
        </style>
        <?php wp_head(); ?>
    </head>
    <body>
        <div class="login-container">
            <h2><?php esc_html_e('Vyberte roli', $plugin_text_domain); ?></h2>
            <form method="post" action="<?php echo esc_url(get_permalink()); ?>">
                <select name="selected_role_id">
                    <?php echo $options_html; // Already escaped in generation ?>
                </select>
                <br>
                <?php wp_nonce_field($nonce_action, $nonce_field_name); ?>
                <input type="submit" value="<?php esc_attr_e('Potvrdit výběr', $plugin_text_domain); ?>">
            </form>
        </div>
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Handles password protection for specific pages for non-logged-in users.
 */
function my_team_plugin_check_password() {
    $plugin_text_domain = 'my-band-plugin';

    // For '/ksefty' page
    if (is_page('ksefty') && !is_user_logged_in()) {
        $ksefty_page_password = get_option('my_team_plugin_manage_kseft_password', 'heslo123'); // Default for safety
        $cookie_name = 'manageKseftAccess_' . COOKIEHASH;
        $nonce_action = 'my_team_plugin_ksefty_login_nonce';
        $nonce_field_name = 'manage_kseft_password_nonce';

        // Check if valid cookie exists
        if (isset($_COOKIE[$cookie_name]) && hash_equals($_COOKIE[$cookie_name], wp_hash($ksefty_page_password, 'my_band_plugin_ksefty_access'))) {
            return; // Valid cookie exists
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$nonce_field_name]) && wp_verify_nonce(sanitize_key(wp_unslash($_POST[$nonce_field_name])), $nonce_action)) {
            if (isset($_POST['manage_kseft_password']) && hash_equals(wp_hash(wp_unslash($_POST['manage_kseft_password']), 'my_band_plugin_ksefty_access'), wp_hash($ksefty_page_password, 'my_band_plugin_ksefty_access'))) {
                setcookie($cookie_name, wp_hash($ksefty_page_password, 'my_band_plugin_ksefty_access'), time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                wp_redirect(get_permalink());
                exit;
            } else {
                wp_die(
                    sprintf('<p style="color:red;">%s</p>', esc_html__('Přístup zamítnut. Nesprávné heslo.', $plugin_text_domain)) .
                    my_team_plugin_get_login_form_html('manage_kseft_password', __('Prosím zadejte heslo pro Kšefty', $plugin_text_domain), $nonce_action, $nonce_field_name),
                    __('Přihlášení', $plugin_text_domain),
                    array('response' => 403, 'back_link' => true)
                );
            }
        } else {
            // Display login form
             wp_die(
                my_team_plugin_get_login_form_html('manage_kseft_password', __('Prosím zadejte heslo pro Kšefty', $plugin_text_domain), $nonce_action, $nonce_field_name),
                __('Přihlášení', $plugin_text_domain),
                array('response' => 200, 'back_link' => true)
            );
        }
    }
    // For '/moje-ksefty' page
    elseif (is_page('moje-ksefty') && !is_user_logged_in()) {
        $selected_role_id_cookie_name = 'selectedRoleId_' . COOKIEHASH;
        $selected_role_text_cookie_name = 'selectedRoleText_' . COOKIEHASH;
        $allowed_roles_cookie_name = 'allowedRoles_' . COOKIEHASH;
        $role_password_nonce_action = 'my_team_plugin_role_password_login_nonce';
        $role_password_nonce_field = 'role_password_nonce';
        $role_selection_nonce_action = 'my_team_plugin_role_selection_nonce';
        $role_selection_nonce_field = 'selected_role_id_nonce';


        // If role cookie is invalid (e.g., "undefined"), clear it and related cookies
        if (isset($_COOKIE[$selected_role_id_cookie_name]) && ($_COOKIE[$selected_role_id_cookie_name] === 'undefined' || !ctype_digit(strval($_COOKIE[$selected_role_id_cookie_name])))) {
            setcookie($selected_role_id_cookie_name, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            setcookie($selected_role_text_cookie_name, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            setcookie($allowed_roles_cookie_name, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }

        // If a role was just selected from the form
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$role_selection_nonce_field]) && wp_verify_nonce(sanitize_key(wp_unslash($_POST[$role_selection_nonce_field])), $role_selection_nonce_action)) {
            if (isset($_POST['selected_role_id'])) {
                $posted_role_id = intval($_POST['selected_role_id']);
                $allowed_roles_from_cookie = isset($_COOKIE[$allowed_roles_cookie_name]) ? array_map('intval', explode(',', sanitize_text_field(wp_unslash($_COOKIE[$allowed_roles_cookie_name])))) : array();

                if (!empty($allowed_roles_from_cookie) && in_array($posted_role_id, $allowed_roles_from_cookie)) {
                    $role_obj = get_post($posted_role_id);
                    if ($role_obj && $role_obj->post_type === 'role') {
                        $role_title_val = $role_obj->post_title;
                        setcookie($selected_role_id_cookie_name, $posted_role_id, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                        setcookie($selected_role_text_cookie_name, urlencode($role_title_val), time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                        wp_redirect(get_permalink());
                        exit;
                    }
                }
            }
        }

        // If a valid role cookie already exists
        if (isset($_COOKIE[$selected_role_id_cookie_name]) && ctype_digit(strval($_COOKIE[$selected_role_id_cookie_name]))) {
             $check_role = get_post(intval($_COOKIE[$selected_role_id_cookie_name]));
             if ($check_role && $check_role->post_type === 'role') { return; }
             else { // clear cookies if role is no longer valid 
                setcookie($selected_role_id_cookie_name, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                setcookie($selected_role_text_cookie_name, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                // We might not want to clear allowed_roles_cookie_name here, as the password might still be valid for other roles.
             }
        }

        // No valid cookie, no valid POST for role selection, so process password or show form
        $roles_with_passwords = get_posts(array('post_type' => 'role', 'numberposts' => -1, 'meta_query' => array(array('key' => 'role_password', 'compare' => 'EXISTS'))));
        $role_passwords_map = array();
        foreach ($roles_with_passwords as $role_obj_item) {
            $password_meta = get_post_meta($role_obj_item->ID, 'role_password', true);
            if (!empty($password_meta)) {
                $role_passwords_map[$role_obj_item->ID] = $password_meta;
            }
        }

        $valid_password_entered = false;
        $matching_roles_for_password = array();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$role_password_nonce_field]) && wp_verify_nonce(sanitize_key(wp_unslash($_POST[$role_password_nonce_field])), $role_password_nonce_action)) {
            if (isset($_POST['role_password']) && !empty($_POST['role_password'])) {
                $submitted_password = wp_unslash($_POST['role_password']);
                foreach ($role_passwords_map as $role_id_map_item => $stored_password) {
                    if ($submitted_password === $stored_password) { // Plain text comparison.
                        $valid_password_entered = true;
                        $matching_roles_for_password[] = $role_id_map_item;
                    }
                }
            }
        }

        if ($valid_password_entered) {
            if (count($matching_roles_for_password) === 1) {
                $selected_role_id_val_single = $matching_roles_for_password[0];
                $role_obj_single = get_post($selected_role_id_val_single);
                $role_title_single = $role_obj_single ? $role_obj_single->post_title : __('Neznámá role', $plugin_text_domain);
                setcookie($selected_role_id_cookie_name, $selected_role_id_val_single, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                setcookie($selected_role_text_cookie_name, urlencode($role_title_single), time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                setcookie($allowed_roles_cookie_name, $selected_role_id_val_single, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                wp_redirect(get_permalink());
                exit;
            } else { // Multiple roles match the password
                setcookie($allowed_roles_cookie_name, implode(',', $matching_roles_for_password), time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                $options_html = '';
                foreach ($matching_roles_for_password as $role_id_option_item) {
                    $options_html .= sprintf('<option value="%s">%s</option>', esc_attr($role_id_option_item), esc_html(get_the_title($role_id_option_item)));
                }
                wp_die(
                    my_team_plugin_get_role_selection_form($options_html, $role_selection_nonce_action, $role_selection_nonce_field),
                    __('Výběr role', $plugin_text_domain),
                    array('response' => 200, 'back_link' => true)
                );
            }
        } else {
            $error_message_html = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role_password'])) ? sprintf('<p style="color:red;">%s</p>', esc_html__('Přístup zamítnut. Nesprávné heslo.', $plugin_text_domain)) : '';
            wp_die(
                $error_message_html . my_team_plugin_get_login_form_html('role_password', __('Prosím zadejte heslo pro Vaši roli', $plugin_text_domain), $role_password_nonce_action, $role_password_nonce_field),
                __('Přihlášení k roli', $plugin_text_domain),
                array('response' => ($_SERVER['REQUEST_METHOD'] === 'POST' ? 403 : 200), 'back_link' => true)
            );
        }
    }
}
add_action('template_redirect', 'my_team_plugin_check_password', 1);

function my_team_plugin_display_selected_role() {
    $plugin_text_domain = 'my-band-plugin';
    $selected_role_id_cookie_name = 'selectedRoleId_' . COOKIEHASH;
    $selected_role_text_cookie_name = 'selectedRoleText_' . COOKIEHASH;
    $allowed_roles_cookie_name = 'allowedRoles_' . COOKIEHASH;

    if (isset($_COOKIE[$selected_role_id_cookie_name]) && isset($_COOKIE[$selected_role_text_cookie_name])) {
        $role_title = urldecode($_COOKIE[$selected_role_text_cookie_name]);
        $allowed_roles = isset($_COOKIE[$allowed_roles_cookie_name]) ? explode(',', sanitize_text_field(wp_unslash($_COOKIE[$allowed_roles_cookie_name]))) : array();
        
        echo '<div id="selected-role-display" class="my-band-plugin-selected-role-footer" style="position: fixed; bottom: 10px; right: 10px; padding: 10px; background: #fff; border: 1px solid #ccc; box-shadow: 0 0 5px rgba(0,0,0,0.1); z-index: 1000; border-radius: 5px;">';
        echo esc_html__('Zvolená role:', $plugin_text_domain) . ' <strong>' . esc_html($role_title) . '</strong>';
        if (count($allowed_roles) > 1 || (count($allowed_roles) === 1 && $allowed_roles[0] !== $_COOKIE[$selected_role_id_cookie_name])) { // Show change if multiple roles allowed OR if the single allowed role is not the currently selected one (edge case)
            $clear_role_link = add_query_arg('clear_selected_role_mbp', '1', site_url('/moje-ksefty')); // Unique query arg
            echo ' (<a href="' . esc_url($clear_role_link) . '">' . esc_html__('změnit', $plugin_text_domain) . '</a>)';
        }
        echo '</div>';
    }
}
add_action('wp_footer', 'my_team_plugin_display_selected_role');

// Handle clearing of selected role cookie
function my_team_plugin_clear_selected_role_cookie() {
    if (isset($_GET['clear_selected_role_mbp']) && $_GET['clear_selected_role_mbp'] === '1' && is_page('moje-ksefty')) {
        $selected_role_id_cookie_name = 'selectedRoleId_' . COOKIEHASH;
        $selected_role_text_cookie_name = 'selectedRoleText_' . COOKIEHASH;
        // Do not clear allowedRolesCookie here, it's tied to password entry.
        // Only clear the specific choice of role.
        setcookie($selected_role_id_cookie_name, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        setcookie($selected_role_text_cookie_name, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        wp_redirect(remove_query_arg('clear_selected_role_mbp', site_url('/moje-ksefty')));
        exit;
    }
}
add_action('template_redirect', 'my_team_plugin_clear_selected_role_cookie', 0); // Run very early


function my_team_plugin_copy_kseft_init() { // Renamed to avoid conflict with potential future WP core function
    $plugin_text_domain = 'my-band-plugin';
    if (!isset($_GET['copy_kseft_id'])) {
        return;
    }
    $original_kseft_id = intval($_GET['copy_kseft_id']);

    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_GET['_wpnonce'])), 'copy_kseft_nonce_' . $original_kseft_id)) {
        wp_die(__('Neplatný požadavek na kopírování (chyba nonce).', $plugin_text_domain), __('Chyba', $plugin_text_domain), array('response' => 403));
    }

    // Assuming copy action should be restricted to users who can edit the original and create new ones.
    if (!current_user_can('edit_post', $original_kseft_id) || !current_user_can(get_post_type_object('kseft')->cap->create_posts)) {
        wp_die(__('Nemáte oprávnění k provedení této akce.', $plugin_text_domain), __('Chyba', $plugin_text_domain), array('response' => 403));
    }

    $original_kseft = get_post($original_kseft_id);

    if (!$original_kseft || $original_kseft->post_type !== 'kseft') {
        wp_die(__('Původní kšeft nebyl nalezen.', $plugin_text_domain), __('Chyba', $plugin_text_domain), array('response' => 404));
    }

    $new_kseft_data = array(
        'post_title' => $original_kseft->post_title . ' (' . __('Kopie', $plugin_text_domain) . ')',
        'post_content' => $original_kseft->post_content,
        'post_type' => 'kseft',
        'post_status' => 'draft',
        'post_author' => get_current_user_id(),
    );

    $new_kseft_id = wp_insert_post($new_kseft_data, true);

    if (is_wp_error($new_kseft_id)) {
        wp_die(sprintf(__('Chyba při kopírování kšeftu: %s', $plugin_text_domain), $new_kseft_id->get_error_message()), __('Chyba', $plugin_text_domain));
    }

    $meta_keys_to_copy = array(
        'kseft_location', 'kseft_meeting_time', 'kseft_performance_start',
        'kseft_performance_end', 'kseft_obsazeni_template', 'kseft_status',
        'kseft_clothing', 'kseft_responsible_for_drinks'
        // 'kseft_description' is main content, copied via post_content.
    );

    foreach ($meta_keys_to_copy as $meta_key) {
        $meta_value = get_post_meta($original_kseft_id, $meta_key, true);
        if ($meta_value !== '') {
            $sanitized_value = is_array($meta_value) ? array_map('sanitize_text_field', $meta_value) : sanitize_text_field($meta_value);
            update_post_meta($new_kseft_id, $meta_key, $sanitized_value);
        }
    }
    update_post_meta($new_kseft_id, 'kseft_event_date', date('Y-m-d'));

    $edit_url = add_query_arg(array('post' => $new_kseft_id, 'action' => 'edit'), admin_url('post.php'));
    wp_redirect($edit_url);
    exit;
}
add_action('template_redirect', 'my_team_plugin_copy_kseft_init');


function my_team_plugin_check_kseft_access() {
    check_ajax_referer('my_band_plugin_ajax_nonce', 'nonce');
    $plugin_text_domain = 'my-band-plugin';
    $kseft_id = isset($_POST['kseft_id']) ? intval($_POST['kseft_id']) : 0;

    if (empty($kseft_id)) {
        wp_send_json_error(array('message' => __('Chybí ID akce.', $plugin_text_domain)));
    }

    $current_user = wp_get_current_user();
    if (!$current_user->exists()) {
        wp_send_json_error(array('message' => __('Uživatel není přihlášen.', $plugin_text_domain)));
    }

    // This logic needs to be clearly defined. 'allowed_roles' meta key is not standard.
    // If it refers to WP roles for who can view/edit this kseft:
    $allowed_wp_roles_for_kseft = get_post_meta($kseft_id, 'allowed_wp_roles_for_kseft', true); // Example meta key
    if (empty($allowed_wp_roles_for_kseft) || !is_array($allowed_wp_roles_for_kseft)) {
         // Fallback to check if user can edit the post if no specific roles are defined for access
        if (!current_user_can('edit_post', $kseft_id)) {
            wp_send_json_error(array('message' => __('Nemáte oprávnění k přístupu k této akci (nejsou definována oprávnění).', $plugin_text_domain)));
        }
    } else {
        $user_has_access = false;
        foreach ($current_user->roles as $user_role_slug) {
            if (in_array($user_role_slug, $allowed_wp_roles_for_kseft)) {
                $user_has_access = true;
                break;
            }
        }
        if (!$user_has_access && !current_user_can('edit_others_posts')) { // Allow those who can edit any post
            wp_send_json_error(array('message' => __('Nemáte oprávnění k přístupu na tuto stránku (role).', $plugin_text_domain)));
        }
    }
    wp_send_json_success(array('message' => __('Přístup povolen.', $plugin_text_domain)));
}
add_action('wp_ajax_check_kseft_access', 'my_team_plugin_check_kseft_access');
// No nopriv for this as it checks logged-in user roles.


function my_team_plugin_restrict_kseft_access() {
    if (is_singular('kseft') && is_main_query()) {
        $kseft_id = get_the_ID();
        $plugin_text_domain = 'my-band-plugin';

        if (is_user_logged_in()) {
            // Logged-in users: Rely on WordPress's capabilities.
            // If 'kseft' CPT is 'public' => true, any logged-in user (even subscriber) can see it by default.
            // If you need finer control, you'd use custom capabilities with map_meta_cap or check specific roles.
            // Example: if (!current_user_can('read_kseft_post', $kseft_id)) { /* deny */ }
            return;
        }

        // Non-logged-in user access logic (based on cookie)
        $selected_role_id_cookie_name = 'selectedRoleId_' . COOKIEHASH;
        $selected_role_id = isset($_COOKIE[$selected_role_id_cookie_name]) ? intval($_COOKIE[$selected_role_id_cookie_name]) : 0;

        if (!$selected_role_id) {
            wp_redirect(site_url('/moje-ksefty?reason=no_role_selected&target_kseft=' . $kseft_id)); // Redirect to role selection/password page
            exit;
        }

        $obsazeni_template_id = get_post_meta($kseft_id, 'kseft_obsazeni_template', true);
        if (empty($obsazeni_template_id)) { // If kseft has no template, access might be denied or handled differently
            wp_redirect(site_url('/moje-ksefty?reason=no_template_assigned&target_kseft=' . $kseft_id));
            exit;
        }
        $template_roles = get_post_meta($obsazeni_template_id, 'obsazeni_template_roles', true);

        if (!is_array($template_roles) || !in_array($selected_role_id, $template_roles)) {
            wp_redirect(site_url('/moje-ksefty?reason=role_mismatch&target_kseft=' . $kseft_id)); // Role from cookie not in this kseft
            exit;
        }
    }
}
add_action('template_redirect', 'my_team_plugin_restrict_kseft_access', 5);
?>

[end of my-band-plugin/my-band-plugin.php]
