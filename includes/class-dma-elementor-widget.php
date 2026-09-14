<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class DMA_Elementor_Widget extends Widget_Base {

    public function get_name() {
        return 'dma-delete-account';
    }

    public function get_title() {
        return __('Delete My Account', 'delete-my-account');
    }

    public function get_icon() {
        return 'eicon-lock-user';
    }

    public function get_categories() {
        return array('delete-my-account', 'general');
    }

    public function get_keywords() {
        return array('account', 'delete', 'gdpr', 'privacy', 'user');
    }

    protected function register_controls() {
        $this->start_controls_section(
            'seccion_info',
            array(
                'label' => __('Info', 'delete-my-account'),
            )
        );

        $this->add_control(
            'nota_ajustes',
            array(
                'type' => Controls_Manager::RAW_HTML,
                'raw'  => sprintf(
                    /* translators: %s: settings page URL */
                    __('This widget uses the [delete_my_account_button] shortcode. It only shows the button to logged-in, non-administrator users. Customize all texts and security options from <a href="%s" target="_blank">Settings → Delete My Account</a>.', 'delete-my-account'),
                    esc_url(admin_url('options-general.php?page=dma-ajustes'))
                ),
                'content_classes' => 'elementor-descriptor',
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        echo do_shortcode('[delete_my_account_button]');
    }

    protected function content_template() {
        ?>
        <div style="padding:12px;border:1px dashed #999;text-align:center;color:#666;font-size:13px;">
            <?php esc_html_e('Preview: the "Delete my account" button and confirmation dialog will render here on the live page.', 'delete-my-account'); ?>
        </div>
        <?php
    }
}
