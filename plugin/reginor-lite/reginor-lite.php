<?php
/**
 * Plugin Name: RegiNor Lite
 * Description: Grunnlag for SalsaNors kursoversikt og kursadministrasjon.
 * Version: 0.1.24
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * Text Domain: reginor-lite
 * Domain Path: /languages
 */

declare(strict_types=1);

namespace RegiNor\Lite;

if (!defined('ABSPATH')) {
    exit;
}

// WP Consent API's Site Health check asks each plugin to declare its own support.
add_filter('wp_consent_api_registered_' . plugin_basename(__FILE__), '__return_true');

require_once __DIR__ . '/src/autoload.php';

add_action('plugins_loaded', [Plugin::class, 'boot']);
register_activation_hook(__FILE__, static function (): void { Frontend\PublicRoutes::rules(); flush_rewrite_rules(false); });
register_deactivation_hook(__FILE__, [Infrastructure\TecSyncQueue::class, 'stop']);
register_deactivation_hook(__FILE__, static function (): void { global $wp_rewrite; unset($wp_rewrite->extra_rules_top['^kursrekke/([^/]+)(?:/([^/]+))?/?$']); flush_rewrite_rules(false); wp_clear_scheduled_hook(Infrastructure\CapacityStore::HOOK); wp_clear_scheduled_hook(Infrastructure\LetsRegAvailabilityStore::HOOK); });
