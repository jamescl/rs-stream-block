<?php
/**
 * Plugin Name: RS Stream Block
 * Description: A custom block for embedding a video using Video.js.
 * Version: 1.0.0
 * Author: Rune Soup
 */

function rs_stream_block_enqueue_assets() {
    // Enqueue Video.js style
    wp_enqueue_style('videojs', 'https://cdnjs.cloudflare.com/ajax/libs/video.js/7.10.2/video-js.min.css');

    // Enqueue custom CSS
    wp_enqueue_style('rs-stream-custom', plugin_dir_url(__FILE__) . 'rs-stream-block.css');

    // Enqueue Video.js script
    wp_enqueue_script('videojs', 'https://cdnjs.cloudflare.com/ajax/libs/video.js/7.10.2/video.min.js', array(), '7.10.2', false);

    // Enqueue Video.js script
	    wp_enqueue_script('rs-stream-block-editor', plugins_url('block.js', __FILE__),array('wp-blocks', 'wp-element', 'wp-editor'));
	}
add_action('enqueue_block_assets', 'rs_stream_block_enqueue_assets');

function rs_stream_block_register_block() {
    // Register the block editor script
    wp_register_script(
        'rs-stream-block-editor',
        plugins_url('block.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-data')
    );

    // Register the server-side rendering script
    register_block_type('rs-stream/video-block', array(
        'attributes' => array(
            'videoId' => array(
                'type' => 'string',
                'default' => '',
            ),
        ),
        'editor_script' => 'rs-stream-block-editor',
        'render_callback' => 'rs_stream_block_render_callback',
    ));
}
add_action('init', 'rs_stream_block_register_block');

function rs_stream_block_render_callback($attributes) {
    $video_id = $attributes['videoId'];

    // Generate the token
    $api_token = get_option('cloudflare_api_token');
    $account_id = get_option('cloudflare_account_id');
    $url = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/stream/{$video_id}/token";
    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_token,
        ),
    );

    $response = wp_remote_post($url, $args);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($body['result']['token'])) {
        // Log the error and return an error message
        error_log('Error generating token: ' . print_r($body, true));
        return '<p>Error generating video token.</p>';
    }

    $token = $body['result']['token'];
    $video_url = "https://customer-ihh9ye7nukyrzsh7.cloudflarestream.com/{$token}/manifest/video.m3u8";

    ob_start();
    ?>
    <video
        id="rs-stream-video-<?php echo esc_attr($video_id); ?>"
        controls preload="none"
        class="video-js vjs-default-skin rs-stream"
        data-setup='{"fluid": true, "controlBar": { "pictureInPictureToggle": false }}'
    >
        <source src="<?php echo esc_url($video_url); ?>" type="application/x-mpegURL" />
    </video>
    <?php
    return ob_get_clean();
}

function rs_stream_block_generate_token_ajax() {
    error_log(print_r($_POST, true)); // Add this line to log $_POST data

    if (isset($_POST['videoId'])) {
        $attributes = array('videoId' => sanitize_text_field($_POST['videoId']));
        $video_html = rs_stream_block_render_callback($attributes);
        wp_send_json_success(array('video_html' => $video_html));
    } else {
        wp_send_json_error(array('message' => 'Video ID not provided.'));
    }
}

function rs_stream_block_register_rest_route() {
    register_rest_route('rs-stream/v1', '/generate-token/', array(
        'methods' => 'POST',
        'callback' => 'rs_stream_block_generate_token_ajax',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));
}

add_action('rest_api_init', 'rs_stream_block_register_rest_route');