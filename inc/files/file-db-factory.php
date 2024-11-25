<?php

use Illuminate\Database\Capsule\Manager as DB;

function get_posts_via_query_builder() {

    // Fetch all published posts
    $posts = DB::table( 'posts' )
        ->where( 'post_status', 'publish' )
        ->where( 'post_type', 'post' )
        ->get();

    foreach ( $posts as $post ) {
        echo $post->post_title . "\n";
    }
}

/**
 * Seed properties to database
 * @param array $properties
 */
function seed_properties_to_database( $properties ) {
    global $wpdb;

    try {
        // Begin transaction
        DB::connection()->beginTransaction();

        foreach ( $properties as $property ) {
            // Extract property data
            $name                 = $property['name'] ?? '';
            $property_id          = $property['objectID'];
            $location             = $property['sdaLocation'] ?? '';
            $building_type        = $property['sdaBuildingType'] ?? '';
            $number_of_bath_rooms = $property['numberOfBathrooms'] ?? 0;
            $number_of_bed_rooms  = $property['numberOfBedrooms'] ?? 0;
            $number_of_rooms      = $number_of_bath_rooms + $number_of_bed_rooms;
            $property_price       = $property['propertyPrice'] ?? '';
            $sda_design_category  = $property['sdaDesignCategory'][0] ?? '';
            $booked_status        = $property['status'] ?? '';
            $long_desc            = $property['propertyDescriptionLong'] ?? '';
            $short_id             = $property['shortId'] ?? '';
            $provider_short_id    = $property['providerShortId'] ?? '';
            $website_url          = sprintf(
                "https://www.housinghub.org.au/property-detail/%s/%s",
                $provider_short_id,
                $short_id
            );

            $image_urls = $property['imageUrls'] ?? [];
            $image_urls = json_encode( $image_urls );

            $property_data = json_encode( $property );

            // Normalize data encoding
            /* $values = [
                'name'                => mb_convert_encoding( $name, 'UTF-8', 'auto' ),
                'property_id'         => mb_convert_encoding( $property_id, 'UTF-8', 'auto' ),
                'location'            => mb_convert_encoding( $location, 'UTF-8', 'auto' ),
                'building_type'       => mb_convert_encoding( $building_type, 'UTF-8', 'auto' ),
                'number_of_rooms'     => $number_of_rooms, // Integer doesn't need encoding
                'max_price_per_room'  => mb_convert_encoding( $property_price, 'UTF-8', 'auto' ),
                'sda_design_category' => mb_convert_encoding( $sda_design_category, 'UTF-8', 'auto' ),
                'booked_status'       => mb_convert_encoding( $booked_status, 'UTF-8', 'auto' ),
                'long_description'    => mb_convert_encoding( $long_desc, 'UTF-8', 'auto' ),
                'website_url'         => mb_convert_encoding( $website_url, 'UTF-8', 'auto' ),
                'image_urls'          => mb_convert_encoding( $image_urls, 'UTF-8', 'auto' ),
                'property_data'       => mb_convert_encoding( $property_data, 'UTF-8', 'auto' ),
            ]; */

            $values = [
                'name'                => $name,
                'property_id'         => $property_id,
                'location'            => $location,
                'building_type'       => $building_type,
                'number_of_rooms'     => $number_of_rooms,
                'max_price_per_room'  => $property_price,
                'sda_design_category' => $sda_design_category,
                'booked_status'       => $booked_status,
                'long_description'    => $long_desc,
                'website_url'         => $website_url,
                'image_urls'          => $image_urls,
                'property_data'       => $property_data,
            ];

            $unique_by = [ 'property_id', 'website_url' ];

            $update_columns = [ 'property_data' ];

            // Perform upsert
            DB::table( 'sync_properties' )->upsert( $values, $unique_by, $update_columns );
        }

        // Commit transaction
        DB::connection()->commit();

    } catch (Exception $e) {
        // Rollback transaction in case of an error
        DB::connection()->rollBack();

        // Log the error or handle it appropriately
        put_program_logs( "Database transaction failed: " . $e->getMessage() );
        throw $e; // Rethrow the exception if needed
    }
}