<?php

declare(strict_types=1);

namespace RegiNor\Lite;

use RegiNor\Lite\Admin\ProjectPage;
use RegiNor\Lite\Admin\CoursePage;
use RegiNor\Lite\Infrastructure\Roles;
use RegiNor\Lite\Infrastructure\ContentTypes;

final class Plugin
{
    public static function boot(): void
    {
        \RegiNor\Lite\Infrastructure\CachePolicy::boot();
        add_action('init', [self::class, 'loadTranslations']);
        add_action('init', [ContentTypes::class, 'register'], 20);
        Roles::boot();
        \RegiNor\Lite\Infrastructure\WebIdentity::boot();
        \RegiNor\Lite\Frontend\PublicRoutes::boot();
        \RegiNor\Lite\Frontend\CalendarEndpoint::boot();
        \RegiNor\Lite\Frontend\SharingMetadata::boot();
        \RegiNor\Lite\Admin\SharingSettings::boot();
        \RegiNor\Lite\Admin\LetsRegCoursePicker::boot();
        \RegiNor\Lite\Admin\LetsRegChangesPanel::boot();
        \RegiNor\Lite\Admin\RegistrationStatusControl::boot();
        \RegiNor\Lite\Infrastructure\TecBridge::boot();
        \RegiNor\Lite\Infrastructure\VenueMap::boot();
        \RegiNor\Lite\Infrastructure\Wpml::boot();
        \RegiNor\Lite\Infrastructure\CapacityStore::boot();
        \RegiNor\Lite\Infrastructure\LetsRegAvailabilityStore::boot();
        \RegiNor\Lite\Frontend\PublicSite::boot();
        \RegiNor\Lite\Frontend\JourneyTracking::boot();
        \RegiNor\Lite\Infrastructure\SalesHistory::boot();
        \RegiNor\Lite\Admin\SalesHistoryPanel::boot();
        \RegiNor\Lite\Infrastructure\ColorPalette::boot();
        // WordPress needs the parent menu before deriving submenu page hooks and URLs.
        add_action('admin_menu', [CoursePage::class, 'register']);
        add_action('admin_menu', [\RegiNor\Lite\Admin\SiteSettings::class, 'register']);
        add_action('admin_menu', [\RegiNor\Lite\Admin\CapacityPage::class, 'register']);
        add_action('admin_menu', [\RegiNor\Lite\Admin\LetsRegPage::class, 'register']);
        add_action('admin_menu', [\RegiNor\Lite\Admin\AnalyticsPage::class, 'register']);
        add_action('admin_menu', [\RegiNor\Lite\Admin\AppearancePage::class, 'register']);
        add_action('admin_enqueue_scripts', static function (): void {
            $page = $_GET['page'] ?? '';
            if (is_string($page) && in_array($page, ['reginor-lite', 'rnl-resources', 'rnl-site', 'rnl-capacity', 'rnl-letsreg', 'rnl-analytics', 'rnl-appearance', 'reginor-lite-status'], true)) {
                wp_enqueue_style('rnl-interface', plugins_url('assets/interface.css', dirname(__DIR__) . '/reginor-lite.php'), [], (string) filemtime(dirname(__DIR__) . '/assets/interface.css'));
                wp_enqueue_style('rnl-admin', plugins_url('assets/admin.css', dirname(__DIR__) . '/reginor-lite.php'), ['rnl-interface'], (string) filemtime(dirname(__DIR__) . '/assets/admin.css'));
            }
            if (in_array($page, ['reginor-lite', 'rnl-resources'], true)) {
                wp_enqueue_editor();
                wp_enqueue_script('rnl-rich-text', plugins_url('assets/rich-text.js', dirname(__DIR__) . '/reginor-lite.php'), ['editor', 'wp-i18n'], (string) filemtime(dirname(__DIR__) . '/assets/rich-text.js'), true);
                wp_set_script_translations('rnl-rich-text', 'reginor-lite', dirname(__DIR__) . '/languages');
                wp_enqueue_script('rnl-admin', plugins_url('assets/admin.js', dirname(__DIR__) . '/reginor-lite.php'), ['wp-i18n'], (string) filemtime(dirname(__DIR__) . '/assets/admin.js'), true);
                wp_set_script_translations('rnl-admin', 'reginor-lite', dirname(__DIR__) . '/languages');
            }
            if (in_array($page, ['rnl-appearance', 'reginor-lite', 'rnl-resources'], true)) {
                wp_enqueue_script('rnl-appearance', plugins_url('assets/appearance.js', dirname(__DIR__) . '/reginor-lite.php'), [], (string) filemtime(dirname(__DIR__) . '/assets/appearance.js'), true);
            }
            if ($page === 'reginor-lite') {
                wp_enqueue_script('rnl-registration-status', plugins_url('assets/registration-status.js', dirname(__DIR__) . '/reginor-lite.php'), ['wp-i18n'], (string) filemtime(dirname(__DIR__) . '/assets/registration-status.js'), true);
                wp_set_script_translations('rnl-registration-status', 'reginor-lite', dirname(__DIR__) . '/languages');
            }
            if ($page === 'reginor-lite' && \RegiNor\Lite\Infrastructure\LetsRegMapping::canUse()) {
                wp_enqueue_script('rnl-letsreg-import', plugins_url('assets/letsreg-import.js', dirname(__DIR__) . '/reginor-lite.php'), ['wp-i18n'], (string) filemtime(dirname(__DIR__) . '/assets/letsreg-import.js'), true);
                wp_set_script_translations('rnl-letsreg-import', 'reginor-lite', dirname(__DIR__) . '/languages');
                wp_enqueue_script('rnl-letsreg-picker', plugins_url('assets/letsreg-picker.js', dirname(__DIR__) . '/reginor-lite.php'), ['wp-i18n', 'rnl-letsreg-import'], (string) filemtime(dirname(__DIR__) . '/assets/letsreg-picker.js'), true);
                wp_set_script_translations('rnl-letsreg-picker', 'reginor-lite', dirname(__DIR__) . '/languages');
            }
            if (in_array($page, ['rnl-capacity', 'reginor-lite'], true)) {
                wp_enqueue_script('rnl-interface', plugins_url('assets/interface.js', dirname(__DIR__) . '/reginor-lite.php'), ['wp-i18n'], (string) filemtime(dirname(__DIR__) . '/assets/interface.js'), true);
                wp_set_script_translations('rnl-interface', 'reginor-lite', dirname(__DIR__) . '/languages');
            }
        });
        add_action('admin_menu', [ProjectPage::class, 'register']);
    }

    public static function loadTranslations(): void
    {
        load_plugin_textdomain(
            'reginor-lite',
            false,
            dirname(plugin_basename(__DIR__ . '/../reginor-lite.php')) . '/languages'
        );
    }
}
