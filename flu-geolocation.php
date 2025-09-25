<?php
/**
 * Geolocation functionality for Fluvial Core
 * Add coordinate fields to pages and validate user location
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
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
    echo '<label for="flu_geo_tolerance_strict"><input type="radio" id="flu_geo_tolerance_strict" name="flu_geo_tolerance" value="strict" ' . checked( $tolerance, 'strict', false ) . '> Estricta (5m)</label><br>';
    echo '<label for="flu_geo_tolerance_normal"><input type="radio" id="flu_geo_tolerance_normal" name="flu_geo_tolerance" value="normal" ' . checked( $tolerance, 'normal', false ) . '> Normal (10m)</label><br>';
    echo '<label for="flu_geo_tolerance_amplio"><input type="radio" id="flu_geo_tolerance_amplio" name="flu_geo_tolerance" value="amplio" ' . checked( $tolerance, 'amplio', false ) . '> Amplio (50m)</label><br>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '<p><em>Deja la URL vacía si no quieres validación de ubicación para esta página.</em></p>';

    // JavaScript para extraer coordenadas
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
 * Check if current page is game start page
 */
function flu_geo_is_game_start_page() {
    $current_url = $_SERVER['REQUEST_URI'];

    // Check if URL is exactly /virus/ or /eu/virus/ (with optional trailing slash)
    $is_virus_start = preg_match('#^/virus/?$#', $current_url);
    $is_eu_virus_start = preg_match('#^/eu/virus/?$#', $current_url);

    $result = $is_virus_start || $is_eu_virus_start;

    // Debug log
    error_log("URL: $current_url, Is game start: " . ($result ? 'YES' : 'NO'));

    return $result;
}

/**
 * Request permissions only on game start pages
 */
function flu_geo_request_permissions() {
    if ( ! flu_geo_is_game_start_page() ) {
        return;
    }
    ?>
    <!-- DEBUG: Esta página SÍ es página de inicio del juego -->
    <script>
        console.log('🎮 GAME START PAGE DETECTED - Will request permissions');

        function getCookie(name) {
            const value = "; " + document.cookie;
            const parts = value.split("; " + name + "=");
            if (parts.length === 2) return parts.pop().split(";").shift();
            return null;
        }

        function setCookie(name, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + value + expires + "; path=/";
        }

        function getPermissions() {
            const cookie = getCookie('flu_permissions');
            if (cookie) {
                try {
                    return JSON.parse(decodeURIComponent(cookie));
                } catch (e) {
                    return {};
                }
            }
            return {};
        }

        function savePermissions(permissions) {
            setCookie('flu_permissions', encodeURIComponent(JSON.stringify(permissions)), 365);
        }

        document.addEventListener('DOMContentLoaded', function() {
            console.log('Game start page detected - checking permissions');

            // Check if permissions were already requested
            const existingPermissions = getPermissions();
            if (existingPermissions.requested === true) {
                console.log('Permissions already requested previously:', existingPermissions);
                return;
            }

            // Request all permissions sequentially
            requestPermissionsSequentially();
        });

        async function requestPermissionsSequentially() {
            const permissions = {
                requested: true,
                geo: 'denied',
                camera: 'denied',
                gyro: 'denied',
                timestamp: new Date().toISOString()
            };

            try {
                // 1. Request geolocation permission
                console.log('Requesting geolocation permission...');
                if (navigator.geolocation) {
                    try {
                        const position = await new Promise((resolve, reject) => {
                            navigator.geolocation.getCurrentPosition(resolve, reject, {
                                enableHighAccuracy: true,
                                timeout: 10000,
                                maximumAge: 60000
                            });
                        });
                        console.log('Geolocation permission granted');
                        permissions.geo = 'granted';
                    } catch (error) {
                        console.log('Geolocation permission denied:', error.message);
                        permissions.geo = 'denied';
                    }
                } else {
                    permissions.geo = 'not_available';
                }

                // 2. Request camera permission
                console.log('Requesting camera permission...');
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' }
                    });
                    console.log('Camera permission granted');
                    permissions.camera = 'granted';
                    // Stop the stream immediately
                    stream.getTracks().forEach(track => track.stop());
                } catch (error) {
                    console.log('Camera permission denied:', error);
                    permissions.camera = 'denied';
                }

                // 3. Request gyroscope permission (iOS)
                console.log('Requesting gyroscope permission...');
                if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
                    try {
                        const response = await DeviceOrientationEvent.requestPermission();
                        console.log('Gyroscope permission:', response);
                        permissions.gyro = response;
                    } catch (error) {
                        console.log('Gyroscope permission error:', error);
                        permissions.gyro = 'denied';
                    }
                } else {
                    // For non-iOS devices, gyroscope is usually available without explicit permission
                    permissions.gyro = 'granted';
                }

                // Save all permissions to cookie
                savePermissions(permissions);
                console.log('All permissions requested and saved to cookie:', permissions);

            } catch (error) {
                console.error('Error requesting permissions:', error);
                // Save even if there were errors
                savePermissions(permissions);
            }
        }
    </script>
    <?php
}
add_action( 'wp_footer', 'flu_geo_request_permissions' );

