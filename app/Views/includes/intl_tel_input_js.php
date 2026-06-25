<?php
load_css(array(
    "assets/js/intl_tel_input/css/intlTelInput.min.css",
));

load_js(array(
    "assets/js/intl_tel_input/js/intlTelInputWithUtils.min.js",
));
?>

<script type="text/javascript">
    function initializeIntlTelInput(inputSelector) {
        var options = {
            nationalMode: true,
            separateDialCode: true,
            strictMode: true,
            initialCountry: "<?php echo get_setting("phone_input_default_country"); ?>" || "us"
        };

        var inputField = $(inputSelector);

        if (inputField.length) {
            return window.intlTelInput(inputField[0], options);
        }

        return null;
    }
</script>