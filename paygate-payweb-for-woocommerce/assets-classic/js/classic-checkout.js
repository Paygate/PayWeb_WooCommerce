jQuery(document).ready(function(){
  jQuery(function(){
    jQuery("body").block({
      message: "",
      overlayCSS: {
        background: "#fff",
        opacity: 0.6
      },
      css: {
        padding:        20,
        textAlign:      "center",
        color:          "#555",
        border:         "3px solid #aaa",
        backgroundColor:"#fff",
        cursor:         "wait"
      }
    });
  });

  jQuery("#paygate_payment_form").submit();
});
