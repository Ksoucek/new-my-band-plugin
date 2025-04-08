<?php
/*
Template Name: Invoice Overview
*/

get_header(); // Načtení hlavičky šablony

$args = array(
    'post_type' => 'kseft',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'meta_key' => 'kseft_event_date', // Klíč pro datum kšeftu
    'orderby' => 'meta_value',
    'order' => 'ASC' // Řazení od minulosti po budoucnost
);

$ksefty = new WP_Query($args);
?>
<div class="wrap">
    <h1>Přehled Faktur</h1>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Název Kšeftu</th>
                <th>Částka</th>
                <th>Status</th>
                <th>Akce</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($ksefty->have_posts()) : ?>
                <?php while ($ksefty->have_posts()) : $ksefty->the_post(); ?>
                    <?php
                    $post_id = get_the_ID();
                    $invoice_data = get_post_meta($post_id, 'invoice_data', true);
                    $status = isset($invoice_data['status']) ? $invoice_data['status'] : 'Nová';
                    ?>
                    <tr>
                        <td><?php the_title(); ?></td>
                        <td><?php echo isset($invoice_data['amount']) ? esc_html($invoice_data['amount']) . ' Kč' : 'N/A'; ?></td>
                        <td>
                            <select class="invoice-status" data-post-id="<?php echo esc_attr($post_id); ?>">
                                <option value="Nová" <?php selected($status, 'Nová'); ?>>Nová</option>
                                <option value="Vytvořena faktura" <?php selected($status, 'Vytvořena faktura'); ?>>Vytvořena faktura</option>
                                <option value="Zaplaceno" <?php selected($status, 'Zaplaceno'); ?>>Zaplaceno</option>
                            </select>
                        </td>
                        <td>
                            <a href="<?php echo add_query_arg('invoice_id', $post_id, site_url('/invoice-details')); ?>" class="button">Otevřít</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else : ?>
                <tr>
                    <td colspan="4">Žádné faktury nejsou k dispozici.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<script>
    jQuery(document).ready(function($) {
        $('.invoice-status').on('change', function() {
            var postId = $(this).data('post-id');
            var status = $(this).val();
            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'update_invoice_status',
                post_id: postId,
                status: status,
                nonce: '<?php echo wp_create_nonce('update_invoice_status'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Status byl úspěšně aktualizován.');
                } else {
                    alert('Chyba při aktualizaci statusu.');
                }
            });
        });
    });
</script>
<?php
wp_reset_postdata(); // Resetování smyčky
get_footer(); // Načtení patičky šablony
?>
