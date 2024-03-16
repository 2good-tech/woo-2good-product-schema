<?php

function add_2good_product_schema($product = null) {
    if (is_product()) {
		if ( ! is_object( $product ) ) {
			global $product;
		}

		if ( ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

        $product_id = $product->get_id();
        $product_reviews = get_woocommerce_product_reviews($product_id, $product);
        $schema_markup = get_schema_markup($product_reviews, $product_id, $product);

        if (wp_script_is('jquery', 'enqueued')) {
            echo wp_get_inline_script_tag($schema_markup, [
                'type' => 'application/ld+json',
                'class' => '2GOOD-product-schema-plugin',
            ]);
        }
    }
}

add_action('wp_head', 'add_2good_product_schema', 10);

function get_schema_markup($product_reviews, $product_id, $product) {

    $freeShippingThreshold = 120; // Set your free shipping threshold here
    $freeShipping = false;
    
	//get custom fields with fallbacks
	$brandNameField = get_post_meta($product_id, 'brand', true);
	$brandName = $brandNameField ? $brandNameField : get_bloginfo( 'name' ); //Brand fallback to sitename
	
	$availabilityField = get_post_meta($product_id, 'availability', true);
	$availability = $availabilityField ? $availabilityField : parse_stocks_def($product->get_stock_status());

	$productMpn = get_post_meta($product_id, 'mpn', true); // Un-comment this line to enable mpn if u need it  

	$productName = get_the_title();
    $productUrl = get_permalink();
	$currency  = get_woocommerce_currency();
    
    //Get Aggregate Rating or fallback to 5/1
	if ( $product->get_rating_count() && wc_review_ratings_enabled() ) {
		$aggregateRating = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => $product->get_average_rating(),
			'reviewCount' => $product->get_review_count(),
		);
	} else {
		$aggregateRating = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => '5.00',
			'reviewCount' => 1,
		);
	}

	if ( '' !== $product->get_price() ) {
		// Assume prices will be valid for a month, unless on sale and there is an end date.
		$price_valid_until = gmdate('Y-m-d', time() + MONTH_IN_SECONDS) . ' 23:59:59';

		if ( $product->is_type( 'variable' ) ) {
			$price = $product->get_variation_price( 'min', true );
			
			//Get end sale date for variations' lowest priced item
			$variation_ids = $product->get_visible_children();
			foreach( $variation_ids as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation->is_on_sale() && $lowest === wc_format_decimal($variation->get_sale_price(), wc_get_price_decimals()) && $variation->get_date_on_sale_to() ) {
					$price_valid_until = gmdate( 'Y-m-d', $variation->get_date_on_sale_to()->getTimestamp() );
				}
			}
		
        } else {

            $price = $product->get_price();
            //Get end sale date for simple product
			if ( $product->is_on_sale() && $product->get_date_on_sale_to() ) {
				$price_valid_until = gmdate( 'Y-m-d', $product->get_date_on_sale_to()->getTimestamp() );
			}

        }
        //Create offer
        $schema_offer = array(
            '@type'             => 'Offer',
            'url'               => $productUrl,
            'price'             => wc_format_decimal( $price, wc_get_price_decimals() ),
            'priceCurrency'     => $currency,
            'priceValidUntil'   => $price_valid_until,
            'priceSpecification'    => array(
                'price'                 => wc_format_decimal( $price, wc_get_price_decimals() ),
                'priceCurrency'         => $currency,
                'valueAddedTaxIncluded' => wc_prices_include_tax() ? true : false,
            ),
            'availability'  => 'http://schema.org/' . $availability,
			'itemCondition' => 'https://schema.org/NewCondition',
            'seller'        => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url(),
			),
        );
		
        // Check if the product is eligible for free shipping and set the shippingDetails property
        if ($price >= $freeShippingThreshold) {
            $freeShipping = true;
        }
        // Set the shipping details
        $schema_offer += array(
            "shippingDetails"   => array(
                array( // Domestic shipping details
                    "@type"         => "OfferShippingDetails",
                    '@id'           => esc_url($productUrl) . '/#DomesticShipping',
                    "shippingRate"  => array(
                        "@type"     => "MonetaryAmount",
                        "value"     => $freeShipping? 0 : 5.00, //edit accordingly
                        "currency"  => $currency,
                        //"freeShippingThreshold" => $freeShippingThreshold
                    ),
                    "shippingDestination"   => array(
                        "@type"     => "DefinedRegion",
                        "addressCountry"    => "BG"
                    ),
                    "deliveryTime"  => array( //edit accordingly
                        "@type" => "ShippingDeliveryTime",
                        "handlingTime"  => array(
                            "@type"   => "QuantitativeValue",
                            "minValue"    => 0,
                            "maxValue"    => 2,
                            "unitCode"    => "DAY"
                        ),
                        "transitTime"   => array(
                            "@type"   => "QuantitativeValue",
                            "minValue"    => 1,
                            "maxValue"    => 5,
                            "unitCode"    => "DAY"
                        )
                    )
                ),
                array( // International shipping details
                    "@type" => "OfferShippingDetails",
                    '@id' => esc_url($productUrl) . '/#InternationalShipping',
                    "shippingRate"  => array(
                        "@type"     => "MonetaryAmount",
                        "value"     => 15.00, //edit accordingly
                        "currency"  => $currency
                    ),
                    "shippingDestination"   => array(
                        "@type"     => "DefinedRegion",
                        "addressCountry"    => array("GR", "RO")
                    ),
                    "deliveryTime"  => array( //edit accordingly
                        "@type" => "ShippingDeliveryTime",
                        "handlingTime"  => array(
                            "@type"   => "QuantitativeValue",
                            "minValue"    => 0,
                            "maxValue"    => 2,
                            "unitCode"    => "DAY"
                        ),
                        "transitTime"   => array(
                            "@type"   => "QuantitativeValue",
                            "minValue"    => 2,
                            "maxValue"    => 7,
                            "unitCode"    => "DAY"
                        )
                    )
                )
            ),
        );

        // Set the return policy edit accordingly
        $countries_obj = new WC_Countries();
        $shipping_countries = $countries_obj->get_shipping_countries();
        $countryCodes = array_keys($shipping_countries);

        $schema_offer += array(
            "hasMerchantReturnPolicy" => array(
                "@type" => "MerchantReturnPolicy",
                "applicableCountry" => $countryCodes,
                "returnPolicyCategory"  => "https://schema.org/MerchantReturnFiniteReturnWindow",
                "merchantReturnDays"    => 14,
                "inStoreReturnsOffered" => "https://schema.org/True",
                "returnMethod"  => "https://schema.org/ReturnByMail",
                "returnFees"    => "https://schema.org/ReturnFeesCustomerResponsibility"
            ),
        );

	}

    //$size = array(); // Handle multiple colors
    //$colors = array(); // Handle multiple colors
    $sku = $product->get_sku() ? $product->get_sku() : $product_id; // Declare SKU or fallback to ID.
    $weight = $product->get_weight();

    /*if ($product && $product->is_type('variable')) {
        // Get the variations only if the product is a variable product
        try {
			$product_variations = array();
            $product_variations = wc_get_product($product_id)->get_available_variations();
            if (!empty($product_variations)) {
                // Loop through variations to get color information
                foreach ($product_variations as $variation) {

                    if (isset($variation['attributes']['attribute_pa_color'])) {
                        $colors[] = $variation['attributes']['attribute_pa_color'];
                    }

                    if (isset($variation['attributes']['attribute_pa_size'])) {
                        $size[] = $variation['attributes']['attribute_pa_size'];
                        
                    }
                }
            }
        } catch (Exception $e) {
            // Handle the exception, you can log it or take appropriate action
            error_log('Error getting product variations: ' . $e->getMessage());
        }

        if (!is_array($product_variations)) {
            // If variations couldn't be retrieved, handle it gracefully
            $product_variations = array();
        }
    }*/ // Un-comment this block to enable colors

	// Get description from Yoast if exists
	if(class_exists('WPSEO_Meta') && class_exists('WPSEO_Replace_Vars')){

		$string =  WPSEO_Meta::get_value( 'metadesc', $product_id );
		
		if ($string !== '') {
			$replacer = new WPSEO_Replace_Vars();
			$fixed_description = $replacer->replace( $string, get_post($product_id) );
		} else {
			$description = html_entity_decode(get_the_excerpt());
			$fixed_description = preg_replace("/<[^>]*>/", "", $description); // Remove the html tags if there are any 
		}

	}    

    // Build the schema properties dynamically based on properties with values
    $schema_properties = array(
        '@context' => 'https://schema.org/',
        '@type' => 'Product',
        '@id' => esc_url($productUrl) . '/#Product',
        'url' => esc_url($productUrl),
        'image' => wp_get_attachment_url( $product->get_image_id() ),
        'name' => $productName,
        'sku' => $sku,
        'description' => $fixed_description ? $fixed_description : wp_strip_all_tags( do_shortcode( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ) ),
 		'offers' => array_filter($schema_offer),
        'mpn' => $productMpn, // Un-comment this line to enable mpn if u need it  
        'brand' => array_filter(array(
            '@type' => 'Brand',
            'name' => $brandName
        )),
        //'color' => implode(', ', $colors), // Un-comment this line to enable colors
        //'size' => implode(', ', $size), // Un-comment this line to enable size
        'weight' => array_filter(array(
            '@type' => $weight ? 'QuantitativeValue' : null,
            'value' => $weight ? $weight : null,
        )),
		'aggregateRating' => $aggregateRating,
        'review' => $product_reviews,
    );

    // Filter out properties with empty values
    $schema_properties = array_filter($schema_properties);

    // Convert the filtered properties to JSON 
    // Add JSON_PRETTY_PRINT for better debugging :P
    $schema_markup = json_encode($schema_properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $schema_markup;

}

