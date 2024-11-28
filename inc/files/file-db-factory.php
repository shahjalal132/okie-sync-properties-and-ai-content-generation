<?php

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Seed properties to database
 * @param array $properties
 */
function seed_properties_to_database( $properties ) {

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
            $short_desc           = $property['propertyDescriptionShort'] ?? '';
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
                'short_description'   => $short_desc,
                'website_url'         => $website_url,
                'image_urls'          => $image_urls,
                'property_data'       => $property_data,
            ];

            $unique_by = [ 'website_url', 'property_id' ];

            $update_columns = [ 'property_id', 'image_urls', 'property_data' ];

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

function seed_properties_to_database_from_csv( $values, $unique_by, $update_columns ) {
    try {
        // Begin transaction
        DB::connection()->beginTransaction();

        // Perform upsert
        DB::table( 'sync_properties' )->upsert( $values, $unique_by, $update_columns );

        // Commit transaction
        DB::connection()->commit();
    } catch (Exception $e) {
        // Rollback transaction in case of an error
        DB::connection()->rollBack();

        // Log the error or handle it appropriately
        put_program_logs( "Database operation failed: " . $e->getMessage() );

        // Optionally rethrow the exception
        throw $e;
    }
}
