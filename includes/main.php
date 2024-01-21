<?php

function add_2good_product_schema() {
    if (is_product()) {
        $product_id = get_the_ID();
        $product_reviews = get_woocommerce_product_reviews($product_id);
        $schema_markup = get_schema_markup($product_reviews, $product_id);

        if (wp_script_is('jquery', 'enqueued')) {
            echo wp_get_inline_script_tag($schema_markup, [
                'type' => 'application/ld+json',
                'class' => '2GOOD-product-schema-plugin',
            ]);
        }
    }
}

add_action('wp_head', 'add_2good_product_schema', 1);

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


function get_schema_markup($product_reviews, $product_id) {
    $productName = get_the_title();
    $productUrl = get_permalink();
    $brandName = get_post_meta($product_id, 'brand', true);
    $aggregateRating = calculate_aggregate_rating($product_reviews);

    $productCurr = get_woocommerce_currency($product_id);
    $productPrice = wc_get_product_price($product_id);
    $priceValidUnt = gmdate('Y-m-d', time()) . ' 23:59:59';
    //If u want to use it just put an if statement, woo/wp should retun
    //taxable or nontaxable string values for this ... we don't need it in our usecase so its up to you dear Developer
    
    //$productValueAddedTaxIncluded = get_post_meta($product_id, '_tax_status', true); // 

    $productMpn = get_post_meta($product_id, 'mpn', true); // Un-comment this line to enable mpn if u need it  

    $size = array(); // Handle multiple colors
    $colors = array(); // Handle multiple colors
    $sku = get_post_meta($product_id, '_sku', true);
    $weight = get_post_meta($product_id, '_weight', true);

    $product_variations = array();
    $product = wc_get_product($product_id);

    if ($product && $product->is_type('variable')) {
        // Get the variations only if the product is a variable product
        try {
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
    } // Un-comment this block to enable colors

    $availability = get_post_meta($product_id, 'availability', true);

    if (empty($availability)) {
        $availability = parse_stocks_def(get_post_meta($product_id, '_stock_status', true));
    }
  
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

    $product_images = get_product_images($product_id, $productUrl);
   
    // Build the schema properties dynamically based on properties with values
    $schema_properties = array(
        '@context' => 'https://schema.org/',
        '@type' => 'Product',
        '@id' => esc_url($productUrl) . '/#Product',
        'url' => esc_url($productUrl),
        'name' => $productName,
        'sku' => $sku,
        'description' => $fixed_description,
        'offers' => array_filter(array(
            '@type' => 'Offer',
            'availability' => 'https://schema.org/' . $availability,
            'price' => $productPrice,
            'priceCurrency' => $productCurr,
            'url' => esc_url($productUrl),
            'priceValidUntil' => $priceValidUnt,
        )),
        'priceSpecification' => array_filter(array(
            '@type' => 'priceSpecification',
            'valueAddedTaxIncluded' => true// Might need to implement this yourself if u have diff products that are not taxable .. see var 'productValueAddedTaxIncluded' above.
        )),
        'seller' => array_filter(array(
            '@type' => 'Organization', 
            'name' => get_bloginfo( 'name' ) // Edit in here and use yours
        )),
        'mpn' => $productMpn, // Un-comment this line to enable mpn if u need it  
        'brand' => array_filter(array(
            '@type' => 'Brand',
            'name' => $brandName
        )),
        'color' => implode(', ', $colors), // Un-comment this line to enable colors
        'size' => implode(', ', $size),
        'weight' => array_filter(array(
            '@type' => $weight ? 'QuantitativeValue' : null,
            'value' => $weight ? $weight : null,
        )),
        'aggregateRating' => array_filter(array(
            '@type' => 'AggregateRating',
            'ratingValue' => $aggregateRating['ratingValue'],
            'reviewCount' => $aggregateRating['reviewCount'],
        )),
        'review' => $product_reviews,
        'image' => $product_images,
    );

    // Filter out properties with empty values
    $schema_properties = array_filter($schema_properties);

    // Convert the filtered properties to JSON 
    // Add JSON_PRETTY_PRINT for better debugging :P
    $schema_markup = json_encode($schema_properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $schema_markup;
}


function calculate_aggregate_rating($product_reviews) {
    $totalRating = 0;
    $totalReviews = count($product_reviews);

    foreach ($product_reviews as $review) {
       
        $totalRating += $review['reviewRating']['ratingValue'];
    }

    $aggregateRating = array(
        'ratingValue' => ($totalReviews > 0) ? round($totalRating / $totalReviews, 1) : 0,
        'reviewCount' => $totalReviews,
    );

    return $aggregateRating;
}

function get_product_images($product_id, $curr_url) {
    $images = array();

    $attachment_ids = $product_id ? wc_get_product($product_id)->get_gallery_image_ids() : array();
   
    $primary_image_id = get_post_thumbnail_id($product_id);
    $primary_image_url = wp_get_attachment_url($primary_image_id);
    
    if (!empty($attachment_ids)) {

        foreach ($attachment_ids as $attachment_id) {
            
            $attachment = wp_get_attachment_image_src($attachment_id, 'full');
            if ($attachment) {
                $image = array(
                    '@type' => 'ImageObject',
                    'url' => esc_url($attachment[0]),
                    'width' => $attachment[1],
                    'height' => $attachment[2],
                );
                $images[] = array_filter($image);
            }
        }
    }

    if ($primary_image_url && !empty($images)) {
        $images[0] = array_merge(['@type' => 'ImageObject', '@id' => $curr_url . '#primaryimage'], $images[0]);
    }

    return $images;
}

function wc_get_product_price( $product_id ) {
    return ( $product = wc_get_product( $product_id ) ) ? $product->get_price() : false;
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
