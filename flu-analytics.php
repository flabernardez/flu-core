<?php
/**
 * Analytics functionality for Fluvial Core
 * Track page visits and display statistics
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Create analytics table on activation
 */
function flu_analytics_create_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'flu_analytics';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        page_id bigint(20) NOT NULL,
        visit_date datetime DEFAULT CURRENT_TIMESTAMP,
        user_ip varchar(45) NOT NULL,
        user_agent text,
        PRIMARY KEY (id),
        KEY page_id (page_id),
        KEY visit_date (visit_date)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'flu_analytics_create_table' );

/**
 * Add analytics meta box to pages
 */
function flu_analytics_add_meta_box() {
    add_meta_box(
        'flu_analytics_settings',
        'Configuración de Analytics',
        'flu_analytics_meta_box_callback',
        'page',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'flu_analytics_add_meta_box' );

/**
 * Meta box callback function
 */
function flu_analytics_meta_box_callback( $post ) {
    wp_nonce_field( 'flu_analytics_save_meta_box_data', 'flu_analytics_meta_box_nonce' );

    $track_visits = get_post_meta( $post->ID, '_flu_analytics_track', true );

    echo '<label for="flu_analytics_track">';
    echo '<input type="checkbox" id="flu_analytics_track" name="flu_analytics_track" value="1" ' . checked( $track_visits, '1', false ) . ' />';
    echo ' Activar seguimiento de visitas';
    echo '</label>';
    echo '<p class="description">Marca esta casilla para registrar las visitas a esta página en las estadísticas.</p>';

    // Show current stats if tracking is enabled
    if ( $track_visits ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'flu_analytics';
        $total_visits = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE page_id = %d",
            $post->ID
        ) );

        $today_visits = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE page_id = %d AND DATE(visit_date) = CURDATE()",
            $post->ID
        ) );

        echo '<div style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-radius: 4px;">';
        echo '<strong>Estadísticas actuales:</strong><br>';
        echo 'Visitas totales: <strong>' . $total_visits . '</strong><br>';
        echo 'Visitas hoy: <strong>' . $today_visits . '</strong>';
        echo '</div>';
    }
}

/**
 * Save meta box data
 */
function flu_analytics_save_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['flu_analytics_meta_box_nonce'] ) ) {
        return;
    }

    if ( ! wp_verify_nonce( $_POST['flu_analytics_meta_box_nonce'], 'flu_analytics_save_meta_box_data' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_page', $post_id ) ) {
        return;
    }

    $track_visits = isset( $_POST['flu_analytics_track'] ) ? '1' : '0';
    update_post_meta( $post_id, '_flu_analytics_track', $track_visits );
}
add_action( 'save_post', 'flu_analytics_save_meta_box_data' );

/**
 * Track page visits
 */
function flu_analytics_track_visit() {
    if ( ! is_page() ) {
        return;
    }

    $page_id = get_the_ID();
    $track_visits = get_post_meta( $page_id, '_flu_analytics_track', true );

    if ( $track_visits !== '1' ) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'flu_analytics';

    $user_ip = flu_analytics_get_user_ip();
    $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';

    $wpdb->insert(
        $table_name,
        array(
            'page_id' => $page_id,
            'user_ip' => $user_ip,
            'user_agent' => $user_agent,
            'visit_date' => current_time( 'mysql' )
        ),
        array(
            '%d',
            '%s',
            '%s',
            '%s'
        )
    );
}
add_action( 'wp_footer', 'flu_analytics_track_visit' );

/**
 * Get user IP address
 */
function flu_analytics_get_user_ip() {
    $ip_keys = array(
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    );

    foreach ( $ip_keys as $key ) {
        if ( array_key_exists( $key, $_SERVER ) === true ) {
            foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
                $ip = trim( $ip );
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
                    return $ip;
                }
            }
        }
    }

    return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

/**
 * Add analytics admin menu
 */
function flu_analytics_admin_menu() {
    add_menu_page(
        'Fluvial Analytics',
        'Analytics',
        'manage_options',
        'flu-analytics',
        'flu_analytics_admin_page',
        'dashicons-chart-line',
        30
    );
}
add_action( 'admin_menu', 'flu_analytics_admin_menu' );

/**
 * Analytics admin page
 */
