<?php
/**
 * Geolocation functionality for Fluvial Core
 * Add coordinate fields to pages and validate user location
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Add settings page for Google Maps API Key
 */
function flu_geo_add_settings_page() {
    add_options_page(
        'Configuración de Geolocalización',
        'Geolocalización',
        'manage_options',
        'flu-geolocation-settings',
        'flu_geo_render_settings_page'
    );
}
add_action( 'admin_menu', 'flu_geo_add_settings_page' );

/**
 * Register settings
 */
function flu_geo_register_settings() {
    register_setting( 'flu_geo_settings_group', 'flu_core_google_maps_key', 'flu_geo_validate_api_key' );
}
add_action( 'admin_init', 'flu_geo_register_settings' );

/**
 * Validate API Key
 */
function flu_geo_validate_api_key( $api_key ) {
    $api_key = sanitize_text_field( $api_key );

    if ( empty( $api_key ) ) {
        add_settings_error(
            'flu_core_google_maps_key',
            'empty_api_key',
            'La clave API no puede estar vacía.',
            'error'
        );
        return get_option( 'flu_core_google_maps_key' );
    }

    if ( strlen( $api_key ) < 30 ) {
        add_settings_error(
            'flu_core_google_maps_key',
            'invalid_api_key',
            'La clave API parece ser inválida. Debe tener al menos 30 caracteres.',
            'error'
        );
        return get_option( 'flu_core_google_maps_key' );
    }

    $test_url = 'https://maps.googleapis.com/maps/api/geocode/json?address=Barcelona&key=' . $api_key;
    $response = wp_remote_get( $test_url );

    if ( is_wp_error( $response ) ) {
        add_settings_error(
            'flu_core_google_maps_key',
            'connection_error',
            'No se pudo validar la clave API. Error de conexión.',
            'error'
        );
        return get_option( 'flu_core_google_maps_key' );
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( isset( $data['status'] ) && $data['status'] === 'REQUEST_DENIED' ) {
        add_settings_error(
            'flu_core_google_maps_key',
            'invalid_api_key',
            'La clave API es inválida o no tiene los permisos necesarios. Verifica que tenga habilitadas las APIs: Geocoding API, Maps JavaScript API y Geometry API.',
            'error'
        );
        return get_option( 'flu_core_google_maps_key' );
    }

    if ( isset( $data['status'] ) && $data['status'] === 'OK' ) {
        add_settings_error(
            'flu_core_google_maps_key',
            'valid_api_key',
            '¡Clave API validada correctamente!',
            'success'
        );
    }

    return $api_key;
}

/**
 * Render settings page
 */
function flu_geo_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $api_key = get_option( 'flu_core_google_maps_key', '' );
    $api_key_masked = ! empty( $api_key ) ? substr( $api_key, 0, 10 ) . str_repeat( '*', strlen( $api_key ) - 10 ) : '';
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <div class="notice notice-info">
            <p><strong>Instrucciones para obtener tu API Key de Google Maps:</strong></p>
            <ol>
                <li>Ve a <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                <li>Crea un proyecto nuevo o selecciona uno existente</li>
                <li>Habilita las siguientes APIs:
                    <ul>
                        <li>Maps JavaScript API</li>
                        <li>Geocoding API</li>
                        <li>Geometry API</li>
                    </ul>
                </li>
                <li>Ve a "Credenciales" y crea una API Key</li>
                <li>Restringe la API Key a tu dominio para mayor seguridad</li>
                <li>Copia la clave y pégala aquí abajo</li>
            </ol>
        </div>

        <?php settings_errors(); ?>

        <form method="post" action="options.php">
            <?php
            settings_fields( 'flu_geo_settings_group' );
            do_settings_sections( 'flu_geo_settings_group' );
            ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="flu_core_google_maps_key">Google Maps API Key</label>
                    </th>
                    <td>
                        <input
                                type="text"
                                id="flu_core_google_maps_key"
                                name="flu_core_google_maps_key"
                                value="<?php echo esc_attr( $api_key ); ?>"
                                class="regular-text"
                                placeholder="AIzaSy..."
                        />
                        <p class="description">
                            Ingresa tu clave API de Google Maps.
                            <?php if ( ! empty( $api_key ) ) : ?>
                                <br><strong>Clave actual:</strong> <?php echo esc_html( $api_key_masked ); ?>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button( 'Guardar y Validar API Key' ); ?>
        </form>

        <?php if ( ! empty( $api_key ) ) : ?>
            <hr>
            <h2>Estado de la API</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Estado</th>
                    <td>
                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                        API Key configurada
                    </td>
                </tr>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Add meta box to pages for geolocation settings
 */
function flu_geo_add_meta_box() {
    add_meta_box(
        'flu_geolocation_settings',
        'Configuración de Geolocalización',
        'flu_geo_meta_box_callback',
        'page',
        'normal',
        'default'
    );
}
add_action( 'add_meta_boxes', 'flu_geo_add_meta_box' );

/**
 * Meta box callback function
 */
function flu_geo_meta_box_callback( $post ) {
    $api_key = get_option( 'flu_core_google_maps_key', '' );

    if ( empty( $api_key ) ) {
        echo '<div class="notice notice-error inline">';
        echo '<p><strong>⚠️ API Key no configurada.</strong> ';
        echo 'Debes configurar tu Google Maps API Key en ';
        echo '<a href="' . admin_url( 'options-general.php?page=flu-geolocation-settings' ) . '">Ajustes > Geolocalización</a>';
        echo ' para utilizar esta funcionalidad.</p>';
        echo '</div>';
        return;
    }

    wp_nonce_field( 'flu_geo_save_meta_box_data', 'flu_geo_meta_box_nonce' );

    $maps_url = get_post_meta( $post->ID, '_flu_geo_maps_url', true );
    $latitude = get_post_meta( $post->ID, '_flu_geo_latitude', true );
    $longitude = get_post_meta( $post->ID, '_flu_geo_longitude', true );
    $tolerance = get_post_meta( $post->ID, '_flu_geo_tolerance', true );

    if ( empty( $tolerance ) ) {
        $tolerance = 'normal';
    }

    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="flu_geo_maps_url">URL de Google Maps</label></th>';
    echo '<td>';
    echo '<input type="url" id="flu_geo_maps_url" name="flu_geo_maps_url" value="' . esc_attr( $maps_url ) . '" size="60" placeholder="Pega aquí la URL de Google Maps" style="width: 100%;" />';
    echo '<p class="description">Busca la ubicación en Google Maps y copia la URL completa</p>';
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row">Coordenadas extraídas</th>';
    echo '<td>';
    echo '<strong>Latitud:</strong> <span id="extracted_lat">' . esc_html( $latitude ) . '</span><br>';
    echo '<strong>Longitud:</strong> <span id="extracted_lng">' . esc_html( $longitude ) . '</span>';
    echo '<input type="hidden" id="flu_geo_latitude" name="flu_geo_latitude" value="' . esc_attr( $latitude ) . '" />';
    echo '<input type="hidden" id="flu_geo_longitude" name="flu_geo_longitude" value="' . esc_attr( $longitude ) . '" />';
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row">Tolerancia de ubicación</th>';
    echo '<td>';
    echo '<label for="flu_geo_tolerance_strict"><input type="radio" id="flu_geo_tolerance_strict" name="flu_geo_tolerance" value="strict" ' . checked( $tolerance, 'strict', false ) . '> Estricta (10m)</label><br>';
    echo '<label for="flu_geo_tolerance_normal"><input type="radio" id="flu_geo_tolerance_normal" name="flu_geo_tolerance" value="normal" ' . checked( $tolerance, 'normal', false ) . '> Normal (50m)</label><br>';
    echo '<label for="flu_geo_tolerance_amplio"><input type="radio" id="flu_geo_tolerance_amplio" name="flu_geo_tolerance" value="amplio" ' . checked( $tolerance, 'amplio', false ) . '> Amplio (200m)</label><br>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '<p><em>Deja la URL vacía si no quieres validación de ubicación para esta página.</em></p>';

    echo '<script>
        document.getElementById("flu_geo_maps_url").addEventListener("input", function() {
            const url = this.value;
            const coords = extractCoordinatesFromGoogleMapsURL(url);
            
            if (coords) {
                document.getElementById("extracted_lat").textContent = coords.lat;
                document.getElementById("extracted_lng").textContent = coords.lng;
                document.getElementById("flu_geo_latitude").value = coords.lat;
                document.getElementById("flu_geo_longitude").value = coords.lng;
            } else {
                document.getElementById("extracted_lat").textContent = "";
                document.getElementById("extracted_lng").textContent = "";
                document.getElementById("flu_geo_latitude").value = "";
                document.getElementById("flu_geo_longitude").value = "";
            }
        });
        
        function extractCoordinatesFromGoogleMapsURL(url) {
            if (!url) return null;
            
            const patterns = [
                /@(-?\d+\.\d+),(-?\d+\.\d+)/,
                /!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/,
                /place\/[^@]*@(-?\d+\.\d+),(-?\d+\.\d+)/
            ];
            
            for (const pattern of patterns) {
                const match = url.match(pattern);
                if (match) {
                    return {
                        lat: parseFloat(match[1]),
                        lng: parseFloat(match[2])
                    };
                }
            }
            
            return null;
        }
    </script>';
}

/**
 * Save meta box data
 */
function flu_geo_save_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['flu_geo_meta_box_nonce'] ) ) {
        return;
    }

    if ( ! wp_verify_nonce( $_POST['flu_geo_meta_box_nonce'], 'flu_geo_save_meta_box_data' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_page', $post_id ) ) {
        return;
    }

    $maps_url = isset( $_POST['flu_geo_maps_url'] ) ? sanitize_url( $_POST['flu_geo_maps_url'] ) : '';
    $latitude = isset( $_POST['flu_geo_latitude'] ) ? sanitize_text_field( $_POST['flu_geo_latitude'] ) : '';
    $longitude = isset( $_POST['flu_geo_longitude'] ) ? sanitize_text_field( $_POST['flu_geo_longitude'] ) : '';
    $tolerance = isset( $_POST['flu_geo_tolerance'] ) ? sanitize_text_field( $_POST['flu_geo_tolerance'] ) : 'normal';

    if ( ! empty( $latitude ) && ! is_numeric( $latitude ) ) {
        $latitude = '';
    }
    if ( ! empty( $longitude ) && ! is_numeric( $longitude ) ) {
        $longitude = '';
    }

    update_post_meta( $post_id, '_flu_geo_maps_url', $maps_url );
    update_post_meta( $post_id, '_flu_geo_latitude', $latitude );
    update_post_meta( $post_id, '_flu_geo_longitude', $longitude );
    update_post_meta( $post_id, '_flu_geo_tolerance', $tolerance );
}
add_action( 'save_post', 'flu_geo_save_meta_box_data' );

