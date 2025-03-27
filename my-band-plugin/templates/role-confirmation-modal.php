<div id="role-confirmation-modal" style="display: none;">
    <div class="modal-content">
        <h3>Potvrdit účast</h3>
        <form id="role-confirmation-form">
            <?php wp_nonce_field('role_confirmation_action', 'role_confirmation_nonce'); ?>
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
            <button type="submit" class="button">Uložit</button>
            <button type="button" class="button" id="close-modal">Zavřít</button>
        </form>
    </div>
</div>