/**
 * Add geolocation validation to pages with coordinates (without requesting permissions again)
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

    $tolerance_meters = 10;
    switch ( $tolerance ) {
        case 'strict':
            $tolerance_meters = 5;
            break;
        case 'amplio':
            $tolerance_meters = 50;
            break;
    }

    ?>
    <script>
        console.log('Geo script loading...');

        var targetLat = <?php echo floatval( $latitude ); ?>;
        var targetLng = <?php echo floatval( $longitude ); ?>;
        var tolerance = <?php echo intval( $tolerance_meters ); ?>;

        console.log('Target coordinates:', targetLat, targetLng);
        console.log('Tolerance:', tolerance, 'meters');

        function calculateDistance(lat1, lng1, lat2, lng2) {
            var R = 6371e3;
            var φ1 = lat1 * Math.PI/180;
            var φ2 = lat2 * Math.PI/180;
            var Δφ = (lat2-lat1) * Math.PI/180;
            var Δλ = (lng2-lng1) * Math.PI/180;

            var a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                Math.cos(φ1) * Math.cos(φ2) *
                Math.sin(Δλ/2) * Math.sin(Δλ/2);
            var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

            return R * c;
        }

        function checkGeolocation() {
            if (!navigator.geolocation) {
                return;
            }

            // Get permissions from cookie instead of localStorage
            function getCookie(name) {
                const value = "; " + document.cookie;
                const parts = value.split("; " + name + "=");
                if (parts.length === 2) return parts.pop().split(";").shift();
                return null;
            }

            function getPermissions() {
                const cookie = getCookie('flu_permissions');
                if (cookie) {
                    try {
                        return JSON.parse(decodeURIComponent(cookie));
                    } catch (e) {
                        return {};
                    }
                }
                return {};
            }

            const permissions = getPermissions();

            if (permissions.geo === 'denied') {
                document.body.classList.add('geo-error');
                window.dispatchEvent(new CustomEvent('fluGeoError', {
                    detail: { error: 'Permission denied' }
                }));
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    var userLat = position.coords.latitude;
                    var userLng = position.coords.longitude;
                    var distance = calculateDistance(targetLat, targetLng, userLat, userLng);

                    console.log('User location:', userLat, userLng);
                    console.log('Distance to target:', distance, 'meters');

                    if (distance <= tolerance) {
                        document.body.classList.add('geo-validated');

                        window.dispatchEvent(new CustomEvent('fluGeoValidated', {
                            detail: { distance: distance, tolerance: tolerance }
                        }));
                    } else {
                        document.body.classList.add('geo-out-of-range');

                        window.dispatchEvent(new CustomEvent('fluGeoOutOfRange', {
                            detail: { distance: distance, tolerance: tolerance }
                        }));
                    }
                },
                function(error) {
                    document.body.classList.add('geo-error');

                    window.dispatchEvent(new CustomEvent('fluGeoError', {
                        detail: { error: error.message }
                    }));
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 30000
                }
            );
        }

        function handleCapturaLinks() {
            var capturaLinks = document.querySelectorAll('a[href="#captura"]');

            capturaLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();

                    var originalText = this.textContent;
                    var spinner = '<span class="geo-loading"></span>';
                    this.innerHTML = 'Verificando ubicación...' + spinner;
                    this.style.pointerEvents = 'none';

                    var self = this;

                    setTimeout(function() {
                        if (document.body.classList.contains('geo-validated')) {
                            window.location.hash = '#captura';
                        } else if (document.body.classList.contains('geo-out-of-range') ||
                            document.body.classList.contains('geo-error')) {
                            window.location.hash = '#localizacion-ko';
                        } else {
                            setTimeout(function() {
                                if (document.body.classList.contains('geo-validated')) {
                                    window.location.hash = '#captura';
                                } else {
                                    window.location.hash = '#localizacion-ko';
                                }

                                self.innerHTML = originalText;
                                self.style.pointerEvents = 'auto';
                            }, 2000);
                            return;
                        }

                        self.innerHTML = originalText;
                        self.style.pointerEvents = 'auto';
                    }, 500);
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(checkGeolocation, 1000);
            handleCapturaLinks();
        });
    </script>
    <?php
}
add_action( 'wp_footer', 'flu_geo_add_validation_script' );

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
