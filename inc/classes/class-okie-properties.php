<?php

namespace BOILERPLATE\Inc;

use BOILERPLATE\Inc\Traits\Program_Logs;
use BOILERPLATE\Inc\Traits\Singleton;

class Okie_Properties {

    use Singleton;
    use Program_Logs;

    private $apiSecretKey;
    private $apiEndpoint;
    private $limit;

    public function __construct() {
        $this->setup_hooks();
    }

    public function setup_hooks() {
        // Register REST API action
        add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );


        // get api secret key and endpoint
        $this->apiEndpoint  = get_option( 'okie_chatgpt_api_endpoint' );
        $this->apiSecretKey = get_option( 'okie_chatgpt_api_secret_key' );
        $this->limit        = get_option( 'option1', 1 );
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

        register_rest_route( 'okie/v1', '/generate-description', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'generate_description' ],
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
        // $wpdb->query( "TRUNCATE TABLE $table_name" );

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

                $sql = $wpdb->prepare(
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
                    $property_data
                );

                if ( false === $wpdb->query( $sql ) ) {
                    throw new \Exception( $wpdb->last_error );
                }

                /* $wpdb->insert(
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
                ); */
            }

            $wpdb->query( 'COMMIT' ); // Commit transaction
            return true;
        } catch (\Exception $e) {
            $wpdb->query( 'ROLLBACK' ); // Rollback transaction on error
            $this->put_program_logs( $e->getMessage() );
            return new \WP_Error( 'db_insert_error', 'Error inserting properties into the database.', [ 'status' => 500 ] );
        }
    }

    public function generate_description() {

        global $wpdb;
        $csv_table        = $wpdb->prefix . 'sync_csv_file_data';
        $properties_table = $wpdb->prefix . 'sync_properties';

        $sql = "
            SELECT wscfd.id, wsp.property_id, wscfd.website_url, wsp.long_description, wsp.short_description
            FROM {$csv_table} AS wscfd
            INNER JOIN {$properties_table} AS wsp
            ON wscfd.website_url = wsp.website_url
            WHERE wscfd.status = 'pending'
            LIMIT {$this->limit}
        ";

        $results = $wpdb->get_results( $sql );

        if ( empty( $results ) ) {
            $this->put_program_logs( 'No pending records found.' );
            return 'No pending records to process.';
        }

        foreach ( $results as $result ) {
            try {
                $csv_row_id      = $result->id;
                $property_row_id = $result->property_id;
                $website_url     = $result->website_url;
                $long_desc       = $result->long_description;
                $this->put_program_logs( 'Old description: ' . $long_desc );

                // Generate a new description
                $new_description = $this->regenerate_description_via_chatgpt( $long_desc );

                if ( strpos( $new_description, 'Error:' ) === 0 || strpos( $new_description, 'API Error:' ) === 0 ) {
                    throw new \Exception( 'Failed to regenerate description: ' . $new_description );
                }

                $this->put_program_logs( 'New description: ' . $new_description );

                // Update description in properties table
                if ( !$this->update_description_in_database_in_properties_table( $property_row_id, $new_description ) ) {
                    throw new \Exception( 'Failed to update description in properties table for property ID: ' . $property_row_id );
                }

                // Update status in CSV table
                if ( !$this->update_status_in_database_in_csv_table( $csv_row_id, 'updated' ) ) {
                    throw new \Exception( 'Failed to update status in CSV table for CSV ID: ' . $csv_row_id );
                }

            } catch (\Exception $e) {
                $this->put_program_logs( 'Error: ' . $e->getMessage() );
                return 'An error occurred: ' . $e->getMessage();
            }
        }

        return 'Description regeneration completed successfully.';
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

    public function regenerate_description_via_chatgpt( $old_description ) {
        $tone = 'formal';

        // Create the headers for the request
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiSecretKey,
        ];

        // Create the payload for the request
        $data = [
            'model'       => 'gpt-4',
            'messages'    => [
                [ 'role' => 'system', 'content' => "You are an assistant that rewrites text in a {$tone} tone." ],
                [ 'role' => 'user', 'content' => "Please rewrite the following text:\n\n{$old_description}" ],
            ],
            'temperature' => 0.7,
        ];

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $this->apiEndpoint );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );

        $response = curl_exec( $ch );

        if ( curl_errno( $ch ) ) {
            $error_message = curl_error( $ch );
            curl_close( $ch );
            return 'Error: ' . $error_message;
        }

        curl_close( $ch );
        $responseData = json_decode( $response, true );

        if ( isset( $responseData['choices'][0]['message']['content'] ) ) {
            return $responseData['choices'][0]['message']['content'];
        }

        $errorMessage = $responseData['error']['message'] ?? 'Unknown error occurred.';
        return 'API Error: ' . $errorMessage;
    }

    public function update_description_in_database_in_properties_table( $property_row_id, $new_description ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sync_properties';

        $result = $wpdb->update(
            $table_name,
            [ 'long_description' => $new_description, 'status' => 'updated' ],
            [ 'property_id' => $property_row_id ]
        );

        if ( $result === false ) {
            $this->put_program_logs( 'Failed to update properties table: ' . $wpdb->last_error );
            return false;
        }

        return true;
    }

    public function update_status_in_database_in_csv_table( $csv_row_id, $new_status ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sync_csv_file_data';

        $result = $wpdb->update(
            $table_name,
            [ 'status' => $new_status ],
            [ 'id' => $csv_row_id ]
        );

        if ( $result === false ) {
            $this->put_program_logs( 'Failed to update CSV table: ' . $wpdb->last_error );
            return false;
        }

        return true;
    }
}
