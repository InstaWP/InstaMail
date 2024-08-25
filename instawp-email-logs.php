<?php
/*
Plugin Name: InstaWP Email Logs
Description: Intercept and log emails sent from your InstaWP Staging WordPress site.    
Version: 1.0.0
Author: InstaWP
*/

// At the beginning of the file
require_once(plugin_dir_path(__FILE__) . 'email-logs-common.php');

// Intercept emails
add_action('wp_mail', 'intercept_email', 10, 1);
function intercept_email($args) {
    // Prepare email data
    $email_data = [
        'to' => is_array($args['to']) ? $args['to'] : [$args['to']],
        'subject' => $args['subject'],
        'message' => $args['message'],
        'headers' => $args['headers'],
        'from' => isset($args['headers']['From']) ? $args['headers']['From'] : '',
        'timestamp' => time()
    ];

    $upload_dir = wp_upload_dir();
    $email_dir = $upload_dir['basedir'] . '/intercepted_emails';
    if (!file_exists($email_dir)) {
        mkdir($email_dir, 0755, true);
    }

    $filename = $email_dir . '/' . time() . '_' . md5(json_encode($email_data)) . '.json';
    file_put_contents($filename, json_encode($email_data, JSON_PRETTY_PRINT));

    // Return the original arguments to allow the email to be sent
    return $args;
}

// Add admin menu under Tools
add_action('admin_menu', 'instawp_email_logs_menu');
function instawp_email_logs_menu() {
    add_management_page(__('InstaWP Email Logs', 'instawp-email-logs'), __('InstaWP Email Logs', 'instawp-email-logs'), 'manage_options', 'instawp-email-logs', 'instawp_email_logs_page');
}

