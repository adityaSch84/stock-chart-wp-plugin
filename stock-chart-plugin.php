<?php
/**
 * Plugin Name: Enhanced Stock Chart
 * Description: Display NSE/BSE stock charts with multiple time periods
 * Version: 2.0.0
 * Author: Aditya Shahi
 * Text Domain: stock-chart
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('STOCK_CHART_PLUGIN_URL', plugin_dir_url(__FILE__));
define('STOCK_CHART_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include required files
require_once STOCK_CHART_PLUGIN_PATH . 'includes/class-stock-chart.php';
require_once STOCK_CHART_PLUGIN_PATH . 'includes/class-admin.php';

// Initialize plugin
function stock_chart_init() {
    new StockChart();
    if (is_admin()) {
        new StockChartAdmin();
    }
}
add_action('plugins_loaded', 'stock_chart_init');

// Activation hook
register_activation_hook(__FILE__, 'stock_chart_activate');
function stock_chart_activate() {
    // Create database tables or set default options
    add_option('stock_chart_api_key', '');
    add_option('stock_chart_cache_timeout', 300);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'stock_chart_deactivate');
function stock_chart_deactivate() {
    // Clean up transients
    delete_transient('stock_chart_*');
}