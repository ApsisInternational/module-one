require(['jquery', 'jquery/ui', 'domReady!', 'mage/translate'], function ($) {
    'use strict';

    const generalElmId = '#apsis_one_sync_sync_subscriber_consent_topic';
    const additionalEmlId = '#apsis_one_sync_sync_additional_consent_topic';
    const text = $.mage.__('You may select maximum 4 additional topics.');
    const tip = '<div class="apsis-tooltip" style="position: absolute; top: -10px; background-color: #333;' +
        'color: #fff; padding: 5px;"></div>';
    const maxAllowedSelection = 4;
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
     * Update options
     *
     * @param element
     */
    function updateAdditionalTopics(element)
    {
        $(additionalEmlId + ' option').each(function () {
            $(this).removeAttr('disabled');
        });
        if (element.val()) {
            let additionalElement = $(additionalEmlId + " option[value='" + element.val() + "']");
            additionalElement.attr('disabled', true);
            additionalElement.attr('selected', false);
        }
    }

    /**
     * Multiselect selection change observer
     */
    //$(additionalEmlId + " option[value='']").remove();
    updateAdditionalTopics($(generalElmId));
    $(document).on('change', additionalEmlId, function() {
        validate($(this));
    });
    $(document).on('change', generalElmId, function() {
        updateAdditionalTopics($(this));
    });
});

