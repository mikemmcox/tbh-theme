jQuery(document).ready(function($) {
    function updateTotalPrice() {
        let totalPrice = 0;
        let selectedVariationId = '';

        $('#custom-quantity-fields .size-quantity-field').each(function() {
            let size = $(this).find('input').data('size');
            let quantity = parseInt($(this).find('input').val(), 10) || 0;
            let pricePerUnit = parseFloat($(this).find('input').data('price')) || 0;
            let variationId = $(this).find('input').data('variation-id');

            if (quantity > 0) {
                selectedVariationId = variationId;
            }

            totalPrice += pricePerUnit * quantity;
        });

        // Update price display
        let formattedPrice = new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(totalPrice);
        $('.woocommerce-variation-price .price').text(formattedPrice);

        // Set variation ID in the hidden input field to be added to the cart
        $('input[name="variation_id"]').val(selectedVariationId);
    }

    // Bind input change event to update price
    $('#custom-quantity-fields input').on('input', function() {
        updateTotalPrice();
    });

    // Handle color variation changes to update product image
    $('form.variations_form').on('change', '.variations_form select', function() {
        let selectedColor = $(this).val(); // Get selected color
        if (selectedColor) {
            let newImage = $(`.woocommerce-product-gallery__image[data-color="${selectedColor}"]`).attr('data-src');
            if (newImage) {
                $('.woocommerce-product-gallery__image img').attr('src', newImage);
            }
        }
    });

    // Initial price update on page load
    updateTotalPrice();
});
