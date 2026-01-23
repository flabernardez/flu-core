<?php
/**
 * Progress tracking functionality for Fluvial Core
 * Track visited pages using cookies and update visual elements
 * ONLY applies to pages under /virus/ hierarchy (or translations like /birusa-goian/)
 * COMPATIBLE CON POLYLANG - Progreso compartido entre idiomas
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Check if current page is under /virus/ hierarchy (any language)
 */
function flu_core_is_virus_page() {
    if ( ! is_page() ) {
        return false;
    }

    $current_page = get_post( get_the_ID() );

    // Check if current page or any ancestor is the virus parent page
    $page_to_check = $current_page;
    while ( $page_to_check ) {
        // Get all translations of this page
        if ( function_exists( 'pll_get_post_translations' ) ) {
            $translations = pll_get_post_translations( $page_to_check->ID );
            foreach ( $translations as $translated_page_id ) {
                $translated_page = get_post( $translated_page_id );
                // Check if any translation slug is 'virus' or 'birusa-goian' or any variant
                if ( in_array( $translated_page->post_name, array( 'virus', 'birusa-goian' ) ) ) {
                    return true;
                }
            }
        }

        // Fallback: check current page slug directly
        if ( in_array( $page_to_check->post_name, array( 'virus', 'birusa-goian' ) ) ) {
            return true;
        }

        if ( $page_to_check->post_parent ) {
            $page_to_check = get_post( $page_to_check->post_parent );
        } else {
            break;
        }
    }

    return false;
}

/**
 * Get the original page ID (canonical language version)
 * This ensures cookies work across all languages
 */
function flu_core_get_canonical_page_id( $page_id ) {
    if ( ! function_exists( 'pll_get_post_translations' ) ) {
        return $page_id;
    }

    // Get all translations
    $translations = pll_get_post_translations( $page_id );

    // Get the default language
    $default_lang = function_exists( 'pll_default_language' ) ? pll_default_language() : 'es';

    // Return the ID of the default language version, or first available
    if ( isset( $translations[ $default_lang ] ) ) {
        return $translations[ $default_lang ];
    }

    // If default not found, return the first translation ID
    return !empty( $translations ) ? reset( $translations ) : $page_id;
}

/**
 * Get parent page ID for a zone (arga or ultzama) in any language
 */
function flu_core_get_zone_parent_id( $zone_slug ) {
    // Try to find by slug in current language
    $page = get_page_by_path( $zone_slug );

    if ( ! $page ) {
        // Try common parent paths
        $possible_paths = array(
            'virus/' . $zone_slug,
            'birusa-goian/' . $zone_slug
        );

        foreach ( $possible_paths as $path ) {
            $page = get_page_by_path( $path );
            if ( $page ) break;
        }
    }

    // If we have Polylang, search in all languages
    if ( ! $page && function_exists( 'pll_languages_list' ) ) {
        $languages = pll_languages_list();

        foreach ( $languages as $lang ) {
            // Search for pages with this slug in this language
            $args = array(
                'post_type' => 'page',
                'name' => $zone_slug,
                'posts_per_page' => 1,
                'lang' => $lang
            );

            $query = new WP_Query( $args );

            if ( $query->have_posts() ) {
                $page = $query->posts[0];
                break;
            }
        }
    }

    return $page ? $page->ID : null;
}

/**
 * Add CSS for visited elements
 */
