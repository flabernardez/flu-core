<?php
/**
 * Back Button functionality for Fluvial Core
 * Allow pages to set custom back button destination
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Add meta box to pages for back button settings
 */
function flu_back_add_meta_box() {
    add_meta_box(
        'flu_back_button_settings',
        'Configuración del Botón Atrás',
        'flu_back_meta_box_callback',
        'page',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'flu_back_add_meta_box' );

/**
 * Meta box callback function
 */
function flu_back_meta_box_callback( $post ) {
    wp_nonce_field( 'flu_back_save_meta_box_data', 'flu_back_meta_box_nonce' );

    $selected_page = get_post_meta( $post->ID, '_flu_back_page_id', true );

    // Get all published pages
    $pages = get_pages( array(
        'sort_column' => 'post_title',
        'sort_order' => 'ASC',
        'post_status' => 'publish'
    ) );

    echo '<label for="flu_back_page_id">' . __('Página de destino al hacer clic en "Atrás":', 'flu-core') . '</label><br>';
    echo '<select id="flu_back_page_id" name="flu_back_page_id" style="width: 100%; margin-top: 10px;">';
    echo '<option value="">' . __('-- Comportamiento por defecto --', 'flu-core') . '</option>';

    foreach ( $pages as $page ) {
        $selected = selected( $selected_page, $page->ID, false );
        echo '<option value="' . esc_attr( $page->ID ) . '" ' . $selected . '>' . esc_html( $page->post_title ) . '</option>';
    }

    echo '</select>';
    echo '<p class="description" style="margin-top: 10px;">' . __('Selecciona la página a la que debe ir el botón "Atrás" del header. Si no seleccionas ninguna, usará el comportamiento por defecto del navegador.', 'flu-core') . '</p>';
}

/**
 * Save meta box data
 */
function flu_back_save_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['flu_back_meta_box_nonce'] ) ) {
        return;
    }

    if ( ! wp_verify_nonce( $_POST['flu_back_meta_box_nonce'], 'flu_back_save_meta_box_data' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_page', $post_id ) ) {
        return;
    }

    $back_page_id = isset( $_POST['flu_back_page_id'] ) ? intval( $_POST['flu_back_page_id'] ) : '';

    update_post_meta( $post_id, '_flu_back_page_id', $back_page_id );
}
add_action( 'save_post', 'flu_back_save_meta_box_data' );

/**
 * Add JavaScript functionality for back button
 */
function flu_back_add_functionality() {
    if ( ! is_page() ) {
        return;
    }

    $post_id = get_the_ID();
    $back_page_id = get_post_meta( $post_id, '_flu_back_page_id', true );

    if ( empty( $back_page_id ) ) {
        return; // No custom back page set, use default behavior
    }

    $back_page_url = get_permalink( $back_page_id );

    if ( ! $back_page_url ) {
        return;
    }

    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Buscar todos los botones/enlaces con la clase flu-back
            var backButtons = document.querySelectorAll('.flu-back');

            if (backButtons.length === 0) {
                console.log('No se encontraron botones con clase .flu-back');
                return;
            }

            console.log('Configurando botón atrás para ir a: <?php echo esc_js( $back_page_url ); ?>');

            backButtons.forEach(function(button) {
                // Si es un enlace, cambiar su href
                if (button.tagName === 'A') {
                    button.href = '<?php echo esc_js( $back_page_url ); ?>';
                } else {
                    // Si es un botón u otro elemento, añadir event listener
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        window.location.href = '<?php echo esc_js( $back_page_url ); ?>';
                    });
                }
            });
        });
    </script>
    <?php
}
add_action( 'wp_footer', 'flu_back_add_functionality' );
