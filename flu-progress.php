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

                    // DESPUÉS de capturar, verificar si la región está completa
                    setTimeout(checkRegionCompletion, 1000);
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

                        // DESPUÉS de capturar, verificar si la región está completa
                        setTimeout(checkRegionCompletion, 1000);
                    }
                }
            });
        }

        function checkRegionCompletion() {
            const url = window.location.pathname;
            let region = null;

            // Solo detectar español por ahora
            if (url.match(/^\/virus\/(arga|ultzama)\//)) {
                region = url.match(/^\/virus\/(arga|ultzama)\//)[1];
            } else {
                return; // No es una página de virus español
            }

            console.log('Verificando región completada:', region);

            // Obtener páginas capturadas (igual que en markVisitedElements)
            const capturedPages = getCapturedPages();

            // IDs conocidos para cada región
            const regionPages = {
                'arga': [180, 186, 184, 182],
                'ultzama': [188, 190]
            };

            const totalPages = regionPages[region];
            if (!totalPages) return;

            // Contar cuántas están capturadas
            let capturedCount = 0;
            totalPages.forEach(function(pageId) {
                if (capturedPages.includes(pageId)) {
                    capturedCount++;
                }
            });

            console.log('Progreso ' + region + ':', capturedCount + '/' + totalPages.length);

            // Si todas están capturadas, crear cookie (IGUAL que las otras)
            if (capturedCount === totalPages.length) {
                const cookieName = region + '_completado';
                const expires = new Date();
                expires.setTime(expires.getTime() + (365 * 24 * 60 * 60 * 1000));
                document.cookie = cookieName + "=si; expires=" + expires.toUTCString() + "; path=/";

                console.log('¡Región completada! Cookie creada:', cookieName);

                // NUEVO: Marcar que se completó una región para forzar refresh después
                sessionStorage.setItem('region_just_completed', region);
            }
        }

        // NUEVO: Detectar si venimos de completar una región y forzar refresh
        function checkIfNeedRefresh() {
            const justCompleted = sessionStorage.getItem('region_just_completed');
            if (justCompleted && window.location.pathname === '/virus/') {
                console.log('Región recién completada detectada, forzando refresh...');
                sessionStorage.removeItem('region_just_completed');
                setTimeout(function() {
                    location.reload();
                }, 500);
            }
        }

        // Ejecutar check de refresh al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            checkIfNeedRefresh();
        });

        document.addEventListener('DOMContentLoaded', function() {
            markVisitedElements();
            trackLinkClicks();
            trackCaptureWhenAtrapado();
        });

        // Detectar cuando el usuario vuelve a la página (especialmente importante en móviles)
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // La página se cargó desde caché (típico con history.back())
                console.log('Página cargada desde caché - remarcando elementos');
                markVisitedElements();
            }
        });

        // También detectar cambios de visibilidad (cuando vuelve a la pestaña)
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                // El usuario volvió a la pestaña, remarcar elementos
                setTimeout(markVisitedElements, 100);
            }
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

    // Clear region completion cookies
    $region_cookies = [
        'arga_completado',
        'ultzama_completado',
        'arga_eu_completado',
        'ultzama_eu_completado'
    ];

    foreach ($region_cookies as $cookie_name) {
        setcookie($cookie_name, '', time() - 3600, '/');
    }

    wp_send_json_success( 'Progreso, permisos y regiones reseteados correctamente' );
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

// ============================================================================
// SISTEMA DE REGIONES COMPLETADAS
// ============================================================================

/**
 * Detectar región y idioma basándose en URL
 */
function flu_detect_region_from_url($url) {
    if (preg_match('#^/virus/(arga|ultzama)/#', $url, $matches)) {
        return ['region' => $matches[1], 'language' => 'es'];
    } elseif (preg_match('#^/eu/virus/(arga|ultzama)/#', $url, $matches)) {
        return ['region' => $matches[1], 'language' => 'eu'];
    }
    return null;
}

/**
 * Obtener todas las páginas de una región específica (por jerarquía padre-hijo)
 */