/**
 * Enqueue Google Maps API
 */
function flu_geo_enqueue_google_maps() {
    if ( ! is_page() ) {
        return;
    }

    $post_id = get_the_ID();
    $latitude = get_post_meta( $post_id, '_flu_geo_latitude', true );
    $longitude = get_post_meta( $post_id, '_flu_geo_longitude', true );

    if ( empty( $latitude ) || empty( $longitude ) ) {
        return;
    }

    $google_maps_key = get_option( 'flu_core_google_maps_key', '' );

    if ( empty( $google_maps_key ) ) {
        error_log( 'Flu Core: Google Maps API key not configured' );
        return;
    }

    wp_enqueue_script(
        'google-maps-api',
        'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $google_maps_key ) . '&libraries=geometry&callback=initMap',
        array(),
        null,
        true
    );
}
add_action( 'wp_enqueue_scripts', 'flu_geo_enqueue_google_maps' );

/**
 * Add geolocation validation to pages with coordinates
 */
function flu_geo_add_validation_script() {
    if ( ! is_page() ) {
        return;
    }

    $post_id = get_the_ID();
    $latitude = get_post_meta( $post_id, '_flu_geo_latitude', true );
    $longitude = get_post_meta( $post_id, '_flu_geo_longitude', true );
    $tolerance = get_post_meta( $post_id, '_flu_geo_tolerance', true );

    if ( empty( $latitude ) || empty( $longitude ) ) {
        return;
    }

    $tolerance_meters = 50;
    switch ( $tolerance ) {
        case 'strict':
            $tolerance_meters = 10;
            break;
        case 'normal':
            $tolerance_meters = 50;
            break;
        case 'amplio':
            $tolerance_meters = 200;
            break;
    }

    ?>
    <script>
        // Inicializar Google Maps
        function initMap() {
            console.log('✅ Google Maps callback initialized');
        }

        console.log('🌍 Flu Geo: Geolocation script loading...');

        var targetLat = <?php echo floatval( $latitude ); ?>;
        var targetLng = <?php echo floatval( $longitude ); ?>;
        var tolerance = <?php echo intval( $tolerance_meters ); ?>;
        var currentPageId = <?php echo intval( $post_id ); ?>;
        var toleranceSetting = '<?php echo esc_js( $tolerance ); ?>';

        console.log('📍 Target coordinates:', targetLat, targetLng);
        console.log('📏 Tolerance:', toleranceSetting, '→', tolerance, 'meters');
        console.log('📄 Page ID:', currentPageId);

        // Verificar si la página ya fue capturada
        function isPageCaptured() {
            var cookie = getCookie('flu_captured_pages');
            if (cookie) {
                try {
                    var captured = JSON.parse(decodeURIComponent(cookie));
                    var isCaptured = captured.includes(currentPageId);
                    console.log('🎯 Page captured status:', isCaptured);
                    return isCaptured;
                } catch (e) {
                    return false;
                }
            }
            return false;
        }

        function getCookie(name) {
            var value = "; " + document.cookie;
            var parts = value.split("; " + name + "=");
            if (parts.length === 2) return parts.pop().split(";").shift();
            return null;
        }

        // Esperar a que Google Maps esté cargado
        function waitForGoogleMaps(callback) {
            if (typeof google !== 'undefined' && typeof google.maps !== 'undefined' &&
                typeof google.maps.geometry !== 'undefined' &&
                typeof google.maps.geometry.spherical !== 'undefined') {
                console.log('✅ Google Maps API fully loaded');
                callback();
            } else {
                console.log('⏳ Waiting for Google Maps API...');
                setTimeout(function() {
                    waitForGoogleMaps(callback);
                }, 100);
            }
        }

        // Verificar geolocalización cada vez que se pulsa el botón
        function checkGeolocation(callback) {
            // Si la página ya fue capturada, permitir acceso directo
            if (isPageCaptured()) {
                console.log('✅ Page already captured, skipping geo check');
                if (callback) callback(true);
                return;
            }

            if (!navigator.geolocation) {
                console.error('❌ Geolocation not supported');
                if (callback) callback(false);
                return;
            }

            console.log('🔍 Requesting fresh location...');

            // Forzar obtención de nueva ubicación cada vez
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    console.log('📱 Location obtained:', position.coords.latitude, position.coords.longitude);
                    console.log('📊 Accuracy:', position.coords.accuracy, 'meters');

                    waitForGoogleMaps(function() {
                        var userLat = position.coords.latitude;
                        var userLng = position.coords.longitude;

                        // Calcular distancia con Google Maps (mismo método que gym-geo)
                        var ubicacionEspecifica = new google.maps.LatLng(targetLat, targetLng);
                        var ubicacionActual = new google.maps.LatLng(userLat, userLng);
                        var distance = google.maps.geometry.spherical.computeDistanceBetween(
                            ubicacionActual,
                            ubicacionEspecifica
                        );

                        console.log('📏 Distance:', Math.round(distance), 'meters');
                        console.log('🎯 Tolerance:', tolerance, 'meters');
                        console.log('🧮 Result:', distance <= tolerance ? '✅ VALID' : '❌ OUT OF RANGE');

                        if (distance <= tolerance) {
                            document.body.classList.add('geo-validated');
                            document.body.classList.remove('geo-out-of-range', 'geo-error');
                            console.log('✅ Location validated!');
                            if (callback) callback(true);
                        } else {
                            document.body.classList.add('geo-out-of-range');
                            document.body.classList.remove('geo-validated', 'geo-error');
                            console.log('❌ Out of range. Need', Math.round(distance - tolerance), 'meters closer');
                            if (callback) callback(false);
                        }
                    });
                },
                function(error) {
                    console.error('❌ Geolocation error:', error.message);
                    document.body.classList.add('geo-error');
                    if (callback) callback(false);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0 // Crítico: siempre obtener posición fresca
                }
            );
        }

        // Interceptar clicks en enlaces #captura
        function handleCapturaLinks() {
            var capturaLinks = document.querySelectorAll('a[href="#captura"]');
            console.log('🔗 Found', capturaLinks.length, 'captura links');

            capturaLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    // Solo interceptar si no está marcado como ya verificado
                    if (this.getAttribute('data-geo-verified') === 'true') {
                        console.log('⏭️ Geo already verified for this click, proceeding...');
                        return; // Dejar que continúe normalmente
                    }

                    e.preventDefault();
                    e.stopPropagation();

                    console.log('🛑 Intercepting click - checking geolocation');

                    var originalText = this.textContent;
                    var spinner = '<span class="geo-loading"></span>';
                    this.innerHTML = 'Verificando ubicación...' + spinner;
                    this.style.pointerEvents = 'none';

                    var self = this;

                    // Verificar geolocalización CADA VEZ
                    waitForGoogleMaps(function() {
                        checkGeolocation(function(isValid) {
                            self.innerHTML = originalText;
                            self.style.pointerEvents = 'auto';

                            if (isValid) {
                                console.log('✅ Geo validated - proceeding to #captura');
                                // Marcar como verificado y permitir navegación
                                self.setAttribute('data-geo-verified', 'true');
                                // Hacer click programático para que flu-3d.php lo maneje
                                setTimeout(function() {
                                    self.click();
                                    // Resetear después
                                    setTimeout(function() {
                                        self.removeAttribute('data-geo-verified');
                                    }, 100);
                                }, 100);
                            } else {
                                console.log('❌ Geo validation failed - redirecting to #localizacion-ko');
                                window.location.hash = '#localizacion-ko';
                            }
                        });
                    });
                }, true); // Usar capture phase
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Flu Geo: Handler initialized');
            handleCapturaLinks();
        });
    </script>
    <?php
}
add_action( 'wp_footer', 'flu_geo_add_validation_script', 5 );

/**
 * Add CSS for loading animation
 */
function flu_geo_add_css_classes() {
    echo '<style>
        .geo-loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(0,0,0,0.1);
            border-top: 2px solid #007cba;
            border-radius: 50%;
            animation: geoSpin 1s linear infinite;
            margin-left: 8px;
            vertical-align: middle;
        }
        
        @keyframes geoSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>';
}
add_action( 'wp_head', 'flu_geo_add_css_classes' );
