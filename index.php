<?php

@set_time_limit(0);

define('WP_API_CORE', 'http://api.wordpress.org/core/version-check/1.7/?locale=');
define('WPQI_CACHE_PATH', 'cache/');
define('WPQI_CACHE_CORE_PATH', WPQI_CACHE_PATH . 'core/');
define('WPQI_CACHE_PLUGINS_PATH', WPQI_CACHE_PATH . 'plugins/');

require('inc/functions.php');

// Create cache directories
if (!is_dir(WPQI_CACHE_PATH)) {
    mkdir(WPQI_CACHE_PATH);
}
if (!is_dir(WPQI_CACHE_CORE_PATH)) {
    mkdir(WPQI_CACHE_CORE_PATH);
}
if (!is_dir(WPQI_CACHE_PLUGINS_PATH)) {
    mkdir(WPQI_CACHE_PLUGINS_PATH);
}

// We verify if there is a preconfig file
if (!file_exists('data.ini')) {
    die();
}
$data_file = parse_ini_file('data.ini');

// Get default language from config file for Wordpress and installator
$language = $data_file['language'];

setlocale(LC_ALL, $language . '.UTF-8');

bindtextdomain('default', './locale');
textdomain('default');

// Install directory
if (isset($_POST['subdomain']) && !empty($_POST['subdomain']) && preg_match('/^[a-z][-a-z0-9]*$/', $_POST['subdomain'])) {
    $subdomain = $_POST['subdomain'];
    $directory = $data_file['directory'] . '/' . $subdomain;
}

