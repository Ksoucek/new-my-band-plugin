<div id="role-selection-modal" style="display: none;">
    <div class="modal-content">
        <h3>Vyberte roli</h3>
        <form id="role-selection-form">
            <label for="initial_role_id">Vyberte roli:</label>
            <select name="initial_role_id" id="initial_role_id">
                <option value="">-- Vyberte roli --</option>
                <?php
                $roles = get_posts(array('post_type' => 'role', 'numberposts' => -1));
                $allowed_roles = isset($_COOKIE['allowedRoles']) ? explode(',', $_COOKIE['allowedRoles']) : array();
                $confirm_anyone = false;
                foreach ($roles as $role) {
                    if (get_post_meta($role->ID, 'role_confirm_anyone', true)) {
                        $confirm_anyone = true;
                        break;
                    }
                }
                foreach ($roles as $role) {
                    if ($confirm_anyone || in_array($role->ID, $allowed_roles)) {
                        echo '<option value="' . esc_attr($role->ID) . '">' . esc_html($role->post_title) . '</option>';
                    }
                }
                ?>
            </select>
            <button type="submit" class="button">Uložit</button>
        </form>
    </div>
</div>
