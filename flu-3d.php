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

        #capturado .flu-captura a-scene,
        #capturado .wp-block-button,
        #capturado a[href*="atrapado"],
        #capturado .flu-captura .wp-block-button a {
            animation: none !important;
            opacity: 1 !important;
            transform: none !important;
        }

        /* IMPORTANTE: Subir modelo en #capturado */
        #capturado .flu-captura a-scene {
            margin-top: -150vh !important;
        }

        #capturado .flu-captura {
            padding-top: 0px;
        }

        #atrapado .flu-captura a-scene,
        #atrapado .wp-block-button,
        #atrapado a[href*="atrapado"],
        #atrapado .flu-captura .wp-block-button a {
            animation-delay: 0s !important;
        }

        /* IMPORTANTE: Subir modelo en #atrapado */
        #atrapado .flu-captura a-scene {
            margin-top: -110vh !important;
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

        #gyro-activate-btn {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
            padding: 18px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            box-shadow: 0 8px 20px rgba(102,126,234,0.4);
            cursor: pointer;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            transition: all 0.3s ease;
            animation: slideUp 0.5s ease-out forwards;
        }

        #gyro-activate-btn:hover {
            transform: translateX(-50%) scale(1.05);
            box-shadow: 0 12px 28px rgba(102,126,234,0.6);
        }
    </style>
    <script>
        var cameraInitialized = false;
        var gyroscopePermissionGranted = false;
        var gyroscopeActive = false;
        var capturaContainers = [];

        document.addEventListener('DOMContentLoaded', function() {
            console.log('🎬 Flu 3D: Initializing...');

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

            // Manejar carga inicial
            if (window.location.hash === '#captura') {
                handleCapturaHash();
            }
            if (window.location.hash === '#atrapado') {
                handleAtrapadorHash();
            }
            if (window.location.hash === '#capturado') {
                handleCapturadoHash();
            }

            // Manejar cambios de hash
            window.addEventListener('hashchange', function() {
                console.log('🔀 Hash changed to:', window.location.hash);

                if (window.location.hash === '#captura') {
                    handleCapturaHash();
                } else if (window.location.hash === '#atrapado') {
                    handleAtrapadorHash();
                } else if (window.location.hash === '#capturado') {
                    handleCapturadoHash();
                } else {
                    hideAtrapado();
                    removeGyroButton();
                }
            });
        });

        function handleCapturaHash() {
            console.log('📸 Handling #captura');

            // Si ya tenemos permiso de giroscopio, activarlo
            if (gyroscopePermissionGranted && !gyroscopeActive) {
                console.log('✅ Gyro permission already granted, activating...');
                activateGyroscope();
            }

            // Inicializar cámara si no está ya
            if (!cameraInitialized) {
                initializeCameraForCaptura();
            }
        }

        function handleAtrapadorHash() {
            console.log('🎯 Handling #atrapado');

            // Mostrar #atrapado
            showAtrapado();

            // Si ya tenemos permiso, activar giroscopio
            if (gyroscopePermissionGranted && !gyroscopeActive) {
                console.log('✅ Gyro permission already granted, activating...');
                activateGyroscope();
            }
            // Si NO tenemos permiso, intentar activar directamente
            else if (!gyroscopePermissionGranted) {
                console.log('⚠️ No gyro permission for #atrapado, trying to activate...');
                gyroscopePermissionGranted = true;
                activateGyroscope();
            }
        }

        function handleCapturadoHash() {
            console.log('🏆 Handling #capturado');

            // Si ya tenemos permiso, activar giroscopio
            if (gyroscopePermissionGranted && !gyroscopeActive) {
                console.log('✅ Gyro permission already granted, activating for #capturado...');
                activateGyroscope();
            }
            // Si NO tenemos permiso (iOS o Android), intentar activar
            else if (!gyroscopePermissionGranted) {
                console.log('⚠️ No gyro permission for #capturado, trying to activate...');
                // Marcar como concedido y activar (el sistema mostrará su propio prompt si es necesario)
                gyroscopePermissionGranted = true;
                activateGyroscope();
            }
        }

        function isIOS() {
            return typeof DeviceOrientationEvent !== 'undefined' &&
                typeof DeviceOrientationEvent.requestPermission === 'function';
        }

        function interceptCapturaLinks() {
            var capturaLinks = document.querySelectorAll('a[href="#captura"]');
            console.log('🔗 Found', capturaLinks.length, 'captura links');

            capturaLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    var self = this;

                    // CASO 1: Ya verificado por geo - proceder a pedir cámara
                    if (this.getAttribute('data-geo-verified') === 'true') {
                        console.log('✅ Geo verified, requesting camera...');
                        e.preventDefault();
                        e.stopPropagation();

                        // Limpiar el atributo para que el próximo click vuelva a verificar
                        this.removeAttribute('data-geo-verified');

                        requestCameraAndNavigate();
                        return;
                    }

                    // CASO 2: iOS sin permiso de giroscopio - pedirlo AHORA
                    if (isIOS() && !gyroscopePermissionGranted) {
                        e.preventDefault();
                        e.stopPropagation();

                        console.log('📱 iOS: Requesting gyro permission with user gesture...');
                        DeviceOrientationEvent.requestPermission()
                            .then(function(response) {
                                if (response === 'granted') {
                                    console.log('✅ Gyro permission granted!');
                                    gyroscopePermissionGranted = true;

                                    // Ahora hacer click de nuevo para que flu-geolocation lo maneje
                                    console.log('🔄 Re-clicking to trigger geo check...');
                                    setTimeout(function() {
                                        self.click();
                                    }, 100);
                                } else {
                                    console.log('❌ Gyro permission denied');
                                    alert('Necesitas activar el sensor de movimiento para continuar');
                                }
                            })
                            .catch(function(error) {
                                console.error('❌ Error requesting gyro permission:', error);
                                alert('Error al solicitar permisos: ' + error.message);
                            });
                        return;
                    }

                    // CASO 3: Android o iOS con permiso ya concedido - dejar pasar a geo
                    if (!isIOS()) {
                        console.log('🤖 Android detected, marking gyro as granted');
                        gyroscopePermissionGranted = true;
                    }

                    console.log('⏭️ Passing to geo verification handler...');
                    // No hacer nada - dejar que flu-geolocation.php lo maneje
                }, true);
            });
        }

        function interceptAtrapadorLinks() {
            var atrapadorLinks = document.querySelectorAll('a[href="#atrapado"], a[href*="#atrapado"]');
            console.log('🎯 Found', atrapadorLinks.length, 'atrapado links');

            atrapadorLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    console.log('🎯 Atrapado link clicked');

                    // Si ya tenemos permiso, navegar directamente
                    if (gyroscopePermissionGranted) {
                        console.log('✅ Already have gyro permission, navigating...');
                        window.location.hash = '#atrapado';
                        return;
                    }

                    // Si es iOS, pedir permiso
                    if (isIOS()) {
                        console.log('📱 iOS: Requesting gyro permission...');
                        DeviceOrientationEvent.requestPermission()
                            .then(function(response) {
                                if (response === 'granted') {
                                    console.log('✅ Gyro permission granted');
                                    gyroscopePermissionGranted = true;
                                    window.location.hash = '#atrapado';
                                } else {
                                    console.log('❌ Gyro permission denied');
                                    alert('Necesitas activar el sensor de movimiento para ver el modelo 3D');
                                }
                            })
                            .catch(function(error) {
                                console.error('❌ Error requesting gyro permission:', error);
                            });
                    } else {
                        // Android: dar permiso y navegar
                        console.log('🤖 Android: Granting permission and navigating...');
                        gyroscopePermissionGranted = true;
                        window.location.hash = '#atrapado';
                    }
                }, true);
            });
        }

        function requestCameraAndNavigate() {
            console.log('📷 Requesting camera...');
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function(stream) {
                    console.log('✅ Camera permission granted');
                    stream.getTracks().forEach(function(track) {
                        track.stop();
                    });
                    window.location.hash = '#captura';
                })
                .catch(function(error) {
                    console.error('❌ Camera error:', error);
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
            }
        }

        function showAtrapado() {
            console.log('👁️ Showing #atrapado');
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
                }, 50);
            }
        }

        function hideAtrapado() {
            console.log('🙈 Hiding #atrapado');
            var atrapado = document.getElementById('atrapado');
            if (atrapado) {
                atrapado.classList.remove('show');
                setTimeout(function() {
                    atrapado.style.pointerEvents = 'none';
                    atrapado.style.visibility = 'hidden';
                    atrapado.style.opacity = '0';
                    atrapado.style.zIndex = '-1';
                }, 400);
            }
        }

        function initializeCameraForCaptura() {
            if (cameraInitialized) return;
            console.log('📷 Initializing camera for #captura');
            cameraInitialized = true;

            capturaContainers.forEach(function(container) {
                if (!container.closest('#capturado')) {
                    requestCameraPermission(container);
                }
            });
        }

        function requestCameraPermission(container) {
            if (container.querySelector('video')) return;

            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function(stream) {
                    console.log('✅ Camera stream obtained');
                    const video = document.createElement('video');
                    video.autoplay = true;
                    video.muted = true;
                    video.playsInline = true;
                    video.srcObject = stream;
                    container.appendChild(video);
                })
                .catch(function(error) {
                    console.error('❌ Camera error:', error);
                });
        }

        function createGyroscopeButton() {
            var existingBtn = document.getElementById('gyro-activate-btn');
            if (existingBtn) {
                console.log('⚠️ Gyro button already exists');
                return;
            }

            console.log('🔘 Creating gyro activation button');
            var button = document.createElement('button');
            button.id = 'gyro-activate-btn';
            button.innerHTML = '📱 Toca para activar el movimiento 3D';

            button.onclick = function() {
                console.log('🔘 Gyro button clicked');
                DeviceOrientationEvent.requestPermission()
                    .then(function(response) {
                        if (response === 'granted') {
                            console.log('✅ Gyro permission granted');
                            gyroscopePermissionGranted = true;
                            activateGyroscope();

                            button.style.transition = 'all 0.3s ease';
                            button.style.opacity = '0';
                            button.style.transform = 'translateX(-50%) scale(0.8)';
                            setTimeout(function() {
                                button.remove();
                            }, 300);
                        } else {
                            console.log('❌ Gyro permission denied');
                            button.innerHTML = '❌ Permiso denegado - Toca de nuevo';
                            button.style.background = 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)';
                            setTimeout(function() {
                                button.innerHTML = '📱 Toca para activar el movimiento 3D';
                                button.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                            }, 2000);
                        }
                    })
                    .catch(function(error) {
                        console.error('❌ Gyro permission error:', error);
                        button.innerHTML = '⚠️ Error - Intenta de nuevo';
                        setTimeout(function() {
                            button.innerHTML = '📱 Toca para activar el movimiento 3D';
                        }, 2000);
                    });
            };

            document.body.appendChild(button);
        }

        function removeGyroButton() {
            var btn = document.getElementById('gyro-activate-btn');
            if (btn) {
                console.log('🗑️ Removing gyro button');
                btn.remove();
            }
        }

        function activateGyroscope() {
            if (gyroscopeActive) {
                console.log('⚠️ Gyroscope already active');
                return;
            }

            console.log('🎮 Activating gyroscope...');
            gyroscopeActive = true;

            // Intentar con DeviceOrientation (más compatible)
            if (window.DeviceOrientationEvent) {
                console.log('✅ Using DeviceOrientationEvent');
                window.addEventListener('deviceorientation', handleOrientation, true);
                window.addEventListener('deviceorientationabsolute', handleOrientation, true);
            }
        }

        function handleOrientation(event) {
            const models = document.querySelectorAll('a-gltf-model, a-box, a-sphere, a-cylinder');
            if (models.length === 0) return;

            var alpha = event.alpha || 0;
            var beta = event.beta || 0;
            var gamma = event.gamma || 0;

            // Ajustar sensibilidad
            var rotationY = -gamma * 0.5;
            var rotationX = (beta - 90) * 0.3;

            // Limitar rotación
            rotationY = Math.max(-20, Math.min(20, rotationY));
            rotationX = Math.max(-15, Math.min(15, rotationX));

            // Aplicar a TODOS los modelos
            models.forEach(function(model) {
                try {
                    model.setAttribute('rotation', {
                        x: rotationX,
                        y: rotationY,
                        z: 0
                    });
                } catch (e) {
                    // Silencioso
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

            // Ajustar posición y rotación según contexto
            const isInAtrapado = container.closest('#atrapado');
            const isInCapturado = container.closest('#capturado');

            let modelY = '1.5'; // Valor por defecto para #captura
            let modelRotationX = '0'; // Rotación por defecto
            let context = 'captura';

            if (isInAtrapado) {
                modelY = '1.5';
                modelRotationX = '-10'; // Mira un poco hacia abajo
                context = 'atrapado';
            } else if (isInCapturado) {
                modelY = '2.5'; // Más alto
                modelRotationX = '-20'; // Mira más hacia
                // abajo para compensar la altura
                context = 'capturado';
            }

            console.log('🎨 Creating model in context:', context, 'with Y:', modelY, 'rotX:', modelRotationX);

            model.setAttribute('position', '0 ' + modelY + ' -3');
            model.setAttribute('rotation', modelRotationX + ' 0 0');
            model.setAttribute('scale', '2 2 2');

            scene.appendChild(model);
            scene.appendChild(camera);
            container.appendChild(scene);

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
add_action('wp_footer', 'flu_3d_aframe_functionality', 1);
