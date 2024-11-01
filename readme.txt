=== Ultimate WordPress Reset ===
Tags: wordpress-reset, wordpress, reset, admin, clean wordpress, clean, reset theme
Contributors: magniumthemes
Requires at least: 2.8
Requires PHP: 5.0
Tested up to: 5.1.1
Stable tag: 1.0

Resets the WordPress database back to default state with one click. Deletes all settings and content. Does not modify any files only resets the database.

== Description ==

Resets the WordPress database back to default state with one click. Deletes all settings and content. Does not modify any files only resets the database.

This plugin helps if your WordPress, theme or plugins works wrong and you want to quickly start from scratch and get fresh WordPress installation, without loosing any files. You can easily reinstall your theme and plugins after reset, without uploading files again. This is very helpful especially for theme developers and testers.

If the admin user exists and has level_10 permissions it will be recreated with its current password and email address. If the admin user does not exist or is a dummy account without admin permissions the username that is logged in will be recreated with its email address and current password.

You can access this plugin page from Admin Bar or Tools > WordPress Reset.

*   All your themes, plugins will be deactivated and all its settings will be resetted.
*   All your content will be removed (posts, pages, custom post types, comments, menus, users and WordPress settings).
*   None of your files will be removed (plugin only reset database).
*   Admin user will be recreated after reset.
*   You can automatically reactivate your plugins after reset.
*   Blog name will not be reseted.
*   Plugin work with ANY theme.

== Installation ==

1. Upload the `ultimate-wordpress-reset` folder to the `/wp-content/plugins/` directory or install directly in Plugins > Add new.
1. Activate the plugin in the 'Plugins' menu in WordPress or by using the link provided by the plugin installer.

== Frequently Asked Questions ==

= How to auto reactivate this plugin after reset? =

Add `define( 'REACTIVATE_WP_RESET', true );` to `wp-config.php` above `/* That's all, stop editing! Happy blogging. */`

= How to auto reactivate my plugins after reset? =

Add an array $reactivate_wp_reset_additional to the `wp-config.php` above `/* That's all, stop editing! Happy blogging. */` that contains the plugin basenames of the plugins to activate, such as:

`
$reactivate_wp_reset_plugins = array(
	'hello.php',
	'akismet/akismet.php'
);
`

== Upgrade ==

1. Go to Plugins in WordPress and update plugin as usual.

== Usage ==

1. Visit the WordPress Reset Tools page by either clicking the link in the Admin Bar or Tools>WordPress Reset
1. Type 'reset' in the text field and click reset.

== Screenshots ==

1. Screenshot 1

== Upgrade Notice ==

= 1.0: =
* Initial Release

== Changelog ==

= 1.0 (2017-11-16): =
* Initial Release
