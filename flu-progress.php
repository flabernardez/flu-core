<?php
/**
 * Progress tracking functionality for Fluvial Core
 * Track visited pages using cookies and update visual elements
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Add CSS for visited elements
 */
function flu_core_add_visited_css() {
    echo '<style>
        .progreso .visited hr.wp-block-separator.has-custom-white-background-color.has-background {
            background-color: var(--wp--preset--color--custom-green) !important;
            color: var(--wp--preset--color--custom-green) !important;
        }
        
        .progreso .visited .wp-block-group.has-border-color.has-custom-green-color.has-text-color.has-link-color {
            background-color: var(--wp--preset--color--custom-green) !important;
        }
        .progreso .visited .wp-block-group.has-border-color.has-custom-green-color.has-text-color.has-link-color h3 a {
            color: var(--wp--preset--color--custom-white) !important;
        }
        
        /* Virus capture states */
        .progreso .sin-capturar {
            display: block;
        }
        .progreso .capturado {
            display: none;
        }
        .progreso .captured .sin-capturar {
            display: none;
        }
        .progreso .captured .capturado {
            display: block;
        }
    </style>';
}
add_action( 'wp_head', 'flu_core_add_visited_css' );

/**
 * Track visited pages using cookies (1 year duration)
 */
function flu_core_track_page_visit() {
    if ( ! is_page() ) {
        return;
    }

    $current_page_id = get_the_ID();
    $cookie_name = 'flu_visited_pages';
    $captured_cookie_name = 'flu_captured_pages';
    $visited = [];
    $captured = [];

    // Get existing visited pages from cookie
    if ( isset( $_COOKIE[ $cookie_name ] ) ) {
        $visited = json_decode( stripslashes( $_COOKIE[ $cookie_name ] ), true );
        if ( ! is_array( $visited ) ) {
            $visited = [];
        }
    }

    // Get existing captured pages from cookie
    if ( isset( $_COOKIE[ $captured_cookie_name ] ) ) {
        $captured = json_decode( stripslashes( $_COOKIE[ $captured_cookie_name ] ), true );
        if ( ! is_array( $captured ) ) {
            $captured = [];
        }
    }

    // Add current page if not already visited
    if ( ! in_array( $current_page_id, $visited ) ) {
        $visited[] = $current_page_id;

        // Set cookie for 1 year
        $expire_time = time() + ( 365 * 24 * 60 * 60 ); // 1 year
        setcookie( $cookie_name, json_encode( $visited ), $expire_time, '/' );
    }

    // Check if URL contains #atrapado and track capture
    if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '#atrapado' ) !== false ) {
        if ( ! in_array( $current_page_id, $captured ) ) {
            $captured[] = $current_page_id;
            setcookie( $captured_cookie_name, json_encode( $captured ), $expire_time, '/' );
        }
    }
}
add_action( 'template_redirect', 'flu_core_track_page_visit' );

/**
 * AJAX handler to track visited pages from links
 */
function flu_core_ajax_track_visit() {
    $page_id = intval( $_POST['page_id'] );

    if ( ! $page_id ) {
        wp_send_json_error( 'Invalid page ID' );
    }

    $cookie_name = 'flu_visited_pages';
    $visited = [];

    // Get existing visited pages from cookie
    if ( isset( $_COOKIE[ $cookie_name ] ) ) {
        $visited = json_decode( stripslashes( $_COOKIE[ $cookie_name ] ), true );
        if ( ! is_array( $visited ) ) {
            $visited = [];
        }
    }

    // Add page if not already visited
    if ( ! in_array( $page_id, $visited ) ) {
        $visited[] = $page_id;

        // Set cookie for 1 year
        $expire_time = time() + ( 365 * 24 * 60 * 60 );
        setcookie( $cookie_name, json_encode( $visited ), $expire_time, '/' );
    }

    wp_send_json_success( 'Page tracked' );
}
add_action( 'wp_ajax_flu_core_track_visit', 'flu_core_ajax_track_visit' );
add_action( 'wp_ajax_nopriv_flu_core_track_visit', 'flu_core_ajax_track_visit' );

