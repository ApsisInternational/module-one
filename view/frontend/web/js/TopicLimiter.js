require(['jquery', 'jquery/ui', 'domReady!', 'mage/translate'], function ($) {
    'use strict';

    const text = $.mage.__('You may select maximum 5 topics.');
    const tip = '<div class="apsis-tooltip" style="position: absolute; top: -25px; background-color: #333;' +
        'color: #fff; padding: 5px;"></div>';
    const classToObserve = '.apsis-topic-subscription';
    const maxAllowedSelection = 5;

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
     * Input selection change observer
     */
    $(document).on('change', classToObserve, function() {
        let $cs = $(classToObserve + ':checkbox:checked');
        removeTooltip($(this));
        if ($cs.length > maxAllowedSelection) {
            $(this).prop("checked", false);
            addTooltip(text, $(this));
            setTimeout(function() {
                removeTooltip($(this));
            }.bind(this), 1000);
        }
    });
});

