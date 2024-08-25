<?php
require_once('../../../wp-load.php');
require_once('email-logs-common.php');
// Call this function at the beginning of the file, after including wp-load.php
handle_ajax_requests();

// Check if the plugin is active
if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

if (!is_plugin_active('instamail/instawp-email-logs.php')) {
    wp_die(__('The InstaWP Email Logs plugin is not active.', 'instawp-email-logs'));
}

// Load text domain for translations
load_plugin_textdomain('instawp-email-logs', false, dirname(plugin_basename(__FILE__)) . '/languages');

// Check if the access is password protected
$access_password = get_option('instawp_email_logs_password');
$otp = get_option('instawp_email_logs_otp');

if (!$access_password) {
    wp_die(__('Access not configured. Please set up a password in the WordPress admin.', 'instawp-email-logs'));
}

// Call this function before processing login attempts
// rate_limit_check();

$is_authorized = check_authorization($access_password, $otp);

// Handle different actions
if (isset($_GET['action'])) {
    handle_action($_GET['action'], $is_authorized);
}

if (!$is_authorized) {
    display_login_form();
    exit;
}

// If no action is set and user is authorized, display the email logs
display_email_logs();

// Functions

function check_authorization($access_password, $otp) {
    $is_authorized = false;

    // Check for OTP in GET parameters
    if (isset($_GET['otp']) && $otp && $_GET['otp'] === $otp) {
        $is_authorized = true;
        setcookie('instawp_email_logs_auth', md5($access_password), time() + 3600, '/');
        delete_option('instawp_email_logs_otp'); // Delete the OTP after use
    }
    // Check for POST password submission
    elseif (isset($_POST['password']) && $_POST['password'] === $access_password) {
        $is_authorized = true;
        setcookie('instawp_email_logs_auth', md5($access_password), time() + 3600, '/');
    } 
    // Check for existing auth cookie
    elseif (isset($_COOKIE['instawp_email_logs_auth']) && $_COOKIE['instawp_email_logs_auth'] === md5($access_password)) {
        $is_authorized = true;
    }

    return $is_authorized;
}

function handle_action($action, $is_authorized) {
    switch ($action) {
        case 'logout':
            handle_logout();
            break;
        default:
            // Handle unknown actions
            break;
    }
}

function handle_logout() {
    setcookie('instawp_email_logs_auth', '', time() - 3600, '/');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

function display_login_form() {
    $error_message = isset($_POST['password']) ? __('Incorrect password. Please try again.', 'instawp-email-logs') : '';
    
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <title><?php esc_html_e('InstaWP Email Logs - Login', 'instawp-email-logs'); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    </head>
    <body class="bg-gray-100 flex items-center justify-center h-screen">
        <form method="post" class="bg-white p-8 rounded shadow-md">
            <?php wp_nonce_field('instawp_email_logs_action', 'instawp_email_logs_nonce'); ?>
            <h2 class="text-2xl mb-4"><?php esc_html_e('InstaWP Email Logs', 'instawp-email-logs'); ?></h2>
            <?php if ($error_message): ?>
                <p class="text-red-500 mb-4"><?php echo esc_html($error_message); ?></p>
            <?php endif; ?>
            <input type="password" name="password" placeholder="<?php esc_attr_e('Enter password', 'instawp-email-logs'); ?>" class="border p-2 w-full mb-4">
            <button type="submit" class="bg-blue-500 text-white p-2 rounded w-full"><?php esc_html_e('Access Logs', 'instawp-email-logs'); ?></button>
        </form>
    </body>
    </html>
    <?php
}

function display_email_logs() {
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $logs_data = get_email_logs($current_page);
    
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <title><?php esc_html_e('InstaWP Email Logs', 'instawp-email-logs'); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    </head>
    <body class="bg-gray-100 p-8">
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-2xl"><?php esc_html_e('InstaWP Email Logs', 'instawp-email-logs'); ?></h1>
            <a href="?action=logout" class="text-red-500 hover:text-red-700 font-bold"><?php esc_html_e('Logout', 'instawp-email-logs'); ?></a>
        </div>
        
        <div class="bg-white shadow-md rounded my-6">
            <?php display_email_logs_table($logs_data['emails'], false, true); ?>
        </div>

        <?php display_pagination($logs_data['total_pages'], $logs_data['current_page']); ?>

        <?php display_email_modal(); ?>

        <?php display_modal_scripts(false, true); ?>

        <div class="text-sm text-gray-600 mt-8">
            <p><?php esc_html_e('Note: Deletion of email logs is only available through the WordPress admin panel.', 'instawp-email-logs'); ?></p>
        </div>
    </body>
    </html>
    <?php
}

// Implement rate limiting for public access
function rate_limit_check() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $transient_name = 'instawp_rate_limit_' . md5($ip);
    $attempt_count = get_transient($transient_name);

    if ($attempt_count === false) {
        set_transient($transient_name, 1, 300); // 5 minutes
    } elseif ($attempt_count > 5) {
        wp_die('Too many attempts. Please try again later.');
    } else {
        set_transient($transient_name, $attempt_count + 1, 300);
    }
}

// Move the nonce check inside the password submission logic
if (isset($_POST['password'])) {
    if (
        isset($_POST['instawp_email_logs_nonce']) &&
        wp_verify_nonce($_POST['instawp_email_logs_nonce'], 'instawp_email_logs_action')
    ) {
        // Process form submission (this is already handled in the earlier code)
    } else {
        wp_die('Invalid nonce. Please try again.');
    }
}

// Add this new function to handle AJAX requests
function handle_ajax_requests() {
    if (isset($_GET['action']) && $_GET['action'] === 'get_email_content') {
        if (!check_authorization(get_option('instawp_email_logs_password'), get_option('instawp_email_logs_otp'))) {
            wp_send_json_error('Unauthorized access');
        }
        
        get_email_content();
    }
}

