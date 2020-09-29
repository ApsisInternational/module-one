define(['jquery', 'domReady!'], function ($) {
    'use strict';

    /**
     * @param {Object} config
     */
    return function (config) {
        $(document).on('blur', '#customer-email', function() {
            let email = $(this).val();
            if (email && /^([+\w-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([\w-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/.test(email)) {
                $.post(config.endpoint, {email: email});
            }
        });
    };
});
