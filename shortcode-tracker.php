<?php
/**
 * Plugin Name:       Shortcode Tracker
 * Plugin URI:        https://github.com/shubham110019/shortcode-tracker
 * Description:       Track, find, and manage all shortcodes across your WordPress site in a user-friendly table.
 * Version:           0.1
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       shortcode-tracker
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// Enqueue AJAX script
function sf_enqueue_scripts() {
    wp_enqueue_script( 'sf-ajax-script', plugin_dir_url( __FILE__ ) . 'ajax-script.js', array('jquery'), null, true );
    wp_localize_script( 'sf-ajax-script', 'sf_ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );

     // Enqueue admin styles
     wp_enqueue_style( 'wp-shortcodes-finder', plugin_dir_url( __FILE__ ) . '/css/wp-shortcodes-finder.css' );
}
add_action( 'admin_enqueue_scripts', 'sf_enqueue_scripts' );

// Add admin menu for the Shortcode Tracker under Tools
function sf_add_admin_menu() {
    add_management_page(
        'Shortcode Tracker', 
        'Shortcode Tracker', 
        'manage_options', 
        'wp-shortcodes-finder', 
        'sf_display_admin_page'
    );
}
add_action( 'admin_menu', 'sf_add_admin_menu' );



// Add "Settings" and "Deactivate" links on the Plugins page
function sf_plugin_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'tools.php?page=wp-shortcodes-finder' ) . '">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'sf_plugin_action_links' );

// Display the admin page content
function sf_display_admin_page() {
    ?>

<div class="wp_shorcode_top_header">
<h1>Shortcode Tracker</h1>
</div>

     <div class="wrap">
       
        <div class="wp_shorcode_form">
        <form id="sf_shortcode_form" method="POST" action="" class="sf-form">
            <?php sf_display_shortcode_options(); ?>
            <?php sf_display_post_type_options(); ?>
            <?php sf_display_post_status_options(); ?>
            <input type="submit" name="sf_find_shortcode" class="wp_shorcode_btn" value="Find Shortcode">
        </form>
      

</div>

<div id="sf_loading" class="wp_shortcode_loading" style="display: none;"></div>

        <div id="sf_results" class="wp_shortcode_sf-results"></div>
    </div>
    <?php
}

// Display available post status in a dropdown
function sf_display_post_status_options() {
    $post_statuses = array(
        '' => '-- All Statuses --',
        'publish' => 'Published',
        'draft' => 'Draft',
        'private' => 'Private',
        'trash' => 'Trash',
    );

    echo '<div class="wp_shortcode_form-group">';
    echo '<label for="sf_post_status">Select Post Status:</label>';
    echo '<select name="sf_post_status" id="sf_post_status">';
    foreach ($post_statuses as $value => $label) {
        echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
}

// Display available shortcodes in a dropdown
function sf_display_shortcode_options() {
    global $shortcode_tags;

    echo '<div class="wp_shortcode_form-group">';
    echo '<label for="sf_shortcode">Select a Shortcode:</label>';
    echo '<select name="sf_shortcode" id="sf_shortcode" required>';
    echo '<option value="">-- Select Shortcode --</option>';

    // List all registered shortcodes
    foreach ( $shortcode_tags as $tag => $callback ) {
        echo '<option value="' . esc_attr( $tag ) . '">' . esc_html( $tag ) . '</option>';
    }
    echo '</select>';
    echo '</div>';
}

// Display available post types in a dropdown
function sf_display_post_type_options() {
    echo '<div class="wp_shortcode_form-group">';
    echo '<label for="sf_post_type">Select Post Type:</label>';
    echo '<select name="sf_post_type" id="sf_post_type">';
    echo '<option value="">-- All Post Types --</option>';

    // List all public post types
    $post_types = get_post_types(array('public' => true), 'objects');
    foreach ($post_types as $post_type) {
        echo '<option value="' . esc_attr($post_type->name) . '">' . esc_html($post_type->label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
}

// Handle AJAX request to get shortcode usage
function sf_ajax_shortcode_usage() {
    // Check for required parameters
    if (!isset($_POST['shortcode']) || empty($_POST['shortcode'])) {
        wp_send_json_error('No shortcode provided.');
        return;
    }

    $shortcode = sanitize_text_field($_POST['shortcode']);
    $post_type = sanitize_text_field($_POST['posttype']);
    $post_status = sanitize_text_field($_POST['poststatus']); // Get the selected post status

    global $wpdb;

    // Log received shortcode, post_type, and post_status for debugging
    error_log("Received shortcode: $shortcode");
    error_log("Received post_type: $post_type");
    error_log("Received post_status: $post_status");

    // Base query with dynamic post type and post status filter
    $query = "
        SELECT ID, post_title, post_content, post_type, post_status
        FROM {$wpdb->posts}
        WHERE post_status IN ('publish', 'draft', 'private', 'trash')
    ";

    // Add post type filter if specified
    if (!empty($post_type)) {
        $query .= $wpdb->prepare(" AND post_type = %s", $post_type);
    }

    // Add post status filter if specified
    if (!empty($post_status)) {
        $query .= $wpdb->prepare(" AND post_status = %s", $post_status);
    }

    // Fetch results
    $results = $wpdb->get_results($query);

    ob_start(); // Start output buffering

    echo "<div class='wp_shortcode_resultbox'>";

    echo '<h2>Shortcode Usage: [' . esc_html($shortcode) . ']</h2>';

    $shortcode_count = 0;

    if (!empty($results)) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>#</th><th>Type</th><th>Title</th><th>Shortcode Usage</th><th>Status</th><th>Actions</th></tr></thead>';
        echo '<tbody>';

        $index = 1; // Initialize the counter for the index number

        foreach ($results as $post) {
            // Only include posts containing the selected shortcode
            if (has_shortcode($post->post_content, $shortcode)) {
                $shortcode_count++;
                $post_type_label = ucfirst($post->post_type);

                // Map post status to readable label
                $status_label = match ($post->post_status) {
                    'publish' => 'Published',
                    'draft' => 'Draft',
                    'private' => 'Private',
                    'trash' => 'Trash',
                    default => ucfirst($post->post_status),
                };

                echo '<tr>';
                echo '<td>' . $index . '</td>'; // Add the index number
                echo '<td>' . esc_html($post_type_label) . '</td>';
                echo '<td><a href="' . get_permalink($post->ID) . '" target="_blank">' . esc_html($post->post_title) . '</a></td>';
                echo '<td>' . sf_extract_shortcode_data($shortcode, $post->post_content) . '</td>';
                echo '<td>' . esc_html($status_label) . '</td>';
                echo '<td><a href="' . get_permalink($post->ID) . '" target="_blank" class="button">View</a></td>';
                echo '</tr>';

                $index++; // Increment the index for each row
            }
        }

        echo '</tbody></table>';
    } else {
        echo '<p>No posts or pages found with this shortcode.</p>';
    }

    // Display the count of posts found
    echo '<p>Total posts found using the shortcode: <strong>' . esc_html($shortcode_count) . '</strong></p>';
    echo "<div>";

    $output = ob_get_clean();
    wp_send_json_success($output);
}

// Extract the actual shortcode usage from post content
function sf_extract_shortcode_data( $shortcode, $content ) {
    preg_match_all( '/' . get_shortcode_regex( array( $shortcode ) ) . '/', $content, $matches );

    if ( !empty( $matches[0] ) ) {
        return esc_html( $matches[0][0] );
    }

    return 'Not found';
}

// Register AJAX actions
add_action('wp_ajax_sf_get_shortcode_usage', 'sf_ajax_shortcode_usage');
add_action('wp_ajax_nopriv_sf_get_shortcode_usage', 'sf_ajax_shortcode_usage'); // Optional, if needed for non-logged-in users
