<?php
/**
 * Plugin Name: Fluvial Core
 * Description: Core functions
 * Requires at least: 6.6
 * Requires PHP: 7.0
 * Version: 0.1.0
 * Author: Flavia Bernárdez Rodríguez
 * Author URI: https://flabernardez.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: flu-core
 *
 * @package CreateBlock
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/* Add Excerpt in Pages */
function flu_core_enable_excerpt_for_pages() {
    add_post_type_support('page', 'excerpt');
}
add_action('init', 'flu_core_enable_excerpt_for_pages');

/* Deactivate all images sizes */
add_filter( 'intermediate_image_sizes', '__return_empty_array' );

/**
 * Allow SVG uploads in WordPress
 */
function flu_core_allow_svg_uploads($mimes) {
    // Add support for SVG
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}
add_filter('upload_mimes', 'flu_core_allow_svg_uploads');

/**
 * Check and fix MIME type for SVG
 */
function flu_core_check_svg_mime_type($data, $file, $filename, $mimes) {
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    if ($ext === 'svg') {
        $data['ext'] = 'svg';
        $data['type'] = 'image/svg+xml';
    }
    return $data;
}
add_filter('wp_check_filetype_and_ext', 'flu_core_check_svg_mime_type', 10, 4);

/**
 * Fix for displaying SVGs in the Media Library
 */
function flu_core_fix_svg_display() {
    echo '<style>.attachment-266x266, .thumbnail img {width: 100% !important;height: auto !important;}</style>';
}
add_action('admin_head', 'flu_core_fix_svg_display');

/**
 * Allow SVG preview in Media Library
 */
function flu_core_svg_mime_display($response, $attachment, $meta) {
    if ($response['mime'] == 'image/svg+xml') {
        $response['sizes'] = array(
            'full' => array(
                'url' => $response['url'],
                'width' => $meta['width'],
                'height' => $meta['height'],
            )
        );
    }
    return $response;
}
add_filter('wp_prepare_attachment_for_js', 'flu_core_svg_mime_display', 10, 3);

/**
 * Disable JPEG image compression
 */
function flu_core_disable_image_compression_quality($quality) {
    return 100; // Set the quality to 100 to disable compression for JPEG
}
add_filter('jpeg_quality', 'flu_core_disable_image_compression_quality');

/**
 * Disable WebP image compression
 */
function flu_core_disable_image_compression_quality_webp($quality, $mime_type) {
    if ('image/webp' === $mime_type) {
        return 100; // Set the quality to 100 to disable compression for WebP
    }
    return $quality;
}
add_filter('wp_editor_set_quality', 'flu_core_disable_image_compression_quality_webp', 10, 2);

/**
 * Allow GLB file uploads
 */
function flu_core_allow_glb_uploads($mimes) {
    $mimes['glb'] = 'model/gltf-binary';
    return $mimes;
}
add_filter('upload_mimes', 'flu_core_allow_glb_uploads');

/**
 * Fix GLB MIME type check
 */
function flu_core_check_glb_mime_type($data, $file, $filename, $mimes) {
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    if ($ext === 'glb') {
        $data['ext'] = 'glb';
        $data['type'] = 'model/gltf-binary';
    }
    return $data;
}
add_filter('wp_check_filetype_and_ext', 'flu_core_check_glb_mime_type', 10, 4);

/**
 * Basic camera functionality for .flu-captura divs
 */
// COMENTAR O ELIMINAR esta función completa en flu-core.php:
/*
function flu_core_simple_camera() {
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
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const divs = document.querySelectorAll('.flu-captura');
            divs.forEach(function(div) {
                navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                    .then(function(stream) {
                        const video = document.createElement('video');
                        video.autoplay = true;
                        video.muted = true;
                        video.playsInline = true;
                        video.srcObject = stream;
                        div.appendChild(video);
                    })
                    .catch(function(error) {
                        console.error('Error al acceder a la cámara:', error);
                    });
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'flu_core_simple_camera');
*/

// Include 3D functionality
require_once plugin_dir_path( __FILE__ ) . 'flu-3d.php';

// Include progress tracking functionality
require_once plugin_dir_path( __FILE__ ) . 'flu-progress.php';

// Include geolocation functionality
require_once plugin_dir_path( __FILE__ ) . 'flu-geolocation.php';

// Include analytics functionality
require_once plugin_dir_path( __FILE__ ) . 'flu-analytics.php';
