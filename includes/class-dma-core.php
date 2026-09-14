<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * DMA_Core
 * --------
 * Security-hardened rebuild of the original [boton_borrar_cuenta]
 * shortcode. Key fixes vs. the original script:
 *
 *  1. Deletion request now goes over POST via fetch() instead of a
 *     GET redirect. A GET request is trivially forgeable (CSRF) via
 *     something as simple as an <img src="..."> on a third-party
 *     page loaded by a victim with an active session — the browser
 *     attaches cookies automatically. POST + nonce-in-body closes
 *     that hole and keeps the nonce out of server logs, browser
 *     history and Referer headers.
 *  2. Password re-authentication is required before deletion (can be
 *     disabled in settings, but is on by default). This adds a proof
 *     of active credential possession, so a hijacked/XSS'd session
 *     can't silently trigger deletion without the user's password.
 *  3. Admin-safe check uses both is_super_admin() (multisite) and
 *     user_can($id, 'manage_options') — not just the literal
 *     'administrator' role — so custom elevated roles are covered.
 *  4. JSON responses instead of wp_die() HTML pages, so the frontend
 *     can show inline errors without a jarring full-page redirect.
 *  5. Optional audit log (username, user ID, IP) before deletion,
 *     since this is an irreversible action.
 *  6. 'dma_before_delete_account' action fires before wp_delete_user()
 *     so other plugins (LMS, memberships, custom tables) can clean up
 *     related data tied to the user ID.
 *  7. Nonce action is still scoped per-user-id, and is verified
 *     against the currently logged-in user's ID server-side (never
 *     trusts a user ID from the request).
 */
class DMA_Core {

    private static $instancia = null;

    public static function instancia() {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_shortcode('delete_my_account_button', array($this, 'shortcode_boton'));
        // Back-compat alias for the original shortcode name.
        add_shortcode('boton_borrar_cuenta', array($this, 'shortcode_boton'));

        add_action('wp_ajax_dma_eliminar_cuenta', array($this, 'ajax_eliminar_cuenta'));
        // Intentionally no wp_ajax_nopriv_ hook: deletion requires an
        // authenticated session, there is nothing to expose to guests.

        add_action('wp_enqueue_scripts', array($this, 'cargar_assets'));
        add_action('wp_head', array($this, 'imprimir_css_dinamico'));
    }