// Combined admin page for logs and settings
function instawp_email_logs_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'instawp-email-logs'));
    }

    wp_enqueue_style('tailwind', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');

    // Handle settings update
    if (isset($_POST['instawp_email_logs_password'])) {
        update_option('instawp_email_logs_password', wp_hash_password($_POST['instawp_email_logs_password']));
        echo '<div class="notice notice-success"><p>' . __('Password updated successfully.', 'instawp-email-logs') . '</p></div>';
    }

    // Handle OTP generation
    if (isset($_POST['generate_otp'])) {
        $otp = generate_otp();
        $public_url = plugins_url('public-email-logs.php', __FILE__);
        $otp_url = add_query_arg('otp', $otp, $public_url);
        echo '<div class="notice notice-success"><p>' . __('OTP generated successfully. Use this URL for one-time access:', 'instawp-email-logs') . ' <a href="' . esc_url($otp_url) . '">' . esc_html($otp_url) . '</a></p></div>';
    }

    // Handle clear all logs action
    if (isset($_POST['clear_all_logs']) && check_admin_referer('clear_all_logs')) {
        $upload_dir = wp_upload_dir();
        $email_dir = trailingslashit($upload_dir['basedir']) . 'intercepted_emails';
        $email_dir = wp_normalize_path($email_dir);
        
        if (is_dir($email_dir)) {
            $files = glob($email_dir . '/*.json');
            $deleted_count = 0;
            foreach ($files as $file) {
                if (is_file($file) && unlink($file)) {
                    $deleted_count++;
                }
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('%d email logs have been cleared.', 'instawp-email-logs'), $deleted_count) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to clear email logs. The directory does not exist.', 'instawp-email-logs') . '</p></div>';
        }
    }

    // Handle delete action
    if (isset($_GET['delete']) && check_admin_referer('delete_email')) {
        $filename = sanitize_file_name($_GET['delete']);
        $result = delete_email($filename);
        if ($result) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Email log deleted successfully.', 'instawp-email-logs') . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to delete email log.', 'instawp-email-logs') . '</p></div>';
        }
    }

    $current_password = get_option('instawp_email_logs_password', '');
    $public_url = plugins_url('public-email-logs.php', __FILE__);

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">' . __('InstaWP Email Logs', 'instawp-email-logs') . '</h1>';

    // Public Access Settings Section (hidden by default)
    echo '<div id="settings-panel" class="bg-white shadow-md rounded my-6 p-6" style="display: none;">';
    echo '<h2 class="text-xl mb-4">' . __('Public Access Settings', 'instawp-email-logs') . '</h2>';
    echo '<form method="post">';
    wp_nonce_field('instawp_email_logs_action', 'instawp_email_logs_nonce');
    echo '<div class="mb-4">';
    echo '<label for="instawp_email_logs_password" class="block text-sm font-bold mb-2">' . __('Access Password', 'instawp-email-logs') . '</label>';
    echo '<input type="text" name="instawp_email_logs_password" id="instawp_email_logs_password" value="' . esc_attr($current_password) . '" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">';
    echo '</div>';
    echo '<div class="flex items-center justify-between">';
    echo '<input type="submit" name="submit" id="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" value="' . __('Save Changes', 'instawp-email-logs') . '">';
    echo '</div>';
    echo '</form>';

    echo '<form method="post" class="mt-4">';
    wp_nonce_field('generate_otp');
    echo '<input type="submit" name="generate_otp" id="generate_otp" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" value="' . __('Generate One-Time Password', 'instawp-email-logs') . '">';
    echo '</form>';

    echo '<p class="mt-4">' . __('Public access URL:', 'instawp-email-logs') . ' <a href="' . esc_url($public_url) . '" target="_blank" class="text-blue-500 hover:text-blue-700">' . esc_html($public_url) . '</a></p>';
    echo '</div>';

    // Email Logs Section
    echo '<div class="bg-white shadow-md rounded my-6 p-6">';
    echo '<div class="flex justify-between items-center mb-4">';
    echo '<h2 class="text-xl">' . __('Recent Emails', 'instawp-email-logs') . '</h2>';

    echo '<div class="flex items-center">';
    echo '<button id="toggle-settings" class="bg-transparent hover:bg-blue-500 text-blue-700 font-semibold hover:text-white py-2 px-4 border border-blue-500 hover:border-transparent rounded focus:outline-none focus:shadow-outline mr-2"><svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>' . __('Public Access', 'instawp-email-logs') . '</button>';

    echo '<form method="post" class="inline-block">';
    wp_nonce_field('clear_all_logs');
    echo '<input type="submit" name="clear_all_logs" value="' . __('Clear All Logs', 'instawp-email-logs') . '" class="bg-transparent hover:bg-red-500 text-red-700 font-semibold hover:text-white py-2 px-4 border border-red-500 hover:border-transparent rounded focus:outline-none focus:shadow-outline" onclick="return confirm(\'' . __('Are you sure you want to clear all logs?', 'instawp-email-logs') . '\');">';
    echo '</form>';
    echo '</div>'; // Close flex container for buttons

    echo '</div>'; // Close flex container for title and buttons

    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $email_logs = get_email_logs($current_page);

    display_email_logs_table($email_logs['emails'], true);
    display_pagination($email_logs['total_pages'], $email_logs['current_page'], true);
    display_email_modal();
    display_modal_scripts(true);

    echo '</div>'; // Close email logs section

    echo '</div>'; // Close wrap

    // Add JavaScript to toggle settings visibility
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var settingsPanel = document.getElementById("settings-panel");
            var toggleButton = document.getElementById("toggle-settings");
            
            toggleButton.addEventListener("click", function() {
                if (settingsPanel.style.display === "none") {
                    settingsPanel.style.display = "block";
                    settingsPanel.scrollIntoView({behavior: "smooth"});
                } else {
                    settingsPanel.style.display = "none";
                }
            });
        });
    </script>';
}

// Function to generate OTP
function generate_otp() {
    $otp = wp_generate_password(12, false);
    update_option('instawp_email_logs_otp', $otp);
    return $otp;
}

// Load text domain for translations
function instawp_email_logs_load_textdomain() {
    load_plugin_textdomain('instawp-email-logs', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'instawp_email_logs_load_textdomain');

// Add AJAX action to get email content
add_action('wp_ajax_get_email_content', 'get_email_content_admin');
function get_email_content_admin() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'instawp-email-logs'));
    }

    get_email_content();
}

// Add this new function to handle email deletion
function delete_email($filename) {
    $upload_dir = wp_upload_dir();
    $email_dir = trailingslashit($upload_dir['basedir']) . 'intercepted_emails';
    $email_dir = wp_normalize_path($email_dir);
    $file_path = $email_dir . '/' . $filename;

    if (file_exists($file_path) && strpos($file_path, $email_dir) === 0) {
        return unlink($file_path);
    }

    return false;
}