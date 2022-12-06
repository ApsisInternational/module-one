define(['jquery', 'domReady!'], function ($) {
    'use strict';

    /**
     * Scroll to the bottom of text
     */
    function consoleScroll() {
        let logData = document.getElementById('log_data'),
            dh = logData.scrollHeight,
            ch = logData.clientHeight;

        if (dh > ch) {
            logData.scrollTop = dh - ch;
        }
    }

    /**
     * Export/return log updater
     */
    return function () {
        consoleScroll();
    };
});
