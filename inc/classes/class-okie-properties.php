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

        $latitude        = "-33.8688197";
        $longitude       = "151.2092955";
        $location_string = "Sydney NSW, Australia";

        try {
            $response = $this->fetch_properties_from_api( $latitude, $longitude, $location_string );

            if ( is_wp_error( $response ) ) {
                return new \WP_Error( 'api_fetch_error', 'Failed to fetch properties from API.', [ 'status' => 500 ] );
            }

            $data       = json_decode( $response, true );
            $properties = $data['pageProps']['results']['hits'];

            // Return success message with data
            return [
                'status'  => 'success',
                'message' => 'Properties fetched successfully!',
            ];
        } catch (\Exception $e) {
            // Log exception and return error response
            // $this->put_program_logs( $e->getMessage() );
            return [
                'status'  => 'error',
                'message' => 'An error occurred! Please try again.',
            ];
        }
    }

    public function fetch_properties_from_api( $latitude, $longitude, $location_string ) {

        $url = sprintf(
            "https://www.housinghub.org.au/_next/data/OEYJiWSM7MIEi5m9OATBw/search-results.json?latitude=%s&longitude=%s&location_string=%s&checkboxRent=true",
            $latitude,
            $longitude,
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
