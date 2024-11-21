<?php

namespace BOILERPLATE\Inc;

use BOILERPLATE\Inc\Traits\Program_Logs;
use BOILERPLATE\Inc\Traits\Singleton;

class Admin_Sub_Menu {

    use Singleton;
    use Program_Logs;

    public function __construct() {
        $this->setup_hooks();
    }

    public function setup_hooks() {
        add_action( 'admin_menu', [ $this, 'register_admin_sub_menu' ] );
        add_filter( 'plugin_action_links_' . PLUGIN_BASE_NAME, [ $this, 'add_plugin_action_links' ] );

        // save api credentials
        add_action( 'wp_ajax_save_credentials', [ $this, 'save_api_credentials' ] );
        add_action( 'wp_ajax_save_options', [ $this, 'save_options' ] );
        add_action( 'wp_ajax_fetch_properties', [ $this, 'fetch_properties' ] );
        add_action( 'wp_ajax_generate_hash', [ $this, 'generate_hash' ] );
        add_action( 'wp_ajax_generate_hash', [ $this, 'generate_description' ] );
        add_action( 'wp_ajax_upload_csv', [ $this, 'handle_csv_upload' ] );
    }

    public function save_api_credentials() {

        $api_url = sanitize_text_field( $_POST['api_url'] );
        $api_key = sanitize_text_field( $_POST['api_key'] );

        if ( empty( $api_url ) || empty( $api_key ) ) {
            wp_send_json_error( 'An error occurred! Please fill all the fields.' );
        }

        update_option( 'okie_chatgpt_api_endpoint', $api_url );
        update_option( 'okie_chatgpt_api_secret_key', $api_key );

        wp_send_json_success( 'Credentials saved successfully!' );
        die();
    }

    public function save_options() {

        $option1 = sanitize_text_field( $_POST['option1'] );
        $option2 = sanitize_text_field( $_POST['option2'] );

        /* if ( empty( $option1 ) || empty( $option2 ) ) {
            wp_send_json_error( 'An error occurred! Please fill all the fields.' );
        } */

        update_option( 'option1', $option1 );
        update_option( 'option2', $option2 );

        wp_send_json_success( 'Options saved successfully!' );
        die();
    }

    function add_plugin_action_links( $links ) {
        $settings_link = '<a href="admin.php?page=okie-settings">' . __( 'Settings', 'okie' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function register_admin_sub_menu() {
        add_submenu_page(
            'options-general.php',
            'Okie Settings',
            'Okie Settings',
            'manage_options',
            'okie-settings',
            [ $this, 'menu_callback_html' ],
        );
    }

    public function menu_callback_html() {
        include_once PLUGIN_BASE_PATH . '/templates/template-admin-sub-menu.php';
    }

    public function generate_hash() {

        // Define the URL for the API endpoint
        $url = site_url() . '/wp-json/okie/v1/get-hash-key';

        // Fetch response from the endpoint
        $response = wp_remote_get( $url, [
            'timeout' => 300,
        ] );

        // Check if the request resulted in an error
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            wp_send_json_error( [
                'message' => 'Failed to generate hash.',
                'error'   => $error_message,
            ] );
            return; // Stop further execution
        }

        // check if status code is not 200 return error
        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            $error_message = wp_remote_retrieve_response_message( $response );
            wp_send_json_error( [
                'message' => 'Failed to generate hash.',
                'error'   => $error_message,
            ] );
            return; // Stop further execution
        }

        // Send success response
        wp_send_json_success( [
            'message' => 'Hash generated successfully!',
        ] );
        return;
    }

    public function generate_description() {

        // Define the URL for the API endpoint
        $url = site_url() . '/wp-json/okie/v1/generate-description';

        // Fetch response from the endpoint
        $response = wp_remote_get( $url, [
            'timeout' => 300,
        ] );

        // Check if the request resulted in an error
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            wp_send_json_error( [
                'message' => 'Failed to generate description.',
                'error'   => $error_message,
            ] );
            return; // Stop further execution
        }

        // check if status code is not 200 return error
        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            $error_message = wp_remote_retrieve_response_message( $response );
            wp_send_json_error( [
                'message' => 'Failed to generate description.',
                'error'   => $error_message,
            ] );
            return; // Stop further execution
        }

        // Send success response
        wp_send_json_success( [
            'message' => 'Description generated successfully!',
        ] );
        return;
    }

    public function fetch_properties() {
        // Define the URL for the API endpoint
        $url = site_url() . '/wp-json/okie/v1/get-properties';

        // Fetch response from the endpoint
        $response = wp_remote_get( $url, [
            'timeout' => 300,
        ] );

        // Check if the request resulted in an error
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            wp_send_json_error( [
                'message' => 'Failed to fetch properties.',
                'error'   => $error_message,
            ] );
            return; // Stop further execution
        }

        // Retrieve the response body
        $body = wp_remote_retrieve_body( $response );

        // Check if the body is empty or invalid
        if ( empty( $body ) ) {
            wp_send_json_error( [
                'message' => 'No data received from the server.',
            ] );
            return; // Stop further execution
        }

        // Decode the JSON response to validate its structure
        $data = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( [
                'message' => 'Invalid JSON response from the server.',
                'error'   => json_last_error_msg(),
            ] );
            return; // Stop further execution
        }

        // Optionally log the response (uncomment if needed)
        // $this->put_program_logs($data);

        // Send success response
        wp_send_json_success( [
            'message' => 'Properties fetched successfully!',
            'data'    => $data,
        ] );
        return;
    }

    public function handle_csv_upload() {

        // Check if a file was uploaded
        if ( !isset( $_FILES['csv_file'] ) || empty( $_FILES['csv_file']['tmp_name'] ) ) {
            wp_send_json_error( 'No file uploaded.' );
            // $this->put_program_logs( 'No file uploaded.' );
        }

        $file = $_FILES['csv_file'];

        // Validate the file type
        $file_type = wp_check_filetype( $file['name'] );
        if ( $file_type['ext'] !== 'csv' ) {
            wp_send_json_error( 'Invalid file format. Only CSV files are allowed.' );
        }

        // Open the file and process its contents
        if ( ( $handle = fopen( $file['tmp_name'], 'r' ) ) !== false ) {

            global $wpdb;
            $table_name = $wpdb->prefix . 'sync_csv_file_data';

            // Read the CSV line by line and insert into the database
            $row_count = 0;
            while ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
                // Skip empty rows
                if ( empty( $data[0] ) ) {
                    continue;
                }

                $website_url = sanitize_text_field( $data[12] );

                $wpdb->insert(
                    $table_name,
                    [
                        'website_url' => $website_url,
                        'status'      => 'pending',
                    ],
                    [ '%s' ]
                );

                $row_count++;
            }

            fclose( $handle );

            wp_send_json_success( "Successfully imported $row_count rows." );
        } else {
            wp_send_json_error( 'Unable to open the uploaded file.' );
        }

        wp_die(); // Always include this in AJAX handlers to properly terminate execution
    }

}