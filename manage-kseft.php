<?php

function my_band_plugin_log_error($message) {
    if (WP_DEBUG === true) {
        error_log($message);
    }
} 