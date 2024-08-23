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


// Adding quantity boxes for size attribute
add_action( 'woocommerce_before_add_to_cart_button', 'add_quantity_inputs_for_sizes' );

function add_quantity_inputs_for_sizes() {
    global $product;

    if ( $product->is_type( 'variable' ) ) {
        $size_attribute = 'pa_size'; // Adjust the attribute slug if needed
        $sizes = wc_get_product_terms( $product->get_id(), $size_attribute, array( 'fields' => 'all' ) );

        if ( ! empty( $sizes ) ) {
            echo '<div id="size-quantity-fields" style="margin-top: 20px;">';
            foreach ( $sizes as $size ) {
                $size_slug = esc_attr( $size->slug );
                $size_name = esc_html( $size->name );

                echo '<div class="quantity-size">';
                echo '<label for="size-' . $size_slug . '" style="margin-right: 10px;">' . $size_name . '</label>';
                echo '<input type="number" id="size-' . $size_slug . '" name="quantity[' . $size_slug . ']" value="0" min="0" class="input-text qty text" size="4" style="width: 60px;"/>';
                echo '</div>';
            }
            echo '</div>';
        }
    }
}

add_action( 'wp_enqueue_scripts', 'remove_default_size_dropdown' );

function remove_default_size_dropdown() {
    if ( is_product() ) {
        ?>
        <style type="text/css">
            .variations select[name^="attribute_pa_size"] {
                display: none !important;
            }

            .variations .label[for^="pa_size"] {
                display: none !important;
            }
        </style>
        <?php
    }
}

add_filter( 'woocommerce_add_to_cart_validation', 'validate_size_quantities', 10, 2 );

function validate_size_quantities( $passed, $product_id ) {
    if ( isset( $_POST['quantity'] ) && is_array( $_POST['quantity'] ) ) {
        $total_quantity = array_sum( $_POST['quantity'] );
        if ( $total_quantity <= 0 ) {
            wc_add_notice( __( 'Please enter a quantity for at least one size.' ), 'error' );
            return false;
        }
    }

    return $passed;
}

add_filter( 'woocommerce_add_cart_item_data', 'add_size_quantities_to_cart', 10, 2 );

function add_size_quantities_to_cart( $cart_item_data, $product_id ) {
    if ( isset( $_POST['quantity'] ) && is_array( $_POST['quantity'] ) ) {
        $cart_item_data['size_quantities'] = $_POST['quantity'];
    }

    return $cart_item_data;
}

add_filter( 'woocommerce_get_item_data', 'display_size_quantities_in_cart', 10, 2 );

function display_size_quantities_in_cart( $item_data, $cart_item ) {
    if ( isset( $cart_item['size_quantities'] ) && is_array( $cart_item['size_quantities'] ) ) {
        foreach ( $cart_item['size_quantities'] as $size => $quantity ) {
            if ( $quantity > 0 ) {
                $item_data[] = array(
                    'name'  => wc_attribute_label( 'pa_size' ) . ' ' . wc_get_product_term_name( $cart_item['product_id'], 'pa_size', $size ),
                    'value' => $quantity,
                );
            }
        }
    }

    return $item_data;
}

add_action( 'woocommerce_add_order_item_meta', 'add_size_quantities_to_order', 10, 2 );

function add_size_quantities_to_order( $item_id, $values ) {
    if ( isset( $values['size_quantities'] ) && is_array( $values['size_quantities'] ) ) {
        foreach ( $values['size_quantities'] as $size => $quantity ) {
            if ( $quantity > 0 ) {
                wc_add_order_item_meta( $item_id, wc_attribute_label( 'pa_size' ) . ' ' . wc_get_product_term_name( $values['product_id'], 'pa_size', $size ), $quantity );
            }
        }
    }
}




