Lets logged-in users permanently delete their own account from the frontend, with password re-authentication and full security hardening.

== Description ==

Adds a "Delete my account" button that any logged-in, non-administrator user can use to permanently
delete their own WordPress account — useful for a Privacy Policy page or a "My Account" page, so
users don't have to request deletion by message.

Available in two ways:

1. Shortcode: `[delete_my_account_button]` (the original `[boton_borrar_cuenta]` name still works as an alias)
2. Elementor Widget: "Delete My Account"

= Security =

This plugin was built with a security-first approach and includes:

* **POST-only deletion request.** The action is triggered via `fetch()` with a POST body, never a GET
  redirect. A GET-based deletion link can be forged as a simple `<img>` tag on any third-party page —
  the browser attaches the victim's session cookies automatically (CSRF). POST closes that hole and
  keeps the nonce out of server logs, browser history, and Referer headers.
* **Password re-authentication.** Before deleting the account, the user must re-enter their current
  password, verified server-side with `wp_check_password()`. This can be turned off in settings, but
  is strongly recommended and enabled by default — it proves active credential possession, so a
  hijacked session or a stray XSS elsewhere on the site can't silently trigger deletion.
* **Robust admin-safe check.** Deletion is blocked for network super admins and for any user with the
  `manage_options` capability — not just the literal "administrator" role name — so custom elevated
  roles are protected too.
* **Nonce verified server-side, scoped to the logged-in user's own ID** — never trusts a user ID
  supplied by the request.
* **Optional audit log** (username, user ID, IP address) written before deletion, since the action is
  irreversible.
* **`dma_before_delete_account` action hook**, fired before the account is deleted, so other plugins
  (LMS progress, memberships, custom tables) can clean up related data tied to the user ID.
* JSON error responses instead of raw `wp_die()` pages, so failures show inline instead of a jarring
  full-page redirect.

Developed by [Fran Velazco](https://www.linkedin.com/in/fran-velazco/).
Built with Claude (Anthropic) as a development assistant and security reviewer.

== Installation ==

1. Upload the `delete-my-account` folder to `/wp-content/plugins/`.
2. Activate it through the 'Plugins' menu in WordPress.
3. Go to Settings → Delete My Account to customize texts and security options.
4. Place the shortcode `[delete_my_account_button]` on your Privacy Policy or My Account page, or
   drag the "Delete My Account" widget in the Elementor editor.

== Changelog ==

= 1.0.0 =
* Initial release: shortcode, settings page, and Elementor widget.
* Security hardening applied during audit: POST-only deletion (fixes CSRF-via-GET), password
  re-authentication, robust admin-safe check, server-side nonce scoping, optional audit log, and a
  cleanup hook for related plugin data.
