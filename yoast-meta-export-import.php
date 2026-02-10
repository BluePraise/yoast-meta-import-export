<?php
/**
 * Plugin Name: Yoast Meta Description Export/Import
 * Description: Export and import Yoast SEO meta descriptions for pages, posts, and custom post types
 * Version: 1.0.0
 * Author: Maggie Chetrit
 * Website: https://magaliechetrit.com
 * License: The Unlicense (https://unlicense.org/)
 * License URI: https://unlicense.org/
 * Text Domain: yoast-meta-export-import
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Yoast_Meta_Export_Import {

    public function __construct() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_ymei_export', array($this, 'handle_export'));
        add_action('admin_post_ymei_import', array($this, 'handle_import'));
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'yoast-meta-export-import',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    public function add_admin_menu() {
        add_management_page(
            __('Yoast Meta Export/Import', 'yoast-meta-export-import'),
            __('Yoast Meta Export/Import', 'yoast-meta-export-import'),
            'manage_options',
            'yoast-meta-export-import',
            array($this, 'admin_page')
        );
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Yoast Meta Description Export/Import', 'yoast-meta-export-import'); ?></h1>

            <div class="card" style="max-width: none;">
                <h2><?php echo esc_html__('Export Meta Descriptions', 'yoast-meta-export-import'); ?></h2>
                <p><?php echo esc_html__('Exports all Yoast SEO meta descriptions for pages, posts and custom post types.', 'yoast-meta-export-import'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="ymei_export">
                    <?php wp_nonce_field('ymei_export_action', 'ymei_export_nonce'); ?>
                    <?php submit_button(__('Export Meta Descriptions', 'yoast-meta-export-import'), 'primary', 'submit', false); ?>
                </form>
            </div>

            <div class="card" style="max-width: none;">
                <h2><?php echo esc_html__('Import Meta Descriptions', 'yoast-meta-export-import'); ?></h2>
                <p><?php echo esc_html__('Imports meta descriptions by matching slugs.', 'yoast-meta-export-import'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="ymei_import">
                    <?php wp_nonce_field('ymei_import_action', 'ymei_import_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="import_file"><?php echo esc_html__('JSON file', 'yoast-meta-export-import'); ?></label>
                            </th>
                            <td>
                                <input type="file" name="import_file" id="import_file" accept=".json" aria-describedby="ymei-import-file-help" required>
                                <p class="description" id="ymei-import-file-help"><?php echo esc_html__('Upload the exported JSON file.', 'yoast-meta-export-import'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Import Meta Descriptions', 'yoast-meta-export-import'), 'primary', 'submit', false); ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function handle_export() {
        // Check permissions and nonce
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'yoast-meta-export-import'));
        }

        if (!isset($_POST['ymei_export_nonce']) || !wp_verify_nonce($_POST['ymei_export_nonce'], 'ymei_export_action')) {
            wp_die(esc_html__('Invalid nonce', 'yoast-meta-export-import'));
        }

        // Get all public post types
        $post_types = get_post_types(array('public' => true), 'names');

        $export_data = array();

        foreach ($post_types as $post_type) {
            $posts = get_posts(array(
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'post_status' => 'any'
            ));

            foreach ($posts as $post) {
                $meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);

                // Only include posts that have a meta description
                if (!empty($meta_desc)) {
                    $export_data[] = array(
                        'post_id' => $post->ID,
                        'post_type' => $post->post_type,
                        'post_title' => $post->post_title,
                        'post_slug' => $post->post_name,
                        'meta_description' => $meta_desc
                    );
                }
            }
        }

        // Create JSON
        $json_data = json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Send download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="yoast-meta-descriptions-' . date('Y-m-d-His') . '.json"');
        header('Content-Length: ' . strlen($json_data));
        echo $json_data;
        exit;
    }

    public function handle_import() {
        // Check permissions and nonce
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'yoast-meta-export-import'));
        }

        if (!isset($_POST['ymei_import_nonce']) || !wp_verify_nonce($_POST['ymei_import_nonce'], 'ymei_import_action')) {
            wp_die(esc_html__('Invalid nonce', 'yoast-meta-export-import'));
        }

        // Check if file was uploaded
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(add_query_arg(array(
                'page' => 'yoast-meta-export-import',
                'message' => 'error',
                'details' => 'upload_failed'
            ), admin_url('tools.php')));
            exit;
        }

        // Read and parse JSON
        $json_content = file_get_contents($_FILES['import_file']['tmp_name']);
        $import_data = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_redirect(add_query_arg(array(
                'page' => 'yoast-meta-export-import',
                'message' => 'error',
                'details' => 'invalid_json'
            ), admin_url('tools.php')));
            exit;
        }

        $updated_count = 0;
        $not_found_count = 0;
        $not_found_slugs = array();

        foreach ($import_data as $item) {
            // Find post by slug and post type
            $posts = get_posts(array(
                'name' => $item['post_slug'],
                'post_type' => $item['post_type'],
                'posts_per_page' => 1,
                'post_status' => 'any'
            ));

            if (!empty($posts)) {
                $post = $posts[0];
                update_post_meta($post->ID, '_yoast_wpseo_metadesc', $item['meta_description']);
                $updated_count++;
            } else {
                $not_found_count++;
                $not_found_slugs[] = $item['post_slug'] . ' (' . $item['post_type'] . ')';
            }
        }

        // Store results in transient for display
        set_transient('ymei_import_results', array(
            'updated' => $updated_count,
            'not_found' => $not_found_count,
            'not_found_slugs' => $not_found_slugs
        ), 60);

        wp_redirect(add_query_arg(array(
            'page' => 'yoast-meta-export-import',
            'message' => 'success'
        ), admin_url('tools.php')));
        exit;
    }
}

// Initialize plugin
new Yoast_Meta_Export_Import();

// Display admin notices
add_action('admin_notices', function() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'yoast-meta-export-import') {
        return;
    }

    $error_messages = array(
        'upload_failed' => __('Upload failed. Please try again.', 'yoast-meta-export-import'),
        'invalid_json' => __('Invalid JSON file.', 'yoast-meta-export-import'),
        'unknown' => __('Unknown error.', 'yoast-meta-export-import'),
    );

    if (isset($_GET['message'])) {
        if ($_GET['message'] === 'success') {
            $results = get_transient('ymei_import_results');
            if ($results) {
                echo '<div class="notice notice-success is-dismissible" role="status" aria-live="polite">';
                echo '<p><strong>' . esc_html__('Import successful!', 'yoast-meta-export-import') . '</strong></p>';
                echo '<p>' . sprintf(
                    esc_html__('Updated: %d items', 'yoast-meta-export-import'),
                    absint($results['updated'])
                ) . '</p>';
                if (!empty($results['not_found'])) {
                    echo '<p>' . sprintf(
                        esc_html__('Not found: %d items', 'yoast-meta-export-import'),
                        absint($results['not_found'])
                    ) . '</p>';
                    echo '<details><summary>' . esc_html__('View not found items', 'yoast-meta-export-import') . '</summary><ul>';
                    foreach ((array) $results['not_found_slugs'] as $slug) {
                        echo '<li>' . esc_html($slug) . '</li>';
                    }
                    echo '</ul></details>';
                }
                echo '</div>';
                delete_transient('ymei_import_results');
            }
        } elseif ($_GET['message'] === 'error') {
            $details = isset($_GET['details']) ? $_GET['details'] : 'unknown';
            $details_key = is_string($details) ? sanitize_key($details) : 'unknown';
            $message = isset($error_messages[$details_key]) ? $error_messages[$details_key] : $error_messages['unknown'];
            echo '<div class="notice notice-error is-dismissible" role="alert" aria-live="assertive">';
            echo '<p><strong>' . esc_html__('Import failed:', 'yoast-meta-export-import') . '</strong> ' . esc_html($message) . '</p>';
            echo '</div>';
        }
    }
});