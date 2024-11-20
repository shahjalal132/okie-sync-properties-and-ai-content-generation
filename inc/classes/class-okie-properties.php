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

        register_rest_route( 'okie/v1', '/get-hash-key', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_hash_value' ],
            'permission_callback' => '__return_true',
        ] );

    }

    public function get_properties() {

        $hash = get_option( 'okie_api_hash_key' ) ?? '';

        $property_list = [
            [
                'state' => 'NSW',
            ],
            [
                'state' => 'VIC',
            ],
            [
                'state' => 'QLD',
            ],
            [
                'state' => 'SA',
            ],
            [
                'state' => 'WA',
            ],
            [
                'state' => 'TAS',
            ],
            [
                'state' => 'ACT',
            ],
            [
                'state' => 'NT',
            ],
        ];

        $all_properties = [];

        try {
            $startFetch = time();
            foreach ( $property_list as $property ) {

                $state = $property['state'];

                // fetch properties from api
                $response = $this->fetch_properties_from_api_via_state( $hash, $state );

                if ( is_wp_error( $response ) ) {
                    return $response;
                }

                $data = json_decode( $response, true );

                $first_properties       = $data['pageProps']['results']['hits'] ?? [];
                $inspections_properties = $data['pageProps']['inspectionResults']['hits'] ?? [];
                $_all_properties        = $data['pageProps']['allResultsForMap'][0]['hits'] ?? [];

                $properties     = array_merge( $first_properties, $inspections_properties, $_all_properties );
                $all_properties = array_merge( $all_properties, $properties );
            }

            if ( !empty( $all_properties ) ) {
                $startDb       = time();
                $insert_result = $this->insert_properties_to_database( $all_properties );

                if ( is_wp_error( $insert_result ) ) {
                    return $insert_result;
                }

                return [
                    'status'     => 'success',
                    'message'    => 'Properties fetched and inserted to the database successfully!',
                    'db_took'    => time() - $startDb . ' seconds',
                    'fetch_took' => time() - $startFetch . ' seconds',
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

    public function get_hash_value() {
        // Initialize cURL
        $curl = curl_init();
        curl_setopt_array( $curl, array(
            CURLOPT_URL            => 'https://www.housinghub.org.au/search-results?latitude=-33.8688197&longitude=151.2092955&location_string=Sydney%20NSW%2C%20Australia',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
        ) );

        // Execute the cURL request and get the response
        $response = curl_exec( $curl );

        // Close the cURL session
        curl_close( $curl );

        // Check if the response is valid
        if ( $response === false ) {
            return 'Failed to fetch data.';
        }

        // Extract the key from the response
        $pattern = '/<link[^>]+href="\/_next\/static\/([^\/]+)\/pages\/_app\.module\.js"/';

        if ( preg_match( $pattern, $response, $matches ) ) {
            // Return the captured key
            $key = $matches[1];
            update_option( 'okie_api_hash_key', $key );

            return [
                'status'  => 'success',
                'key'     => $key,
                'message' => 'Key extracted successfully.',
            ];
        }

        // Return a message if the key is not found
        return [
            'status'  => 'error',
            'message' => 'Key not found in response.',
        ];
    }

    public function insert_properties_to_database( $properties ) {

        global $wpdb;
        $table_name = $wpdb->prefix . 'sync_properties';

        $wpdb->query( 'START TRANSACTION' ); // Begin transaction
        // truncate table
        $wpdb->query( "TRUNCATE TABLE $table_name" );

        try {
            foreach ( $properties as $property ) {

                $property_id       = $property['objectID'];
                $long_desc         = $property['propertyDescriptionLong'] ?? '';
                $short_desc        = $property['propertyDescriptionShort'] ?? '';
                $short_id          = $property['shortId'] ?? '';
                $provider_short_id = $property['providerShortId'] ?? '';

                // get website url
                $website_url = sprintf( "https://www.housinghub.org.au/property-detail/%s/%s", $provider_short_id, $short_id );

                $property_data = json_encode( $property );

                /* $sql = $wpdb->prepare(
                    "INSERT INTO $table_name (property_id, long_description, short_description, short_id, provider_short_id, website_url, property_data)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE 
                        long_description = VALUES(long_description), 
                        short_description = VALUES(short_description), 
                        property_data = VALUES(property_data)",
                    $property_id,
                    $long_desc,
                    $short_desc,
                    $short_id,
                    $provider_short_id,
                    $website_url,
                    $property_data,
                    $long_desc,
                    $short_desc,
                    $property_data
                );

                if ( false === $wpdb->query( $sql ) ) {
                    throw new \Exception( $wpdb->last_error );
                } */

                $wpdb->insert(
                    $table_name,
                    [
                        'property_id'       => $property_id,
                        'long_description'  => $long_desc,
                        'short_description' => $short_desc,
                        'short_id'          => $short_id,
                        'provider_short_id' => $provider_short_id,
                        'website_url'       => $website_url,
                        'property_data'     => $property_data,
                    ]
                );
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

    public function fetch_properties_from_api_via_state( $hash, $state ) {

        $url = sprintf(
            "https://www.housinghub.org.au/_next/data/%s/search-results.json?state=%s&checkboxRent=true&sort=posted_desc",
            urlencode( $hash ),
            urlencode( $state )
        );

        $curl = curl_init();
        curl_setopt_array( $curl, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
        ) );

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