/**
 * AJAX handler to track captured pages (reached #atrapado)
 */
function flu_core_ajax_track_capture() {
    $page_id = intval( $_POST['page_id'] );

    if ( ! $page_id ) {
        wp_send_json_error( 'Invalid page ID' );
    }

    $cookie_name = 'flu_captured_pages';
    $captured = [];

    // Get existing captured pages from cookie
    if ( isset( $_COOKIE[ $cookie_name ] ) ) {
        $captured = json_decode( stripslashes( $_COOKIE[ $cookie_name ] ), true );
        if ( ! is_array( $captured ) ) {
            $captured = [];
        }
    }

    // Add page if not already captured
    if ( ! in_array( $page_id, $captured ) ) {
        $captured[] = $page_id;

        // Set cookie for 1 year
        $expire_time = time() + ( 365 * 24 * 60 * 60 );
        setcookie( $cookie_name, json_encode( $captured ), $expire_time, '/' );
    }

    wp_send_json_success( 'Page captured' );
}
add_action( 'wp_ajax_flu_core_track_capture', 'flu_core_ajax_track_capture' );
add_action( 'wp_ajax_nopriv_flu_core_track_capture', 'flu_core_ajax_track_capture' );

/**
 * Add visited class to elements and track clicks
 */
function flu_core_add_visited_functionality() {
    ?>
    <script>
        function getCookie(name) {
            const value = "; " + document.cookie;
            const parts = value.split("; " + name + "=");
            if (parts.length === 2) return parts.pop().split(";").shift();
            return null;
        }

        function getVisitedPages() {
            const cookie = getCookie('flu_visited_pages');
            if (cookie) {
                try {
                    return JSON.parse(decodeURIComponent(cookie));
                } catch (e) {
                    return [];
                }
            }
            return [];
        }

        function getCapturedPages() {
            const cookie = getCookie('flu_captured_pages');
            if (cookie) {
                try {
                    return JSON.parse(decodeURIComponent(cookie));
                } catch (e) {
                    return [];
                }
            }
            return [];
        }

        function markVisitedElements() {
            const visitedPages = getVisitedPages();
            const capturedPages = getCapturedPages();
            const progressoLoops = document.querySelectorAll('.wp-block-query.progreso');

            progressoLoops.forEach(function(loop) {
                const listItems = loop.querySelectorAll('li[class*="post-"]');

                listItems.forEach(function(li) {
                    const classList = li.className;
                    const match = classList.match(/post-(\d+)/);

                    if (match && match[1]) {
                        const pageId = parseInt(match[1]);

                        if (visitedPages.includes(pageId)) {
                            li.classList.add('visited');
                        }

                        if (capturedPages.includes(pageId)) {
                            li.classList.add('captured');
                        }
                    }
                });
            });
        }

        function updateCapturedLinks() {
            const capturedPages = getCapturedPages();
            const progressoLoops = document.querySelectorAll('.wp-block-query.progreso');

            progressoLoops.forEach(function(loop) {
                const listItems = loop.querySelectorAll('li[class*="post-"]');

                listItems.forEach(function(li) {
                    const classList = li.className;
                    const match = classList.match(/post-(\d+)/);

                    if (match && match[1]) {
                        const pageId = parseInt(match[1]);

                        // Si el virus está capturado, modificar todos los enlaces dentro del <li>
                        if (capturedPages.includes(pageId)) {
                            const links = li.querySelectorAll('a[href]');

                            links.forEach(function(link) {
                                const href = link.getAttribute('href');

                                // Solo modificar si el href apunta a una página (no a anclas o externos)
                                if (href && !href.startsWith('#') && !href.startsWith('mailto:') && !href.startsWith('tel:')) {
                                    // Eliminar cualquier hash existente y añadir #capturado
                                    const cleanHref = href.split('#')[0];
                                    const newHref = cleanHref + '#capturado';

                                    // Solo actualizar si es diferente
                                    if (href !== newHref) {
                                        link.setAttribute('href', newHref);
                                        console.log('Link actualizado para virus capturado:', pageId, '->', newHref);
                                    }
                                }
                            });
                        }
                    }
                });
            });
        }

        function trackLinkClicks() {
            const progressoLoops = document.querySelectorAll('.wp-block-query.progreso');

            progressoLoops.forEach(function(loop) {
                const links = loop.querySelectorAll('a[href]');

                links.forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        const listItem = this.closest('li[class*="post-"]');
                        if (listItem) {
                            const classList = listItem.className;
                            const match = classList.match(/post-(\d+)/);

                            if (match && match[1]) {
                                const pageId = match[1];

                                // Track via AJAX
                                const xhr = new XMLHttpRequest();
                                xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

                                const data = 'action=flu_core_track_visit&page_id=' + pageId;
                                xhr.send(data);
                            }
                        }
                    });
                });
            });
        }

        function trackCaptureWhenAtrapado() {
            // Check if current page has #atrapado in URL and track it
            if (window.location.hash === '#atrapado') {
                const currentPageId = document.body.className.match(/page-id-(\d+)/);
                if (currentPageId && currentPageId[1]) {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

                    const data = 'action=flu_core_track_capture&page_id=' + currentPageId[1];
                    xhr.send(data);

                    // Actualizar los enlaces después de capturar
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            console.log('Virus capturado, actualizando enlaces...');
                            // Recargar las cookies y actualizar enlaces
                            setTimeout(function() {
                                markVisitedElements();
                                updateCapturedLinks();
                            }, 100);
                        }
                    };
                }
            }

            // Listen for hash changes to detect when someone reaches #atrapado
            window.addEventListener('hashchange', function() {
                if (window.location.hash === '#atrapado') {
                    const currentPageId = document.body.className.match(/page-id-(\d+)/);
                    if (currentPageId && currentPageId[1]) {
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

                        const data = 'action=flu_core_track_capture&page_id=' + currentPageId[1];
                        xhr.send(data);

                        // Actualizar los enlaces después de capturar
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                console.log('Virus capturado, actualizando enlaces...');
                                // Recargar las cookies y actualizar enlaces
                                setTimeout(function() {
                                    markVisitedElements();
                                    updateCapturedLinks();
                                }, 100);
                            }
                        };
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            markVisitedElements();
            updateCapturedLinks(); // NUEVA LÍNEA: Actualizar enlaces al cargar la página
            trackLinkClicks();
            trackCaptureWhenAtrapado();
        });
    </script>
    <?php
}
add_action( 'wp_footer', 'flu_core_add_visited_functionality' );

