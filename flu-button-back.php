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

    $pages = get_pages( array(
        'sort_column' => 'post_title',
        'sort_order' => 'ASC',
        'post_status' => 'publish'
    ) );

    echo '<label for="flu_back_page_id">' . __('Página de destino al hacer clic en "Atrás":', 'flu-core') . '</label><br>';
    echo '<select id="flu_back_page_id" name="flu_back_page_id" style="width: 100%; margin-top: 10px;">';
    echo '<option value="">' . __('-- Comportamiento por defecto --', 'flu-core') . '</option>';
    echo '<option value="history_back" ' . selected( $selected_page, 'history_back', false ) . '>' . __('← Página anterior (Historial)', 'flu-core') . '</option>';

    foreach ( $pages as $page ) {
        $selected = selected( $selected_page, $page->ID, false );
        echo '<option value="' . esc_attr( $page->ID ) . '" ' . $selected . '>' . esc_html( $page->post_title ) . '</option>';
    }

    echo '</select>';
    echo '<p class="description" style="margin-top: 10px;">' . __('Selecciona la página a la que debe ir el botón "Atrás" del header.', 'flu-core') . '</p>';
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

    $back_page_id = isset( $_POST['flu_back_page_id'] ) ? sanitize_text_field( $_POST['flu_back_page_id'] ) : '';

    update_post_meta( $post_id, '_flu_back_page_id', $back_page_id );
}
add_action( 'save_post', 'flu_back_save_meta_box_data' );

/**
 * Start output buffering to capture entire page HTML
 */
function flu_back_start_buffer() {
    if ( is_admin() ) {
        return;
    }
    ob_start( 'flu_back_process_buffer' );
}
add_action( 'template_redirect', 'flu_back_start_buffer', 1 );

/**
 * Process the buffered HTML to add href to links inside .flu-back
 */
function flu_back_process_buffer( $html ) {
    if ( empty( $html ) || strpos( $html, 'flu-back' ) === false ) {
        return $html;
    }

    // Obtener el post ID
    $post_id = get_queried_object_id();

    if ( ! $post_id ) {
        return $html;
    }

    $back_page_id = get_post_meta( $post_id, '_flu_back_page_id', true );

    if ( empty( $back_page_id ) ) {
        return $html;
    }

    // Detectar idioma
    $current_lang = function_exists( 'pll_current_language' ) ? pll_current_language() : 'es';
    $is_euskera = ( $current_lang === 'eu' );

    // Textos accesibles
    $aria_label = $is_euskera ? 'Atzera joan' : 'Volver atrás';
    $sr_text = $is_euskera ? 'Aurreko orrialdera itzuli' : 'Volver a la página anterior';

    // Determinar URL
    if ( $back_page_id === 'history_back' ) {
        $back_url = 'javascript:history.back()';
    } else {
        $back_url = get_permalink( $back_page_id );
        $back_title = get_the_title( $back_page_id );

        if ( ! $back_url ) {
            return $html;
        }

        $sr_text = $is_euskera
            ? 'Itzuli hona: ' . $back_title
            : 'Volver a: ' . $back_title;
    }

    // Buscar: <div ... class="...flu-back..." ...> ... <a ... href="..." ...> ... </a> ... </div>
    // El enlace está DENTRO del div con clase flu-back
    $pattern = '/(<div[^>]*class="[^"]*\bflu-back\b[^"]*"[^>]*>)(.*?)(<a\s+[^>]*)(href=")([^"]*)("[^>]*>)(.*?)(<\/a>)(.*?)(<\/div>)/si';

    $html = preg_replace_callback( $pattern, function( $matches ) use ( $back_url, $aria_label, $sr_text ) {
        $div_open = $matches[1];      // <div class="...flu-back...">
        $before_a = $matches[2];      // contenido antes del <a>
        $a_start = $matches[3];       // <a ...
        $href_attr = $matches[4];     // href="
        $old_href = $matches[5];      // valor antiguo del href (#)
        $a_rest = $matches[6];        // " resto de atributos>
        $a_content = $matches[7];     // contenido del enlace (svg)
        $a_close = $matches[8];       // </a>
        $after_a = $matches[9];       // contenido después del </a>
        $div_close = $matches[10];    // </div>

        // Eliminar aria-label existente si lo hay
        $a_start = preg_replace( '/\s+aria-label="[^"]*"/', '', $a_start );
        $a_rest = preg_replace( '/\s+aria-label="[^"]*"/', '', $a_rest );

        // Construir el enlace con nuevos atributos
        $new_a = $a_start . 'href="' . esc_attr( $back_url ) . '" aria-label="' . esc_attr( $aria_label ) . '"' . $a_rest;

        // Añadir span para screen readers
        $sr_span = '<span class="screen-reader-text">' . esc_html( $sr_text ) . '</span>';

        return $div_open . $before_a . $new_a . $a_content . $sr_span . $a_close . $after_a . $div_close;
    }, $html );

    return $html;
}

/**
 * Add JavaScript for keyboard support
 */
function flu_back_add_functionality() {
    if ( ! is_page() ) {
        return;
    }

    $post_id = get_the_ID();
    $back_page_id = get_post_meta( $post_id, '_flu_back_page_id', true );

    if ( empty( $back_page_id ) || $back_page_id !== 'history_back' ) {
        return;
    }

    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var backContainers = document.querySelectorAll('.flu-back');

            backContainers.forEach(function(container) {
                var link = container.querySelector('a');
                if (link) {
                    link.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            window.history.back();
                        }
                    });
                }
            });
        });
    </script>
    <?php
}
add_action( 'wp_footer', 'flu_back_add_functionality' );

/**
 * Add CSS for screen reader text and focus styles
 */
function flu_back_add_accessibility_css() {
    ?>
    <style>
        .screen-reader-text {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }

        .flu-back a:focus {
            outline: 2px solid var(--wp--preset--color--accent, #00ff88);
            outline-offset: 2px;
        }

        .flu-back a:focus:not(:focus-visible) {
            outline: none;
        }

        .flu-back a:focus-visible {
            outline: 2px solid var(--wp--preset--color--accent, #00ff88);
            outline-offset: 2px;
        }
    </style>
    <?php
}
add_action( 'wp_head', 'flu_back_add_accessibility_css' );
