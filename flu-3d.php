<?php
/**
 * A-Frame 3D functionality for Fluvial Core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Load A-Frame library
 */
function flu_3d_load_aframe() {
    echo '<script src="https://aframe.io/releases/1.4.0/aframe.min.js"></script>';
}
add_action('wp_head', 'flu_3d_load_aframe');

/**
 * Add A-Frame functionality to camera and 3D images
 */
function flu_3d_aframe_functionality() {
    ?>
    <style>
        .flu-captura {
            position: relative;
            overflow: hidden;
        }
        .flu-captura video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1;
        }
        .flu-captura a-scene {
            position: relative !important;
            z-index: 1;
            background: transparent !important;
            width: 100vw !important;
            height: 100vh !important;
            display: block !important;
            margin-top: -100vh;
            pointer-events: none;
            opacity: 0;
            animation: fadeInModel 0.3s ease-in-out forwards;
        }
        .flu-3d img {
            display: none;
        }
        html.a-fullscreen .flu-captura .a-canvas {
            position: absolute !important;
        }
        .wp-block-button,
        a[href*="atrapado"],
        .flu-captura .wp-block-button a {
            position: relative !important;
            z-index: 1000 !important;
            pointer-events: auto !important;
            opacity: 0;
            animation: fadeInButton 0.3s ease-in-out forwards;
        }
        .flu-captura .a-canvas {
            position: absolute !important;
            pointer-events: none !important;
        }

        #atrapado {
            position: fixed;
            top: 0;
            width: 100vw;
            height: 100%;
            z-index: -1;
            opacity: 0;
            visibility: hidden;
            transform: scale(0.9);
            pointer-events: none !important;
            transition: opacity 0.4s ease-out, transform 0.4s ease-out, visibility 0s 0.4s, z-index 0s 0.4s;
            overflow-y: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        #atrapado > * {
            width: 100%;
            max-width: 600px;
            pointer-events: none !important;
        }

        #atrapado.show {
            z-index: 2000;
            opacity: 1;
            visibility: visible;
            transform: scale(1);
            pointer-events: auto !important;
            transition: opacity 0.4s ease-out, transform 0.4s ease-out, visibility 0s, z-index 0s;
        }

        #atrapado.show > * {
            pointer-events: auto !important;
        }

        /* ============================================
         * SECCIÓN #CAPTURADO - SIN ANIMACIONES
         * ============================================ */

        #capturado .flu-captura a-scene,
        #capturado .wp-block-button,
        #capturado a[href*="atrapado"],
        #capturado .flu-captura .wp-block-button a {
            animation: none !important;
            opacity: 1 !important;
            transform: none !important;
        }

        /*
         * ⚙️ AJUSTE DE PADDING SUPERIOR DEL MODELO 3D EN #CAPTURADO
         * Cambia el valor de "padding-top" para ajustar la posición:
         * - 0px   = Pegado arriba
         * - 10px  = Poco espacio
         * - 20px  = Espacio normal (VALOR ACTUAL) ← MODIFICA AQUÍ
         * - 30px  = Más espacio
         */
        #capturado .flu-captura {
            padding-top: 20px; /* ← MODIFICA ESTE VALOR */
        }

        /* ============================================
         * SECCIÓN #ATRAPADO - SIN DELAYS DE ANIMACIÓN
         * ============================================ */

        #atrapado .flu-captura a-scene,
        #atrapado .wp-block-button,
        #atrapado a[href*="atrapado"],
        #atrapado .flu-captura .wp-block-button a {
            animation-delay: 0s !important;
        }

        @keyframes fadeInModel {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes fadeInButton {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
    </style>
    <script>
        var cameraInitialized = false;
        var gyroscopeInitialized = false;
        var capturaContainers = [];
        var lastValidRotation = { x: 0, y: 0 };

        document.addEventListener('DOMContentLoaded', function() {
            const capturaDivs = document.querySelectorAll('.flu-captura');

            capturaDivs.forEach(function(div) {
                capturaContainers.push(div);

                const flu3dImg = div.querySelector('.flu-3d img');

                if (flu3dImg && flu3dImg.src) {
                    const imgSrc = flu3dImg.src;
                    const fileName = imgSrc.split('/').pop().split('.')[0];
                    const modelPath = '/wp-content/uploads/' + fileName + '.glb';
                    flu3dImg.style.display = 'none';
                    createModelAFrameScene(div, modelPath);
                } else {
                    createBasicAFrameScene(div);
                }
            });

            initializeAtrapado();
            interceptCapturaLinks();
            interceptAtrapadorLinks();

            if (window.location.hash === '#captura') {
                initializeCameraForCaptura();
            }

            if (window.location.hash === '#atrapado') {
                // Si ya se pidió permiso en #captura, reutilizarlo
                if (gyroscopeInitialized) {
                    showAtrapado();
                } else if (typeof DeviceOrientationEvent === 'undefined' ||
                    typeof DeviceOrientationEvent.requestPermission !== 'function') {
                    // Android: activar directamente
                    enableGyroscopeForAtrapado();
                    showAtrapado();
                } else {
                    // iOS sin permiso previo: mostrar botón
                    createGyroscopeButton();
                    showAtrapado();
                }
            }

            window.addEventListener('hashchange', function() {
                if (window.location.hash === '#captura' && !cameraInitialized) {
                    initializeCameraForCaptura();
                }

                if (window.location.hash === '#atrapado') {
                    // Si es iOS y NO se ha pedido permiso antes, mostrar botón
                    if (!gyroscopeInitialized &&
                        typeof DeviceOrientationEvent !== 'undefined' &&
                        typeof DeviceOrientationEvent.requestPermission === 'function') {
                        createGyroscopeButton();
                    }
                    showAtrapado();
                } else {
                    hideAtrapado();
                    // Eliminar botón si existe
                    var existingBtn = document.getElementById('gyro-activate-btn');
                    if (existingBtn) {
                        existingBtn.remove();
                    }
                }
            });
        });

        function interceptCapturaLinks() {
            var capturaLinks = document.querySelectorAll('a[href="#captura"]');

            capturaLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    // Si NO está marcado como geo-checked, dejar que flu-geolocation.php lo maneje primero
                    if (this.getAttribute('data-geo-checked') !== 'true') {
                        return; // No hacer nada, dejar pasar al handler de geo
                    }

                    // Si llegamos aquí, geo ya fue validada
                    e.preventDefault();
                    e.stopPropagation();

                    if (typeof DeviceOrientationEvent !== 'undefined' &&
                        typeof DeviceOrientationEvent.requestPermission === 'function') {

                        DeviceOrientationEvent.requestPermission()
                            .then(function(response) {
                                if (response === 'granted') {
                                    requestCameraAndNavigate();
                                } else {
                                    alert('Necesitas activar el sensor de movimiento para ver el modelo en AR');
                                }
                            })
                            .catch(function(error) {
                                alert('Error: ' + error.message);
                            });
                    } else {
                        requestCameraAndNavigate();
                    }
                }, true);
            });
        }

        function interceptAtrapadorLinks() {
            var atrapadorLinks = document.querySelectorAll('a[href="#atrapado"], a[href*="#atrapado"]');

            atrapadorLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Si ya se activó el giroscopio antes (en #captura), simplemente navegar
                    if (gyroscopeInitialized) {
                        window.location.hash = '#atrapado';
                        return;
                    }

                    // Si no, pedir permiso (solo en iOS)
                    if (typeof DeviceOrientationEvent !== 'undefined' &&
                        typeof DeviceOrientationEvent.requestPermission === 'function') {

                        DeviceOrientationEvent.requestPermission()
                            .then(function(response) {
                                if (response === 'granted') {
                                    enableGyroscopeForAtrapado();
                                    window.location.hash = '#atrapado';
                                } else {
                                    alert('Necesitas activar el sensor de movimiento para ver el modelo 3D en movimiento');
                                }
                            })
                            .catch(function(error) {
                                alert('Error al solicitar permisos');
                            });
                    } else {
                        enableGyroscopeForAtrapado();
                        window.location.hash = '#atrapado';
                    }
                }, true);
            });
        }

        function requestCameraAndNavigate() {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function(stream) {
                    stream.getTracks().forEach(function(track) {
                        track.stop();
                    });
                    window.location.hash = '#captura';
                })
                .catch(function(error) {
                    alert('Error al acceder a la cámara: ' + error.message);
                });
        }

        function initializeAtrapado() {
            var atrapado = document.getElementById('atrapado');
            if (atrapado) {
                atrapado.classList.remove('show');
                atrapado.style.pointerEvents = 'none';
                atrapado.style.visibility = 'hidden';
                atrapado.style.opacity = '0';
                atrapado.style.zIndex = '-1';

                var allChildren = atrapado.querySelectorAll('*');
                allChildren.forEach(function(child) {
                    child.style.pointerEvents = 'none';
                });
            }
        }

        function showAtrapado() {
            var atrapado = document.getElementById('atrapado');
            if (atrapado) {
                window.scrollTo(0, 0);

                setTimeout(function() {
                    atrapado.classList.add('show');
                    atrapado.style.pointerEvents = 'auto';
                    atrapado.style.visibility = 'visible';
                    atrapado.style.opacity = '1';
                    atrapado.style.zIndex = '2000';

                    var allChildren = atrapado.querySelectorAll('*');
                    allChildren.forEach(function(child) {
                        child.style.pointerEvents = 'auto';
                    });

                    // CRÍTICO: Forzar que A-Frame actualice los modelos en #atrapado
                    setTimeout(function() {
                        var models = atrapado.querySelectorAll('a-gltf-model, a-box, a-sphere');

                        if (models.length > 0) {
                            // Forzar actualización haciendo un pequeño cambio y luego restaurando
                            models.forEach(function(model) {
                                // Guardar rotación actual
                                var currentRot = model.getAttribute('rotation');

                                // Forzar cambio
                                model.setAttribute('rotation', '0.1 0.1 0.1');

                                // Restaurar inmediatamente
                                setTimeout(function() {
                                    if (currentRot) {
                                        model.setAttribute('rotation', currentRot);
                                    } else {
                                        model.setAttribute('rotation', '0 0 0');
                                    }
                                }, 10);
                            });
                        }

                        // Si el giroscopio NO está activo, activarlo ahora
                        if (!gyroscopeInitialized) {
                            enableGyroscopeForAtrapado();
                        }
                    }, 300);
                }, 50);
            }
        }

        function hideAtrapado() {
            var atrapado = document.getElementById('atrapado');
            if (atrapado) {
                atrapado.classList.remove('show');

                setTimeout(function() {
                    atrapado.style.pointerEvents = 'none';
                    atrapado.style.visibility = 'hidden';
                    atrapado.style.opacity = '0';
                    atrapado.style.zIndex = '-1';

                    var allChildren = atrapado.querySelectorAll('*');
                    allChildren.forEach(function(child) {
                        child.style.pointerEvents = 'none';
                    });
                }, 400);
            }
        }

        function initializeCameraForCaptura() {
            if (cameraInitialized) return;

            cameraInitialized = true;

            capturaContainers.forEach(function(container) {
                if (!container.closest('#capturado')) {
                    requestCameraPermission(container);
                }
            });

            enableGyroscope();
        }

        function requestCameraPermission(container) {
            if (container.querySelector('video')) {
                return;
            }

            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function(stream) {
                    const video = document.createElement('video');
                    video.autoplay = true;
                    video.muted = true;
                    video.playsInline = true;
                    video.srcObject = stream;
                    container.appendChild(video);
                })
                .catch(function(error) {
                    // Silencioso
                });
        }

        function enableGyroscopeForAtrapado() {
            var checkInterval = setInterval(function() {
                var atrapado = document.getElementById('atrapado');
                if (atrapado) {
                    var model = atrapado.querySelector('a-gltf-model');
                    if (model && model.hasLoaded) {
                        clearInterval(checkInterval);
                        if (!gyroscopeInitialized) {
                            enableGyroscope();
                        }
                    }
                }
            }, 100);

            setTimeout(function() {
                clearInterval(checkInterval);
                if (!gyroscopeInitialized) {
                    enableGyroscope();
                }
            }, 3000);
        }

        function createGyroscopeButton() {
            // Evitar duplicados
            var existingBtn = document.getElementById('gyro-activate-btn');
            if (existingBtn) {
                return;
            }

            // Crear botón flotante
            var button = document.createElement('button');
            button.id = 'gyro-activate-btn';
            button.innerHTML = '📱 Toca para activar el movimiento 3D';
            button.style.cssText = 'position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); z-index: 10000; padding: 18px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 30px; font-size: 16px; font-weight: 600; box-shadow: 0 8px 20px rgba(102,126,234,0.4); cursor: pointer; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; transition: all 0.3s ease;';

            // Efecto hover
            button.onmouseover = function() {
                this.style.transform = 'translateX(-50%) scale(1.05)';
                this.style.boxShadow = '0 12px 28px rgba(102,126,234,0.6)';
            };
            button.onmouseout = function() {
                this.style.transform = 'translateX(-50%) scale(1)';
                this.style.boxShadow = '0 8px 20px rgba(102,126,234,0.4)';
            };

            button.onclick = function() {
                DeviceOrientationEvent.requestPermission()
                    .then(function(response) {
                        if (response === 'granted') {
                            enableGyroscopeForAtrapado();
                            button.style.transition = 'all 0.3s ease';
                            button.style.opacity = '0';
                            button.style.transform = 'translateX(-50%) scale(0.8)';
                            setTimeout(function() {
                                button.remove();
                            }, 300);
                        } else {
                            button.innerHTML = '❌ Permiso denegado - Toca de nuevo';
                            button.style.background = 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)';
                            setTimeout(function() {
                                button.innerHTML = '📱 Toca para activar el movimiento 3D';
                                button.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                            }, 2000);
                        }
                    })
                    .catch(function(error) {
                        button.innerHTML = '⚠️ Error - Intenta de nuevo';
                        setTimeout(function() {
                            button.innerHTML = '📱 Toca para activar el movimiento 3D';
                        }, 2000);
                    });
            };

            document.body.appendChild(button);

            // Animación de entrada
            setTimeout(function() {
                button.style.animation = 'slideUp 0.5s ease-out forwards';
            }, 100);
        }

        function enableGyroscope() {
            if (gyroscopeInitialized) return;

            gyroscopeInitialized = true;

            if ('Gyroscope' in window && 'Accelerometer' in window) {
                tryBasicSensorsAPI();
                return;
            }

            if ('RelativeOrientationSensor' in window) {
                tryRelativeSensorAPI();
                return;
            }

            if ('AbsoluteOrientationSensor' in window) {
                tryGenericSensorAPI();
                return;
            }

            if (window.DeviceOrientationEvent) {
                window.addEventListener('deviceorientation', handleOrientation);
                window.addEventListener('deviceorientationabsolute', handleOrientation);
            }
        }

        async function tryBasicSensorsAPI() {
            try {
                const gyroscope = new Gyroscope({ frequency: 60 });
                const accelerometer = new Accelerometer({ frequency: 60 });

                let gyroData = { x: 0, y: 0, z: 0 };
                let angles = { pitch: 0, yaw: 0, roll: 0 };

                gyroscope.addEventListener('reading', function() {
                    gyroData.x = gyroscope.x || 0;
                    gyroData.y = gyroscope.y || 0;
                    gyroData.z = gyroscope.z || 0;

                    const dt = 1/60;
                    angles.pitch += gyroData.x * dt * (180 / Math.PI);
                    angles.yaw += gyroData.y * dt * (180 / Math.PI);
                    angles.roll += gyroData.z * dt * (180 / Math.PI);

                    angles.pitch = angles.pitch % 360;
                    angles.yaw = angles.yaw % 360;
                    angles.roll = angles.roll % 360;
                });

                accelerometer.addEventListener('reading', function() {
                    // Buscar TODOS los modelos cada vez
                    const models = document.querySelectorAll('a-gltf-model, a-box, a-sphere');

                    models.forEach(function(model) {
                        try {
                            const rotX = angles.pitch * 0.3;
                            const rotY = angles.yaw * 0.5;
                            const rotZ = -angles.roll * 0.2;

                            model.setAttribute('rotation', {
                                x: Math.max(-15, Math.min(15, rotX)),
                                y: rotY,
                                z: Math.max(-10, Math.min(10, rotZ))
                            });
                        } catch (e) {
                            // Silencioso
                        }
                    });
                });

                gyroscope.start();
                accelerometer.start();

            } catch (error) {
                // Silencioso
            }
        }

        async function tryRelativeSensorAPI() {
            try {
                const sensor = new RelativeOrientationSensor({ frequency: 60 });

                sensor.addEventListener('reading', function() {
                    // Buscar TODOS los modelos cada vez
                    const models = document.querySelectorAll('a-gltf-model, a-box, a-sphere');
                    if (models.length === 0) return;

                    const q = sensor.quaternion;
                    if (!q || q.length !== 4) return;

                    const [x, y, z, w] = q;

                    const sinp = 2 * (w * x + y * z);
                    const pitch = Math.abs(sinp) >= 1 ?
                        Math.sign(sinp) * Math.PI / 2 :
                        Math.asin(sinp);

                    const siny_cosp = 2 * (w * y - z * x);
                    const cosy_cosp = 1 - 2 * (x * x + y * y);
                    const yaw = Math.atan2(siny_cosp, cosy_cosp);

                    const sinr_cosp = 2 * (w * z + x * y);
                    const cosr_cosp = 1 - 2 * (y * y + z * z);
                    const roll = Math.atan2(sinr_cosp, cosr_cosp);

                    const pitchDeg = pitch * (180 / Math.PI);
                    const yawDeg = yaw * (180 / Math.PI);
                    const rollDeg = roll * (180 / Math.PI);

                    models.forEach(function(model) {
                        try {
                            const rotX = pitchDeg * 0.3;
                            const rotY = yawDeg * 0.5;
                            const rotZ = -rollDeg * 0.2;

                            model.setAttribute('rotation', {
                                x: Math.max(-15, Math.min(15, rotX)),
                                y: rotY,
                                z: Math.max(-10, Math.min(10, rotZ))
                            });
                        } catch (e) {
                            // Silencioso
                        }
                    });
                });

                sensor.start();

            } catch (error) {
                // Silencioso
            }
        }

        async function tryGenericSensorAPI() {
            try {
                const sensor = new AbsoluteOrientationSensor({ frequency: 60, referenceFrame: 'device' });

                sensor.addEventListener('reading', function() {
                    // Buscar TODOS los modelos cada vez
                    const models = document.querySelectorAll('a-gltf-model, a-box, a-sphere');
                    if (models.length === 0) return;

                    const q = sensor.quaternion;
                    if (!q || q.length !== 4) return;

                    const [x, y, z, w] = q;

                    const sinp = 2 * (w * x + y * z);
                    const pitch = Math.abs(sinp) >= 1 ?
                        Math.sign(sinp) * Math.PI / 2 :
                        Math.asin(sinp);

                    const siny_cosp = 2 * (w * y - z * x);
                    const cosy_cosp = 1 - 2 * (x * x + y * y);
                    const yaw = Math.atan2(siny_cosp, cosy_cosp);

                    const sinr_cosp = 2 * (w * z + x * y);
                    const cosr_cosp = 1 - 2 * (y * y + z * z);
                    const roll = Math.atan2(sinr_cosp, cosr_cosp);

                    const pitchDeg = pitch * (180 / Math.PI);
                    const yawDeg = yaw * (180 / Math.PI);
                    const rollDeg = roll * (180 / Math.PI);

                    models.forEach(function(model) {
                        try {
                            const rotX = pitchDeg * 0.3;
                            const rotY = yawDeg * 0.5;
                            const rotZ = -rollDeg * 0.2;

                            model.setAttribute('rotation', {
                                x: Math.max(-15, Math.min(15, rotX)),
                                y: rotY,
                                z: Math.max(-10, Math.min(10, rotZ))
                            });
                        } catch (e) {
                            // Silencioso
                        }
                    });
                });

                sensor.start();

            } catch (error) {
                // Silencioso
            }
        }

        function handleOrientation(event) {
            const models = document.querySelectorAll('a-gltf-model, a-box, a-sphere');
            if (models.length === 0) return;

            var alpha = event.alpha || 0;
            var beta = event.beta || 0;
            var gamma = event.gamma || 0;

            var rotationY = -gamma * 0.5;
            var rotationX = (beta - 90) * 0.3;

            rotationY = Math.max(-20, Math.min(20, rotationY));
            rotationX = Math.max(-15, Math.min(15, rotationX));

            var deltaX = Math.abs(rotationX - lastValidRotation.x);
            var deltaY = Math.abs(rotationY - lastValidRotation.y);

            if (lastValidRotation.x !== 0 && (deltaX > 30 || deltaY > 30)) {
                return;
            }

            lastValidRotation.x = rotationX;
            lastValidRotation.y = rotationY;

            // Actualizar TODOS los modelos, estén donde estén
            models.forEach(function(model) {
                try {
                    model.setAttribute('rotation', {
                        x: rotationX,
                        y: rotationY,
                        z: 0
                    });
                } catch (e) {
                    // Silencioso si hay error
                }
            });
        }

        function createBasicAFrameScene(container) {
            const scene = document.createElement('a-scene');
            scene.setAttribute('vr-mode-ui', 'enabled: false');
            scene.setAttribute('device-orientation-permission-ui', 'enabled: false');
            scene.setAttribute('background', 'transparent');
            scene.setAttribute('embedded', '');

            const camera = document.createElement('a-camera');
            camera.setAttribute('look-controls', 'enabled: false');
            camera.setAttribute('wasd-controls', 'enabled: false');
            camera.setAttribute('device-orientation-controls', 'enabled: false');
            camera.setAttribute('position', '0 1.6 0');

            const box = document.createElement('a-box');
            box.setAttribute('position', '-1 0.5 -3');
            box.setAttribute('rotation', '0 45 0');
            box.setAttribute('color', '#4CC3D9');

            const sphere = document.createElement('a-sphere');
            sphere.setAttribute('position', '0 1.25 -5');
            sphere.setAttribute('radius', '1.25');
            sphere.setAttribute('color', '#EF2D5E');

            const cylinder = document.createElement('a-cylinder');
            cylinder.setAttribute('position', '1 0.75 -3');
            cylinder.setAttribute('radius', '0.5');
            cylinder.setAttribute('height', '1.5');
            cylinder.setAttribute('color', '#FFC65D');

            const plane = document.createElement('a-plane');
            plane.setAttribute('position', '0 0 -4');
            plane.setAttribute('rotation', '-90 0 0');
            plane.setAttribute('width', '4');
            plane.setAttribute('height', '4');
            plane.setAttribute('color', '#7BC8A4');
            plane.setAttribute('opacity', '0.8');

            scene.appendChild(box);
            scene.appendChild(sphere);
            scene.appendChild(cylinder);
            scene.appendChild(plane);
            scene.appendChild(camera);

            container.appendChild(scene);
        }

        function createModelAFrameScene(container, modelPath) {
            const scene = document.createElement('a-scene');
            scene.setAttribute('vr-mode-ui', 'enabled: false');
            scene.setAttribute('device-orientation-permission-ui', 'enabled: false');
            scene.setAttribute('background', 'color: transparent; transparent: true');
            scene.setAttribute('renderer', 'alpha: true; antialias: true');
            scene.setAttribute('embedded', '');

            const camera = document.createElement('a-camera');
            camera.setAttribute('look-controls', 'enabled: false');
            camera.setAttribute('wasd-controls', 'enabled: false');
            camera.setAttribute('device-orientation-controls', 'enabled: false');
            camera.setAttribute('position', '0 1.6 0');

            const model = document.createElement('a-gltf-model');
            model.setAttribute('src', modelPath);
            model.setAttribute('position', '0 1.5 -3');
            model.setAttribute('rotation', '0 0 0');
            model.setAttribute('scale', '2 2 2');

            scene.appendChild(model);
            scene.appendChild(camera);

            container.appendChild(scene);

            const isInCapturado = container.closest('#capturado');
            const isInAtrapado = container.closest('#atrapado');

            if (!isInCapturado && !isInAtrapado) {
                const randomModelDelay = Math.random() * 1000 + 1000;
                const buttonDelay = randomModelDelay + 500;

                scene.style.animationDelay = randomModelDelay + 'ms';

                const buttons = container.querySelectorAll('.wp-block-button, a[href*="atrapado"]');
                buttons.forEach(function(button) {
                    button.style.animationDelay = buttonDelay + 'ms';
                });
            }
        }
    </script>
    <?php
}
add_action('wp_footer', 'flu_3d_aframe_functionality', 10);