/**
 * Update visual elements based on visited pages
 */
function flu_core_update_progress_elements( $content ) {
    // Only modify if .progreso query loop exists
    if ( strpos( $content, 'wp-block-query progreso' ) === false ) {
        return $content;
    }

    $cookie_name = 'flu_visited_pages';
    $visited = [];

    // Get visited pages from cookie
    if ( isset( $_COOKIE[ $cookie_name ] ) ) {
        $visited = json_decode( stripslashes( $_COOKIE[ $cookie_name ] ), true );
        if ( ! is_array( $visited ) ) {
            $visited = [];
        }
    }

    if ( empty( $visited ) ) {
        return $content;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors( true );
    $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $content );
    libxml_clear_errors();

    $xpath = new DOMXPath( $dom );

    // Find all <li> elements in .progreso query loops
    foreach ( $xpath->query( "//div[contains(@class, 'wp-block-query') and contains(@class, 'progreso')]//li[contains(@class, 'post-')]" ) as $li ) {
        // Get post/page ID from class "post-123"
        preg_match( '/post-(\d+)/', $li->getAttribute( 'class' ), $matches );
        $post_id = isset( $matches[1] ) ? intval( $matches[1] ) : 0;

        if ( $post_id && in_array( $post_id, $visited ) ) {
            // Change .wp-block-separator background color
            foreach ( $xpath->query( ".//*[contains(@class, 'wp-block-separator') and contains(@class, 'has-custom-white-background-color')]", $li ) as $separator ) {
                $class = $separator->getAttribute( 'class' );
                $class = str_replace( 'has-custom-white-background-color', 'has-custom-green-background-color', $class );
                $separator->setAttribute( 'class', $class );
            }

            // Update wp-block-group elements
            foreach ( $xpath->query( ".//*[contains(@class, 'wp-block-group') and contains(@class, 'has-border-color') and contains(@class, 'has-custom-green-color')]", $li ) as $group ) {
                $class = $group->getAttribute( 'class' );

                // Add green background and white text classes if not present
                if ( strpos( $class, 'has-custom-green-background-color' ) === false ) {
                    $class .= ' has-custom-green-background-color';
                }
                if ( strpos( $class, 'has-custom-white-color' ) === false ) {
                    $class .= ' has-custom-white-color';
                }

                $group->setAttribute( 'class', $class );
            }
        }
    }

    // Clean up and return
    $content = $dom->saveHTML( $dom->documentElement );
    $content = preg_replace( '/^<!DOCTYPE.+?>/', '', str_replace( [ '<html>', '</html>', '<body>', '</body>' ], '', $content ) );

    return $content;
}
add_filter( 'the_content', 'flu_core_update_progress_elements', 12 );

