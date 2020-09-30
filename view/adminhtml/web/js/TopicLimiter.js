require(['jquery', 'jquery/ui', 'domReady!', 'mage/translate'], function ($) {
    'use strict';

    const text = $.mage.__('You may select maximum 5 topics.');
    const tip = '<div class="apsis-tooltip" style="position: absolute; top: -10px; background-color: #333;' +
        'color: #fff; padding: 5px;"></div>';
    const maxAllowedSelection = 5;
    let last_valid_selection = null;

    /**
     * Add tooltip with text
     *
     * @param text
     * @param element
     */
    function addTooltip(text, element)
    {
        element.attr('data-title', text);
        element.parent()
            .css('position', 'relative')
            .append(tip);
        $(".apsis-tooltip").append(text);
    }

    /**
     * Remove tooltip element
     *
     * @param element
     */
    function removeTooltip(element)
    {
        element.css('position', '');
        $('.apsis-tooltip').remove();
    }

    /**
     * Check maximum allowed selection
     *
     * @param element
     */
    function validate(element)
    {
        removeTooltip(element);
        if (element.val().length > maxAllowedSelection) {
            element.val(last_valid_selection);
            addTooltip(text, element);
            setTimeout(function() {
                removeTooltip(element);
            }.bind(this), 1000);
        } else {
            last_valid_selection = element.val();
        }
    }

    /**
     * Multiselect selection change observer
     */
    $(document).on('change', '#apsis_one_sync_sync_subscriber_consent_topic', function() {
        validate($(this));
    });
});

