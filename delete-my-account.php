<?php
/**
 * Plugin Name: Delete My Account (Self-Service)
 * Description: Lets a logged-in user permanently delete their own WordPress account from the frontend (e.g. Privacy Policy page or My Account), with password re-authentication, double confirmation, and full security hardening. Available as a shortcode and an Elementor widget.
 * Version: 1.0.0
 * Author: Fran Velazco
 * Author URI: https://www.linkedin.com/in/fran-velazco/
 * Text Domain: delete-my-account
 * License: GPLv2
 */

if (!defined('ABSPATH')) {
    exit; // Direct access not allowed.
}

define('DMA_PLUGIN_FILE', __FILE__);
define('DMA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DMA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DMA_VERSION', '1.0.0');

require_once DMA_PLUGIN_DIR . 'includes/class-dma-opciones.php';
require_once DMA_PLUGIN_DIR . 'includes/class-dma-core.php';
require_once DMA_PLUGIN_DIR . 'includes/class-dma-elementor.php';

add_action('plugins_loaded', function () {
    DMA_Opciones::instancia();
    DMA_Core::instancia();
    DMA_Elementor::instancia();
});
