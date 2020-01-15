define(['jquery', 'domReady!'], function ($) {
    'use strict';

    /**
     * Scroll to the bottom of text
     */
    function consoleScroll() {
        var logData = document.getElementById('log_data'),
            dh = logData.scrollHeight,
            ch = logData.clientHeight;

        if (dh > ch) {
            logData.scrollTop = dh - ch;
        }
    }

    /**
     * Update elements
     * @param {String} log
     * @param {String} url
     */
    function doUpdate(log, url) {
        $.post(url, {
            log: log
        }, function (json) {
            $('#log_data').html(json.content);
            $('#one-log-header').html(json.header);
            consoleScroll();
        });
    }

    /**
     * Export/return log updater
     * @param {Object} logUpdater
     */
    return function (logUpdater) {
        consoleScroll();

        $('#one-log-selector').change(function () {
            doUpdate($('#one-log-selector').val(), logUpdater.url);
        });

        $('#one-log-reloader').click(function () {
            doUpdate($('#one-log-selector').val(), logUpdater.url);
        });
    };
});
