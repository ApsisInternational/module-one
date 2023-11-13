require(['jquery', 'jquery/ui', 'domReady!', 'mage/translate'], function ($) {
    'use strict';

    const mouseEnterText = $.mage.__('CLICK TO COPY');
    const successText = $.mage.__('COPIED TO CLIPBOARD');
    const tip = '<div class="apsis-tooltip"></div>';

    /**
     * Add tooltip with text
     *
     * @param text
     * @param element
     */
    function add(text, element)
    {
        element.attr('data-title', text);
        element.css({'cursor': 'copy'})
            .parent()
            .css('position', 'relative')
            .append(tip);
        $(".apsis-tooltip").append(text);
    }

    /**
     * Remove element
     *
     * @param element
     */
    function remove(element)
    {
        element.css('position', '');
        $('.apsis-tooltip').remove();
    }

    /**
     * Copy text to clipboard
     *
     * @param element
     */
    function copy(element)
    {
        element.select();
        remove(element);
        add(successText, element);
        document.execCommand("copy");
    }

    /**
     * Mouse leave observer
     */
    $(document).on('mouseleave', '.apsis-copy-helper', function () {
        remove($(this));
    });

    /**
     * Mouse enter observer
     */
    $(document).on('mouseenter', '.apsis-copy-helper', function () {
        add(mouseEnterText, $(this));
    });

    /**
     * Mouse click observer
     */
    $(document).on('click', '.apsis-copy-helper', function () {
        copy($(this));
        setTimeout(function () {
            remove($(this));
        }.bind(this), 1000);
    });
});