function flu_core_add_visited_css() {
    // Add body classes for completed zones (only on virus pages)
    if ( flu_core_is_virus_page() ) {
        $body_classes = array();

        if ( isset( $_COOKIE['arga_completado'] ) && $_COOKIE['arga_completado'] === 'si' ) {
            $body_classes[] = 'arga-completado';
        }

        if ( isset( $_COOKIE['ultzama_completado'] ) && $_COOKIE['ultzama_completado'] === 'si' ) {
            $body_classes[] = 'ultzama-completado';
        }

        if ( !empty( $body_classes ) ) {
            add_filter( 'body_class', function( $classes ) use ( $body_classes ) {
                return array_merge( $classes, $body_classes );
            } );
        }
    }

    // CSS for progress visualization - applies to ALL pages with .progreso elements
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
        
        /* Virus capture states - only for virus pages */
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
            cursor: pointer;
        }
        
        /* Zone completion badges - hidden by default */
        .zona-completada-badge,
        [data-zone-badge] {
            display: none !important;
        }
        
        /* Show badges when zone is complete */
        body.arga-completado [data-zone-badge="arga"],
        body.arga-completado .arga-completado-badge {
            display: block !important;
        }
        
        body.ultzama-completado [data-zone-badge="ultzama"],
        body.ultzama-completado .ultzama-completado-badge {
            display: block !important;
        }
        
        /* Virus bloqueados - progreso secuencial - only for virus pages */
        .progreso li.locked {
            opacity: 0.5;
            pointer-events: auto;
        }
        
        .progreso li.locked a {
            cursor: pointer;
            pointer-events: auto;
        }
        
        .progreso li.locked .sin-capturar,
        .progreso li.locked .bloqueada {
            filter: blur(3px);
            transition: filter 0.3s ease;
        }
        
        .progreso li.unlocked {
            opacity: 1;
            pointer-events: auto;
        }
        
        .progreso li.unlocked .sin-capturar,
        .progreso li.unlocked .bloqueada {
            filter: blur(0);
        }
        
        /* Modal de virus bloqueado */
        .virus-locked-overlay {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            background: rgba(0, 0, 0, 0.20);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: var(--wp--preset--spacing--50);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .virus-locked-overlay.show {
            display: flex;
            opacity: 1;
        }

        .virus-locked-modal {
            background-color: var(--wp--preset--color--custom-white);
            border-radius: 32px;
            padding: var(--wp--preset--spacing--50);
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .virus-locked-overlay.show .virus-locked-modal {
            transform: scale(1);
        }

        .virus-locked-emoji {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .virus-locked-title {
            font-family: var(--wp--preset--font-family--nunito) !important;
            font-size: var(--wp--preset--font-size--large);
            font-weight: 900;
            text-transform: uppercase;
            color: var(--wp--preset--color--custom-green-dark);
            margin-bottom: 12px;
        }

        .virus-locked-text {
            font-family: var(--wp--preset--font-family--inter);
            font-size: var(--wp--preset--font-size--medium);
            line-height: 1.3;
            color: var(--wp--preset--color--custom-green-dark);
            margin-bottom: 24px;
        }

        .virus-locked-button {
            color: var(--wp--preset--color--custom-green-dark);
            font-family: var(--wp--preset--font-family--inter);
            font-size: var(--wp--preset--font-size--medium);
            background-color: var(--wp--preset--color--custom-yellow);
            border: none;
            padding: var(--wp--preset--spacing--30) var(--wp--preset--spacing--40);
            border-radius: 9999px;
            cursor: pointer;
            transition: transform 0.1s ease;
        }

        .virus-locked-button:active {
            transform: scale(0.98);
        }
        
        /* Modal de río completado */
        .river-complete-overlay {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            background: rgba(0, 0, 0, 0.20);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: var(--wp--preset--spacing--50);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .river-complete-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .river-complete-modal {
            background-color: var(--wp--preset--color--custom-white);
            border-radius: 32px;
            padding: var(--wp--preset--spacing--50);
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .river-complete-overlay.show .river-complete-modal {
            transform: scale(1);
        }

        .river-complete-emoji {
            font-size: 64px;
            margin-bottom: 20px;
            animation: celebrate 0.6s ease;
        }

        @keyframes celebrate {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }

        .river-complete-title {
            font-family: var(--wp--preset--font-family--nunito) !important;
            font-size: var(--wp--preset--font-size--large);
            font-weight: 900;
            text-transform: uppercase;
            color: var(--wp--preset--color--custom-green-dark);
            margin-bottom: 12px;
        }

        .river-complete-text {
            font-family: var(--wp--preset--font-family--inter);
            font-size: var(--wp--preset--font-size--medium);
            line-height: 1.3;
            color: var(--wp--preset--color--custom-green-dark);
            margin-bottom: 24px;
        }

        .river-complete-button {
            color: var(--wp--preset--color--custom-green-dark);
            font-family: var(--wp--preset--font-family--inter);
            font-size: var(--wp--preset--font-size--medium);
            background-color: var(--wp--preset--color--custom-yellow);
            border: none;
            padding: var(--wp--preset--spacing--30) var(--wp--preset--spacing--40);
            border-radius: 9999px;
            cursor: pointer;
            transition: transform 0.1s ease;
        }

        .river-complete-button:active {
            transform: scale(0.98);
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
    $canonical_page_id = flu_core_get_canonical_page_id( $current_page_id );

    $cookie_name = 'flu_visited_pages';
    $captured_cookie_name = 'flu_captured_pages';
    $visited = [];
    $captured = [];

    if ( isset( $_COOKIE[ $cookie_name ] ) ) {
        $visited = json_decode( stripslashes( $_COOKIE[ $cookie_name ] ), true );
        if ( ! is_array( $visited ) ) {
            $visited = [];
        }
    }

    if ( isset( $_COOKIE[ $captured_cookie_name ] ) ) {
        $captured = json_decode( stripslashes( $_COOKIE[ $captured_cookie_name ] ), true );
        if ( ! is_array( $captured ) ) {
            $captured = [];
        }
    }

    if ( ! in_array( $canonical_page_id, $visited ) ) {
        $visited[] = $canonical_page_id;
        $expire_time = time() + ( 365 * 24 * 60 * 60 );
        setcookie( $cookie_name, json_encode( $visited ), $expire_time, '/' );
    }

    if ( flu_core_is_virus_page() ) {
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '#atrapado' ) !== false ) {
            if ( ! in_array( $canonical_page_id, $captured ) ) {
                $captured[] = $canonical_page_id;
                setcookie( $captured_cookie_name, json_encode( $captured ), $expire_time, '/' );
            }
        }
    }
}
add_action( 'template_redirect', 'flu_core_track_page_visit' );

function flu_core_ajax_track_visit() {
    $page_id = intval( $_POST['page_id'] );
    if ( ! $page_id ) {
        wp_send_json_error( 'Invalid page ID' );
    }

    $canonical_page_id = flu_core_get_canonical_page_id( $page_id );
    $cookie_name = 'flu_visited_pages';
    $visited = [];

    if ( isset( $_COOKIE[ $cookie_name ] ) ) {
        $visited = json_decode( stripslashes( $_COOKIE[ $cookie_name ] ), true );
        if ( ! is_array( $visited ) ) {
            $visited = [];
        }
    }

    if ( ! in_array( $canonical_page_id, $visited ) ) {
        $visited[] = $canonical_page_id;
        $expire_time = time() + ( 365 * 24 * 60 * 60 );
        setcookie( $cookie_name, json_encode( $visited ), $expire_time, '/' );
    }

    wp_send_json_success( 'Page tracked' );
}
add_action( 'wp_ajax_flu_core_track_visit', 'flu_core_ajax_track_visit' );
add_action( 'wp_ajax_nopriv_flu_core_track_visit', 'flu_core_ajax_track_visit' );

function flu_core_ajax_track_capture() {
    $page_id = intval( $_POST['page_id'] );
    if ( ! $page_id ) {
        wp_send_json_error( 'Invalid page ID' );
    }

    $canonical_page_id = flu_core_get_canonical_page_id( $page_id );
    $cookie_name = 'flu_captured_pages';
    $captured = [];

    if ( isset( $_COOKIE[ $cookie_name ] ) ) {
        $captured = json_decode( stripslashes( $_COOKIE[ $cookie_name ] ), true );
        if ( ! is_array( $captured ) ) {
            $captured = [];
        }
    }

    if ( ! in_array( $canonical_page_id, $captured ) ) {
        $captured[] = $canonical_page_id;
        $expire_time = time() + ( 365 * 24 * 60 * 60 );
        setcookie( $cookie_name, json_encode( $captured ), $expire_time, '/' );
    }

    wp_send_json_success( 'Page captured' );
}
add_action( 'wp_ajax_flu_core_track_capture', 'flu_core_ajax_track_capture' );
add_action( 'wp_ajax_nopriv_flu_core_track_capture', 'flu_core_ajax_track_capture' );

function flu_core_ajax_check_zone_completion() {
    $cookie_name = 'flu_captured_pages';
    $captured = [];

    if ( isset( $_COOKIE[ $cookie_name ] ) ) {
        $captured = json_decode( stripslashes( $_COOKIE[ $cookie_name ] ), true );
        if ( ! is_array( $captured ) ) {
            $captured = [];
        }
    }

    $arga_parent_id = flu_core_get_zone_parent_id( 'arga' );
    $ultzama_parent_id = flu_core_get_zone_parent_id( 'ultzama' );

    $expire_time = time() + ( 365 * 24 * 60 * 60 );

    $response = array(
        'arga_status' => 'incomplete',
        'ultzama_status' => 'incomplete',
        'arga_children' => array(),
        'ultzama_children' => array(),
        'captured' => $captured
    );

    if ( $arga_parent_id ) {
        $arga_children_posts = get_posts( array(
            'post_type' => 'page',
            'post_parent' => $arga_parent_id,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'suppress_filters' => false
        ) );

        $arga_children = array();
        foreach ( $arga_children_posts as $child ) {
            $canonical_id = flu_core_get_canonical_page_id( $child->ID );
            if ( ! in_array( $canonical_id, $arga_children ) ) {
                $arga_children[] = $canonical_id;
            }
        }

        $response['arga_children'] = $arga_children;
        $all_arga_captured = !empty($arga_children) && empty(array_diff($arga_children, $captured));

        if ( $all_arga_captured ) {
            setcookie( 'arga_completado', 'si', $expire_time, '/' );
            $response['arga_status'] = 'complete';
        }
    }

    if ( $ultzama_parent_id ) {
        $ultzama_children_posts = get_posts( array(
            'post_type' => 'page',
            'post_parent' => $ultzama_parent_id,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'suppress_filters' => false
        ) );

        $ultzama_children = array();
        foreach ( $ultzama_children_posts as $child ) {
            $canonical_id = flu_core_get_canonical_page_id( $child->ID );
            if ( ! in_array( $canonical_id, $ultzama_children ) ) {
                $ultzama_children[] = $canonical_id;
            }
        }

        $response['ultzama_children'] = $ultzama_children;
        $all_ultzama_captured = !empty($ultzama_children) && empty(array_diff($ultzama_children, $captured));

        if ( $all_ultzama_captured ) {
            setcookie( 'ultzama_completado', 'si', $expire_time, '/' );
            $response['ultzama_status'] = 'complete';
        }
    }

    wp_send_json_success( $response );
}
add_action( 'wp_ajax_flu_core_check_zone_completion', 'flu_core_ajax_check_zone_completion' );
add_action( 'wp_ajax_nopriv_flu_core_check_zone_completion', 'flu_core_ajax_check_zone_completion' );

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
                try { return JSON.parse(decodeURIComponent(cookie)); } catch (e) { return []; }
            }
            return [];
        }

        function getCapturedPages() {
            const cookie = getCookie('flu_captured_pages');
            if (cookie) {
                try { return JSON.parse(decodeURIComponent(cookie)); } catch (e) { return []; }
            }
            return [];
        }

        function getCanonicalPageId(element) {
            const canonicalId = element.getAttribute('data-canonical-id');
            if (canonicalId) return parseInt(canonicalId);
            const classList = element.className;
            const match = classList.match(/post-(\d+)/);
            return match ? parseInt(match[1]) : null;
        }

        function markVisitedElements() {
            const visitedPages = getVisitedPages();
            const capturedPages = getCapturedPages();
            const progressoLoops = document.querySelectorAll('.wp-block-query.progreso');

            progressoLoops.forEach(function(loop) {
                const listItems = loop.querySelectorAll('li[class*="post-"]');
                listItems.forEach(function(li) {
                    const pageId = getCanonicalPageId(li);
                    if (pageId) {
                        if (visitedPages.includes(pageId)) li.classList.add('visited');
                        if (capturedPages.includes(pageId)) li.classList.add('captured');
                    }
                });
            });
        }

        function applySequentialUnlock() {
            const currentPath = window.location.pathname;
            const isVirusPage = currentPath.includes('/virus/') || currentPath.includes('/birusa-goian/');
            if (!isVirusPage) return;

            const capturedPages = getCapturedPages();
            const progressoLoops = document.querySelectorAll('.wp-block-query.progreso');

            progressoLoops.forEach(function(loop) {
                const listItems = Array.from(loop.querySelectorAll('li[class*="post-"]'));
                let nextUnlocked = -1;

                for (let i = 0; i < listItems.length; i++) {
                    const li = listItems[i];
                    const pageId = getCanonicalPageId(li);
                    if (pageId && !capturedPages.includes(pageId)) {
                        nextUnlocked = i;
                        break;
                    }
                }

                if (nextUnlocked === -1) nextUnlocked = listItems.length;

                listItems.forEach(function(li, index) {
                    const pageId = getCanonicalPageId(li);
                    if (pageId) {
                        const isCaptured = capturedPages.includes(pageId);
                        if (isCaptured || index === nextUnlocked) {
                            li.classList.remove('locked');
                            li.classList.add('unlocked');
                            const sinCapturar = li.querySelector('.sin-capturar');
                            if (sinCapturar) sinCapturar.classList.remove('bloqueada');
                        } else {
                            li.classList.add('locked');
                            li.classList.remove('unlocked');
                            const sinCapturar = li.querySelector('.sin-capturar');
                            if (sinCapturar) sinCapturar.classList.add('bloqueada');
                        }
                    }
                });
            });
        }

        function makeCapturadoClickable() {
            const capturedPages = getCapturedPages();
            const progressoLoops = document.querySelectorAll('.wp-block-query.progreso');

            progressoLoops.forEach(function(loop) {
                const listItems = loop.querySelectorAll('li[class*="post-"]');
                listItems.forEach(function(li) {
                    const pageId = getCanonicalPageId(li);
                    if (pageId && capturedPages.includes(pageId)) {
                        const capturadoDiv = li.querySelector('.wp-block-group.capturado');
                        if (capturadoDiv) {
                            const linkElement = li.querySelector('a[href]');
                            if (linkElement) {
                                const baseUrl = linkElement.getAttribute('href').split('#')[0];
                                const targetUrl = baseUrl + '#capturado';
                                capturadoDiv.style.cursor = 'pointer';
                                capturadoDiv.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    window.location.href = targetUrl;
                                });
                            }
                        }
                    }
                });
            });
        }

        function showVirusLockedModal() {
            const currentPath = window.location.pathname;
            const isEuskera = currentPath.includes('/eu/');

            const overlay = document.createElement('div');
            overlay.className = 'virus-locked-overlay';

            const modal = document.createElement('div');
            modal.className = 'virus-locked-modal';

            const emoji = document.createElement('div');
            emoji.className = 'virus-locked-emoji';
            emoji.textContent = '🔒';

            const title = document.createElement('div');
            title.className = 'virus-locked-title';
            title.textContent = isEuskera ? 'Birusak blokeatuta' : 'Virus bloqueado';

            const text = document.createElement('div');
            text.className = 'virus-locked-text';
            text.textContent = isEuskera
                ? 'Aurretik dauden birusak harrapatu behar dituzu lehenengo'
                : 'Debes capturar los virus anteriores primero';

            const button = document.createElement('button');
            button.className = 'virus-locked-button';
            button.textContent = isEuskera ? 'Itxi' : 'Cerrar';

            modal.appendChild(emoji);
            modal.appendChild(title);
            modal.appendChild(text);
            modal.appendChild(button);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            setTimeout(function() { overlay.classList.add('show'); }, 10);

            button.addEventListener('click', function() {
                overlay.classList.remove('show');
                setTimeout(function() { document.body.removeChild(overlay); }, 300);
            });

            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    overlay.classList.remove('show');
                    setTimeout(function() { document.body.removeChild(overlay); }, 300);
                }
            });
        }

        function trackLinkClicks() {
            const currentPath = window.location.pathname;
            const isVirusPage = currentPath.includes('/virus/') || currentPath.includes('/birusa-goian/');
            const progressoLoops = document.querySelectorAll('.wp-block-query.progreso');

            progressoLoops.forEach(function(loop) {
                const links = loop.querySelectorAll('a[href]');
                links.forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        const listItem = this.closest('li[class*="post-"]');
                        if (listItem) {
                            if (isVirusPage && listItem.classList.contains('locked')) {
                                e.preventDefault();
                                e.stopPropagation();
                                showVirusLockedModal();
                                return false;
                            }
                            const pageId = getCanonicalPageId(listItem);
                            if (pageId) {
                                const xhr = new XMLHttpRequest();
                                xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                xhr.send('action=flu_core_track_visit&page_id=' + pageId);
                            }
                        }
                    });
                });
            });
        }

        function trackCaptureWhenAtrapado() {
            function captureCurrentPage() {
                const currentPageId = document.body.className.match(/page-id-(\d+)/);
                if (currentPageId && currentPageId[1]) {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.send('action=flu_core_track_capture&page_id=' + currentPageId[1]);
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            setTimeout(function() {
                                markVisitedElements();
                                applySequentialUnlock();
                                makeCapturadoClickable();
                                checkZoneCompletion();
                            }, 100);
                        }
                    };
                }
            }

            if (window.location.hash === '#atrapado') captureCurrentPage();
            window.addEventListener('hashchange', function() {
                if (window.location.hash === '#atrapado') captureCurrentPage();
            });
        }

        function checkZoneCompletion() {
            const argaCompletoBefore = getCookie('arga_completado');
            const ultzamaCompletoBefore = getCookie('ultzama_completado');

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('action=flu_core_check_zone_completion');

            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        const argaCompletoAfter = getCookie('arga_completado');
                        const ultzamaCompletoAfter = getCookie('ultzama_completado');

                        const argaJustCompleted = (argaCompletoBefore !== 'si' && argaCompletoAfter === 'si');
                        const ultzamaJustCompleted = (ultzamaCompletoBefore !== 'si' && ultzamaCompletoAfter === 'si');

                        if (argaJustCompleted || ultzamaJustCompleted) {
                            // Solo recargar si estamos en la página padre /virus/ o /birusa-goian/
                            if (isVirusParentPage()) {
                                setTimeout(function() { location.reload(); }, 1000);
                            }
                            return;
                        }

                        if (argaCompletoAfter === 'si') showCompletionBadges('arga');
                        if (ultzamaCompletoAfter === 'si') showCompletionBadges('ultzama');
                    } catch (e) { console.error('Error parsing response:', e); }
                }
            };
        }

        function showCompletionBadges(zone) {
            document.querySelectorAll('.' + zone + '-completado').forEach(function(badge) { badge.style.display = 'block'; });
            document.querySelectorAll('[data-zone="' + zone + '"]').forEach(function(badge) { badge.style.display = 'block'; });
            document.querySelectorAll('img[alt*="' + zone + '"]').forEach(function(img) { img.style.display = 'block'; });
        }

        function setCookie(name, value, days) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = name + "=" + value + ";expires=" + expires.toUTCString() + ";path=/";
        }

        function isVirusParentPage() {
            const currentPath = window.location.pathname;
            const cleanPath = currentPath.replace(/\/$/, '');
            const pathParts = cleanPath.split('/').filter(p => p);
            const lastPart = pathParts[pathParts.length - 1];
            return (lastPart === 'virus' || lastPart === 'birusa-goian');
        }

        // Flag para evitar mostrar modales múltiples veces
        var riverModalShowing = false;

        function showRiverCompleteModal(riverName, bothComplete) {
            const cookieName = 'flu_' + riverName + '_modal_shown';
            if (getCookie(cookieName) === 'si') return;

            // Evitar mostrar el modal si ya hay uno mostrándose
            if (riverModalShowing) {
                console.log('⚠️ Ya hay un modal de río mostrándose, ignorando');
                return;
            }
            riverModalShowing = true;

            const currentPath = window.location.pathname;
            const isEuskera = currentPath.includes('/eu/');

            const overlay = document.createElement('div');
            overlay.className = 'river-complete-overlay';

            const modal = document.createElement('div');
            modal.className = 'river-complete-modal';

            const emoji = document.createElement('div');
            emoji.className = 'river-complete-emoji';
            emoji.textContent = '🎉';

            const title = document.createElement('div');
            title.className = 'river-complete-title';
            title.textContent = isEuskera ? 'Ibaia garbi!' : '¡Río limpio!';

            const text = document.createElement('div');
            text.className = 'river-complete-text';
            if (bothComplete) {
                text.textContent = isEuskera
                    ? 'Ibaietako birusak garbitzen amaitu duzu! Zorionak!'
                    : '¡Terminaste de limpiar los ríos de virus! Enhorabuena';
            } else {
                text.textContent = isEuskera
                    ? riverName.toUpperCase() + ' ibaia birusetatik garbitzen lortu duzu. Zorionak!'
                    : 'Has conseguido limpiar el río ' + riverName.toUpperCase() + ' de virus. ¡Enhorabuena!';
            }

            const button = document.createElement('button');
            button.className = 'river-complete-button';
            button.textContent = isEuskera ? 'Itxi' : 'Cerrar';

            modal.appendChild(emoji);
            modal.appendChild(title);
            modal.appendChild(text);
            modal.appendChild(button);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            setTimeout(function() { overlay.classList.add('show'); }, 100);

            // SOLO guardar cookie cuando el usuario cierre el modal
            function closeModal() {
                setCookie(cookieName, 'si', 365);
                overlay.classList.remove('show');
                setTimeout(function() {
                    if (overlay.parentNode) {
                        document.body.removeChild(overlay);
                    }
                    riverModalShowing = false;
                }, 300);
            }

            button.addEventListener('click', closeModal);

            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    closeModal();
                }
            });
        }

        function checkAndShowRiverModals() {
            // SOLO mostrar modales en páginas padre /virus/ o /birusa-goian/
            if (!isVirusParentPage()) {
                console.log('📍 No estamos en página padre de virus, no mostramos modales');
                return;
            }

            console.log('📍 Estamos en página padre, verificando ríos completados...');

            const argaCompleto = getCookie('arga_completado');
            const ultzamaCompleto = getCookie('ultzama_completado');
            const bothComplete = (argaCompleto === 'si' && ultzamaCompleto === 'si');

            const argaModalShown = getCookie('flu_arga_modal_shown');
            const ultzamaModalShown = getCookie('flu_ultzama_modal_shown');

            setTimeout(function() {
                if (bothComplete) {
                    if (argaCompleto === 'si' && argaModalShown !== 'si') {
                        showRiverCompleteModal('arga', true);
                    } else if (ultzamaCompleto === 'si' && ultzamaModalShown !== 'si') {
                        showRiverCompleteModal('ultzama', true);
                    }
                } else {
                    if (argaCompleto === 'si' && argaModalShown !== 'si') {
                        showRiverCompleteModal('arga', false);
                    }
                    if (ultzamaCompleto === 'si' && ultzamaModalShown !== 'si') {
                        const delay = (argaCompleto === 'si' && argaModalShown !== 'si') ? 500 : 0;
                        setTimeout(function() { showRiverCompleteModal('ultzama', false); }, delay);
                    }
                }
            }, 800);
        }

        function checkZoneOnReturn() {
            window.addEventListener('hashchange', function() {
                if (isVirusParentPage()) {
                    checkZoneCompletion();
                    setTimeout(function() { checkAndShowRiverModals(); }, 500);
                }
            });

            if (isVirusParentPage()) {
                let verificationCount = 0;
                const verificationInterval = setInterval(function() {
                    checkZoneCompletion();
                    verificationCount++;
                    if (verificationCount >= 3) {
                        clearInterval(verificationInterval);
                        setTimeout(function() { checkAndShowRiverModals(); }, 300);
                    }
                }, 1000);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            const isVirusPage = currentPath.includes('/virus/') || currentPath.includes('/birusa-goian/');

            markVisitedElements();
            trackLinkClicks();

            if (isVirusPage) {
                applySequentialUnlock();
                makeCapturadoClickable();
                trackCaptureWhenAtrapado();
                checkZoneCompletion();
                // checkAndShowRiverModals se llama desde checkZoneOnReturn después de las verificaciones
                checkZoneOnReturn();

                setTimeout(function() {
                    if (getCookie('arga_completado') === 'si') showCompletionBadges('arga');
                    if (getCookie('ultzama_completado') === 'si') showCompletionBadges('ultzama');
                }, 500);
            }

            const progressReadyEvent = new Event('fluProgressReady');
            document.dispatchEvent(progressReadyEvent);
        });
    </script>
    <?php
}
add_action( 'wp_footer', 'flu_core_add_visited_functionality' );