/**
 * Handle AJAX request to reset visited pages cookies
 */
function flu_core_reset_visited_pages() {
    // Verificar nonce de seguridad
    if ( ! wp_verify_nonce( $_POST['nonce'], 'flu_core_reset_nonce' ) ) {
        wp_die( 'Error de seguridad' );
    }

    // Clear progress cookies
    $cookie_name = 'flu_visited_pages';
    $captured_cookie_name = 'flu_captured_pages';
    setcookie( $cookie_name, '', time() - 3600, '/' );
    setcookie( $captured_cookie_name, '', time() - 3600, '/' );

    // Clear permissions cookie
    $permissions_cookie_name = 'flu_permissions';
    setcookie( $permissions_cookie_name, '', time() - 3600, '/' );

    wp_send_json_success( 'Progreso y permisos reseteados correctamente' );
}
add_action( 'wp_ajax_flu_core_reset_visited', 'flu_core_reset_visited_pages' );
add_action( 'wp_ajax_nopriv_flu_core_reset_visited', 'flu_core_reset_visited_pages' );

/**
 * Enqueue script for reset button functionality
 */
function flu_core_enqueue_reset_script() {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var processed = false;
            var resetButtons = document.querySelectorAll('.reset-cookies');

            resetButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (processed) {
                        return false;
                    }

                    if (!confirm('¿Seguro que quieres resetear tu progreso? Esta acción no se puede deshacer.')) {
                        return false;
                    }

                    processed = true;

                    var buttonLink = this.querySelector('a.wp-block-button__link');
                    if (!buttonLink) {
                        processed = false;
                        return false;
                    }

                    var originalText = buttonLink.textContent;
                    buttonLink.style.pointerEvents = 'none';
                    buttonLink.textContent = 'Reseteando...';

                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                alert('Progreso reseteado correctamente. La página se recargará.');
                                location.reload();
                            } else {
                                alert('Error: ' + response.data);
                                buttonLink.style.pointerEvents = 'auto';
                                buttonLink.textContent = originalText;
                                processed = false;
                            }
                        } else {
                            alert('Error de conexión');
                            buttonLink.style.pointerEvents = 'auto';
                            buttonLink.textContent = originalText;
                            processed = false;
                        }
                    };

                    xhr.onerror = function() {
                        alert('Error de conexión');
                        buttonLink.style.pointerEvents = 'auto';
                        buttonLink.textContent = originalText;
                        processed = false;
                    };

                    var data = 'action=flu_core_reset_visited&nonce=<?php echo wp_create_nonce('flu_core_reset_nonce'); ?>';
                    xhr.send(data);

                    return false;
                });
            });
        });
    </script>
    <?php
}
add_action( 'wp_footer', 'flu_core_enqueue_reset_script' );
