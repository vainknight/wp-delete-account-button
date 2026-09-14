<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * DMA_Opciones
 * ------------
 * Stores customizable text/behavior options and registers the
 * settings page under Settings → Delete My Account.
 */
class DMA_Opciones {

    private static $instancia = null;
    const OPTION_KEY = 'dma_opciones';

    public static function instancia() {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'registrar_pagina'));
        add_action('admin_init', array($this, 'registrar_ajustes'));
    }

    public static function defaults() {
        return array(
            'texto_boton'           => 'Delete my account',
            'texto_no_logueado'     => 'You need to be logged in to delete an account.',
            'texto_advertencia'     => 'This action is PERMANENT. You will lose access to all your data, and this cannot be undone.',
            'texto_placeholder_pwd' => 'Enter your current password to confirm',
            'texto_boton_confirmar' => 'Permanently delete my account',
            'texto_boton_cancelar'  => 'Cancel',
            'color_boton'           => '#EF4444',
            'color_boton_hover'     => '#CE1212',
            'requerir_password'     => 1,
            'registrar_auditoria'   => 1,
        );
    }

    public function obtener_opciones() {
        $guardadas = get_option(self::OPTION_KEY, array());
        return wp_parse_args($guardadas, self::defaults());
    }

    public function registrar_pagina() {
        add_options_page(
            __('Delete My Account', 'delete-my-account'),
            __('Delete My Account', 'delete-my-account'),
            'manage_options',
            'dma-ajustes',
            array($this, 'render_pagina')
        );
    }

    public function registrar_ajustes() {
        register_setting('dma_grupo_opciones', self::OPTION_KEY, array(
            'sanitize_callback' => array($this, 'sanitizar_opciones'),
        ));
    }

    public function sanitizar_opciones($input) {
        $defaults = self::defaults();
        $limpio   = array();

        foreach ($defaults as $clave => $valor_defecto) {
            if (!isset($input[$clave])) {
                // Checkboxes: absence means "unchecked" (0), not an error.
                $limpio[$clave] = in_array($clave, array('requerir_password', 'registrar_auditoria'), true) ? 0 : $valor_defecto;
                continue;
            }

            $valor = $input[$clave];

            if (strpos($clave, 'color') !== false) {
                $limpio[$clave] = sanitize_hex_color($valor) ? $valor : $valor_defecto;
            } elseif (in_array($clave, array('requerir_password', 'registrar_auditoria'), true)) {
                $limpio[$clave] = !empty($valor) ? 1 : 0;
            } else {
                $limpio[$clave] = sanitize_text_field($valor);
            }
        }

        return $limpio;
    }

    public function render_pagina() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $o = $this->obtener_opciones();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Delete My Account — Settings', 'delete-my-account'); ?></h1>
            <p><?php esc_html_e('Customize the button text, warning message, and security behavior. Use the [delete_my_account_button] shortcode wherever you want the button to appear.', 'delete-my-account'); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields('dma_grupo_opciones'); ?>

                <h2 class="title"><?php esc_html_e('Security', 'delete-my-account'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Require password re-entry', 'delete-my-account'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->nombre_campo('requerir_password')); ?>" value="1" <?php checked(!empty($o['requerir_password'])); ?>>
                                <?php esc_html_e('Ask the user to re-enter their current password before deleting the account (strongly recommended).', 'delete-my-account'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Audit log', 'delete-my-account'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($this->nombre_campo('registrar_auditoria')); ?>" value="1" <?php checked(!empty($o['registrar_auditoria'])); ?>>
                                <?php esc_html_e('Log deleted account username/email, user ID, and IP address to the PHP error log for audit purposes.', 'delete-my-account'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php esc_html_e('Texts', 'delete-my-account'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php $this->fila_texto('texto_boton', __('Button label', 'delete-my-account'), $o); ?>
                    <?php $this->fila_texto('texto_no_logueado', __('Message when not logged in', 'delete-my-account'), $o); ?>
                    <?php $this->fila_texto('texto_advertencia', __('Warning message shown in the confirmation dialog', 'delete-my-account'), $o); ?>
                    <?php $this->fila_texto('texto_placeholder_pwd', __('Password field placeholder', 'delete-my-account'), $o); ?>
                    <?php $this->fila_texto('texto_boton_confirmar', __('Confirm button label', 'delete-my-account'), $o); ?>
                    <?php $this->fila_texto('texto_boton_cancelar', __('Cancel button label', 'delete-my-account'), $o); ?>
                </table>

                <h2 class="title"><?php esc_html_e('Appearance', 'delete-my-account'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php $this->fila_color('color_boton', __('Button text color', 'delete-my-account'), $o); ?>
                    <?php $this->fila_color('color_boton_hover', __('Button text color (hover)', 'delete-my-account'), $o); ?>
                </table>

                <?php submit_button(__('Save changes', 'delete-my-account')); ?>
            </form>
        </div>
        <?php
    }

    private function nombre_campo($clave) {
        return self::OPTION_KEY . '[' . $clave . ']';
    }

    private function fila_color($clave, $label, $o) {
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($clave); ?>"><?php echo esc_html($label); ?></label></th>
            <td><input type="color" id="<?php echo esc_attr($clave); ?>" name="<?php echo esc_attr($this->nombre_campo($clave)); ?>" value="<?php echo esc_attr($o[$clave]); ?>"></td>
        </tr>
        <?php
    }

    private function fila_texto($clave, $label, $o) {
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($clave); ?>"><?php echo esc_html($label); ?></label></th>
            <td><input type="text" id="<?php echo esc_attr($clave); ?>" name="<?php echo esc_attr($this->nombre_campo($clave)); ?>" value="<?php echo esc_attr($o[$clave]); ?>" class="regular-text"></td>
        </tr>
        <?php
    }
}