function flu_analytics_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Handle date filtering
    $start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : date( 'Y-m-01' );
    $end_date = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : date( 'Y-m-t' );
    $filter_type = isset( $_GET['filter_type'] ) ? sanitize_text_field( $_GET['filter_type'] ) : 'month';

    // Set dates based on filter type
    switch ( $filter_type ) {
        case 'today':
            $start_date = date( 'Y-m-d' );
            $end_date = date( 'Y-m-d' );
            break;
        case 'week':
            $start_date = date( 'Y-m-d', strtotime( 'monday this week' ) );
            $end_date = date( 'Y-m-d', strtotime( 'sunday this week' ) );
            break;
        case 'month':
            $start_date = date( 'Y-m-01' );
            $end_date = date( 'Y-m-t' );
            break;
        case 'year':
            $start_date = date( 'Y-01-01' );
            $end_date = date( 'Y-12-31' );
            break;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'flu_analytics';

    // Get page statistics
    $page_stats = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.post_title, fa.page_id, COUNT(*) as visits 
         FROM $table_name fa 
         JOIN {$wpdb->posts} p ON fa.page_id = p.ID 
         WHERE DATE(fa.visit_date) BETWEEN %s AND %s 
         GROUP BY fa.page_id 
         ORDER BY visits DESC",
        $start_date,
        $end_date
    ) );

    // Get daily visit data for chart
    $daily_visits = $wpdb->get_results( $wpdb->prepare(
        "SELECT DATE(visit_date) as date, COUNT(*) as visits 
         FROM $table_name 
         WHERE DATE(visit_date) BETWEEN %s AND %s 
         GROUP BY DATE(visit_date) 
         ORDER BY date ASC",
        $start_date,
        $end_date
    ) );

    // Calculate totals
    $total_visits = array_sum( array_column( $page_stats, 'visits' ) );
    $total_pages = count( $page_stats );

    ?>
    <div class="wrap">
        <h1>Fluvial Analytics</h1>

        <!-- Filter Form -->
        <div class="flu-analytics-filters" style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <form method="get" action="">
                <input type="hidden" name="page" value="flu-analytics">

                <div style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                    <div>
                        <label for="filter_type"><strong>Filtro rápido:</strong></label><br>
                        <select name="filter_type" id="filter_type" onchange="toggleCustomDates()">
                            <option value="today" <?php selected( $filter_type, 'today' ); ?>>Hoy</option>
                            <option value="week" <?php selected( $filter_type, 'week' ); ?>>Esta semana</option>
                            <option value="month" <?php selected( $filter_type, 'month' ); ?>>Este mes</option>
                            <option value="year" <?php selected( $filter_type, 'year' ); ?>>Este año</option>
                            <option value="custom" <?php selected( $filter_type, 'custom' ); ?>>Personalizado</option>
                        </select>
                    </div>

                    <div id="custom_dates" style="display: <?php echo $filter_type === 'custom' ? 'flex' : 'none'; ?>; gap: 10px;">
                        <div>
                            <label for="start_date"><strong>Desde:</strong></label><br>
                            <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr( $start_date ); ?>">
                        </div>
                        <div>
                            <label for="end_date"><strong>Hasta:</strong></label><br>
                            <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr( $end_date ); ?>">
                        </div>
                    </div>

                    <div>
                        <input type="submit" value="Aplicar filtro" class="button button-primary">
                    </div>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                <h3 style="margin: 0; color: #666;">Visitas Totales</h3>
                <p style="font-size: 2.5em; margin: 10px 0; color: #2271b1; font-weight: bold;"><?php echo number_format( $total_visits ); ?></p>
            </div>
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                <h3 style="margin: 0; color: #666;">Páginas con Visitas</h3>
                <p style="font-size: 2.5em; margin: 10px 0; color: #00a32a; font-weight: bold;"><?php echo $total_pages; ?></p>
            </div>
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                <h3 style="margin: 0; color: #666;">Promedio por Página</h3>
                <p style="font-size: 2.5em; margin: 10px 0; color: #d63638; font-weight: bold;"><?php echo $total_pages > 0 ? number_format( $total_visits / $total_pages, 1 ) : '0'; ?></p>
            </div>
        </div>

        <!-- Chart -->
        <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>Gráfico de Visitas</h2>
            <canvas id="visitsChart" width="400" height="100"></canvas>
        </div>

        <!-- Page Statistics Table -->
        <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>Estadísticas por Página</h2>

            <?php if ( empty( $page_stats ) ): ?>
                <p>No hay datos de visitas para el período seleccionado.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                    <tr>
                        <th>Página</th>
                        <th>Visitas</th>
                        <th>% del Total</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $page_stats as $stat ): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $stat->post_title ); ?></strong>
                            </td>
                            <td><?php echo number_format( $stat->visits ); ?></td>
                            <td><?php echo $total_visits > 0 ? number_format( ( $stat->visits / $total_visits ) * 100, 1 ) : '0'; ?>%</td>
                            <td>
                                <a href="<?php echo get_edit_post_link( $stat->page_id ); ?>" class="button button-small">Editar</a>
                                <a href="<?php echo get_permalink( $stat->page_id ); ?>" class="button button-small" target="_blank">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function toggleCustomDates() {
            const filterType = document.getElementById('filter_type').value;
            const customDates = document.getElementById('custom_dates');
            customDates.style.display = filterType === 'custom' ? 'flex' : 'none';
        }

        // Chart data
        const dailyVisitsData = <?php echo json_encode( $daily_visits ); ?>;

        // Prepare chart data
        const labels = [];
        const data = [];

        // Create date range
        const startDate = new Date('<?php echo $start_date; ?>');
        const endDate = new Date('<?php echo $end_date; ?>');

        for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
            const dateStr = d.toISOString().split('T')[0];
            labels.push(dateStr);

            // Find visits for this date
            const dayData = dailyVisitsData.find(item => item.date === dateStr);
            data.push(dayData ? parseInt(dayData.visits) : 0);
        }

        // Create chart
        const ctx = document.getElementById('visitsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Visitas',
                    data: data,
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        ticks: {
                            maxTicksLimit: 10
                        }
                    }
                }
            }
        });
    </script>

    <style>
        .flu-analytics-filters select,
        .flu-analytics-filters input[type="date"] {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .wp-list-table th {
            background: #f9f9f9;
            font-weight: 600;
        }

        .wp-list-table td {
            vertical-align: middle;
        }

        .button-small {
            font-size: 11px;
            height: auto;
            line-height: 16px;
            padding: 2px 6px;
        }
    </style>
    <?php
}

/**
 * Add analytics initialization to main plugin
 */
function flu_analytics_init() {
    // Create table if it doesn't exist
    global $wpdb;
    $table_name = $wpdb->prefix . 'flu_analytics';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
        flu_analytics_create_table();
    }
}
add_action( 'init', 'flu_analytics_init' );