function flu_get_region_pages($region, $language) {
    // Basándome en el debug, buscar las páginas padre específicas
    $parent_page = null;
    $all_pages = get_pages(['number' => 1000]);

    foreach ($all_pages as $page) {
        $page_url = str_replace(home_url(), '', get_permalink($page->ID));

        if ($language === 'es') {
            // Buscar páginas como /virus/arga/ o /virus/ultzama/
            if ($page_url === "/virus/{$region}/" || $page->post_name === $region) {
                // Verificar que esté en la estructura correcta
                if (strpos($page_url, '/virus/') === 0) {
                    $parent_page = $page;
                    break;
                }
            }
        } elseif ($language === 'eu') {
            // Buscar páginas como /eu/virus/arga/ o /eu/virus/ultzama/
            if ($page_url === "/eu/virus/{$region}/" ||
                (strpos($page_url, '/eu/virus/') === 0 && $page->post_name === $region)) {
                $parent_page = $page;
                break;
            }
        }
    }

    if (!$parent_page) {
        error_log("FLU: No se encontró página padre para región: $region, idioma: $language");
        return [];
    }

    // Obtener todas las páginas hijas
    $child_pages = get_pages([
        'parent' => $parent_page->ID,
        'number' => 100
    ]);

    $region_page_ids = [];
    foreach ($child_pages as $child_page) {
        $region_page_ids[] = $child_page->ID;
    }

    error_log("FLU: Región $region ($language) - Página padre: {$parent_page->ID} ({$parent_page->post_title}), Páginas hijas: " . count($region_page_ids) . " - IDs: " . implode(', ', $region_page_ids));

    return $region_page_ids;
}

/**
 * Verificar si todas las páginas de una región están capturadas
 */
function flu_check_region_completion_auto($region, $language) {
    // Obtener páginas capturadas
    $captured_cookie_name = 'flu_captured_pages';
    $captured = [];

    if (isset($_COOKIE[$captured_cookie_name])) {
        $captured = json_decode(stripslashes($_COOKIE[$captured_cookie_name]), true);
        if (!is_array($captured)) {
            $captured = [];
        }
    }

    // Obtener todas las páginas de esta región
    $region_pages = flu_get_region_pages($region, $language);

    if (empty($region_pages)) {
        error_log("FLU: No se encontraron páginas para región: $region, idioma: $language");
        return false;
    }

    // Verificar si todas están capturadas
    $total_pages = count($region_pages);
    $captured_count = 0;

    foreach ($region_pages as $page_id) {
        if (in_array($page_id, $captured)) {
            $captured_count++;
        }
    }

    $is_complete = ($captured_count === $total_pages);

    error_log("FLU: Región $region ($language): $captured_count/$total_pages páginas capturadas. Completado: " . ($is_complete ? 'SÍ' : 'NO'));

    // Si está completa, crear la cookie
    if ($is_complete) {
        if ($language === 'eu') {
            $cookie_name = $region . '_eu_completado';
        } else {
            $cookie_name = $region . '_completado';
        }

        $expire_time = time() + (365 * 24 * 60 * 60); // 1 año
        setcookie($cookie_name, 'si', $expire_time, '/');

        error_log("FLU: ¡REGIÓN COMPLETADA! Cookie creada: $cookie_name = si");
    }

    return $is_complete;
}

/**
 * JavaScript para detectar #atrapado y verificar completitud de región
 */
function flu_auto_region_script() {
    ?>
    <script>
        // Función para crear cookies (igual que las otras cookies)
        function setCookie(name, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + value + expires + "; path=/";
        }

        function getCookie(name) {
            const value = "; " + document.cookie;
            const parts = value.split("; " + name + "=");
            if (parts.length === 2) return parts.pop().split(";").shift();
            return null;
        }

        // Función para verificar completitud de región
        function checkRegionCompletionAuto() {
            const url = window.location.pathname;
            let region = null;
            let language = null;

            // Detectar región y idioma
            if (url.match(/^\/virus\/(arga|ultzama)\//)) {
                region = url.match(/^\/virus\/(arga|ultzama)\//)[1];
                language = 'es';
            } else if (url.match(/^\/eu\/virus\/(arga|ultzama)\//)) {
                region = url.match(/^\/eu\/virus\/(arga|ultzama)\//)[1];
                language = 'eu';
            }

            if (!region || !language) {
                return; // No es una página de virus
            }

            console.log('Verificando completitud de región:', region, '(' + language + ')');

            // Obtener IDs dinámicamente vía AJAX (solo para obtener datos, no para crear cookies)
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            const data = 'action=flu_get_region_pages&region=' + region + '&language=' + language;
            xhr.send(data);

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            const regionPages = response.data.region_pages;

                            // Obtener páginas capturadas desde cookie
                            const capturedCookie = getCookie('flu_captured_pages');
                            let capturedPages = [];

                            if (capturedCookie) {
                                try {
                                    capturedPages = JSON.parse(decodeURIComponent(capturedCookie));
                                } catch (e) {
                                    capturedPages = [];
                                }
                            }

                            // Contar páginas capturadas en esta región
                            let capturedCount = 0;
                            regionPages.forEach(pageId => {
                                if (capturedPages.includes(pageId)) {
                                    capturedCount++;
                                }
                            });

                            const isComplete = capturedCount === regionPages.length;

                            console.log('Progreso región:', capturedCount + '/' + regionPages.length + ' páginas completadas');

                            // Si está completa, crear la cookie usando JavaScript
                            if (isComplete) {
                                let cookieName;
                                if (language === 'eu') {
                                    cookieName = region + '_eu_completado';
                                } else {
                                    cookieName = region + '_completado';
                                }

                                setCookie(cookieName, 'si', 365); // 1 año
                                console.log('¡Región completada! Cookie creada:', cookieName, '= si');
                            }
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                    }
                }
            };
        }

        // Ejecutar cuando se llega a #atrapado
        window.addEventListener('hashchange', function() {
            if (window.location.hash === '#atrapado') {
                setTimeout(checkRegionCompletionAuto, 1000); // Dar tiempo a que se procese la captura
            }
        });

        // Ejecutar si ya está en #atrapado al cargar
        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.hash === '#atrapado') {
                setTimeout(checkRegionCompletionAuto, 1500);
            }
        });
    </script>
    <?php
}
add_action('wp_footer', 'flu_auto_region_script');

