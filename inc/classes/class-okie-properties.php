<?php

namespace BOILERPLATE\Inc;

use BOILERPLATE\Inc\Traits\Program_Logs;
use BOILERPLATE\Inc\Traits\Singleton;

class Okie_Properties {

    use Singleton;
    use Program_Logs;

    public function __construct() {
        $this->setup_hooks();
    }

    public function setup_hooks() {
        // Register REST API action
        add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );
    }

    public function register_rest_route() {
        register_rest_route( 'okie/v1', '/get-properties', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_properties' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function get_properties() {
        
        $hash            = "oVAhQ9P5GoMLlJLF6mHhE";
        $latitude        = "-33.8688197";
        $longitude       = "151.2092955";
        $location_string = "Sydney NSW, Australia";

        try {
            $response = $this->fetch_properties_from_api( $hash, $latitude, $longitude, $location_string );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $data = json_decode( $response, true );

            if ( json_last_error() !== JSON_ERROR_NONE ) {
                return new \WP_Error( 'json_decode_error', 'Error decoding API response.', [ 'status' => 500 ] );
            }

            $properties = $data['pageProps']['results']['hits'] ?? [];

            if ( !empty( $properties ) && is_array( $properties ) ) {
                $insert_result = $this->insert_properties_to_database( $properties );

                if ( is_wp_error( $insert_result ) ) {
                    return $insert_result;
                }

                return [
                    'status'  => 'success',
                    'message' => 'Properties fetched and saved successfully!',
                ];
            } else {
                return [
                    'status'  => 'success',
                    'message' => 'No properties found to save.',
                ];
            }
        } catch (\Exception $e) {
            $this->put_program_logs( $e->getMessage() );
            return new \WP_Error( 'unexpected_error', 'An unexpected error occurred! Please try again later.', [ 'status' => 500 ] );
        }
    }

    public function insert_properties_to_database( $properties ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sync_properties';

        $wpdb->query( 'START TRANSACTION' ); // Begin transaction

        try {
            foreach ( $properties as $property ) {
                $proper_id     = $property['objectID'];
                $long_desc     = $property['propertyDescriptionLong'] ?? '';
                $short_desc    = $property['propertyDescriptionShort'] ?? '';
                $property_data = json_encode( $property );
                $status        = 'pending';

                $sql = $wpdb->prepare(
                    "INSERT INTO $table_name (property_id, long_description, short_description, property_data, status) 
                    VALUES (%s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE 
                        long_description = VALUES(long_description), 
                        short_description = VALUES(short_description), 
                        property_data = VALUES(property_data), 
                        status = VALUES(status)",
                    $proper_id,
                    $long_desc,
                    $short_desc,
                    $property_data,
                    $status
                );

                if ( false === $wpdb->query( $sql ) ) {
                    throw new \Exception( $wpdb->last_error );
                }
            }

            $wpdb->query( 'COMMIT' ); // Commit transaction
            return true;
        } catch (\Exception $e) {
            $wpdb->query( 'ROLLBACK' ); // Rollback transaction on error
            $this->put_program_logs( $e->getMessage() );
            return new \WP_Error( 'db_insert_error', 'Error inserting properties into the database.', [ 'status' => 500 ] );
        }
    }

    public function fetch_properties_from_api( $hash, $latitude, $longitude, $location_string ) {
        $url = sprintf(
            "https://www.housinghub.org.au/_next/data/%s/search-results.json?latitude=%s&longitude=%s&location_string=%s&checkboxRent=true",
            urlencode( $hash ),
            urlencode( $latitude ),
            urlencode( $longitude ),
            urlencode( $location_string )
        );

        $curl = curl_init();

        curl_setopt_array( $curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
        ] );

        $response = curl_exec( $curl );

        if ( curl_errno( $curl ) ) {
            $error_message = curl_error( $curl );
            curl_close( $curl );
            return new \WP_Error( 'curl_error', "cURL error: $error_message", [ 'status' => 500 ] );
        }

        $http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
        curl_close( $curl );

        if ( $http_code !== 200 ) {
            return new \WP_Error( 'api_http_error', "API returned HTTP code $http_code", [ 'status' => $http_code ] );
        }

        return $response;
    }
}
