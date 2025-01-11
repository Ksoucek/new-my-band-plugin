<div id="role-confirmation-modal" style="display: none;">
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
                <div id="map"></div>
            </div>
            <div id="default-player-field" style="display: none;">
                <label for="default_player">Jméno hráče:</label>
                <input type="text" name="default_player" id="default_player" value="">
            </div>
            <button type="submit" class="button">Uložit</button>
            <button type="button" class="button" id="close-modal">Zavřít</button>
        </form>
    </div>
</div>