/**
 * AJAX handler para obtener IDs de páginas de región (solo lectura)
 */
function flu_ajax_get_region_pages() {
    $region = sanitize_text_field($_POST['region']);
    $language = sanitize_text_field($_POST['language']);

    if (!$region || !$language) {
        wp_send_json_error('Parámetros inválidos');
    }

    // Usar la función existente para obtener páginas
    $region_pages = flu_get_region_pages($region, $language);

    wp_send_json_success([
        'region_pages' => $region_pages,
        'total_pages' => count($region_pages),
        'region' => $region,
        'language' => $language
    ]);
}
add_action('wp_ajax_flu_get_region_pages', 'flu_ajax_get_region_pages');
add_action('wp_ajax_nopriv_flu_get_region_pages', 'flu_ajax_get_region_pages');

/**
 * Función debug para mostrar estado de regiones
 */
function flu_debug_region_status() {
    if (!isset($_GET['debug_regions'])) {
        return;
    }

    echo '<div style="position:fixed; top:10px; right:10px; background:white; padding:15px; border:2px solid #333; z-index:9999; max-width:400px; max-height:80vh; overflow-y:auto;">';
    echo '<h3>Debug - Estado de Regiones</h3>';

    // Buscar todas las páginas que contengan 'arga' o 'ultzama' en el título o slug
    echo '<h4>Búsqueda de páginas:</h4>';
    $all_pages = get_pages(['number' => 1000]);

    $arga_pages = [];
    $ultzama_pages = [];

    foreach ($all_pages as $page) {
        $page_url = str_replace(home_url(), '', get_permalink($page->ID));
        $title = $page->post_title;
        $slug = $page->post_name;

        if (stripos($title, 'arga') !== false || stripos($slug, 'arga') !== false || stripos($page_url, 'arga') !== false) {
            $arga_pages[] = "ID: {$page->ID}, Título: {$title}, Slug: {$slug}, URL: {$page_url}, Padre: {$page->post_parent}";
        }

        if (stripos($title, 'ultzama') !== false || stripos($slug, 'ultzama') !== false || stripos($page_url, 'ultzama') !== false) {
            $ultzama_pages[] = "ID: {$page->ID}, Título: {$title}, Slug: {$slug}, URL: {$page_url}, Padre: {$page->post_parent}";
        }
    }

    echo '<p><strong>Páginas con "ARGA":</strong><br>';
    if (empty($arga_pages)) {
        echo 'No se encontraron páginas<br>';
    } else {
        foreach ($arga_pages as $info) {
            echo $info . '<br>';
        }
    }
    echo '</p>';

    echo '<p><strong>Páginas con "ULTZAMA":</strong><br>';
    if (empty($ultzama_pages)) {
        echo 'No se encontraron páginas<br>';
    } else {
        foreach ($ultzama_pages as $info) {
            echo $info . '<br>';
        }
    }
    echo '</p>';

    $regions = [
        ['arga', 'es'],
        ['ultzama', 'es'],
        ['arga', 'eu'],
        ['ultzama', 'eu']
    ];

    echo '<h4>Estado actual:</h4>';
    foreach ($regions as $region_info) {
        list($region, $language) = $region_info;

        $region_pages = flu_get_region_pages($region, $language);
        $cookie_name = ($language === 'eu') ? $region . '_eu_completado' : $region . '_completado';
        $cookie_value = isset($_COOKIE[$cookie_name]) ? $_COOKIE[$cookie_name] : 'no existe';

        echo "<p><strong>$region ($language):</strong><br>";
        echo "Páginas encontradas: " . count($region_pages) . "<br>";
        if (!empty($region_pages)) {
            echo "IDs: " . implode(', ', $region_pages) . "<br>";
        }
        echo "Cookie: $cookie_name = $cookie_value</p>";
    }

    echo '<p><em>Añade ?debug_regions=1 a cualquier URL para ver este debug</em></p>';
    echo '</div>';
}
add_action('wp_head', 'flu_debug_region_status');
