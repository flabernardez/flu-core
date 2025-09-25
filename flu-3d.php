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
        #atrapado,
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
        console.log('🎥 3D script loading on page:', window.location.pathname);

        document.addEventListener('DOMContentLoaded', function() {
            const capturaDivs = document.querySelectorAll('.flu-captura');

            capturaDivs.forEach(function(div) {
                requestCameraAccess(div);
                enableGyroscopeIfPermitted();

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
        });

        function requestCameraAccess(container) {
            // Check if there's already a video element in this container
            if (container.querySelector('video')) {
                console.log('Camera already active in this container');
                return;
            }

            // Get permissions from cookie
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

            console.log('Camera permission status:', permissions.camera);

            if (permissions.camera === 'denied') {
                console.log('Camera permission was denied previously');
                return;
            }

            if (permissions.camera !== 'granted') {
                console.log('Camera permission not yet granted, skipping camera access');
                return;
            }

            // Only try to access camera if permission was explicitly granted
            navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'environment',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            })
                .then(function(stream) {
                    // Double check that video wasn't added while waiting for permission
                    if (container.querySelector('video')) {
                        console.log('Video already exists, stopping new stream');
                        stream.getTracks().forEach(track => track.stop());
                        return;
                    }

                    const video = document.createElement('video');
                    video.autoplay = true;
                    video.muted = true;
                    video.playsInline = true;
                    video.srcObject = stream;

                    // Add cleanup when the page is unloaded
                    window.addEventListener('beforeunload', function() {
                        if (stream) {
                            stream.getTracks().forEach(track => track.stop());
                        }
                    });

                    container.appendChild(video);
                    console.log('Camera access successful');
                })
                .catch(function(error) {
                    console.log('Camera access failed:', error);

                    // If it failed due to permission, update the cookie
                    if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
                        const permissions = getPermissions();
                        permissions.camera = 'denied';

                        function setCookie(name, value, days) {
                            var expires = "";
                            if (days) {
                                var date = new Date();
                                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                                expires = "; expires=" + date.toUTCString();
                            }
                            document.cookie = name + "=" + value + expires + "; path=/";
                        }

                        setCookie('flu_permissions', encodeURIComponent(JSON.stringify(permissions)), 365);
                    }
                });
        }

        function enableGyroscopeIfPermitted() {
            // Get permissions from cookie
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

            if (permissions.gyro === 'granted') {
                enableGyroscope();
            } else if (!permissions.gyro && typeof DeviceOrientationEvent === 'undefined') {
                // Fallback for non-iOS devices where permissions weren't set yet
                enableGyroscope();
            }
        }

        function enableGyroscope() {
            window.addEventListener('deviceorientation', handleOrientation);
        }

        function handleOrientation(event) {
            // Debug - añadir esto temporalmente
            console.log('Alpha:', event.alpha, 'Beta:', event.beta, 'Gamma:', event.gamma);
            const cameras = document.querySelectorAll('a-camera');
            cameras.forEach(function(camera) {
                const alpha = (event.alpha || 0) * 0.1;//
                // ← ESTE valor (rotación Y - horizontal)
                const beta = Math.max(-30, Math.min(30,
                    (event.beta || 0) - 90)) * 0.05;  //
                // ← ESTE valor (rotación X - vertical)
                const gamma = (event.gamma || 0) * .05;// ←
                // ESTE valor (rotación Z - inclinación)
                camera.setAttribute('rotation', beta + ' ' + alpha + ' ' + (-gamma));
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
            model.setAttribute('scale', '1 1 1');

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
