<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, WordPress Language, and ABSPATH. You can find more information
 * by visiting {@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** Heroku ClearDB settings - from Heroku Environment ** //
$db = parse_url($_ENV["CLEARDB_DATABASE_URL"]);

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', trim($db["path"],"/"));

/** MySQL database username */
define('DB_USER', $db["user"]);

/** MySQL database password */
define('DB_PASSWORD', $db["pass"]);

/** MySQL hostname */
define('DB_HOST', $db["host"]);

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'b]Jj+uqf${~+N2ysy%z%d{X5i~:|V95l$@KD_)3vP954X--[4Wjv!Y-n{JP|QY~G');
define('SECURE_AUTH_KEY',  'G3Vg~3-/iJW;wk|OK8q((z}v@5e_1;u3|/LT5UXdo-sDF<BRU HP|n?F]e)J-xAT');
define('LOGGED_IN_KEY',    '1:SDfGoZ:$`?];[F]fZtM<U7?~7><AxL>$BDr?LY939e_S$Fo/j/AbD0d`=|7;;#');
define('NONCE_KEY',        'w<Nlf)Wny9EoRV|spJ~zxz*fe9<QERd*CP3{5Lpt-z@$$$yJi_7,w3@4pV%Mm;P$');
define('AUTH_SALT',        'DGddJ^f6Dy+ST&2-7h~}m1KOo!9{~,P7KoC4SzD&Fa{2(L_Uy eO`wqEp[j|a29z');
define('SECURE_AUTH_SALT', 'd@DsC?+m%kU9`yA5]ie2!2jxTtM6}a0@D63RD+1a;?y#My-6 #+hm<-lqf%1H#+a');
define('LOGGED_IN_SALT',   'd_Fy=CVccQ2q-u%6C<<{G=eZ(q;$XT-,d.-&*z87}*hc>4Nf-q]BLI1z{c~CU28^');
define('NONCE_SALT',       'P]wT&7MO+x#E|/4^`,WutH.7GUE,jS!-0n/fQjo#bxu0.h-gBl/0~8iXwE#;n`Hc');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * WordPress Localized Language, defaults to English.
 *
 * Change this to localize WordPress. A corresponding MO file for the chosen
 * language must be installed to wp-content/languages. For example, install
 * de_DE.mo to wp-content/languages and set WPLANG to 'de_DE' to enable German
 * language support.
 */
define('WPLANG', $_ENV["WORDPRESS_LANG"]);

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
