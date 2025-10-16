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
    echo '<label for="flu_geo_tolerance_strict"><input type="radio" id="flu_geo_tolerance_strict" name="flu_geo_tolerance" value="strict" ' . checked( $tolerance, 'strict', false ) . '> Estricta (10m)</label><br>';
    echo '<label for="flu_geo_tolerance_normal"><input type="radio" id="flu_geo_tolerance_normal" name="flu_geo_tolerance" value="normal" ' . checked( $tolerance, 'normal', false ) . '> Normal (50m)</label><br>';
    echo '<label for="flu_geo_tolerance_amplio"><input type="radio" id="flu_geo_tolerance_amplio" name="flu_geo_tolerance" value="amplio" ' . checked( $tolerance, 'amplio', false ) . '> Amplio (200m)</label><br>';
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

    wp_enqueue_script(
        'google-maps-geo',
        'https://maps.googleapis.com/maps/api/js?key=AIzaSyA5RJ70oNMmFe-cwV-ibuZ8RCIy5L0vNhU&libraries=geometry',
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
        case 'amplio':
            $tolerance_meters = 200;
            break;
    }

    ?>
    <script>
        var targetLat = <?php echo floatval( $latitude ); ?>;
        var targetLng = <?php echo floatval( $longitude ); ?>;
        var tolerance = <?php echo intval( $tolerance_meters ); ?>;

        function calculateDistance(lat1, lng1, lat2, lng2) {
            if (typeof google !== 'undefined' && google.maps && google.maps.geometry) {
                var ubicacionEspecifica = new google.maps.LatLng(lat1, lng1);
                var ubicacionActual = new google.maps.LatLng(lat2, lng2);
                return google.maps.geometry.spherical.computeDistanceBetween(ubicacionActual, ubicacionEspecifica);
            }

            // Fallback: Haversine formula
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

        function checkGeolocation(button) {
            if (!navigator.geolocation) {
                console.error('Geolocalización no soportada');
                window.location.hash = '#localizacion-ko';
                return;
            }

            var originalText = button.textContent;
            button.textContent = 'Comprobando...';
            button.style.pointerEvents = 'none';

            // Agregar la clase active al contenedor progress-btn si existe
            var progressBtn = button.closest('.progress-btn');
            if (progressBtn) {
                progressBtn.classList.add('active');
            }

            // Primero pedir permiso de giroscopio en iOS (antes de la geolocalización)
            if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
                console.log('iOS detectado - pidiendo permiso de giroscopio primero...');
                DeviceOrientationEvent.requestPermission()
                    .then(function(response) {
                        console.log('Respuesta permiso giroscopio:', response);
                        // Continuar con geolocalización independientemente de la respuesta
                        proceedWithGeolocation(button, originalText, progressBtn);
                    })
                    .catch(function(error) {
                        console.log('Error permiso giroscopio (continuando):', error);
                        // Continuar con geolocalización aunque falle
                        proceedWithGeolocation(button, originalText, progressBtn);
                    });
            } else {
                // Android o navegadores sin restricción - continuar directamente
                proceedWithGeolocation(button, originalText, progressBtn);
            }
        }

        function proceedWithGeolocation(button, originalText, progressBtn) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    var userLat = position.coords.latitude;
                    var userLng = position.coords.longitude;
                    var distance = calculateDistance(targetLat, targetLng, userLat, userLng);

                    console.log('Distancia calculada:', distance, 'metros');
                    console.log('Tolerancia:', tolerance, 'metros');

                    var tiempoEspera = 2000;

                    if (distance <= tolerance) {
                        console.log('✓ Ubicación correcta');
                        setTimeout(function() {
                            window.location.hash = '#captura';
                            button.textContent = originalText;
                            button.style.pointerEvents = 'auto';
                            if (progressBtn) {
                                progressBtn.classList.remove('active');
                            }
                        }, tiempoEspera);
                    } else {
                        console.log('✗ Fuera de rango');
                        setTimeout(function() {
                            window.location.hash = '#localizacion-ko';
                            button.textContent = originalText;
                            button.style.pointerEvents = 'auto';
                            if (progressBtn) {
                                progressBtn.classList.remove('active');
                            }
                        }, tiempoEspera);
                    }
                },
                function(error) {
                    console.error('Error de geolocalización:', error.message);
                    button.textContent = originalText;
                    button.style.pointerEvents = 'auto';
                    if (progressBtn) {
                        progressBtn.classList.remove('active');
                    }
                    window.location.hash = '#localizacion-ko';
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        }

        function handleCapturaLinks() {
            var capturaLinks = document.querySelectorAll('a[href="#captura"]');

            capturaLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    checkGeolocation(this);
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            handleCapturaLinks();
        });

        // Prevenir caché de la página
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
    </script>
    <?php
}
add_action( 'wp_footer', 'flu_geo_add_validation_script' );

/**
 * Add CSS for loading animation and progress button
 */
function flu_geo_add_css_classes() {
    if ( ! is_page() ) {
        return;
    }

    $post_id = get_the_ID();
    $latitude = get_post_meta( $post_id, '_flu_geo_latitude', true );
    $longitude = get_post_meta( $post_id, '_flu_geo_longitude', true );

    if ( empty( $latitude ) || empty( $longitude ) ) {
        return;
    }

    echo '<style>
        .progress-btn {
            transition: all 0.4s ease;
            position: relative;
        }
        
        .progress-btn .wp-block-button__link {
            position: relative;
            z-index: 10;
        }
        
        .progress-btn .progress {
            width: 0;
            z-index: 5;
            background-color: var(--wp--preset--color--accent, #4CC3D9);
            opacity: 0;
            transition: all 0.3s ease;
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            border-radius: inherit;
        }
        
        .progress-btn.active .progress {
            opacity: 0.3;
            animation: progress-anim 2s ease 0s forwards;
        }
        
        @keyframes progress-anim {
            0% {
                width: 0%;
            }
            10% {
                width: 15%;
            }
            30% {
                width: 40%;
            }
            50% {
                width: 55%;
            }
            80% {
                width: 100%;
            }
            100% {
                width: 100%;
            }
        }
    </style>';
}
add_action( 'wp_head', 'flu_geo_add_css_classes' );
