<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

function get_email_logs($page = 1, $per_page = 20) {
    $upload_dir = wp_upload_dir();
    $email_dir = trailingslashit($upload_dir['basedir']) . 'intercepted_emails';
    $email_dir = wp_normalize_path($email_dir);
    $emails = glob($email_dir . '/*.json');

    // Sort emails by modification time in descending order
    usort($emails, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    $total_emails = count($emails);
    $total_pages = ceil($total_emails / $per_page);
    $offset = ($page - 1) * $per_page;
    $emails_to_display = array_slice($emails, $offset, $per_page);

    return [
        'emails' => $emails_to_display,
        'total_pages' => $total_pages,
        'current_page' => $page,
    ];
}

function get_email_content() {
    $upload_dir = wp_upload_dir();
    $email_dir = trailingslashit($upload_dir['basedir']) . 'intercepted_emails';
    $email_dir = wp_normalize_path($email_dir);
    $filename = sanitize_file_name($_GET['filename']);
    $file = $email_dir . '/' . $filename;

    if (file_exists($file) && is_file($file) && dirname(realpath($file)) === realpath($email_dir)) {
        echo file_get_contents($file);exit;
        $content = json_decode(file_get_contents($file), true);
        
        echo json_encode($content);
    } else {
        echo 'Email not found.';
    }

    wp_die();
}

function display_email_logs_table($emails, $is_admin = false, $is_public = false) {
    echo '<table class="min-w-full table-auto">';
    echo '<thead class="bg-gray-200"><tr>
        <th class="w-1/6 px-4 py-2">Date</th>
        <th class="w-1/6 px-4 py-2">To</th>
        <th class="w-1/2 px-4 py-2">Subject</th>
        <th class="w-1/6 px-4 py-2">Actions</th>
    </tr></thead>';
    echo '<tbody>';

    foreach ($emails as $email) {
        $content = json_decode(file_get_contents($email), true);
        $date = date('Y-m-d H:i:s', filemtime($email));
        
        $to = isset($content['to']) ? implode(', ', (array)$content['to']) : '';
        $subject = isset($content['subject']) ? $content['subject'] : '';

        echo '<tr>';
        echo '<td class="border px-4 py-2">' . esc_html($date) . '</td>';
        echo '<td class="border px-4 py-2">' . esc_html($to) . '</td>';
        echo '<td class="border px-4 py-2">' . esc_html($subject) . '</td>';
        echo '<td class="border px-4 py-2">';
        if ($is_public) {
            echo '<a href="#" onclick="viewEmail(\'' . esc_js(basename($email)) . '\', \'' . esc_js($_SERVER['PHP_SELF']) . '\'); return false;" class="text-blue-500 hover:text-blue-700 mr-2">View</a>';
        } else {
            echo '<a href="#" onclick="viewEmail(\'' . esc_js(basename($email)) . '\'); return false;" class="text-blue-500 hover:text-blue-700 mr-2">View</a>';
        }
        if ($is_admin) {
            echo '<a href="' . wp_nonce_url(admin_url('tools.php?page=instawp-email-logs&delete=' . basename($email)), 'delete_email') . '" class="text-red-500 hover:text-red-700" onclick="return confirm(\'Are you sure?\')">Delete</a>';
        }
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

function display_pagination($total_pages, $current_page, $is_admin = false) {
    if ($total_pages > 1) {
        echo '<div class="flex justify-center mt-4">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $class = $i === $current_page ? 'bg-blue-500 text-white' : 'text-blue-500';
            $url = $is_admin ? add_query_arg('paged', $i) : "?paged=$i";
            echo '<a href="' . $url . '" class="mx-1 px-3 py-2 bg-white border ' . $class . ' rounded">' . $i . '</a>';
        }
        echo '</div>';
    }
}

function display_email_modal() {
    ?>
    <div id="emailModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title"></h3>
                            <div class="mt-2 max-h-96 overflow-y-auto">
                                <iframe id="emailContentFrame" class="w-full h-96 border-0"></iframe>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="closeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function display_modal_scripts($is_admin = false, $is_public = false) {
    $ajax_url = $is_admin ? 'ajaxurl' : "'" . admin_url('admin-ajax.php') . "'";
    if ($is_public) {
        $ajax_url = "window.location.href";
    }
    ?>
    <script>
    function viewEmail(filename, publicUrl = null) {
        const url = publicUrl ? 
            `${publicUrl}?action=get_email_content&filename=${filename}` :
            `${<?php echo $ajax_url; ?>}?action=get_email_content&filename=${filename}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                const emailData = data;
                if (emailData.subject) {
                    const modalTitle = document.getElementById("modal-title");
                    modalTitle.textContent = `${emailData.subject} (To: ${emailData.to})`;

                    const iframe = document.getElementById("emailContentFrame");
                    const isHtmlEmail = /<[a-z][\s\S]*>/i.test(emailData.message);
                    
                    if (isHtmlEmail) {
                        const iframeContent = `
                            <html>
                                <head>
                                    <style>
                                        body { font-family: Arial, sans-serif; padding: 20px; }
                                    </style>
                                </head>
                                <body>${emailData.message}</body>
                            </html>
                        `;
                        iframe.srcdoc = iframeContent;
                    } else {
                        iframe.srcdoc = `
                            <html>
                                <head>
                                    <style>
                                        body {
                                            font-family: monospace;
                                            white-space: pre-wrap;
                                            padding: 20px;
                                            word-wrap: break-word;
                                            max-width: 100%;                                        }
                                    </style>
                                </head>
                                <body><pre>${emailData.message}</pre></body>
                            </html>
                        `;
                    }

                    openModal();
                } else {
                    alert('Error: ' + data.data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while fetching the email content.');
            });
    }

    function openModal() {
        const modal = document.getElementById("emailModal");
        modal.classList.remove("hidden");
        document.addEventListener("keydown", handleEscapeKey);
        modal.addEventListener("click", handleOutsideClick);
    }

    function closeModal() {
        const modal = document.getElementById("emailModal");
        modal.classList.add("hidden");
        document.removeEventListener("keydown", handleEscapeKey);
        modal.removeEventListener("click", handleOutsideClick);
    }

    function handleEscapeKey(event) {
        if (event.key === "Escape") {
            closeModal();
        }
    }

    function handleOutsideClick(event) {
        const modalContent = document.querySelector("#emailModal > div > div:nth-child(3)");
        if (!modalContent.contains(event.target)) {
            closeModal();
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        const modal = document.getElementById("emailModal");
        modal.addEventListener("click", handleOutsideClick);
    });
    </script>
    <?php
}