function parse_stocks_def($stock_string) {
    // The $stock_string will be matched and converted to either one of the following:
    /* 
        BackOrder
        Discontinued
        InStock
        InStoreOnly
        LimitedAvailability
        OnlineOnly
        OutOfStock
        PreOrder
        PreSale
        SoldOut
    */
    $mapping = array(
        'backorder' => 'BackOrder',
        'onbackorder' => 'BackOrder', // I'm cheating again... I know :)
        'discontinued' => 'Discontinued',
        'instock' => 'InStock',
        'instoreonly' => 'InStoreOnly',
        'limitedavailability' => 'LimitedAvailability',
        'onlineonly' => 'OnlineOnly',
        'outofstock' => 'OutOfStock',
        'preorder' => 'PreOrder',
        'presale' => 'PreSale',
        'soldout' => 'SoldOut',
    );

    // Convert to lowercase and check if it exists in the mapping
    $lowercase_stock = strtolower($stock_string);
    if (array_key_exists($lowercase_stock, $mapping)) {
        return $mapping[$lowercase_stock];
    }

    // If not found, return the original string
    return $stock_string;
}

function get_woocommerce_product_reviews($product_id) {
    $reviews = [];
    $args = [
        'status' => 'approve',
        'type' => 'review',
        'post_id' => $product_id,
    ];

    $comments = get_comments($args);

    foreach ($comments as $comment) {
        $rating = (int)get_comment_meta($comment->comment_ID, 'rating', true);
        $max_rating = 5; // Change it if u have diff metrics in place
        $min_rating = 1; // Change it if u have diff metrics in place
        $comment_content = str_replace("\r\n", "", $comment->comment_content);

        $review_data = array(
            '@type'        => 'Review',
            'author'       => array('@type' => 'Person', 'name' => $comment->comment_author),
            'datePublished' => $comment->comment_date,
            'description' => $comment_content,
            'reviewRating' => array('@type' => 'Rating', 'bestRating' => $max_rating, 'ratingValue' => $rating, 'worstRating' => $min_rating),
        );

        $reviews[] = $review_data;
    }

    return $reviews;
}
