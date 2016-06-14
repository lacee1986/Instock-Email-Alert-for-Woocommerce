/**
* Plugin Name: Instock Email Alert for Woocommerce
* Plugin URI: https://wordpress.org/plugins/instock-email-alert-for-woocommerce/
* Description: Sends an email alert for the subscribed users when the product is in stock.
* Version: 1.0
* Author: Laszlo Kruchio
*/

// On Ready
jQuery(document).ready(function () {
    
    function numberOfItems () {
        if ( jQuery('#instock_alert_options #subscribed_list li:not(".hidden")').length < 6 ) {
            jQuery('#instock_alert_options .expand').hide();        
        } else {
            jQuery('#instock_alert_options .expand').show();
        }
    }
    
    numberOfItems();

    jQuery('.expand').click(function(){
        var list = jQuery('#subscribed_list');
        var button = jQuery('.expand span');
        if ( list.hasClass('expanded') ) {
            list.removeClass('expanded');
            button.text('Show more');
        } else {
            list.addClass('expanded');
            button.text('Show less');
        }
        numberOfItems();
    });
    
    jQuery('.filters .filter').change(function() {
        var checkbox = jQuery(this);
        var name = checkbox.attr('id');
        jQuery('#instock_alert_options #subscribed_list li:not(:first-child)').each(function(){
            var list_item = jQuery(this);
            if ( name == 'filter_sent' && list_item.hasClass('sent') ) {
                list_item.removeClass('hidden');
            } else if ( name == 'filter_waiting' && list_item.hasClass('waiting') ) {
                list_item.removeClass('hidden');
            } else if ( name == 'filter_all' ) {
                list_item.removeClass('hidden');
            } else {
                list_item.addClass('hidden');   
            }
        });    
        numberOfItems();
    });

});
