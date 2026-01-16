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

        /* Loader de cámara */
        .camera-loader {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 200;
            background: var(--wp--preset--color--custom-white);
            padding: 20px 16px;
            border-radius: 16px;
            backdrop-filter: blur(10px);
            opacity: 1;
            transition: opacity 0.3s ease-out;
        }

        .camera-loader.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .camera-loader-text {
            color: var(--wp--preset--color--custom-grey);
            font-size: 18px;
            font-weight: 600;
            text-align: center;
        }

        .camera-loader-dots {
            display: inline-block;
            min-width: 30px;
            text-align: left;
        }

        /* Loader de virus con aguja de brújula */
        .virus-loader {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 100;
            width: 80%;
            max-width: 300px;
            opacity: 1;
            transition: opacity 0.3s ease-out;
        }

        .virus-loader.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .virus-loader-text {
            color: var(--wp--preset--color--custom-white, #fff);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 30px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }

        .virus-compass {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            position: relative;
        }

        .virus-compass-circle {
            width: 100%;
            height: 100%;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            position: relative;
        }

        .virus-compass-needle {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 3px;
            height: 25px;
            background: linear-gradient(to bottom, var(--wp--preset--color--accent, #00ff88), rgba(255,255,255,0.5));
            transform-origin: center bottom;
            transform: translate(-50%, -100%);
            animation: compassSwing 2s ease-in-out infinite;
            box-shadow: 0 0 10px var(--wp--preset--color--accent, #00ff88);
        }

        .virus-compass-needle::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-bottom: 6px solid var(--wp--preset--color--accent, #00ff88);
        }

        @keyframes compassSwing {
            0% { transform: translate(-50%, -100%) rotate(-30deg); }
            50% { transform: translate(-50%, -100%) rotate(30deg); }
            100% { transform: translate(-50%, -100%) rotate(-30deg); }
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
            transition: opacity 0.5s ease-in-out;
        }

        .flu-captura a-scene.loaded {
            opacity: 1;
        }

        .flu-3d img {
            display: none;
        }
        html.a-fullscreen .flu-captura .a-canvas {
            position: absolute !important;
        }

        #captura .wp-block-button {
            position: relative !important;
            z-index: 1000 !important;
            pointer-events: none !important;
        }

        #captura .wp-block-button a {
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
            pointer-events: none;
        }

        #captura .wp-block-button.visible {
            pointer-events: auto !important;
        }

        #captura .wp-block-button.visible a {
            opacity: 1;
            pointer-events: auto;
        }

        .flu-captura .a-canvas {
            position: absolute !important;
            pointer-events: none !important;
        }

        #atrapado {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -1;
            opacity: 0;
            visibility: hidden;
            pointer-events: none !important;
            transition: opacity 0.4s ease-out, visibility 0s 0.4s, z-index 0s 0.4s;
            overflow-y: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(0, 0, 0, 0.95);
        }

        #atrapado > * {
            width: 100%;
            max-width: 600px;
            pointer-events: none !important;
        }

        #atrapado.show {
            z-index: 9999;
            width: 100vw;
            margin: 0;
            background: transparent;
            opacity: 1;
            visibility: visible;
            pointer-events: auto !important;
            transition: opacity 0.4s ease-out, visibility 0s, z-index 0s;
        }

        #atrapado.show > * {
            pointer-events: auto !important;
        }

        /* Modal para activar giroscopio */
        .gyro-activate-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: 0;
        }

        .gyro-activate-overlay.hidden {
            display: none;
        }

        .gyro-activate-modal {
            background: var(--wp--preset--color--base, #fff);
            border-radius: 16px;
            padding: 32px 24px;
            max-width: 320px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .gyro-activate-text {
            color: var(--wp--preset--color--contrast, #000);
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 24px;
            font-weight: 500;
        }

        .gyro-activate-button {
            background: var(--wp--preset--color--accent, #00ff88);
            color: var(--wp--preset--color--base, #000);
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            touch-action: manipulation;
            width: 100%;
            transition: transform 0.1s ease;
        }

        .gyro-activate-button:active {
            transform: scale(0.98);
        }
    </style>
    <script>
        var cameraInitialized = false;
        var gyroscopeInitialized = false;
        var capturaContainers = [];

        // Smoothed rotation values
        var currentRotation = { x: 0, y: 0, z: 0 };
        var targetRotation = { x: 0, y: 0, z: 0 };
        var smoothingFactor = 0.12;
        var animationFrameId = null;
        var sensorActive = false;
        var lastBeta = null;

        function getCookie(name) {
            const value = "; " + document.cookie;
            const parts = value.split("; " + name + "=");
            if (parts.length === 2) return parts.pop().split(";").shift();
            return null;
        }

        function setCookie(name, value, days) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = name + "=" + value + ";expires=" + expires.toUTCString() + ";path=/";
        }

        function hasGyroPermission() {
            return getCookie('flu_gyro_permission') === 'granted';
        }

        function saveGyroPermission() {
            setCookie('flu_gyro_permission', 'granted', 365); // 1 año
            console.log('✅ Permiso de giroscopio guardado en cookie');
        }

        // Función para crear el loader de cámara
        function createCameraLoader() {
            const loader = document.createElement('div');
            loader.className = 'camera-loader';

            const text = document.createElement('div');
            text.className = 'camera-loader-text';

            const textContent = document.createElement('span');
            textContent.textContent = 'Cargando cámara';

            const dots = document.createElement('span');
            dots.className = 'camera-loader-dots';
            dots.textContent = '';

            text.appendChild(textContent);
            text.appendChild(dots);
            loader.appendChild(text);

            // Animar los puntos
            let dotCount = 0;
            const dotInterval = setInterval(function() {
                dotCount = (dotCount + 1) % 4;
                dots.textContent = '.'.repeat(dotCount);
            }, 400);

            // Guardar el interval para poder limpiarlo después
            loader.dotInterval = dotInterval;

            return loader;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const capturaDivs = document.querySelectorAll('.flu-captura');

            capturaDivs.forEach(function(div) {
                capturaContainers.push(div);

                const isInCapturado = div.closest('#capturado') !== null;
                const isInCaptura = div.closest('#captura') !== null;

                if (isInCapturado) {
                    console.log('📍 Encontrado .flu-captura en #capturado');
                    const flu3dImg = div.querySelector('.flu-3d img');
                    if (flu3dImg && flu3dImg.src) {
                        const imgSrc = flu3dImg.src;
                        const fileName = imgSrc.split('/').pop().split('.')[0];
                        const modelPath = '/wp-content/uploads/' + fileName + '.glb';
                        flu3dImg.style.display = 'none';
                        createModelAFrameSceneForCapturado(div, modelPath);
                    }
                } else if (!isInCaptura) {
                    const flu3dImg = div.querySelector('.flu-3d img');
                    if (flu3dImg && flu3dImg.src) {
                        const imgSrc = flu3dImg.src;
                        const fileName = imgSrc.split('/').pop().split('.')[0];
                        const modelPath = '/wp-content/uploads/' + fileName + '.glb';
                        flu3dImg.style.display = 'none';
                        createModelAFrameSceneForCapturado(div, modelPath);
                    }
                }
            });

            initializeAtrapado();
            handleCapturaHash();

            window.addEventListener('hashchange', function() {
                handleCapturaHash();

                if (window.location.hash === '#atrapado') {
                    showAtrapado();
                } else {
                    hideAtrapado();
                }
            });

            document.addEventListener('click', function(e) {
                var target = e.target.closest('a[href="#atrapado"]');
                if (target) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.location.hash = '#atrapado';
                }
            }, true);

            if (window.location.hash === '#atrapado') {
                showAtrapado();
            }
        });

        function handleCapturaHash() {
            if (window.location.hash === '#captura' && !cameraInitialized) {
                if (document.body.classList.contains('geo-validated') || cameraInitialized) {
                    console.log('📍 Llegando a #captura - Solicitando permisos');
                    requestCameraAndGyroscopePermissions();
                } else {
                    console.log('⚠️ Acceso directo sin validación');
                    window.location.hash = 'presentacion';
                }
            }
        }

        function requestCameraAndGyroscopePermissions() {
            console.log('🎯 Iniciando solicitud de permisos');

            if (typeof DeviceOrientationEvent !== 'undefined' &&
                typeof DeviceOrientationEvent.requestPermission === 'function') {

                // Verificar si ya tenemos el permiso guardado
                if (hasGyroPermission()) {
                    console.log('✅ Permiso ya concedido previamente (cookie)');
                    requestCameraAndInitialize();
                    return;
                }

                console.log('🔐 iOS - Mostrando modal para activar');
                showGyroActivateButton(function() {
                    // Este callback se ejecuta cuando el usuario toca el botón
                    DeviceOrientationEvent.requestPermission()
                        .then(function(response) {
                            console.log('📱 Respuesta:', response);
                            if (response === 'granted') {
                                console.log('✅ Permiso concedido');
                                saveGyroPermission(); // Guardar en cookie
                                requestCameraAndInitialize();
                            } else {
                                console.log('❌ Permiso denegado');
                                alert('Necesitas activar el sensor de movimiento');
                            }
                        })
                        .catch(function(error) {
                            console.error('❌ Error:', error);
                            requestCameraAndInitialize();
                        });
                });
            } else {
                console.log('📱 Android - No requiere permiso');
                requestCameraAndInitialize();
            }
        }

        function showGyroActivateButton(callback) {
            const overlay = document.createElement('div');
            overlay.className = 'gyro-activate-overlay';

            const modal = document.createElement('div');
            modal.className = 'gyro-activate-modal';

            const text = document.createElement('div');
            text.className = 'gyro-activate-text';
            text.textContent = 'Para ver el virus en 3D necesitamos activar el sensor de movimiento';

            const button = document.createElement('button');
            button.className = 'gyro-activate-button';
            button.textContent = 'Activar';

            modal.appendChild(text);
            modal.appendChild(button);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            button.addEventListener('click', function() {
                console.log('👆 Usuario tocó el botón');
                overlay.classList.add('hidden');
                setTimeout(function() {
                    document.body.removeChild(overlay);
                }, 300);
                callback();
            });
        }

        function requestCameraAndInitialize() {
            console.log('📸 Pidiendo cámara');
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function(stream) {
                    stream.getTracks().forEach(function(track) {
                        track.stop();
                    });
                    console.log('✅ Cámara OK');
                    initializeCameraForCaptura();
                })
                .catch(function(error) {
                    console.error('❌ Error cámara:', error);
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
                setTimeout(function() {
                    atrapado.classList.add('show');
                    atrapado.style.pointerEvents = 'auto';
                    atrapado.style.visibility = 'visible';
                    atrapado.style.opacity = '1';
                    atrapado.style.zIndex = '9999';

                    var allChildren = atrapado.querySelectorAll('*');
                    allChildren.forEach(function(child) {
                        child.style.pointerEvents = 'auto';
                    });
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

            console.log('📸 Inicializando cámara');

            capturaContainers.forEach(function(container) {
                // Crear el loader de cámara primero
                const cameraLoader = createCameraLoader();
                container.appendChild(cameraLoader);

                // Después de 1 segundo, cargar la cámara
                setTimeout(function() {
                    requestCameraPermission(container, function() {
                        // Ocultar el loader de cámara
                        cameraLoader.classList.add('hidden');

                        // Limpiar el interval de animación de puntos
                        if (cameraLoader.dotInterval) {
                            clearInterval(cameraLoader.dotInterval);
                        }

                        // Remover el loader después de la transición
                        setTimeout(function() {
                            if (cameraLoader.parentNode) {
                                container.removeChild(cameraLoader);
                            }
                        }, 300);

                        const flu3dImg = container.querySelector('.flu-3d img');

                        if (flu3dImg && flu3dImg.src) {
                            const imgSrc = flu3dImg.src;
                            const fileName = imgSrc.split('/').pop().split('.')[0];
                            const modelPath = '/wp-content/uploads/' + fileName + '.glb';
                            createModelAFrameSceneWithLoader(container, modelPath);
                        } else {
                            createBasicAFrameSceneWithLoader(container);
                        }
                    });
                }, 1000); // Retraso de 1 segundo
            });

            cameraInitialized = true;
            console.log('🎯 Activando giroscopio');
            enableGyroscope();
        }

        function requestCameraPermission(container, callback) {
            if (container.querySelector('video')) {
                if (callback) callback();
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

                    if (callback) {
                        setTimeout(callback, 100);
                    }
                })
                .catch(function(error) {
                    console.error('❌ Error cámara:', error);
                    if (callback) callback();
                });
        }

        function startSmoothAnimation() {
            if (animationFrameId) return;

            function animate() {
                currentRotation.x += (targetRotation.x - currentRotation.x) * smoothingFactor;
                currentRotation.y += (targetRotation.y - currentRotation.y) * smoothingFactor;
                currentRotation.z += (targetRotation.z - currentRotation.z) * smoothingFactor;

                const models = document.querySelectorAll('a-gltf-model, a-box, a-sphere');
                models.forEach(function(model) {
                    model.setAttribute('rotation', {
                        x: currentRotation.x,
                        y: currentRotation.y,
                        z: currentRotation.z
                    });
                });

                animationFrameId = requestAnimationFrame(animate);
            }

            animate();
        }

        function enableGyroscope() {
            startSmoothAnimation();

            if (gyroscopeInitialized) {
                console.log('⚠️ Giroscopio ya inicializado');
                return;
            }

            gyroscopeInitialized = true;
            console.log('✅ Inicializando giroscopio');

            if ('RelativeOrientationSensor' in window) {
                tryRelativeSensorAPI();
            } else if ('AbsoluteOrientationSensor' in window) {
                tryAbsoluteSensorAPI();
            } else if (window.DeviceOrientationEvent) {
                window.addEventListener('deviceorientation', handleOrientation);
            }
        }

        function tryRelativeSensorAPI() {
            try {
                const sensor = new RelativeOrientationSensor({ frequency: 60 });

                sensor.addEventListener('reading', function() {
                    if (sensorActive && sensorActive !== 'relative') return;

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

                    targetRotation.x = Math.max(-15, Math.min(15, pitchDeg * 0.3));
                    targetRotation.y = yawDeg * 0.5;
                    targetRotation.z = Math.max(-10, Math.min(10, -rollDeg * 0.2));
                });

                sensor.addEventListener('error', function(e) {
                    console.log('❌ RelativeOrientationSensor error:', e.error);
                    sensorActive = false;
                    tryAbsoluteSensorAPI();
                });

                sensor.start();
                sensorActive = 'relative';
                console.log('✅ RelativeOrientationSensor');

            } catch (error) {
                console.log('❌ RelativeOrientationSensor N/A');
                tryAbsoluteSensorAPI();
            }
        }

        function tryAbsoluteSensorAPI() {
            if (sensorActive) return;

            try {
                const sensor = new AbsoluteOrientationSensor({ frequency: 60, referenceFrame: 'device' });

                sensor.addEventListener('reading', function() {
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

                    targetRotation.x = Math.max(-15, Math.min(15, pitchDeg * 0.3));
                    targetRotation.y = yawDeg * 0.5;
                    targetRotation.z = Math.max(-10, Math.min(10, -rollDeg * 0.2));
                });

                sensor.addEventListener('error', function(e) {
                    console.log('❌ AbsoluteOrientationSensor error:', e.error);
                    sensorActive = false;
                    window.addEventListener('deviceorientation', handleOrientation);
                });

                sensor.start();
                sensorActive = 'absolute';
                console.log('✅ AbsoluteOrientationSensor');

            } catch (error) {
                console.log('❌ AbsoluteOrientationSensor N/A');
                window.addEventListener('deviceorientation', handleOrientation);
                console.log('✅ deviceorientation fallback');
            }
        }

        function handleOrientation(event) {
            var beta = event.beta;
            var gamma = event.gamma || 0;

            if (beta === null || beta === undefined) return;

            if (beta > 90) beta = 90;
            else if (beta < -90) beta = -90;

            if (lastBeta !== null) {
                var deltaBeta = Math.abs(beta - lastBeta);
                if (deltaBeta > 45) return;
            }
            lastBeta = beta;

            var rotationX = (beta - 90) * 0.2;
            var rotationY = -gamma * 0.4;

            rotationX = Math.max(-12, Math.min(12, rotationX));
            rotationY = Math.max(-15, Math.min(15, rotationY));

            targetRotation.x = rotationX;
            targetRotation.y = rotationY;
            targetRotation.z = 0;
        }

        function createBasicAFrameSceneWithLoader(container) {
            const loader = createVirusLoader();
            container.appendChild(loader);

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

            const randomDelay = Math.random() * 1000 + 2000;

            setTimeout(function() {
                loader.classList.add('hidden');
                scene.classList.add('loaded');

                setTimeout(function() {
                    const buttons = container.querySelectorAll('.wp-block-button');
                    buttons.forEach(function(btn) {
                        btn.classList.add('visible');
                    });
                }, 1000);
            }, randomDelay);
        }

        function createModelAFrameSceneWithLoader(container, modelPath) {
            const loader = createVirusLoader();
            container.appendChild(loader);

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
            model.setAttribute('scale', '2 2 2');
            model.setAttribute('rotation', '0 0 0');

            scene.appendChild(model);
            scene.appendChild(camera);

            container.appendChild(scene);

            const randomDelay = Math.random() * 1000 + 2000;

            setTimeout(function() {
                loader.classList.add('hidden');
                scene.classList.add('loaded');

                setTimeout(function() {
                    const buttons = container.querySelectorAll('.wp-block-button');
                    buttons.forEach(function(btn) {
                        btn.classList.add('visible');
                    });
                }, 1000);
            }, randomDelay);
        }

        function createModelAFrameSceneForCapturado(container, modelPath) {
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
            model.setAttribute('position', '0 2.5 -3');
            model.setAttribute('scale', '2 2 2');
            model.setAttribute('rotation', '0 0 0');

            scene.appendChild(model);
            scene.appendChild(camera);

            container.appendChild(scene);

            scene.classList.add('loaded');

            if (!gyroscopeInitialized) {
                if (typeof DeviceOrientationEvent !== 'undefined' &&
                    typeof DeviceOrientationEvent.requestPermission === 'function') {

                    // Verificar si ya tenemos el permiso guardado
                    if (hasGyroPermission()) {
                        console.log('✅ Permiso ya concedido previamente en #capturado (cookie)');
                        enableGyroscope();
                        return;
                    }

                    console.log('🔐 iOS - Mostrando modal para #capturado');
                    setTimeout(function() {
                        showGyroActivateButton(function() {
                            DeviceOrientationEvent.requestPermission()
                                .then(function(response) {
                                    console.log('📱 Respuesta en #capturado:', response);
                                    if (response === 'granted') {
                                        console.log('✅ Permiso concedido en #capturado');
                                        saveGyroPermission(); // Guardar en cookie
                                        enableGyroscope();
                                    } else {
                                        console.log('❌ Permiso denegado en #capturado');
                                    }
                                })
                                .catch(function(error) {
                                    console.error('❌ Error en #capturado:', error);
                                });
                        });
                    }, 500);
                } else {
                    console.log('📱 Android - Activando giroscopio directamente en #capturado');
                    enableGyroscope();
                }
            }
        }

        function createVirusLoader() {
            const loader = document.createElement('div');
            loader.className = 'virus-loader';

            const text = document.createElement('div');
            text.className = 'virus-loader-text';
            text.textContent = 'Localizando virus';

            const compass = document.createElement('div');
            compass.className = 'virus-compass';

            const circle = document.createElement('div');
            circle.className = 'virus-compass-circle';

            const needle = document.createElement('div');
            needle.className = 'virus-compass-needle';

            circle.appendChild(needle);
            compass.appendChild(circle);
            loader.appendChild(text);
            loader.appendChild(compass);

            return loader;
        }
    </script>
    <?php
}
add_action('wp_footer', 'flu_3d_aframe_functionality');