if (isset($_GET['action'])) {
    switch ($_GET['action']) {

        case "check_before_upload":

            $data = array();

            /*--------------------------*/
            /*  We verify if we can connect to DB or WP is not installed yet
            /*--------------------------*/

            // DB Test
            try {
                $db = new PDO('mysql:host='. $data_file['dbhost'], $data_file['dbuser'], $data_file['dbpassword']);
            } catch (Exception $e) {
                $data['db'] = "error etablishing connection";
            }

            // WordPress test
            if (!isset($directory) || file_exists($directory . '/wp-config.php')) {
                $data['wp'] = "error directory";
            }

            // We send the response
            echo json_encode($data);

            break;

        case "download_wp":

            // Get WordPress data
            $wp = json_decode(file_get_contents(WP_API_CORE . $language))->offers[0];

            /*--------------------------*/
            /*  We download the latest version of WordPress
            /*--------------------------*/

            if (!file_exists(WPQI_CACHE_CORE_PATH . 'wordpress-' . $wp->version . '-' . $language  . '.zip')) {
                file_put_contents(WPQI_CACHE_CORE_PATH . 'wordpress-' . $wp->version . '-' . $language  . '.zip', file_get_contents($wp->download));
            }

            break;

        case "unzip_wp":

            // If we want to put WordPress in a subfolder we create it
            if (!isset($directory)) {
                die();
            }

            // Get WordPress data
            $wp = json_decode(file_get_contents(WP_API_CORE . $language))->offers[0];

            /*--------------------------*/
            /*  We create the website folder with the files and the WordPress folder
            /*--------------------------*/

            // Let's create the folder
            mkdir($directory);

            // We set the good writing rights
            chmod($directory, 0775);

            $zip = new ZipArchive;

            // We verify if we can use the archive
            if ($zip->open(WPQI_CACHE_CORE_PATH . 'wordpress-' . $wp->version . '-' . $language  . '.zip') === true) {

                // Let's unzip
                $zip->extractTo(WPQI_CACHE_CORE_PATH);
                $zip->close();

                // We scan the folder
                $files = scandir(WPQI_CACHE_CORE_PATH . 'wordpress');

                // We remove the "." and ".." from the current folder and its parent
                $files = array_diff($files, array( '.', '..' ));

                // We move the files and folders
                foreach ($files as $file) {
                    rename(WPQI_CACHE_CORE_PATH . 'wordpress/' . $file, $directory . '/' . $file);
                }

                rmdir(WPQI_CACHE_CORE_PATH . 'wordpress'); // We remove WordPress folder
                unlink($directory . '/wp-content/plugins/hello.php'); // We remove Hello Dolly plugin
            }

            break;

            case "wp_config":

                if (!isset($directory)) {
                    die();
                }

                // Create user DB
                $dbname = $data_file['dbprefix'] . $subdomain;
                $dbuser = $subdomain;
                $dbpassword = random_password();

                try {
                    $db = new PDO('mysql:host='. $data_file['dbhost'], $data_file['dbuser'], $data_file['dbpassword']);

                    // Create database
                    $db->exec("CREATE USER '$dbuser'@'localhost' IDENTIFIED BY '$dbpassword'");
                    $db->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
                    $db->exec("GRANT ALL PRIVILEGES ON `$dbname`.* TO '$dbuser'@'localhost'");
                } catch (Exception $e) {
                    die();
                }

                /*--------------------------*/
                /*  Let's create the wp-config file
                /*--------------------------*/

                // We retrieve each line as an array
                $config_file = file($directory . '/wp-config-sample.php');

                // Managing the security keys
                $secret_keys = explode("\n", file_get_contents('https://api.wordpress.org/secret-key/1.1/salt/'));

                foreach ($secret_keys as $k => $v) {
                    $secret_keys[$k] = substr($v, 28, 64);
                }

                // We change the data
                $key = 0;
                foreach ($config_file as &$line) {
                    if (!preg_match('/^define\(\'([A-Z_]+)\',([ ]+)/', $line, $match)) {
                        continue;
                    }

                    $constant = $match[1];

                    switch ($constant) {
                        case 'DB_NAME':
                            $line = "define('DB_NAME', '" . $dbname . "');\r\n";
                            break;
                        case 'DB_USER':
                            $line = "define('DB_USER', '" . $dbuser . "');\r\n";
                            break;
                        case 'DB_PASSWORD':
                            $line = "define('DB_PASSWORD', '" . $dbpassword . "');\r\n";
                            break;
                        case 'DB_HOST':
                            $line = "define('DB_HOST', '" . $data_file['dbhost'] . "');\r\n";
                            break;
                        case 'AUTH_KEY':
                        case 'SECURE_AUTH_KEY':
                        case 'LOGGED_IN_KEY':
                        case 'NONCE_KEY':
                        case 'AUTH_SALT':
                        case 'SECURE_AUTH_SALT':
                        case 'LOGGED_IN_SALT':
                        case 'NONCE_SALT':
                            $line = "define('" . $constant . "', '" . $secret_keys[$key++] . "');\r\n";
                            break;

                        case 'WPLANG':
                            $line = "define('WPLANG', '" . $data_file['language'] . "');\r\n";
                            break;
                    }
                }
                unset($line);

                $handle = fopen($directory . '/wp-config.php', 'w');
                foreach ($config_file as $line) {
                    fwrite($handle, $line);
                }
                fclose($handle);

                // We set the good rights to the wp-config file
                chmod($directory . '/wp-config.php', 0666);

                break;

            case "install_wp":

                if (!isset($directory)) {
                    die();
                }

                /*--------------------------*/
                /*  Let's install WordPress database
                /*--------------------------*/

                define('WP_INSTALLING', true);

                /** Load WordPress Bootstrap */
                require_once($directory . '/wp-load.php');

                /** Load WordPress Administration Upgrade API */
                require_once($directory . '/wp-admin/includes/upgrade.php');

                /** Load wpdb */
                require_once($directory . '/wp-includes/wp-db.php');

                // WordPress installation
                wp_install($_POST['title'], $_POST['username'], $_POST['email'], 1, '', $_POST['password']);

                // We update the options with the right siteurl et homeurl value
                $url = 'http://' . $subdomain . '.' . $data_file['domain'] . '/';

                update_option('siteurl', $url);
                update_option('home', $url);
                break;

            case "install_theme":

                /** Load WordPress Bootstrap */
                require_once($directory . '/wp-load.php');

                /** Load WordPress Administration Upgrade API */
                require_once($directory . '/wp-admin/includes/upgrade.php');

                /*--------------------------*/
                /*  We install themes
                /*--------------------------*/

                // Delete default themes ?
                if ($data_file['delete_default_themes']) {
                    foreach (wp_get_themes() as $key => $theme) {
                        delete_theme($key);
                    }
                }

                $themes_dir = $directory . '/wp-content/themes/';

                // We scan the folder
                $themes = scandir('themes');

                // We remove the "." and ".." corresponding to the current and parent folder
                $themes = array_diff($themes, array( '.', '..' ));

                // We move the archives and we unzip
                foreach ($themes as $theme) {

                    // We verify if we have to retrive somes plugins via the WP Quick Install "plugins" folder
                    if (preg_match('#(.*).zip$#', $theme) == 1) {
                        $zip = new ZipArchive;

                        // We verify we can use the archive
                        if ($zip->open('themes/' . $theme) === true) {

                            // We unzip the archive in the theme folder
                            $zip->extractTo($themes_dir);
                            $zip->close();
                        }
                    }
                }

                // Let's activate the theme
                switch_theme($data_file['theme']);

            break;

            case "install_plugins":

                /*--------------------------*/
                /*  Let's retrieve the plugin folder
                /*--------------------------*/

                $plugins_dir = $directory . '/wp-content/plugins/';

                if (!empty($data_file['plugins'])) {
                    $plugins = explode(";", $data_file['plugins']);
                    $plugins = array_map('trim', $plugins);

                    foreach ($plugins as $plugin) {

                        // We retrieve the plugin XML file to get the link to downlad it
                        $plugin_repo = file_get_contents("http://api.wordpress.org/plugins/info/1.0/$plugin.json");

                        if ($plugin_repo && $plugin = json_decode($plugin_repo)) {
                            $plugin_path = WPQI_CACHE_PLUGINS_PATH . $plugin->slug . '-' . $plugin->version . '.zip';

                            if (!file_exists($plugin_path)) {
                                // We download the lastest version
                                if ($download_link = file_get_contents($plugin->download_link)) {
                                    file_put_contents($plugin_path, $download_link);
                                }
                            }

                            // We unzip it
                            $zip = new ZipArchive;
                            if ($zip->open($plugin_path) === true) {
                                $zip->extractTo($plugins_dir);
                                $zip->close();
                            }
                        }
                    }
                }

                // We scan the folder
                $plugins = scandir('plugins');

                // We remove the "." and ".." corresponding to the current and parent folder
                $plugins = array_diff($plugins, array( '.', '..' ));

                // We move the archives and we unzip
                foreach ($plugins as $plugin) {

                    // We verify if we have to retrive somes plugins via the WP Quick Install "plugins" folder
                    if (preg_match('#(.*).zip$#', $plugin) == 1) {
                        $zip = new ZipArchive;

                        // We verify we can use the archive
                        if ($zip->open('plugins/' . $plugin) === true) {

                            // We unzip the archive in the plugin folder
                            $zip->extractTo($plugins_dir);
                            $zip->close();
                        }
                    }
                }

                /*--------------------------*/
                /*  We activate extensions
                /*--------------------------*/

                /** Load WordPress Bootstrap */
                require_once($directory . '/wp-load.php');

                /** Load WordPress Plugin API */
                require_once($directory . '/wp-admin/includes/plugin.php');

                // Activation
                activate_plugins(array_keys(get_plugins()));

            break;

            case "success":

                /*--------------------------*/
                /*  If we have a success we add the link to the admin and the website
                /*--------------------------*/

                /** Load WordPress Bootstrap */
                require_once($directory . '/wp-load.php');

                /** Load WordPress Administration Upgrade API */
                require_once($directory . '/wp-admin/includes/upgrade.php');

                // Link to the admin
                echo '<a href="' . home_url() . '" class="btn btn-default" target="_blank">' . _('Go to your site') . '</a>' . PHP_EOL;
                echo '<a href="' . admin_url() . '" class="btn btn-primary" target="_blank">' . _('Login to your backoffice') . '</a>';

                break;
    }
} else {
    ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo substr($language, 0, 2) ?>">
    <head>
        <meta charset="utf-8" />
        <title>WP Quick Install</title>
        <!-- Get out Google!-->
        <meta name="robots" content="noindex, nofollow">
        <!-- CSS files -->
        <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Open+Sans%3A300italic%2C400italic%2C600italic%2C300%2C400%2C600&#038;subset=latin%2Clatin-ext&#038;ver=3.9.1" />
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" />
        <link rel="stylesheet" href="assets/css/style.css" />
    </head>
    <body class="wp-core-ui">
        <h1 id="logo">WordPress</h1>

        <div id="response"></div>
        <div class="progress" style="display:none;">
            <div class="progress-bar progress-bar-striped active" style="width: 0%;"></div>
        </div>
        <div id="success" style="display:none; margin: 10px 0;">
            <h1><?php echo _('Wordpress successfully installed !'); ?></h1>
        </div>
        <form method="post" action="">

            <div id="errors" class="alert alert-danger" style="display:none;">
                <strong><?php echo _('Warning'); ?></strong>
            </div>

            <h1><?php echo _('Wordpress installation'); ?></h1>

            <div class="row">
                <div class="form-group col-sm-12">
                    <input name="subdomain" type="text" class="form-control" size="25" value="" required placeholder="<?php echo _('Subdomain name'); ?>" />
                </div>
            </div>
            <div class="row">
                <div class="form-group col-sm-12">
                    <input name="title" type="text" class="form-control" size="25" value="" required placeholder="<?php echo _('Site name'); ?>" />
                </div>
            </div>
            <div class="row">
                <div class="form-group col-sm-6">
                    <input name="username" type="text" class="form-control" size="25" value="" required placeholder="<?php echo _('Username'); ?>" />
                </div>
                <div class="form-group col-sm-6">
                    <input name="password" type="password" class="form-control" size="25" value="" required placeholder="<?php echo _('Password'); ?>" />
                </div>
            </div>
            <div class="row">
                <div class="form-group col-sm-12">
                    <input name="email" type="email" class="form-control" size="25" value="" required placeholder="<?php echo _('Email'); ?>" />
                </div>
            </div>

            <p><button type="submit" id="submit" class="btn btn-primary"><?php echo _('Install Now !'); ?></button></p>

        </form>

        <script id="client-locale" type="application/json">
        {
          "download": "<?php echo _('WordPress download in Progress'); ?>",
          "decompress": "<?php echo _('Decompressing Files'); ?>",
          "config": "<?php echo _('Database and file Creation for wp-config'); ?>",
          "database": "<?php echo _('Database Installation in Progress'); ?>",
          "theme": "<?php echo _('Theme Installation in Progress'); ?>",
          "plugins": "<?php echo _('Plugins Installation in Progress'); ?>",
          "success": "<?php echo _('Successful installation completed'); ?>"
        }
        </script>
        <script src="https://code.jquery.com/jquery-2.2.3.min.js"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.15.0/jquery.validate.min.js"></script>
        <script src="assets/js/script.js"></script>
    </body>
</html>
<?php

}