    // ---------------------------------------------------------
    // Shortcode: [delete_my_account_button] ([boton_borrar_cuenta] alias)
    // ---------------------------------------------------------
    public function shortcode_boton($atts) {
        $o = DMA_Opciones::instancia()->obtener_opciones();

        if (!is_user_logged_in()) {
            return '<p class="dma-no-logueado">' . esc_html($o['texto_no_logueado']) . '</p>';
        }

        $user_id = get_current_user_id();
        $nonce   = wp_create_nonce('dma_borrar_cuenta_' . $user_id);

        ob_start();
        ?>
        <div class="dma-wrapper">
            <button type="button" class="dma-btn-abrir" data-nonce="<?php echo esc_attr($nonce); ?>">
                <?php echo esc_html($o['texto_boton']); ?>
            </button>

            <div class="dma-modal" hidden>
                <div class="dma-modal-contenido" role="dialog" aria-modal="true" aria-labelledby="dma-modal-titulo">
                    <p id="dma-modal-titulo" class="dma-modal-advertencia">
                        <?php echo esc_html($o['texto_advertencia']); ?>
                    </p>

                    <?php if (!empty($o['requerir_password'])) : ?>
                        <input type="password"
                               class="dma-input-password"
                               placeholder="<?php echo esc_attr($o['texto_placeholder_pwd']); ?>"
                               autocomplete="current-password">
                    <?php endif; ?>

                    <p class="dma-error" hidden></p>

                    <div class="dma-modal-botones">
                        <button type="button" class="dma-btn-cancelar"><?php echo esc_html($o['texto_boton_cancelar']); ?></button>
                        <button type="button" class="dma-btn-confirmar"><?php echo esc_html($o['texto_boton_confirmar']); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ---------------------------------------------------------
    // AJAX handler — POST only, password re-check, hardened
    // ---------------------------------------------------------
    public function ajax_eliminar_cuenta() {
        // Enforce POST explicitly. wp_ajax_* is reachable via GET too,
        // so this check is what actually closes the CSRF-via-GET hole
        // (nonce alone is not enough once a GET link can be forged).
        if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
            wp_send_json_error(array('message' => __('Invalid request method.', 'delete-my-account')), 405);
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('No active session.', 'delete-my-account')), 401);
        }

        $user_id = get_current_user_id();

        // Nonce is read from POST body, never from the URL.
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'dma_borrar_cuenta_' . $user_id)) {
            wp_send_json_error(array('message' => __('Security check failed. Please reload the page and try again.', 'delete-my-account')), 403);
        }

        // Admin-safe check: covers both the literal "administrator"
        // role and any custom role granted manage_options, plus
        // network super admins on multisite.
        if (is_super_admin($user_id) || user_can($user_id, 'manage_options')) {
            wp_send_json_error(array('message' => __('Administrator accounts must be deleted from the dashboard.', 'delete-my-account')), 403);
        }

        $o = DMA_Opciones::instancia()->obtener_opciones();

        // Password re-authentication: proves the request comes from
        // someone who actually holds the credentials right now, not
        // just an active (possibly hijacked) session/cookie.
        if (!empty($o['requerir_password'])) {
            $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
            $user     = get_userdata($user_id);

            if (!$user || $password === '' || !wp_check_password($password, $user->user_pass, $user_id)) {
                wp_send_json_error(array('message' => __('Incorrect password.', 'delete-my-account')), 403);
            }
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';

        $user_data_antes = get_userdata($user_id);

        /**
         * Fires right before the user is permanently deleted, so other
         * plugins (LMS progress, memberships, custom tables, etc.) can
         * clean up data tied to this user ID while it still exists.
         */
        do_action('dma_before_delete_account', $user_id, $user_data_antes);

        if (!empty($o['registrar_auditoria']) && $user_data_antes) {
            error_log(sprintf(
                '[Delete My Account] User #%d (%s / %s) deleted their own account. IP: %s',
                $user_id,
                $user_data_antes->user_login,
                $user_data_antes->user_email,
                $this->obtener_ip_cliente()
            ));
        }

        wp_logout();

        if (wp_delete_user($user_id)) {
            wp_send_json_success(array('redirect' => home_url()));
        }

        wp_send_json_error(array('message' => __('There was an error deleting the account. Please contact support.', 'delete-my-account')), 500);
    }

    /**
     * Best-effort client IP for the audit log only. Not used for any
     * security decision, so spoofable headers are an acceptable
     * trade-off here (logging, not access control).
     */
    private function obtener_ip_cliente() {
        foreach (array('HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR') as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', sanitize_text_field(wp_unslash($_SERVER[$key])))[0];
                return trim($ip);
            }
        }
        return 'unknown';
    }

    // ---------------------------------------------------------
    // Assets
    // ---------------------------------------------------------
    public function cargar_assets() {
        wp_enqueue_script(
            'dma-delete-account',
            DMA_PLUGIN_URL . 'assets/delete-account.js',
            array(),
            DMA_VERSION,
            true
        );

        wp_localize_script('dma-delete-account', 'dmaAjax', array(
            'url' => admin_url('admin-ajax.php'),
        ));
    }

    public function imprimir_css_dinamico() {
        $o = DMA_Opciones::instancia()->obtener_opciones();
        ?>
        <style id="dma-estilos-dinamicos">
            .dma-btn-abrir {
                background: transparent !important;
                color: <?php echo esc_html($o['color_boton']); ?>;
                padding: 0;
                border: none !important;
                box-shadow: none !important;
                outline: none !important;
                cursor: pointer;
                font-weight: 600;
                font-family: inherit;
                font-size: 14px;
                text-decoration: none;
                transition: color 0.2s;
                display: inline-block;
            }
            .dma-btn-abrir:hover,
            .dma-btn-abrir:focus {
                color: <?php echo esc_html($o['color_boton_hover']); ?>;
                text-decoration: underline;
                background: transparent !important;
            }
            .dma-modal {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.6);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 100000;
            }
            .dma-modal[hidden] {
                display: none;
            }
            .dma-modal-contenido {
                background: #fff;
                border-radius: 8px;
                padding: 24px;
                max-width: 420px;
                width: 90%;
                font-family: inherit;
            }
            .dma-modal-advertencia {
                color: #7a1212;
                font-weight: 600;
                margin-top: 0;
            }
            .dma-input-password {
                width: 100%;
                box-sizing: border-box;
                padding: 8px 10px;
                margin: 12px 0;
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            .dma-error {
                color: #c83727;
                font-size: 13px;
                margin: 8px 0;
            }
            .dma-modal-botones {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                margin-top: 16px;
            }
            .dma-btn-cancelar {
                background: #eee;
                color: #333;
                border: none;
                border-radius: 4px;
                padding: 8px 14px;
                cursor: pointer;
            }
            .dma-btn-confirmar {
                background: <?php echo esc_html($o['color_boton']); ?>;
                color: #fff;
                border: none;
                border-radius: 4px;
                padding: 8px 14px;
                cursor: pointer;
                font-weight: 600;
            }
            .dma-btn-confirmar:hover {
                background: <?php echo esc_html($o['color_boton_hover']); ?>;
            }
            .dma-btn-confirmar:disabled {
                opacity: 0.6;
                cursor: default;
            }
        </style>
        <?php
    }
}
