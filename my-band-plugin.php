<?php
/*
Plugin Name: Muzikantské kšefty
Description: Umožňuje uživatelům vytvářet a spravovat týmy, přidávat členy a plánovat události.
Version: 1.0
Author: Vaše Jméno
*/

error_log('Muzikantské kšefty plugin loaded');

function my_team_plugin_enqueue_scripts() {
    wp_enqueue_script('my-team-plugin-script', plugins_url('/js/my-team-plugin.js', __FILE__), array('jquery'), '1.0', true);
    wp_localize_script('my-team-plugin-script', 'myTeamPlugin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'post_id' => get_the_ID() // Přidání post_id do lokalizovaného skriptu
     ));
    wp_enqueue_style('my-team-plugin-style', plugins_url('/css/my-team-plugin.css', __FILE__));
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
}
add_action('init', 'my_team_plugin_register_post_types');

// Přidání metaboxu pro výchozího hráče při tvorbě role
function my_team_plugin_add_role_meta_boxes() {
    add_meta_box('role_default_player', 'Výchozí hráč', 'my_team_plugin_render_role_default_player_meta_box', 'role', 'normal', 'high');
}
add_action('add_meta_boxes', 'my_team_plugin_add_role_meta_boxes');

function my_team_plugin_render_role_default_player_meta_box($post) {
    $default_player = get_post_meta($post->ID, 'role_default_player', true);
    ?>
    <label for="role_default_player">Výchozí hráč:</label>
    <input type="text" name="role_default_player" id="role_default_player" value="<?php echo esc_attr($default_player); ?>" size="25" />
    <?php
}

function my_team_plugin_save_role_meta_box_data($post_id) {
    if (array_key_exists('role_default_player', $_POST)) {
        update_post_meta($post_id, 'role_default_player', sanitize_text_field($_POST['role_default_player']));
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
    if ($ksefty->have_posts()) {
        $output = '<table>';
        $output .= '<thead><tr><th>Termín</th><th>Název</th><th>Umístění</th><th>Stav obsazení</th><th>Stav</th></tr></thead>';
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
            $output .= '<tr>';
            $output .= '<td>' . esc_html($event_date) . '</td>';
            $output .= '<td><a href="' . get_permalink() . '">' . get_the_title() . '</a></td>';
            $output .= '<td><a href="' . esc_url($location) . '" target="_blank">' . esc_html($location) . '</a></td>';
            $output .= '<td><a href="' . get_permalink() . '" class="button kseft-status-button ' . esc_attr($obsazeni_class) . '">' . esc_html($obsazeni_text) . '</a></td>';
            $output .= '<td>' . esc_html($status) . '</td>';
            $output .= '</tr>';
        }
        $output .= '</tbody>';
        $output .= '</table>';
        wp_reset_postdata();
    } else {
        $output = 'No ksefty found.';
    }
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
    $event_date = get_post_meta($post->ID(), 'kseft_event_date', true);
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
}
add_action('save_post', 'my_team_plugin_save_meta_box_data');

function my_team_plugin_add_kseft_meta_boxes() {
    add_meta_box('kseft_obsazeni_template', 'Šablona obsazení', 'my_team_plugin_render_kseft_obsazeni_template_meta_box', 'kseft', 'side', 'default');
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

function my_team_plugin_save_kseft_meta_box_data($post_id) {
    if (array_key_exists('kseft_obsazeni_template', $_POST)) {
        update_post_meta($post_id, 'kseft_obsazeni_template', sanitize_text_field($_POST['kseft_obsazeni_template']));
    }
}
add_action('save_post', 'my_team_plugin_save_kseft_meta_box_data');

function my_team_plugin_display_kseft_details($content) {
    if (is_singular('kseft')) {
        $location = get_post_meta(get_the_ID(), 'kseft_location', true);
        $meeting_time = get_post_meta(get_the_ID(), 'kseft_meeting_time', true);
        $event_date = get_post_meta(get_the_ID(), 'kseft_event_date', true);
        $status = get_post_meta(get_the_ID(), 'kseft_status', true);
        $obsazeni_template_id = get_post_meta(get_the_ID(), 'kseft_obsazeni_template', true);
        $obsazeni_template = get_post($obsazeni_template_id);
        $custom_content = '<h3>Detaily Kšeftu</h3>';
        $custom_content .= '<p><strong>Lokace:</strong> <a href="' . esc_url($location) . '" target="_blank">' . esc_html($location) . '</a></p>';
        $custom_content .= '<p><strong>Čas srazu:</strong> ' . esc_html($meeting_time) . '</p>';
        $custom_content .= '<p><strong>Datum kšeftu:</strong> ' . esc_html($event_date) . '</p>';
        $custom_content .= '<p><strong>Status:</strong> ' . esc_html($status) . '</p>';
        if ($obsazeni_template) {
            $custom_content .= '<h4>Obsazení:</h4>';
            $roles = get_post_meta($obsazeni_template_id, 'obsazeni_template_roles', true);
            if ($roles) {
                $custom_content .= '<ul>';
                foreach ($roles as $role_id) {
                    $role = get_post($role_id);
                    if ($role) {
                        $role_status = get_post_meta(get_the_ID(), 'role_status_' . $role_id, true);
                        $role_substitute = get_post_meta(get_the_ID(), 'role_substitute_' . $role_id, true);
                        $default_player = get_post_meta($role_id, 'role_default_player', true);
                        $pickup_location = get_post_meta(get_the_ID(), 'pickup_location_' . $role_id, true);
                        $button_class = 'role-confirmation';
                        $button_text = $role_status ?: 'Nepotvrzeno';
                        if ($role_status === 'Jdu') {
                            $button_class .= ' role-confirmation-jdu';
                        } elseif ($role_status === 'Záskok') {
                            $button_class .= ' role-confirmation-zaskok';
                        } else {
                            $button_class .= ' role-confirmation-nepotvrzeno';
                        }
                        $custom_content .= '<li>' . esc_html($role->post_title);
                        if ($role_status === 'Záskok') {
                            $custom_content .= ' (' . esc_html($role_substitute) . ')';
                        }
                        $custom_content .= ' <button class="button ' . esc_attr($button_class) . '" data-role-id="' . esc_attr($role_id) . '" data-default-player="' . esc_attr($default_player) . '" data-pickup-location="' . esc_attr($pickup_location) . '">' . esc_html($button_text) . '</button></li>';
                    }
                }
                $custom_content .= '</ul>';
            }
        }

        // Přidání tlačítek pro přechod na další nebo předchozí kšeft
        $prev_kseft = my_team_plugin_get_adjacent_kseft($event_date, 'prev');
        $next_kseft = my_team_plugin_get_adjacent_kseft($event_date, 'next');
        $custom_content .= '<div class="kseft-navigation" style="display: flex; justify-content: space-between; align-items: center;">';
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
                        <label for="default_player">Výchozí hráč:</label>
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

    update_post_meta($post_id, 'role_status_' . $role_id, $role_status);
    update_post_meta($post_id, 'role_substitute_' . $role_id, $role_substitute);
    update_post_meta($post_id, 'pickup_location_' . $role_id, $pickup_location);

    echo 'Účast byla potvrzena.';
    wp_die();
}
add_action('wp_ajax_save_role_confirmation', 'my_team_plugin_save_role_confirmation');
add_action('wp_ajax_nopriv_save_role_confirmation', 'my_team_plugin_save_role_confirmation');

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

?>