/**
 * Update visual elements based on visited pages
 */
function flu_core_update_progress_elements( $content ) {
    if ( strpos( $content, 'wp-block-query progreso' ) === false ) {
        return $content;
    }

    $cookie_name = 'flu_visited_pages';
    $visited = [];

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

    foreach ( $xpath->query( "//div[contains(@class, 'wp-block-query') and contains(@class, 'progreso')]//li[contains(@class, 'post-')]" ) as $li ) {
        preg_match( '/post-(\d+)/', $li->getAttribute( 'class' ), $matches );
        $post_id = isset( $matches[1] ) ? intval( $matches[1] ) : 0;

        if ( $post_id ) {
            $canonical_id = flu_core_get_canonical_page_id( $post_id );
            $li->setAttribute( 'data-canonical-id', $canonical_id );

            if ( in_array( $canonical_id, $visited ) ) {
                foreach ( $xpath->query( ".//*[contains(@class, 'wp-block-separator') and contains(@class, 'has-custom-white-background-color')]", $li ) as $separator ) {
                    $class = $separator->getAttribute( 'class' );
                    $class = str_replace( 'has-custom-white-background-color', 'has-custom-green-background-color', $class );
                    $separator->setAttribute( 'class', $class );
                }

                foreach ( $xpath->query( ".//*[contains(@class, 'wp-block-group') and contains(@class, 'has-border-color') and contains(@class, 'has-custom-green-color')]", $li ) as $group ) {
                    $class = $group->getAttribute( 'class' );
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
    }

    $content = $dom->saveHTML( $dom->documentElement );
    $content = preg_replace( '/^<!DOCTYPE.+?>/', '', str_replace( [ '<html>', '</html>', '<body>', '</body>' ], '', $content ) );

    return $content;
}
add_filter( 'the_content', 'flu_core_update_progress_elements', 12 );

/**
 * Handle AJAX request to reset visited pages cookies
 */
function flu_core_reset_visited_pages() {
    if ( ! wp_verify_nonce( $_POST['nonce'], 'flu_core_reset_nonce' ) ) {
        wp_die( 'Error de seguridad' );
    }

    $cookies_to_clear = array(
        'flu_visited_pages',
        'flu_captured_pages',
        'flu_permissions',
        'flu_gyro_permission',
        'arga_completado',
        'ultzama_completado',
        'flu_arga_modal_shown',
        'flu_ultzama_modal_shown'
    );

    foreach ( $cookies_to_clear as $cookie_name ) {
        setcookie( $cookie_name, '', time() - 3600, '/' );
    }

    wp_send_json_success( 'Progreso, permisos y logros reseteados correctamente' );
}
add_action( 'wp_ajax_flu_core_reset_visited', 'flu_core_reset_visited_pages' );
add_action( 'wp_ajax_nopriv_flu_core_reset_visited', 'flu_core_reset_visited_pages' );

/**
 * Enqueue script for reset button functionality
 */
function flu_core_enqueue_reset_script() {
    if ( ! flu_core_is_virus_page() ) {
        return;
    }

    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var processed = false;
            var resetButtons = document.querySelectorAll('.reset-cookies');

            resetButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (processed) return false;
                    if (!confirm('¿Seguro que quieres resetear tu progreso? Esta acción no se puede deshacer.')) return false;

                    processed = true;
                    var buttonLink = this.querySelector('a.wp-block-button__link');
                    if (!buttonLink) { processed = false; return false; }

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

                    xhr.send('action=flu_core_reset_visited&nonce=<?php echo wp_create_nonce('flu_core_reset_nonce'); ?>');
                    return false;
                });
            });
        });
    </script>
    <?php
}
add_action( 'wp_footer', 'flu_core_enqueue_reset_script' );
