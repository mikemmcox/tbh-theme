<?php
/**
 * Theme functions and definitions.
 *
 * For additional information on potential customization options,
 * read the developers' documentation:
 *
 * https://developers.elementor.com/docs/hello-elementor-theme/
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0' );

/**
 * Load child theme scripts & styles.
 *
 * @return void
 */
function hello_elementor_child_scripts_styles() {

	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[
			'hello-elementor-theme-style',
		],
		HELLO_ELEMENTOR_CHILD_VERSION
	);

}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20 );

add_action( 'elementor/theme/register_conditions', function( $conditions_manager ) {
    class All_Subcategories_Archive extends ElementorPro\Modules\ThemeBuilder\Conditions\Taxonomy {
        private $taxonomy;

        public function get_name() {
            return 'all_child_of_' . $this->taxonomy->name;
        }

        public function get_label() {
            return sprintf( __( 'All Subcategories Of', 'elementor-pro' ), $this->taxonomy->labels->singular_name );
        }

        public function __construct( $data ) {
            parent::__construct( $data );
            $this->taxonomy = $data['object'];
        }

        public function is_term() {
            $taxonomy = $this->taxonomy->name;
            $current = get_queried_object();
            return ( $current && isset( $current->taxonomy ) && $taxonomy === $current->taxonomy );
        }

        public function check( $args ) {
            $id = (int) $args['id'];
            /**
             * @var \WP_Term $current
             */
            $current = get_queried_object();
            if ( ! $this->is_term() || 0 === $current->parent ) {
                return false;
            }

            while ( $current->parent > 0 ) {
                if ( $id === $current->parent ) {
                    return true;
                }
                $current = get_term_by( 'id', $current->parent, $current->taxonomy );
            }

            return false;
        }
    }

    $taxonomy = get_taxonomy('product_cat');
    $conditions_manager->get_condition( 'product_archive' )->register_sub_condition( new All_Subcategories_Archive([ 'object' => $taxonomy ]) );
}, 100 );



function get_product_brand_logo() {
    global $product;

    // Get the product's brand attribute terms
    $brand_terms = wp_get_post_terms($product->get_id(), 'pa_brand'); // Replace 'pa_brand' with your actual attribute taxonomy if different

    if (!empty($brand_terms) && !is_wp_error($brand_terms)) {
        // Assuming the product can have only one brand, so we take the first term
        $brand = $brand_terms[0];

        // Get the ACF image field for this brand term
        $brand_logo = get_field('brand_logo_image', 'pa_brand_' . $brand->term_id); // Replace 'brand_logo_image' with your ACF field name

        if ($brand_logo) {
            $logo_url = $brand_logo['url'];
            $alt_text = $brand_logo['alt'];

            // Get the archive link for the brand term
            $brand_link = get_term_link($brand);

            if (!is_wp_error($brand_link)) {
                // Wrap the image in an anchor link pointing to the brand archive page
                return '<a href="' . esc_url($brand_link) . '"><img src="' . esc_url($logo_url) . '" alt="' . esc_attr($alt_text) . '" /></a>';
            }
        }
    }

    return ''; // Return nothing if no brand or logo is found
}

add_shortcode('product_brand_logo', 'get_product_brand_logo');



// Remove default WooCommerce size dropdown
add_filter('woocommerce_dropdown_variation_attribute_options_args', 'remove_default_size_dropdown', 10, 1);
function remove_default_size_dropdown($args) {
    // Check if this is the size attribute
    if ($args['attribute'] === 'attribute_pa_size') {
        $args['show_option_none'] = ''; // Remove the default options
        $args['options'] = array(); // Clear the options
    }
    return $args;
}

// Add custom quantity fields
add_action('woocommerce_before_single_variation', 'add_custom_quantity_fields', 20);
function add_custom_quantity_fields() {
    global $product;

    if ($product->is_type('variable')) {
        $available_variations = $product->get_available_variations();

        if ($available_variations) {
            echo '<div id="custom-quantity-fields">';

            // Collect sizes and prices
            $sizes = array();
            foreach ($available_variations as $variation) {
                $variation_id = $variation['variation_id'];
                $size = $variation['attributes']['attribute_pa_size']; // Ensure this matches your attribute slug
                $price = $variation['display_price'];

                if (!isset($sizes[$size])) {
                    $sizes[$size] = array(
                        'id' => $variation_id,
                        'price' => $price
                    );
                }
            }

            // Ensure sizes are ordered according to WooCommerce sorting
            uasort($sizes, function($a, $b) {
                return $a['id'] - $b['id']; // Adjust sorting as per your requirement
            });

            foreach ($sizes as $size => $data) {
                echo '<div class="size-quantity-field">';
                echo '<label for="quantity_' . esc_attr($size) . '">' . esc_html($size) . '</label>';
                echo '<input type="number" id="quantity_' . esc_attr($size) . '" name="quantity_' . esc_attr($size) . '" value="0" min="0" data-size="' . esc_attr($size) . '" data-variation-id="' . esc_attr($data['id']) . '" data-price="' . esc_attr($data['price']) . '">';
                echo '</div>';
            }

            echo '</div>';
        }
    }
}










add_filter('woocommerce_add_cart_item_data', 'add_custom_quantity_to_cart_item', 10, 2);
function add_custom_quantity_to_cart_item($cart_item_data, $product_id) {
    if (isset($_POST['quantity'])) {
        $cart_item_data['custom_quantities'] = $_POST['quantity'];
    }
    return $cart_item_data;
}

add_filter('woocommerce_get_item_data', 'display_custom_quantities_cart', 10, 2);
function display_custom_quantities_cart($item_data, $cart_item) {
    if (isset($cart_item['custom_quantities'])) {
        foreach ($cart_item['custom_quantities'] as $variation_id => $quantity) {
            $item_data[] = array(
                'key'     => 'Quantity for Variation ' . $variation_id,
                'value'   => $quantity
            );
        }
    }
    return $item_data;
}

add_action('woocommerce_checkout_create_order_line_item', 'add_custom_quantities_order_meta', 10, 4);
function add_custom_quantities_order_meta($item, $cart_item_key, $values, $order) {
    if (isset($values['custom_quantities'])) {
        foreach ($values['custom_quantities'] as $variation_id => $quantity) {
            $item->add_meta_data('Quantity for Variation ' . $variation_id, $quantity);
        }
    }
}


add_filter('woocommerce_email_order_meta_keys', 'add_custom_quantities_email', 10, 1);
function add_custom_quantities_email($keys) {
    $keys[] = 'Quantity for Variation';
    return $keys;
}


function custom_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script(
        'custom-woocommerce', 
        get_stylesheet_directory_uri() . '/assets/js/custom-woocommerce.js', 
        array('jquery'), 
        null, 
        true // Load in footer
    );
}
add_action('wp_enqueue_scripts', 'custom_enqueue_scripts');





