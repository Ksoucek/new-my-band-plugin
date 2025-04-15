<?php
/*
Template Name: Invoice Overview
*/

get_header(); // Načtení hlavičky šablony
?>
<style>
    body {
        background-color: rgb(31, 31, 31); /* Šedé pozadí stránky */
        color: #fff; /* Bílý text */
        font-family: Arial, sans-serif;
    }
    table.invoice-overview {
        background-color: rgb(31, 31, 31); /* Šedé pozadí tabulky */
        border-collapse: collapse;
        width: 100%;
        margin-top: 20px;
    }
    table.invoice-overview th, table.invoice-overview td {
        padding: 10px;
        border: 1px solid #ddd; /* Jemné ohraničení */
        color: #fff; /* Bílý text */
    }
    table.invoice-overview th {
        background-color: rgb(20, 20, 20); /* O něco tmavší šedá pro hlavičky */
        text-align: left;
    }
    table.invoice-overview tr:nth-child(even) {
        background-color: rgb(40, 40, 40); /* Jemné střídání barev řádků */
    }
    table.invoice-overview tr:hover {
        background-color: rgb(50, 50, 50); /* Zvýraznění při najetí myší */
    }
    select.invoice-status {
        background-color: rgb(20, 20, 20); /* Stejná barva jako tabulka */
        color: #fff; /* Bílý text */
        border: none;
        padding: 5px 10px;
        border-radius: 20px; /* Kulaté rohy */
        width: 100%;
        box-sizing: border-box;
    }
    select.invoice-status:focus {
        outline: none;
        box-shadow: 0 0 5px rgb(50, 50, 50); /* Zvýraznění při zaostření */
    }
    .button {
        background-color: #0073aa; /* Barva tlačítek */
        color: #fff; /* Bílý text */
        border: none;
        padding: 10px 20px;
        border-radius: 20px; /* Kulaté rohy */
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        margin-top: 10px;
    }
    .button:hover {
        color: #fff; /* Zachování bílé barvy textu při přejetí myší */
    }
</style>
<?php
$today = current_time('Y-m-d');
$args = array(
    'post_type'      => 'kseft',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_key'       => 'kseft_event_date', // Klíč pro datum kšeftu
    'orderby'        => 'meta_value',
    'order'          => 'ASC' // Řazení od minulosti po budoucnost
);
if ( !isset($_GET['all']) ) {
    $args['meta_query'] = array(
        array(
            'key'     => 'kseft_event_date',
            'value'   => $today,
            'compare' => '>=',
            'type'    => 'DATE'
        )
    );
}

$ksefty = new WP_Query($args);
?>
<div class="wrap">
    <h1>Přehled Faktur</h1>
    <form method="get" style="text-align: center; margin-bottom: 20px;">
        <label>
            <input type="checkbox" name="all" value="1" <?php if(isset($_GET['all'])) echo 'checked="checked"'; ?>>
            Zobrazit všechny faktury
        </label>
        <button type="submit" class="button">Použít</button>
    </form>
    <table class="invoice-overview">
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
                            <span class="invoice-status"><?php echo esc_html($status); ?></span> <!-- Status je nyní pouze pro čtení -->
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
