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

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'neznik');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', 'root');

/** MySQL hostname */
define('DB_HOST', 'localhost');

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
define('AUTH_KEY',         'F+y_O|/Lsa9nY=YTc{e@l1K82b9i 9zep-Heh8|nW,9q.:k0QY}yK*C%QiCL8/M#');
define('SECURE_AUTH_KEY',  '}gi7DgiDq&15o*@HqyC.h yKm|x~Zy7L{E=rBC1p!6Vka opkFXV`R&w+$kl^sEj');
define('LOGGED_IN_KEY',    'X:Nf(@}(dd;a:dV)kjRfNji20FH+H3SA[hXe,h|:_m{)+1.M($IsDcb5cXE_|HBT');
define('NONCE_KEY',        'xpiHnVsNMKy8%=i^OyL-58+z(e5hy#YGN)p+UX}e0#+Tn1b!(>Xiy)q[IfU9wxv^');
define('AUTH_SALT',        'p~z$!!F@:N0R@3tp~5ZDBD29Pb/Voj>pF>{n]:z]v2R:M=Cl|51oV,n/y)w*DH8w');
define('SECURE_AUTH_SALT', 'Nb->b&%B=%@DmbfaJlk,A[piJn@ua_CsuZ(zD9TK;:8NY!~!hBi-@?a)>wffa !_');
define('LOGGED_IN_SALT',   'bAU;2dx-p{YT=iyCGZu<:mSVL#z{JGM|W)c~MXJGhIY~|Sb-+nu-HRY{`J+6Nj<5');
define('NONCE_SALT',       'wUG$#:j-0<FdITp|YtF.P1!;urzwVx$QfGv6]b LZk{V4t{:zP*Du/7!{btoogZh');

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
