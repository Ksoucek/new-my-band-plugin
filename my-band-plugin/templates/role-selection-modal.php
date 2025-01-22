<div id="role-selection-modal" style="display: none;">
    <div class="modal-content">
        <h3>Vyberte roli</h3>
        <form id="role-selection-form">
            <label for="initial_role_id">Vyberte roli:</label>
            <select name="initial_role_id" id="initial_role_id">
                <option value="">-- Vyberte roli --</option>
                <?php
                $roles = get_posts(array('post_type' => 'role', 'numberposts' => -1));
                foreach ($roles as $role) {
                    echo '<option value="' . esc_attr($role->ID) . '">' . esc_html($role->post_title) . '</option>';
                }
                ?>
            </select>
            <button type="submit" class="button">Uložit</button>
        </form>
    </div>
</div>
