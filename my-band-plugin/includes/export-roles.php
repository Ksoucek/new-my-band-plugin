<?php
// Kontrola přístupu
if (!defined('ABSPATH')) {
    exit;
}

function my_band_plugin_export_roles() {
    try {
        // Kontrola oprávnění
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte dostatečná oprávnění pro tento export.');
        }

        // Získání všech rolí
        $roles = get_posts(array(
            'post_type' => 'role',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        if (empty($roles)) {
            wp_die('Nebyly nalezeny žádné role k exportu.');
        }

        // Příprava dat pro export
        $export_data = array();
        foreach ($roles as $role) {
            $export_data[] = array(
                'role_name' => $role->post_title,
                'role_id' => $role->ID,
                'password' => get_post_meta($role->ID, 'role_password', true),
                'default_player' => get_post_meta($role->ID, 'role_default_player', true),
                'default_pickup_location' => get_post_meta($role->ID, 'role_default_pickup_location', true),
                'confirm_anyone' => get_post_meta($role->ID, 'role_confirm_anyone', true) ? 'Ano' : 'Ne'
            );
        }

        // Nastavení hlaviček pro download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=role-export-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Otevření výstupního streamu
        $output = fopen('php://output', 'w');
        if ($output === false) {
            throw new Exception('Nelze vytvořit výstupní soubor.');
        }

        // BOM pro Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Zápis hlavičky
        fputcsv($output, array(
            'Název role',
            'ID role',
            'Heslo',
            'Výchozí hráč',
            'Výchozí místo vyzvednutí',
            'Může potvrdit kdokoliv'
        ), ';');

        // Zápis dat
        foreach ($export_data as $row) {
            fputcsv($output, array(
                $row['role_name'],
                $row['role_id'],
                $row['password'],
                $row['default_player'],
                $row['default_pickup_location'],
                $row['confirm_anyone']
            ), ';');
        }

        fclose($output);
        exit;

    } catch (Exception $e) {
        error_log('Export rolí selhal: ' . $e->getMessage());
        wp_die('Došlo k chybě při exportu: ' . esc_html($e->getMessage()));
    }
}

// Přidání admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=role',
        'Export rolí',
        'Export rolí',
        'manage_options',
        'export-roles',
        'my_band_plugin_render_export_page'
    );
});

// Render stránky
function my_band_plugin_render_export_page() {
    ?>
    <div class="wrap">
        <h1>Export rolí</h1>
        <p>Kliknutím na tlačítko níže exportujete seznam všech rolí včetně hesel a dalších nastavení.</p>
        <form method="post" action="">
            <?php wp_nonce_field('export_roles_action', 'export_roles_nonce'); ?>
            <input type="submit" name="export_roles" class="button button-primary" value="Exportovat role">
        </form>
    </div>
    <?php
}

// Zpracování exportu
add_action('admin_init', function() {
    if (
        isset($_POST['export_roles']) && 
        isset($_POST['export_roles_nonce']) && 
        wp_verify_nonce($_POST['export_roles_nonce'], 'export_roles_action')
    ) {
        try {
            my_band_plugin_export_roles();
        } catch (Exception $e) {
            error_log('Export rolí selhal: ' . $e->getMessage());
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html('Došlo k chybě při exportu: ' . $e->getMessage()); ?></p>
                </div>
                <?php
            });
        }
    }
}); 