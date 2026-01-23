<?php
/**
 * Animation system for Fluvial Core
 * Handle page load animations and transitions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Add only keyframe CSS in head
 */
function flu_animations_add_css() {
    ?>
    <style>
        /* Keyframe */
        @keyframes fluFadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* Ocultar main y posts ANTES del render para evitar parpadeo */
        main {
            opacity: 0;
        }

        .wp-block-post-template > li,
        .wp-block-post-template > .wp-block-post,
        ul.wp-block-post-template > li {
            opacity: 0;
        }

        /* Header siempre visible */
        header,
        .wp-block-template-part,
        .is-position-sticky {
            opacity: 1 !important;
        }
    </style>
    <?php
}
add_action( 'wp_head', 'flu_animations_add_css', 1 );

/**
 * Add animation JavaScript - TODO se maneja aquí
 */
function flu_animations_add_js() {
    ?>
    <script>
        function initFluAnimations() {
            console.log('🎬 Flu Animations: Iniciando sistema de animaciones');

            // 1. HEADER: Visible instantáneamente
            const headers = document.querySelectorAll('header, .wp-block-template-part, .is-position-sticky');
            headers.forEach(function(header) {
                header.style.opacity = '1';
                header.style.animation = 'none';
            });
            console.log('✅ Headers visibles:', headers.length);

            // 2. MAIN: Fade-in a 1s
            const mainElements = document.querySelectorAll('main');
            mainElements.forEach(function(main) {
                main.style.opacity = '0';
                main.style.animation = 'fluFadeIn 0.2s ease-out 1s forwards';
            });
            console.log('✅ Main configurado para fade-in a 1s');

            // 3. CONTENEDORES DE POSTS: Visibles inmediatamente (no heredan opacity del main)
            const postContainers = document.querySelectorAll('.wp-block-query, .wp-block-post-template, ul.wp-block-post-template');
            postContainers.forEach(function(container) {
                container.style.opacity = '1';
                container.style.visibility = 'visible';
            });
            console.log('✅ Contenedores de posts visibles:', postContainers.length);

            // 4. POSTS INDIVIDUALES: Animación secuencial desde 1.3s
            const postLists = document.querySelectorAll('.wp-block-post-template, ul.wp-block-post-template');
            let totalPosts = 0;

            postLists.forEach(function(list) {
                const posts = list.querySelectorAll(':scope > li, :scope > .wp-block-post');

                posts.forEach(function(post, index) {
                    const delay = 1.3 + (index * 0.2);
                    post.style.opacity = '0';
                    post.style.animation = 'fluFadeIn 0.2s ease-out ' + delay + 's forwards';
                    totalPosts++;
                    console.log('   📝 Post ' + totalPosts + ' → ' + delay.toFixed(1) + 's');
                });
            });

            console.log('✅ Total posts animados:', totalPosts);
            console.log('⏰ Timeline: Header (0s) → Main (1s) → Posts (1.3s+)');
        }

        // Esperar a que el sistema de progreso esté listo
        document.addEventListener('fluProgressReady', function() {
            console.log('🔗 Flu Animations: Progreso listo, iniciando animaciones');
            initFluAnimations();
        });

        // Fallback: si no hay sistema de progreso, iniciar después de DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                // Si después de 100ms no se ha ejecutado, ejecutar de todos modos
                if (!document.body.classList.contains('flu-animations-initialized')) {
                    console.log('🔗 Flu Animations: Iniciando sin esperar progreso');
                    initFluAnimations();
                }
            }, 100);
        });
    </script>
    <?php
}
add_action( 'wp_footer', 'flu_animations_add_js' );
