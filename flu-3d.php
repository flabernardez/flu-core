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
            /* Animación de aparición del modelo con delay aleatorio */
            opacity: 0;
            animation: fadeInModel 0.3s ease-in-out forwards;
        }
        .flu-3d img {
            display: none;
        }
        /* Solo sobrescribir el position fixed problemático */
        html.a-fullscreen .flu-captura .a-canvas {
            position: absolute !important;
        }
        /* Asegurar que el botón quede por encima */
        .wp-block-button,
        a[href*="atrapado"],
        .flu-captura .wp-block-button a {
            position: relative !important;
            z-index: 1000 !important;
            pointer-events: auto !important;
            /* Animación de aparición del botón */
            opacity: 0;
            animation: fadeInButton 0.3s ease-in-out forwards;
        }
        /* Asegurar que el canvas de A-Frame no bloquee clicks */
        .flu-captura .a-canvas {
            position: absolute !important;
            pointer-events: none !important;
        }

        /* Modal #atrapado - Estado inicial oculto */
        #atrapado {
            position: fixed;
            top: 0;
            width: 100vw;
            height: 100%;
            z-index: 2000;
            opacity: 0;
            transform: scale(0.9);
            pointer-events: none;
            transition: opacity 0.4s ease-out, transform 0.4s ease-out;
            overflow-y: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Contenedor interno para centrar el contenido */
        #atrapado > * {
            width: 100%;
            max-width: 600px;
        }

        /* Modal #atrapado - Estado visible */
        #atrapado.show {
            opacity: 1;
            transform: scale(1);
            pointer-events: auto;
        }

        /* Keyframes para las animaciones */
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
    </style>
    <script>
        var cameraInitialized = false;
        var gyroscopeInitialized = false;
        var capturaContainers = [];
        var lastValidRotation = { x: 0, y: 0 }; // Para detectar cambios bruscos

        document.addEventListener('DOMContentLoaded', function() {
            const capturaDivs = document.querySelectorAll('.flu-captura');

            capturaDivs.forEach(function(div) {
                capturaContainers.push(div);

                // Preparar la escena 3D pero NO activar la cámara todavía
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

            // Verificar si ya estamos en #captura al cargar
            if (window.location.hash === '#captura') {
                initializeCameraForCaptura();
            }

            // Escuchar cambios en el hash
            window.addEventListener('hashchange', function() {
                if (window.location.hash === '#captura' && !cameraInitialized) {
                    initializeCameraForCaptura();
                }

                // Mostrar modal #atrapado cuando se llega a ese hash
                if (window.location.hash === '#atrapado') {
                    var atrapado = document.getElementById('atrapado');
                    if (atrapado) {
                        // NO hacer scroll, mantener posición actual
                        // Prevenir comportamiento de scroll del navegador
                        event.preventDefault();

                        // Mostrar modal con animación
                        setTimeout(function() {
                            atrapado.classList.add('show');
                        }, 50);
                    }
                }
            });

            // Prevenir scroll al hash #atrapado
            window.addEventListener('click', function(e) {
                var target = e.target.closest('a[href="#atrapado"]');
                if (target) {
                    e.preventDefault();
                    window.location.hash = '#atrapado';
                }
            });

            // Verificar si ya estamos en #atrapado al cargar
            if (window.location.hash === '#atrapado') {
                var atrapado = document.getElementById('atrapado');
                if (atrapado) {
                    setTimeout(function() {
                        atrapado.classList.add('show');
                    }, 50);
                }
            }
        });

        function initializeCameraForCaptura() {
            if (cameraInitialized) return;

            console.log('Iniciando cámara para captura...');
            cameraInitialized = true;

            capturaContainers.forEach(function(container) {
                requestCameraPermission(container);
            });

            // Activar giroscopio automáticamente
            setTimeout(function() {
                requestGyroscopePermission();
            }, 500);
        }

        function requestCameraPermission(container) {
            // Verificar si ya hay un video en el container
            if (container.querySelector('video')) {
                console.log('Cámara ya inicializada en este contenedor');
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
                    console.log('Cámara activada correctamente');
                })
                .catch(function(error) {
                    console.error('Error al acceder a la cámara:', error);
                });
        }

        function requestGyroscopePermission() {
            if (gyroscopeInitialized) return;

            console.log('Solicitando permiso de giroscopio...');

            if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
                // iOS 13+ requiere permiso explícito
                console.log('Pidiendo permiso iOS...');
                DeviceOrientationEvent.requestPermission()
                    .then(function(response) {
                        console.log('Respuesta permiso giroscopio:', response);
                        if (response === 'granted') {
                            gyroscopeInitialized = true;
                            enableGyroscope();
                        } else {
                            console.warn('Permiso de giroscopio denegado');
                            alert('Necesitas activar el sensor de movimiento para ver el modelo en AR');
                        }
                    })
                    .catch(function(error) {
                        console.error('Error al solicitar permiso giroscopio:', error);
                        alert('Error al solicitar permiso: ' + error.message);
                    });
            } else {
                // Android y otros navegadores no requieren permiso explícito
                console.log('Habilitando giroscopio sin permiso (Android/otros)');
                gyroscopeInitialized = true;
                enableGyroscope();
            }
        }

        function enableGyroscope() {
            console.log('Giroscopio habilitado, esperando eventos...');

            var eventReceived = false;

            // Verificar que el evento funciona
            var testHandler = function(event) {
                if (!eventReceived && (event.alpha !== null || event.beta !== null || event.gamma !== null)) {
                    eventReceived = true;
                    console.log('✓ Giroscopio funcionando!');
                    console.log('  Alpha (brújula):', event.alpha);
                    console.log('  Beta (inclinación adelante/atrás):', event.beta);
                    console.log('  Gamma (inclinación izq/der):', event.gamma);
                    window.removeEventListener('deviceorientation', testHandler);
                }
            };

            window.addEventListener('deviceorientation', testHandler);

            // Si después de 3 segundos no hay eventos, reportar
            setTimeout(function() {
                if (!eventReceived) {
                    console.error('⚠ No se reciben eventos del giroscopio');
                    console.log('Verifica que el dispositivo tenga sensores de orientación');
                }
            }, 3000);

            // Añadir el handler principal
            window.addEventListener('deviceorientation', handleOrientation);
        }

        function handleOrientation(event) {
            // Rotar el MODELO en lugar de la cámara (más natural estilo Pokémon GO)
            const models = document.querySelectorAll('a-gltf-model, a-box, a-sphere');

            if (models.length === 0) {
                return;
            }

            // Solo logear cada 60 frames (~1 segundo)
            if (!window.gyroLogCounter) window.gyroLogCounter = 0;
            window.gyroLogCounter++;

            if (window.gyroLogCounter % 60 === 0) {
                console.log('Evento giroscopio recibido:', {
                    alpha: event.alpha?.toFixed(2),
                    beta: event.beta?.toFixed(2),
                    gamma: event.gamma?.toFixed(2)
                });
            }

            models.forEach(function(model) {
                // Valores del giroscopio
                var alpha = event.alpha || 0;  // Brújula (0-360)
                var beta = event.beta || 0;    // Inclinación adelante/atrás (-180 a 180)
                var gamma = event.gamma || 0;  // Inclinación izquierda/derecha (-90 a 90)

                // Convertir a rotaciones para el modelo (invertidas para seguir el dispositivo)
                // Estilo Pokémon GO: el modelo se mueve sutilmente siguiendo la inclinación
                var rotationY = -gamma * 0.5;  // Sigue inclinación lateral
                var rotationX = (beta - 90) * 0.3;  // Sigue inclinación adelante/atrás

                // Limitar el rango de movimiento para que sea sutil
                rotationY = Math.max(-20, Math.min(20, rotationY));
                rotationX = Math.max(-15, Math.min(15, rotationX));

                // Filtro anti-glitch: detectar cambios demasiado bruscos (más de 30° en un frame)
                var deltaX = Math.abs(rotationX - lastValidRotation.x);
                var deltaY = Math.abs(rotationY - lastValidRotation.y);

                if (lastValidRotation.x !== 0 && (deltaX > 30 || deltaY > 30)) {
                    console.log('⚠️ Glitch detectado, ignorando frame (deltaX:', deltaX.toFixed(1), 'deltaY:', deltaY.toFixed(1), ')');
                    return; // Ignorar este frame anómalo
                }

                // Guardar rotación válida
                lastValidRotation.x = rotationX;
                lastValidRotation.y = rotationY;

                // Aplicar rotación base + movimiento del giroscopio
                var baseRotationY = 180; // Rotación base (mirando al usuario)

                model.setAttribute('rotation', {
                    x: rotationX,
                    y: baseRotationY + rotationY,
                    z: 0
                });
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
            model.setAttribute('position', '0 0.8 -3');
            model.setAttribute('rotation', '0 180 0');
            model.setAttribute('scale', '2 2 2');

            scene.appendChild(model);
            scene.appendChild(camera);

            container.appendChild(scene);

            // Generar delay aleatorio entre 1000ms (1s) y 2000ms (2s) para el modelo
            const randomModelDelay = Math.random() * 1000 + 1000;

            // El botón aparece 500ms después que el modelo
            const buttonDelay = randomModelDelay + 500;

            // Aplicar delay aleatorio al modelo (a-scene)
            scene.style.animationDelay = randomModelDelay + 'ms';

            // Aplicar delay al botón
            const buttons = container.querySelectorAll('.wp-block-button, #atrapado, a[href*="atrapado"]');
            buttons.forEach(function(button) {
                button.style.animationDelay = buttonDelay + 'ms';
            });

            // Log para debug
            console.log(`Modelo aparecerá en ${randomModelDelay}ms, botón en ${buttonDelay}ms`);
        }
    </script>
    <?php
}
add_action('wp_footer', 'flu_3d_aframe_functionality');
