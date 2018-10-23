jQuery(function() {
    jQuery.ajaxSetup({
        complete: function(xhr, textStatus) {
            var result = JSON.parse(xhr.responseText);
            if (result.paygate_override) {
                jQuery('.woocommerce-error').remove();
                initPayPopup(result);
                return false;
            }
            return;
        }
    });
    jQuery(document).ajaxComplete(function() {
        if (jQuery('body').hasClass('woocommerce-checkout') || jQuery('body').hasClass('woocommerce-cart')) {
            jQuery('html, body').stop();
        }
    });
});

function initPayPopup(result) {
    jQuery("body").append("<div id='payPopup'></div>");
    jQuery("#payPopup").append("<div id='payPopupContent'></div>");
    jQuery("#payPopupContent").append("<form target='myIframe' name='paygate_checkout' id='paygate_checkout' action='https://secure.paygate.co.za/payweb3/process.trans' method='post'><input type='hidden' name='PAY_REQUEST_ID' value='" + result.PAY_REQUEST_ID + "' size='200'><input type='hidden' name='CHECKSUM' value='" + result.CHECKSUM + "' size='200'></form><iframe style='width:100%;height:100%;' id='payPopupFrame' name='myIframe'  src='#' ></iframe><script type='text/javascript'>document.getElementById('paygate_checkout').submit();</script>");
}
jQuery(document).on('submit', 'form#order_review', function(e) {
    e.preventDefault();
    jQuery.ajax({
        'url': wc_add_to_cart_params.ajax_url,
        'type': 'POST',
        'dataType': 'json',
        'data': {
            'action': 'order_pay_payment',
            'order_id': 73
        },
        'async': false
    }).complete(function(result) {
        var result = JSON.parse(result);
        initPayPopup(result);
    });
});