<?php
/**
 * Geolocation functionality for Fluvial Core
 * Add coordinate fields to pages and validate user location
 * WITH GPS PRE-WARMING AND CONTINUOUS POSITION TRACKING
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
    $manual_latitude = get_post_meta( $post->ID, '_flu_geo_manual_latitude', true );
    $manual_longitude = get_post_meta( $post->ID, '_flu_geo_manual_longitude', true );
    $tolerance = get_post_meta( $post->ID, '_flu_geo_tolerance', true );

    if ( empty( $tolerance ) ) {
        $tolerance = 'normal';
    }

    echo '<table class="form-table">';

    // NUEVOS CAMPOS MANUALES (PRIORITARIOS)
    echo '<tr>';
    echo '<th scope="row"><label for="flu_geo_manual_latitude">Coordenadas Manuales (PRIORITARIAS)</label></th>';
    echo '<td>';
    echo '<strong>Latitud:</strong><br>';
    echo '<input type="number" step="0.000001" id="flu_geo_manual_latitude" name="flu_geo_manual_latitude" value="' . esc_attr( $manual_latitude ) . '" placeholder="Ej: 38.345678" style="width: 100%; max-width: 300px;" /><br><br>';
    echo '<strong>Longitud:</strong><br>';
    echo '<input type="number" step="0.000001" id="flu_geo_manual_longitude" name="flu_geo_manual_longitude" value="' . esc_attr( $manual_longitude ) . '" placeholder="Ej: -0.481747" style="width: 100%; max-width: 300px;" />';
    echo '<p class="description">Si introduces coordenadas manuales, estas tendrán prioridad sobre las extraídas de Google Maps.</p>';
    echo '</td>';
    echo '</tr>';

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
    echo '<label for="flu_geo_tolerance_strict"><input type="radio" id="flu_geo_tolerance_strict" name="flu_geo_tolerance" value="strict" ' . checked( $tolerance, 'strict', false ) . '> Estricta (50m)</label><br>';
    echo '<label for="flu_geo_tolerance_normal"><input type="radio" id="flu_geo_tolerance_normal" name="flu_geo_tolerance" value="normal" ' . checked( $tolerance, 'normal', false ) . '> Normal (100m)</label><br>';
    echo '<label for="flu_geo_tolerance_amplio"><input type="radio" id="flu_geo_tolerance_amplio" name="flu_geo_tolerance" value="amplio" ' . checked( $tolerance, 'amplio', false ) . '> Amplio (150m)</label><br>';
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
    $manual_latitude = isset( $_POST['flu_geo_manual_latitude'] ) ? sanitize_text_field( $_POST['flu_geo_manual_latitude'] ) : '';
    $manual_longitude = isset( $_POST['flu_geo_manual_longitude'] ) ? sanitize_text_field( $_POST['flu_geo_manual_longitude'] ) : '';
    $tolerance = isset( $_POST['flu_geo_tolerance'] ) ? sanitize_text_field( $_POST['flu_geo_tolerance'] ) : 'normal';

    if ( ! empty( $latitude ) && ! is_numeric( $latitude ) ) {
        $latitude = '';
    }
    if ( ! empty( $longitude ) && ! is_numeric( $longitude ) ) {
        $longitude = '';
    }
    if ( ! empty( $manual_latitude ) && ! is_numeric( $manual_latitude ) ) {
        $manual_latitude = '';
    }
    if ( ! empty( $manual_longitude ) && ! is_numeric( $manual_longitude ) ) {
        $manual_longitude = '';
    }

    update_post_meta( $post_id, '_flu_geo_maps_url', $maps_url );
    update_post_meta( $post_id, '_flu_geo_latitude', $latitude );
    update_post_meta( $post_id, '_flu_geo_longitude', $longitude );
    update_post_meta( $post_id, '_flu_geo_manual_latitude', $manual_latitude );
    update_post_meta( $post_id, '_flu_geo_manual_longitude', $manual_longitude );
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

    // Prioridad: coordenadas manuales primero
    $manual_latitude = get_post_meta( $post_id, '_flu_geo_manual_latitude', true );
    $manual_longitude = get_post_meta( $post_id, '_flu_geo_manual_longitude', true );

    if ( ! empty( $manual_latitude ) && ! empty( $manual_longitude ) ) {
        $latitude = $manual_latitude;
        $longitude = $manual_longitude;
    } else {
        $latitude = get_post_meta( $post_id, '_flu_geo_latitude', true );
        $longitude = get_post_meta( $post_id, '_flu_geo_longitude', true );
    }

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
        'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $google_maps_key ) . '&libraries=geometry',
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

    // Prioridad: coordenadas manuales primero
    $manual_latitude = get_post_meta( $post_id, '_flu_geo_manual_latitude', true );
    $manual_longitude = get_post_meta( $post_id, '_flu_geo_manual_longitude', true );

    if ( ! empty( $manual_latitude ) && ! empty( $manual_longitude ) ) {
        $latitude = $manual_latitude;
        $longitude = $manual_longitude;
    } else {
        $latitude = get_post_meta( $post_id, '_flu_geo_latitude', true );
        $longitude = get_post_meta( $post_id, '_flu_geo_longitude', true );
    }

    $tolerance = get_post_meta( $post_id, '_flu_geo_tolerance', true );

    if ( empty( $latitude ) || empty( $longitude ) ) {
        return;
    }

    $tolerance_meters = 100;
    switch ( $tolerance ) {
        case 'strict':
            $tolerance_meters = 50;
            break;
        case 'amplio':
            $tolerance_meters = 150;
            break;
    }

    ?>
    <script>
        console.log('🌍 Flu Geo: GPS Pre-warming System Active');

        var targetLat = <?php echo floatval( $latitude ); ?>;
        var targetLng = <?php echo floatval( $longitude ); ?>;
        var tolerance = <?php echo intval( $tolerance_meters ); ?>;

        console.log('📍 Target coordinates:', targetLat, targetLng);
        console.log('📏 Tolerance:', tolerance, 'meters');

        // GPS Pre-warming system
        var gpsWatchId = null;
        var bestPosition = null;
        var bestAccuracy = Infinity;
        var isGpsWarmedUp = false;

        function waitForGoogleMaps(callback) {
            if (typeof google !== 'undefined' && typeof google.maps !== 'undefined' && typeof google.maps.geometry !== 'undefined') {
                console.log('✅ Google Maps API loaded');
                callback();
            } else {
                console.log('⏳ Waiting for Google Maps API...');
                setTimeout(function() {
                    waitForGoogleMaps(callback);
                }, 100);
            }
        }

        function detectGoogleMapsApp() {
            var userAgent = navigator.userAgent || navigator.vendor || window.opera;

            // Detectar iOS
            var isIOS = /iPad|iPhone|iPod/.test(userAgent) && !window.MSStream;

            // Detectar Android
            var isAndroid = /android/i.test(userAgent);

            return { isIOS: isIOS, isAndroid: isAndroid };
        }

        function tryOpenGoogleMapsForPreWarming() {
            var device = detectGoogleMapsApp();

            console.log('🗺️ Intentando pre-calentar GPS con Google Maps...');

            // Crear un iframe invisible que intente abrir Google Maps
            // Esto ayuda a que el GPS se active en segundo plano
            var mapsUrl;

            if (device.isIOS) {
                // iOS - intentar abrir Google Maps app o web
                mapsUrl = 'comgooglemaps://?center=' + targetLat + ',' + targetLng + '&zoom=16';

                // Timeout para fallback a web version
                setTimeout(function() {
                    console.log('📱 Fallback a Google Maps web para pre-warming');
                }, 1000);

            } else if (device.isAndroid) {
                // Android - intentar abrir Google Maps app
                mapsUrl = 'geo:' + targetLat + ',' + targetLng + '?q=' + targetLat + ',' + targetLng;
            }

            // Crear elemento invisible para intentar trigger
            if (mapsUrl) {
                var trigger = document.createElement('iframe');
                trigger.style.display = 'none';
                trigger.src = mapsUrl;
                document.body.appendChild(trigger);

                // Limpiar después de 2 segundos
                setTimeout(function() {
                    document.body.removeChild(trigger);
                }, 2000);
            }
        }

        function startGPSPreWarming() {
            console.log('🔥 Iniciando pre-calentamiento GPS con watchPosition...');

            var warmupStartTime = Date.now();
            var warmupDuration = 20000; // 20 segundos de pre-calentamiento

            // Intentar abrir Google Maps en segundo plano
            tryOpenGoogleMapsForPreWarming();

            if (!navigator.geolocation) {
                console.error('❌ Geolocalización no soportada');
                return;
            }

            gpsWatchId = navigator.geolocation.watchPosition(
                function(position) {
                    var elapsed = Date.now() - warmupStartTime;
                    var accuracy = position.coords.accuracy;

                    console.log('📡 GPS Reading:', {
                        accuracy: accuracy.toFixed(2) + 'm',
                        elapsed: (elapsed/1000).toFixed(1) + 's',
                        lat: position.coords.latitude.toFixed(6),
                        lng: position.coords.longitude.toFixed(6)
                    });

                    // Guardar la mejor posición (menor accuracy)
                    if (accuracy < bestAccuracy) {
                        bestPosition = position;
                        bestAccuracy = accuracy;
                        console.log('✨ Nueva mejor posición guardada:', accuracy.toFixed(2) + 'm');
                    }

                    // Si conseguimos buena precisión antes de tiempo, marcar como listo
                    if (accuracy <= 20 && elapsed >= 5000) {
                        console.log('🎯 Excelente precisión alcanzada:', accuracy.toFixed(2) + 'm');
                        isGpsWarmedUp = true;
                        stopGPSPreWarming();
                    }

                    // Después de 20 segundos, usar la mejor posición que tengamos
                    if (elapsed >= warmupDuration) {
                        console.log('⏰ Pre-calentamiento completado. Mejor precisión:', bestAccuracy.toFixed(2) + 'm');
                        isGpsWarmedUp = true;
                        stopGPSPreWarming();
                    }
                },
                function(error) {
                    console.error('❌ Error en watchPosition:', error.message);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 30000,
                    maximumAge: 0
                }
            );
        }

        function stopGPSPreWarming() {
            if (gpsWatchId !== null) {
                navigator.geolocation.clearWatch(gpsWatchId);
                gpsWatchId = null;
                console.log('🛑 GPS pre-warming detenido');
            }
        }

        function checkGeolocationWithWarmup(linkElement) {
            if (!navigator.geolocation) {
                console.error('❌ Geolocation not supported');
                document.body.classList.remove('geo-checking');
                window.location.hash = 'localizacion-ko';
                setTimeout(function() {
                    var section = document.getElementById('localizacion-ko');
                    if (section) section.scrollIntoView();
                }, 100);
                return;
            }

            console.log('🎯 Iniciando validación con GPS pre-calentado');

            var startTime = Date.now();
            var minWaitTime = 2000; // Mínimo 2 segundos de espera visual

            // Si ya tenemos una buena posición del pre-calentamiento, usarla
            if (bestPosition && bestAccuracy < 100) {
                console.log('✅ Usando posición pre-calentada:', bestAccuracy.toFixed(2) + 'm');

                var elapsed = Date.now() - startTime;
                var remainingWait = Math.max(0, minWaitTime - elapsed);

                setTimeout(function() {
                    validatePosition(bestPosition);
                }, remainingWait);

                return;
            }

            // Si no, hacer una última lectura fresca
            console.log('📡 Obteniendo lectura GPS final...');

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    console.log('✅ Posición final obtenida:', position.coords.accuracy.toFixed(2) + 'm');

                    // Si esta posición es mejor que la pre-calentada, usarla
                    if (!bestPosition || position.coords.accuracy < bestAccuracy) {
                        bestPosition = position;
                        bestAccuracy = position.coords.accuracy;
                    }

                    var elapsed = Date.now() - startTime;
                    var remainingWait = Math.max(0, minWaitTime - elapsed);

                    setTimeout(function() {
                        validatePosition(bestPosition);
                    }, remainingWait);
                },
                function(error) {
                    console.error('❌ Error en lectura final:', error.message);

                    // Si tenemos una posición del pre-calentamiento, usarla
                    if (bestPosition) {
                        console.log('⚠️ Usando posición pre-calentada como fallback');
                        var elapsed = Date.now() - startTime;
                        var remainingWait = Math.max(0, minWaitTime - elapsed);

                        setTimeout(function() {
                            validatePosition(bestPosition);
                        }, remainingWait);
                    } else {
                        document.body.classList.remove('geo-checking');
                        window.location.hash = 'localizacion-ko';
                        setTimeout(function() {
                            var section = document.getElementById('localizacion-ko');
                            if (section) section.scrollIntoView();
                        }, 100);
                    }
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        }

        function validatePosition(position) {
            waitForGoogleMaps(function() {
                var userLat = position.coords.latitude;
                var userLng = position.coords.longitude;
                var accuracy = position.coords.accuracy;

                console.log('🔍 Validando posición:', {
                    accuracy: accuracy.toFixed(2) + 'm',
                    userLat: userLat.toFixed(6),
                    userLng: userLng.toFixed(6)
                });

                var ubicacionEspecifica = new google.maps.LatLng(targetLat, targetLng);
                var ubicacionActual = new google.maps.LatLng(userLat, userLng);
                var distance = google.maps.geometry.spherical.computeDistanceBetween(
                    ubicacionActual,
                    ubicacionEspecifica
                );

                console.log('📏 Distancia calculada:', distance.toFixed(2) + 'm');
                console.log('🎯 Tolerancia:', tolerance + 'm');
                console.log('✔️ ¿Dentro del rango?', distance <= tolerance);

                document.body.classList.remove('geo-checking');

                if (distance <= tolerance) {
                    console.log('✅ ¡Ubicación validada! → #captura');
                    document.body.classList.add('geo-validated');
                    document.body.classList.remove('geo-out-of-range');
                    window.location.hash = 'captura';
                    setTimeout(function() {
                        var section = document.getElementById('captura');
                        if (section) section.scrollIntoView();
                    }, 100);
                } else {
                    console.log('❌ Fuera de rango (' + distance.toFixed(2) + 'm) → #localizacion-ko');
                    document.body.classList.add('geo-out-of-range');
                    document.body.classList.remove('geo-validated');
                    window.location.hash = 'localizacion-ko';
                    setTimeout(function() {
                        var section = document.getElementById('localizacion-ko');
                        if (section) section.scrollIntoView();
                    }, 100);
                }
            });
        }

        function handleCapturaLinks() {
            var capturaLinks = document.querySelectorAll('a[href="#captura"]');

            capturaLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    document.body.classList.add('geo-checking');

                    this.setAttribute('data-original-text', this.textContent);

                    var spinner = '<span class="geo-loading"></span>';
                    this.innerHTML = 'Verificando ubicación...' + spinner;
                    this.style.pointerEvents = 'none';

                    checkGeolocationWithWarmup(this);

                }, true);
            });
        }

        function handleLocationSections() {
            var koSection = document.getElementById('localizacion-ko');

            function updateSections() {
                var hash = window.location.hash;

                if (koSection) {
                    if (hash === '#localizacion-ko') {
                        koSection.style.display = 'block';
                    } else {
                        koSection.style.display = 'none';
                    }
                }

                if (hash === '#presentacion' || hash === '') {
                    document.body.classList.remove('geo-checking', 'geo-validated', 'geo-out-of-range');

                    var capturaLinks = document.querySelectorAll('a[href="#captura"]');
                    capturaLinks.forEach(function(link) {
                        var originalText = link.getAttribute('data-original-text');
                        if (originalText) {
                            link.innerHTML = originalText;
                        }
                        link.style.pointerEvents = 'auto';
                    });
                }
            }

            updateSections();
            window.addEventListener('hashchange', updateSections);
        }

        // Iniciar pre-calentamiento al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Página cargada - Iniciando sistema GPS');

            // Iniciar pre-calentamiento GPS inmediatamente
            startGPSPreWarming();

            handleCapturaLinks();
            handleLocationSections();

            // Detener pre-calentamiento si el usuario navega fuera
            window.addEventListener('beforeunload', function() {
                stopGPSPreWarming();
            });
        });
    </script>
    <?php
}
add_action( 'wp_footer', 'flu_geo_add_validation_script' );

/**
 * Add CSS for loading animation and geo states
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
        
        #localizacion-ko {
            display: none;
        }
    </style>';
}
add_action( 'wp_head', 'flu_geo_add_css_classes' );
