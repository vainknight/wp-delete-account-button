<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * DMA_Elementor
 * -------------
 * Registers the "Delete My Account" widget for Elementor, only if
 * Elementor is active. Does nothing if it isn't.
 */
class DMA_Elementor {

    private static $instancia = null;

    public static function instancia() {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_action('elementor/widgets/register', array($this, 'registrar_widget'));
        add_action('elementor/elements/categories_registered', array($this, 'registrar_categoria'));
    }

    public function registrar_categoria($elements_manager) {
        $elements_manager->add_category('delete-my-account', array(
            'title' => __('Delete My Account', 'delete-my-account'),
            'icon'  => 'fa fa-user-times',
        ));
    }

    public function registrar_widget($widgets_manager) {
        if (!did_action('elementor/loaded')) {
            return;
        }

        require_once DMA_PLUGIN_DIR . 'includes/class-dma-elementor-widget.php';
        $widgets_manager->register(new \DMA_Elementor_Widget());
    }
}
