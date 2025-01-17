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
    private $title_rewrite_instruction;
    private $short_description_rewrite_instruction;
    private $description_rewrite_instruction;

    public function __construct() {
        $this->setup_hooks();
    }

    public function setup_hooks() {
        // Register REST API action
        add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );


        // get api secret key and endpoint
        $this->apiEndpoint                           = get_option( 'okie_chatgpt_api_endpoint' );
        $this->apiSecretKey                          = get_option( 'okie_chatgpt_api_secret_key' );
        $this->limit                                 = get_option( 'option1', 1 );
        $this->title_rewrite_instruction             = get_option( 'option2', '[Number of Bedrooms] Bedroom Home | [Location] | [Type of Home] | Okie — Extract the number of bedrooms and location from the description. use "SDA," "MTA," "STA," "ILO," or "SIL" for type if mentioned, "(Non SDA)" if stated as not registered, or "General Listing" if unspecified. always end with "Okie"' );
        $this->short_description_rewrite_instruction = get_option( 'option4', '"Discover [Number of Bedrooms] Bedroom [Type of Home] in [Location]. Features include [Key Features]. Contact Okie to find your ideal home today!" — Ensure 120-160 characters, include bedrooms, type (e.g., SDA, MTA, STA, SIL, ILO, or (Non SDA)), location, 1-2 key features, and a CTA for engagement.' );
        $this->description_rewrite_instruction       = get_option( 'option5', '' );
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

        register_rest_route( 'okie/v1', '/rewrite-contents', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rewrite_contents_callback' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'okie/v1', '/sync-properties', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'sync_properties' ],
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

                // $this->put_program_logs( 'Total properties fetched: ' . count( $all_properties ) );
                $startDb = time();

                // insert properties to database
                seed_properties_to_database( $all_properties );

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

        // get hash from laravel application start
        $_url      = "https://arandomdomain.space/public/get-hash-key";
        $_response = wp_remote_get( $_url );
        // get body from response
        $_key = wp_remote_retrieve_body( $_response );

        $return_message = '';
        if ( !empty( $_key ) ) {
            update_option( 'okie_api_hash_key', $_key );
            $return_message = "Hash key Found $_key";
        } else {
            $return_message = "Hash key not found";
        }

        return [
            'status'  => 'success',
            'message' => $return_message,
        ];
        // get hash from laravel application end

        // get hash key from direct api process start
        /* $curl = curl_init();
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
        ]; */
        // get hash key from direct api process end
    }

    public function rewrite_contents_callback() {

        global $wpdb;
        $properties_table = $wpdb->prefix . 'sync_properties';

        // Prepare the SQL query
        $sql = "
            SELECT wsp.id, wsp.property_id, wsp.name, wsp.long_description, wsp.short_description, wsp.property_data
            FROM {$properties_table} wsp WHERE wsp.status = 'pending'
            LIMIT {$this->limit}
        ";

        // Execute the SQL query
        $results = $wpdb->get_results( $sql );

        // Check if there are any results
        if ( empty( $results ) ) {
            $this->put_program_logs( 'No pending records found.' );
            return 'No pending records to process.';
        }

        $error_count = 0;

        foreach ( $results as $result ) {
            try {
                // Get values
                $row_id = $result->id;
                // $this->put_program_logs( 'row id: ' . $row_id );
                $property_row_id = $result->property_id;
                $title           = $result->name ?? '';
                // $this->put_program_logs( 'old title: ' . $title );
                // $long_desc = $result->long_description ?? '';
                // $this->put_program_logs( 'old description: ' . $long_desc );
                $short_desc = $result->short_description ?? '';
                // $this->put_program_logs( 'old short description: ' . $short_desc );
                $property_data = $result->property_data ?? '';

                // Decode property data
                $propertys_data = json_decode( $property_data, true );
                $long_desc      = $propertys_data['propertyDescriptionLong'] ?? '';
                // put_program_logs( 'old long description: ' . $long_desc );

                // Generate title
                $new_title = $this->rewrite_content_via_chatgpt( $this->title_rewrite_instruction, $property_data );
                if ( strpos( $new_title, 'Error:' ) === 0 ) {
                    throw new \Exception( "Failed to generate title for property ID: {$property_row_id}" );
                }
                // $this->put_program_logs( 'new title: ' . $new_title );


                // Generate short description
                $new_short_desc = $this->rewrite_content_via_chatgpt( $this->short_description_rewrite_instruction, $property_data );
                if ( strpos( $new_short_desc, 'Error:' ) === 0 ) {
                    throw new \Exception( "Failed to generate short description for property ID: {$property_row_id}" );
                }
                // $this->put_program_logs( 'new short description: ' . $new_short_desc );

                // Generate a new description
                $new_description = $this->rewrite_content_via_chatgpt( $this->description_rewrite_instruction, $long_desc );
                if ( strpos( $new_description, 'Error:' ) === 0 ) {
                    throw new \Exception( "Failed to generate description for property ID: {$property_row_id}" );
                }
                // $this->put_program_logs( 'new description: ' . $new_description );

                // Prepare new contents
                $new_contents = [
                    'title'             => $new_title,
                    'short_description' => $new_short_desc,
                    'description'       => $new_description,
                ];

                // Update new contents in properties table
                if ( !$this->update_new_contents_in_properties_table( $row_id, $new_contents ) ) {
                    throw new \Exception( "Failed to update contents in properties table for property ID: {$property_row_id}" );
                }

            } catch (\Exception $e) {
                $error_count++;
                $this->put_program_logs( 'Error: ' . $e->getMessage() );
            }
        }

        $status_message = $error_count > 0
            ? "Process completed with {$error_count} errors."
            : 'Description regeneration completed successfully.';

        return $status_message;
    }

    public function fetch_properties_from_api_via_state( $hash, $state ) {

        // url for direct request to api
        /* $url = sprintf(
            "https://www.housinghub.org.au/_next/data/%s/search-results.json?state=%s&checkboxRent=true&sort=posted_desc",
            urlencode( $hash ),
            urlencode( $state )
        ); */

        // url for request to laravel application
        $url = sprintf( "https://arandomdomain.space/public/get-properties/%s/%s", $hash, $state );

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
        // $this->put_program_logs( 'get properties response: ' . $response );

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

    public function rewrite_content_via_chatgpt( string $user_instruction = '', string $old_contents ) {

        $tone = 'formal';
        // Create the headers for the request
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiSecretKey,
        ];

        // Set the user_instruction
        $user_instruction = $user_instruction ?? "Please rewrite the following text";

        // Create the payload for the request
        $data = [
            'model'       => 'gpt-4o',
            'messages'    => [
                [ 'role' => 'system', 'content' => "You are an assistant that rewrites text in a {$tone} tone." ],
                [ 'role' => 'user', 'content' => "{$user_instruction}:\n\n{$old_contents}" ],
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

    public function update_new_contents_in_properties_table( int $row_id, array $new_contents ) {

        global $wpdb;
        $table_name = $wpdb->prefix . 'sync_properties';

        $result = $wpdb->update(
            $table_name,
            [
                'name'                         => $new_contents['title'],
                'long_description'             => $new_contents['description'],
                'short_description'            => $new_contents['short_description'],
                'status'                       => 'updated',
                'is_title_updated'             => 'yes',
                'is_short_description_updated' => 'yes',
            ],
            [ 'id' => $row_id ]
        );

        if ( $result === false ) {
            $this->put_program_logs( 'Failed to update properties table: ' . $wpdb->last_error );
            return $wpdb->last_error;
        }

        return true;
    }

    public function sync_properties() {

        global $wpdb;
        $table_name = $wpdb->prefix . 'sync_properties';

        // Prepare the query
        $sql = $wpdb->prepare( "SELECT * FROM $table_name WHERE status = 'updated' AND is_title_updated = 'yes' AND is_short_description_updated = 'yes' AND is_synced = 'no' LIMIT $this->limit" );

        // Execute the query
        $properties = $wpdb->get_results( $sql );

        if ( $wpdb->last_error ) {
            // put_program_logs( 'Database error: ' . $wpdb->last_error );
            return 'Error fetching properties from the database.';
        }

        if ( !empty( $properties ) && is_array( $properties ) ) {
            foreach ( $properties as $property ) {
                $this->insert_property_to_stay_post_type( $property );
            }
        } else {
            return 'No pending properties found for syncing.';
        }

        return 'Properties synchronized successfully.';
    }

    public function insert_property_to_stay_post_type( $property ) {

        global $wpdb;
        $table_name = $wpdb->prefix . 'sync_properties';

        try {
            $serial_id                   = $property->id;
            $property_id                 = $property->property_id ?? '';
            $title                       = $property->name ?? '';
            $description                 = $property->long_description ?? '';
            $short_description           = $property->short_description ?? '';
            $location                    = $property->location ?? '';
            $number_of_rooms             = $property->number_of_rooms ?? 0;
            $sda_design_category         = $property->sda_design_category ?? '';
            $booked_status               = $property->booked_status ?? '';
            $vacancy                     = $property->vacancy ?? 0;
            $has_fire_sprinklers         = $property->has_fire_sprinklers ?? '';
            $has_breakout_room           = $property->has_breakout_room ?? '';
            $onsite_overnight_assistance = $property->onsite_overnight_assistance ?? '';
            $email                       = $property->email ?? '';
            $phone                       = $property->phone ?? '';
            $image_urls                  = $property->image_urls ?? '';

            // put_program_logs( 'Image URLs: ' . $image_urls );
            $image_urls = json_decode( $image_urls, true );

            $property_data = $property->property_data ?? '';
            $property_data = json_decode( $property_data, true );

            $latitude  = $property_data['latitude'] ?? '';
            $longitude = $property_data['longitude'] ?? '';

            $beds                    = $property_data['numberOfBedrooms'] ?? 0;
            $bathrooms               = $property_data['numberOfBathrooms'] ?? 0;
            $parking                 = $property_data['parking'] ?? 0;
            $numberOfSdaResidents    = $property_data['numberOfSdaResidents'] ?? 0;
            $ooaAppointmentAndReview = $property_data['ooaAppointmentAndReview'] ?? '';
            $supportProvided         = $property_data['supportProvider'] ?? '';

            // property additional information
            $date_available_to_move_in        = $property_data['dateAvailableToMoveIn'] ?? '';
            $energy_efficiency_rating         = $property_data['energyEfficiencyRating'] ?? '';
            $gym                              = $property_data['gym'] == 'true' ? 'Gym' : null;
            $hasooa                           = $property_data['hasOoa'] == 'true' ? 'Hasooa' : null;
            $liftaccessible                   = $property_data['liftAccessible'] == 'true' ? 'Lift Accessible' : null;
            $listingid                        = $property_data['listingId'] ?? '';
            $mediumtermaccommodationfunding   = $property_data['mediumTermAccommodationFunding'] == 'true' ? 'Medium Term Accommodation Funding' : null;
            $numberofpeoplesharing            = $property_data['numberOfPeopleSharing'] ?? '';
            $outdoorarea                      = $property_data['outdoorArea'] == 'true' ? 'Outdoor Area' : null;
            $postcode                         = $property_data['postcode'] ?? '';
            $propertyprice                    = $property_data['propertyPrice'] ?? '';
            $property_type                    = $property_data['propertyType'] ?? '';
            $reasonablerentcontrassetbyndia   = $property_data['reasonableRentContrAsSetByNdia'] == 'true' ? 'Reasonable Rent Contrasset By NDIA' : null;
            $rumpusroom                       = $property_data['rumpusRoom'] == 'true' ? 'Rumpus Room' : null;
            $sdaamount                        = $property_data['sdaAmount'] ?? '';
            $sdalocation                      = $property_data['sdaLocation'] ?? '';
            $sayoverwhichsupportproviderisooa = $property_data['sayOverWhichSUpportProviderIsOoa'] == 'true' ? 'Say Over Which Support Provider Is Ooa' : null;
            $state                            = $property_data['state'] ?? '';
            $status                           = $property_data['status'] ?? '';
            $suburb                           = $property_data['suburb'] ?? '';
            $youdecideonyourowncoresupports   = $property_data['youDecideOnYourOwnCoreSupports'] == 'true' ? 'You Decide On Your Own Core Supports' : null;
            $publisheddate                    = date( 'Y-m-d H:i:s', strtotime( $property_data['publishedDate'] ?? '' ) );

            $expirydate                   = date( 'Y-m-d H:i:s', strtotime( $property_data['expiryDate'] ?? '' ) );
            $number_of_toilets            = $property_data['numberOfToilets'] ?? '';
            $shortid                      = $property_data['shortId'] ?? '';
            $listing_certified            = $property_data['listingCertified'] == 'true' ? 'Listing Certified' : null;
            $listinglevel                 = $property_data['listingLevel'] ?? '';
            $onsiteactiveovernightsupport = $property_data['onsiteActiveOvernightSupport'] == 'true' ? 'Onsite Active Overnight Support' : null;
            $wheelchairaccessibleparking  = $property_data['wheelchairAccessibleParking'] ?? '';
            $tagstatus                    = $property_data['tagStatus'] ?? '';
            $open_for_inspection          = $property_data['openForInspection'] == 'true' ? 'Open For Inspection' : null;
            $openforinspectiondate        = $property_data['openForInspectionDate'] ?? '';
            $inspectionregisterlink       = $property_data['inspectionRegisterLink'] ?? '';
            $propertypricepercentage      = $property_data['propertyPricePercentage'] ?? '';
            $tenantincomepercentage       = $property_data['tenantIncomePercentage'] == 'true' ? 'Tenant Income Percentage' : null;
            $providershortid              = $property_data['providerShortId'] ?? '';
            $support_provider_website     = $property_data['supportProviderWebsite'] ?? '';

            $max_price_per_room = $property->max_price_per_room ?? '';
            $website1           = $property->website1 ?? '';
            $website2           = $property->website2 ?? '';
            $website3           = $property->website3 ?? '';
            $website4           = $property->website4 ?? '';
            $website5           = $property->website5 ?? '';





            // Taxonomies data
            $data = [
                'accommodationLength'           => $property_data['accommodationLength'] ?? '',
                'propertyAvailableOptions'      => $property_data['propertyAvailableOptions'] ?? '',
                'place_sda_home'                => $property_data['propertySdaType'] ?? '',
                'place_sda_building_type'       => $property_data['sdaBuildingType'] ?? '',
                'place_gender_of_housemate'     => $property_data['genderOfHousemates'] ?? '',
                'place_looking_for_a_housemate' => $property_data['lookingForAHousemate'] ?? '',
                'place_who_can_live_here'       => $property_data['whoCanLiveHere'] ?? '',
                'place_age_of_housemates'       => $property_data['ageOfHousemates'] ?? '',
                'place_sda_design_category'     => $property_data['sdaDesignCategory'] ?? '',
                'place_support_provided'        => $property_data['supportProvided'] ?? '',
                'housefeatures'                 => array_filter( array(
                    $property_data['broadbandInternetAvailable'] == 'true' ? 'broadband-internet-available' : null,
                    $property_data['cooling'] == 'true' ? 'cooling' : null,
                    // $property_data['coolingSystem'] == 'true' ? 'cooling-system' : null,
                    // $property_data['detailsNotProvided'] == 'true' ? 'details-not-provided' : null,
                    $property_data['dishwasher'] == 'true' ? 'dishwasher' : null,
                    $property_data['ductedVacuumSystem'] == 'true' ? 'ducted-vacuum-system' : null,
                    $property_data['heating'] == 'true' ? 'heating' : null,
                    // $property_data['heatingSystem'] == 'true' ? 'heating-system' : null,
                    $property_data['energyEfficiencyRating'] == 'HIGH' ? 'high-energy-efficiency-rating' : null,
                    $property_data['energyEfficiencyRating'] == 'MEDIUM' ? 'medium-energy-efficiency-rating' : null,
                    $property_data['energyEfficiencyRating'] == 'LOW' ? 'low-energy-efficiency-rating' : null,
                    $property_data['payTvAccess'] == 'true' ? 'pay-tv-access' : null,
                    $property_data['solarHotWater'] == 'true' ? 'solar-hot-water' : null,
                    $property_data['solarPanels'] == 'true' ? 'solar-panels' : null,
                    $property_data['sprinklers'] == 'true' ? 'sprinklers' : null,
                ) ),
                'accessibilityandsafety'        => array_filter( array(
                    $property_data['accessibleFeatures'] == 'true' ? 'accessible-features' : null,
                    $property_data['ceilingHoist'] == 'true' ? 'ceiling-hoist' : null,
                    $property_data['commonwealthRentAssistanceAvailable'] == 'true' ? 'commonwealth-rent-assistance-available' : null,
                    // $property_data['DetailsNotProvided'] == 'true' ? 'details-not-provided' : null,
                    $property_data['doorwayWidthsGreaterThan1000mm'] == 'true' ? 'doorway-widths-greater-than-1m' : null,
                    $property_data['emergencyPowerBackup'] == 'true' ? 'emergency-power-backup' : null,
                    $property_data['onsiteOvernightAssistance'] == 'true' ? 'onsite-overnight-assistance' : null,
                    $property_data['petAllowed'] == 'true' ? 'pets-allowed' : null,
                    $property_data['wheelchairAccessible'] == 'true' ? 'wheelchair-accessible' : null,
                ) ),
                'livingspacefeatures'           => array_filter( array(
                    $property_data['alarmSystem'] == 'true' ? 'alarm-system' : null,
                    $property_data['automatedDoors'] == 'true' ? 'automated-doors' : null,
                    $property_data['builtInWardrobes'] == 'true' ? 'built-in-wardrobes' : null,
                    // $property_data['DetailsNotProvided'] == 'true' ? 'details-not-provided' : null,
                    $property_data['ensuite'] == 'true' ? 'ensuite' : null,
                    $property_data['fireSprinklers'] == 'true' ? 'fire-sprinklers' : null,
                    $property_data['floorboards'] == 'true' ? 'floorboards' : null,
                    $property_data['furnished'] == 'true' ? 'furnished' : null,
                    $property_data['intercom'] == 'true' ? 'intercom' : null,
                    // $property_data['strongAndRobustBuild'] == 'true' ? 'strong-robust-build' : null,
                    $property_data['strongAndRobustConstruction'] == 'true' ? 'strong-robustconstruction' : null,
                    $property_data['study'] == 'true' ? 'study' : null,
                ) ),
            ];

            // Transform input data
            $transformed_data = [];
            foreach ( $data as $key => $value ) {
                $transformed_data[$key] = transform_data( $value );
            }

            // Define taxonomy mappings
            $taxonomies_names = [
                'accommodationLength_tax_name'           => 'place_accommodation_length',
                'propertyAvailableOptions_tax_name'      => 'place_property_availability',
                'place_sda_home_tax_name'                => 'place_sda_home',
                'place_sda_building_type_tax_name'       => 'place_sda_building_type',
                'housefeatures_tax_name'                 => 'housefeatures',
                'accessibilityandsafety_tax_name'        => 'accessibilityandsafety',
                'livingspacefeatures_tax_name'           => 'livingspacefeatures',
                'place_gender_of_housemate_tax_name'     => 'place_gender_of_housemate',
                'place_looking_for_a_housemate_tax_name' => 'place_looking_for_a_housemate',
                'place_who_can_live_here_tax_name'       => 'place_who_can_live_here',
                'place_age_of_housemates_tax_name'       => 'place_age_of_housemates',
                'place_sda_design_category_tax_name'     => 'place_sda_design_category',
                'place_support_provided_tax_name'        => 'place_support_provided',
                'area_tax_name'                          => 'area',
            ];

            // return "Beds : $beds, Baths : $bathrooms, Parking : $parking, NumberOfSdaResidents : $numberOfSdaResidents";

            $additional_infos = [
                '_property_id'                        => $property_id,
                'beds'                                => $beds,
                'bathrooms'                           => $bathrooms,
                'parking'                             => $parking,
                'location'                            => array( 'address' => $location, 'map_picker' => '', 'latitude' => $latitude, 'longitude' => $longitude ),
                'location-2'                          => array(),
                'location-3'                          => array(),
                'location-4'                          => array(),
                'location-5'                          => array(),
                'location-6'                          => array(),
                'location-7'                          => array(),
                'location-8'                          => array(),
                'location-9'                          => array(),
                'location-10'                         => array(),
                'location-11'                         => array(),
                'rooms'                               => $number_of_rooms,
                'sda-residents'                       => $numberOfSdaResidents,
                'text-2'                              => $supportProvided,
                'text-3'                              => '',
                'text-4'                              => $ooaAppointmentAndReview,
                // Nearby places data
                'shopping'                            => '',
                'parks'                               => '',
                'medical_centre'                      => '',
                'hospital'                            => '',
                'train'                               => '',
                'bus'                                 => 1,
                'recreational'                        => '',
                'fast_food'                           => '',
                'descriptionarea'                     => '',
                'counciltext'                         => '',
                'councillink'                         => '',
                'wikitext'                            => '',
                'wikilink'                            => '',
                'lac_details'                         => '',
                // Property Additional information
                'pl_date_available_to_move_in'        => $date_available_to_move_in,
                'pl_energy_efficiency_rating'         => $energy_efficiency_rating,
                'pl_gym'                              => $gym,
                'pl_hasooa'                           => $hasooa,
                'pl_liftaccessible'                   => $liftaccessible,
                'pl_listingid'                        => $listingid,
                'pl_mediumtermaccommodationfunding'   => $mediumtermaccommodationfunding,
                'pl_numberofpeoplesharing'            => $numberofpeoplesharing,
                // 'pl_ooaappointmentandreview' => $ooaAppointmentAndReview,
                'pl_outdoorarea'                      => $outdoorarea,
                'pl_postcode'                         => $postcode,
                'pl_propertyprice'                    => $propertyprice,
                'pl_property_type'                    => $property_type,
                'pl_reasonablerentcontrassetbyndia'   => $reasonablerentcontrassetbyndia,
                'pl_rumpusroom'                       => $rumpusroom,
                'pl_sdaamount'                        => $sdaamount,
                'pl_sdalocation'                      => $sdalocation,
                'pl_sayoverwhichsupportproviderisooa' => $sayoverwhichsupportproviderisooa,
                'pl_state'                            => $state,
                'pl_status'                           => $status,
                'pl_suburb'                           => $suburb,
                'pl_youdecideonyourowncoresupports'   => $youdecideonyourowncoresupports,
                'pl_publisheddate'                    => $publisheddate,
                'pl_expirydate'                       => $expirydate,
                'pl_number_of_toilets'                => $number_of_toilets,
                'pl_shortid'                          => $shortid,
                'pl_listing_certified'                => $listing_certified,
                'pl_listinglevel'                     => $listinglevel,
                'pl_onsiteactiveovernightsupport'     => $onsiteactiveovernightsupport,
                'pl_wheelchairaccessibleparking'      => $wheelchairaccessibleparking,
                'pl_tagstatus'                        => $tagstatus,
                'pl_open_for_inspection'              => $open_for_inspection,
                'pl_openforinspectiondate'            => $openforinspectiondate,
                'pl_inspectionregisterlink'           => $inspectionregisterlink,
                'pl_propertypricepercentage'          => $propertypricepercentage,
                'pl_tenantincomepercentage'           => $tenantincomepercentage,
                'pl_providershortid'                  => $providershortid,
                'pl_max_price_per_room'               => $max_price_per_room,
                'pl_vacancy'                          => $vacancy,
                'pl_has_fire_sprintklers'             => $has_fire_sprinklers,
                'pl_has_breakout_room'                => $has_breakout_room,
                'pl_email'                            => $email,
                'pl_phone'                            => $phone,
                'pl_support_provider_website'         => $support_provider_website,
                'pl_website1'                         => $website1,
                'pl_website2'                         => $website2,
                'pl_website3'                         => $website3,
                'pl_website4'                         => $website4,
                'pl_website5'                         => $website5,
                'pl_short_description'                => $short_description,
                'pl_longitude'                        => $longitude,
                'pl_latitude'                         => $latitude,
                'pl_location'                         => $location,


            ];

            // post data
            $post_data = array(
                'post_title'   => sanitize_text_field( $title ),
                'post_content' => $description,
                'post_status'  => 'publish',
                'post_type'    => 'place',
            );

            // Check if the property already exists
            $args = array(
                'post_type'  => 'place',
                'meta_query' => array(
                    array(
                        'key'     => '_property_id',
                        'value'   => $property_id,
                        'compare' => '=',
                    ),
                ),
            );

            // Check if the product already exists
            $existing_post = new \WP_Query( $args );

            if ( $existing_post->have_posts() ) {

                // Update the existing post
                $existing_post_id = $existing_post->posts[0]->ID;

                wp_update_post( [
                    'ID'           => $existing_post_id,
                    'post_title'   => sanitize_text_field( $title ),
                    'post_content' => wp_kses_post( $description ),
                ] );

                $post_id = $existing_post_id;

                // set additional information's
                foreach ( $additional_infos as $key => $value ) {
                    update_post_meta( $post_id, sanitize_key( $key ), sanitize_text_field( $value ) );
                }


                // update is synced status
                $wpdb->update(
                    $table_name,
                    array( 'is_synced' => 'yes' ),
                    array( 'id' => $serial_id ),
                    array( '%s' ), // Data format for is_synced
                    array( '%d' )  // Data format for serial_id
                );

            } else {

                // Insert a new post
                $post_data = [
                    'post_title'   => sanitize_text_field( $title ),
                    'post_content' => wp_kses_post( $description ),
                    'post_status'  => 'publish',
                    'post_type'    => 'place',
                ];

                $post_id = wp_insert_post( $post_data );

                // set product images with unique image names
                set_property_gallery_images( $post_id, $image_urls );

                // set additional information's
                foreach ( $additional_infos as $key => $value ) {
                    update_post_meta( $post_id, sanitize_key( $key ), sanitize_text_field( $value ) );
                }

                if ( is_wp_error( $post_id ) ) {
                    throw new \Exception( 'Failed to insert post: ' . $post_id->get_error_message() );
                }

                // Fetch term IDs for taxonomies
                $terms_ids = match_terms_and_get_ids( $transformed_data, $taxonomies_names );

                // Assign terms to the post
                assign_terms_to_post( $post_id, $terms_ids, $taxonomies_names );

                // update is synced status
                $wpdb->update(
                    $table_name,
                    array( 'is_synced' => 'yes' ),
                    array( 'id' => $serial_id ),
                    array( '%s' ), // Data format for is_synced
                    array( '%d' )  // Data format for serial_id
                );
            }

        } catch (\Exception $e) {
            put_program_logs( 'Error inserting property: ' . $e->getMessage() );
            return $e->getMessage();
        }

    }

}