# Fluvial Core - AGENTS.md

## Plugin Information

- **Plugin Name**: Fluvial Core
- **Version**: 0.1.1
- **Author**: Flavia Bernárdez Rodríguez
- **Author URI**: https://flabernardez.com
- **Description**: Core functions for the Fluvial project (educational game about river pollution)
- **Requires at least**: WordPress 6.6
- **Requires PHP**: 7.0

## Files

| File | Purpose |
|------|---------|
| `flu-core.php` | Main plugin file - core functions, SVG/GLB/GeoJSON uploads, excerpt support |
| `flu-progress.php` | Tracks user progress through pages, visited pages, virus captures, zone completion |
| `flu-analytics.php` | Internal analytics - counts page visits (no IP/cookies stored) |
| `flu-3d.php` | 3D viewer with gyroscope support |
| `flu-button-back.php` | Back button functionality |
| `flu-animations.php` | Animations for the site |
| `flu-geolocation.php` | Geolocation functionality |

## Features Implemented

### Progress Tracking (flu-progress.php)

Cookies used (all first-party, 365 days):
- `flu_visited_pages` - Pages visited by user (green separators)
- `flu_captured_pages` - Pages where user captured a virus
- `arga_completado` - Arga river zone completed
- `ultzama_completado` - Ultzama river zone completed
- `flu_arga_modal_shown` - Arga completion modal dismissed
- `flu_ultzama_modal_shown` - Ultzama completion modal dismissed
- `flu_gyro_permission` - Gyroscope permission granted

### Analytics (flu-analytics.php)

- Stores only `page_id` and `visit_date` in `wp_flu_analytics` table
- **No cookies used**
- **No IP or User Agent stored** (privacy-friendly)
- Admin dashboard at: WordPress Admin > Analytics

### 3D Viewer (flu-3d.php)

- Uses Three.js for 3D model viewing
- Gyroscope/device orientation support with permission cookie

### File Uploads (flu-core.php)

Allowed MIME types in Media Library:
- **SVG** (`image/svg+xml`)
- **GLB** (`model/gltf-binary`)
- **GeoJSON** (`application/geo+json`)

## Context from Previous Sessions

1. **Cookie policy created**: Full HTML table of cookies for privacy policy in Spanish and Basque
2. **Privacy text drafted**: Analytics section stating no third-party cookies, no Google Analytics
3. **Analytics anonymized**: Removed IP and User Agent storage to make analytics truly anonymous

## Development Notes

- All functions use `flu_` prefix (e.g., `flu_core_track_page_visit()`)
- Code follows WordPress coding standards
- No npm build process - pure PHP/JS
- Analytics hooks into `wp_footer` to track page visits server-side
- Tracking can be enabled/disabled per page via metabox in page editor

## Common Tasks

- **Build**: No build required (no npm/webpack)
- **Lint**: Not configured
- **Test**: Manual testing on local WordPress

## Privacy Policy Info

For privacy policy, use:

**Spanish (Cookies section)**:
- See conversation for full cookie table in HTML

**Basque (Euskara)**:
- Full translation available in clipboard

**Analytics statement**:
> Le informamos que no utilizamos cookies analíticas. El proceso de captación de visitas es totalmente anónimo: no se emplean cookies y no se instala ningún servicio de analítica de terceros.
