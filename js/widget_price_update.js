// TODO: Change the attribute names 

jQuery(document).on('woocommerce_variation_select_change', function() {
    jQuery('.variations_form').each(function() {
        // When variation is found, grab the display price and update Divido_widget
        jQuery(this).on('found_variation', function(event, variation) {
            var new_price = variation.display_price;
            var widget = jQuery("[data-"+environment.environment+"-widget]");
            widget.attr("data-"+environment.environment+"-amount", new_price);
        });
    });
});
