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

// ** Heroku Postgres settings - from Heroku Environment ** //
$db = parse_url($_ENV["DATABASE_URL"]);

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
define('AUTH_KEY',         '/:m48iFIT|,XlI1^:TOqK3yt`LNR@PTz{%J(-[:J<Ur[;D RMWV(l]wlqhQOvfx}');
define('SECURE_AUTH_KEY',  'ONA:#>Ocstf!@N1,HXW~0-P$t7[@0~a9z)pgRXv,Yxx[<~1okRvGq(xI#liAq*AJ');
define('LOGGED_IN_KEY',    '}DGtY_+i&*^(V%0GuqD+xvpTP>$i)Ny@Vc|0k:tAAN:P-mOP@y7m[(io_<={#hP|');
define('NONCE_KEY',        'rAQ+i&rj3(O{uKX,hW;O9p1Tet4rx^iFdVE]}]<GI!%k0n:Zk+_rMTw%/H)Z1XuP');
define('AUTH_SALT',        'X9e4[D`|V6/h+L|<}`?o>&C(b8#pfChspBsc1e.h@sn|qAHkK$_-@1*ma#DzzRj`');
define('SECURE_AUTH_SALT', 'S5q-z]buWIOe9; sYAfM}*:e*}]m|j.Q8Myx,`nU~-S`0bj>wI=epr7ikauY7iJj');
define('LOGGED_IN_SALT',   'IHZ>|M[]L,1|7#N6,}Rk@L|NH.k>gF%Hg?yIP7yK(-j@zo3|Z))<w;[iR^wEu|k9');
define('NONCE_SALT',       '+Di~g(QS kX#.)Xk)ItB[TUUwS&^|vHu|BLeHp}jq+~K6E{@!je-aaplJTv1b=f6');

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
define('WPLANG', '');

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
