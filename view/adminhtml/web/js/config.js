define(['Magento_Ui/js/modal/alert', 'Magento_Ui/js/modal/confirm', 'domReady!'], function (alert, confirmation) {
    'use strict';

    const ID_SECTION = 'apsis_one_mappings_section_mapping_section';
    const ID_ACCOUNT_ID = 'apsis_one_accounts_oauth_id';
    const ID_ACCOUNT_SECRET = 'apsis_one_accounts_oauth_secret';
    const ID_ACCOUNT_REGION = 'apsis_one_accounts_oauth_region';
    const ACCOUNT_ELEMENT_LIST = [ID_ACCOUNT_ID, ID_ACCOUNT_SECRET, ID_ACCOUNT_REGION];
    const ID_BUTTON_REST = 'apsis_reset_button';
    const ID_BUTTON_SAVE = 'save';
    const ID_DELETE_ENABLED = 'apsis_one_configuration_profile_sync_delete_enabled';

    const YES = '1';
    const NO = '0';

    const TRIGGER_CLICK = 'click';
    const TRIGGER_VALUE = 'value';
    const TRIGGER_RELOAD = 'reload';

    const P_TAG_OPEN = '<p>';
    const P_TAG_CLOSE = '</p>';
    const BR = '<br>';

    const MSG_INFO = P_TAG_OPEN + 'See this article in the <a target="_blank"' +
        ' href="https://help.apsis.com/en/articles/321-about-the-magento-integration">APSIS Knowledge Base</a>'
        + ' for a summary of the process of integrating Magento with your APSIS One Account.' + P_TAG_CLOSE;

    const MSG_CONFIRM = '<span style="color:red"> Do you wish to continue? </span>';
    const MSG_SAVE = '<span style="color:red"> Saving this configuration </span>';
    const MSG_DATA = ' and all synced Profiles & Events will be synced again. ';
    const END = 'This action is irreversible.';
    const MSG_P_RESET = MSG_SAVE + 'will trigger a partial reset. All configurations except for the account will' +
        ' be removed, ';
    const MSG_F_RESET = 'will trigger a full reset. All configurations will be removed ';
    const MSG_F_RESET_CONFIG = MSG_SAVE + MSG_F_RESET;
    const MSG_ACTION = 'This action ';
    const MSG_PRODUCTION = BR + 'Strictly for testing on stage environment only. Not recommended on Production' +
        ' environment.';
    const MSG_SECTION_VALUE_CHANGE = P_TAG_OPEN + MSG_P_RESET + MSG_DATA + P_TAG_CLOSE + MSG_INFO;
    const MSG_ACCOUNT_VALUE_CHANGE = P_TAG_OPEN + MSG_F_RESET_CONFIG + MSG_DATA + P_TAG_CLOSE + MSG_INFO;
    const MSG_SECTION_CONFIRMED_SAVE = P_TAG_OPEN + MSG_P_RESET + MSG_DATA + END + BR + BR + MSG_CONFIRM
        + P_TAG_CLOSE;
    const MSG_ACCOUNT_CONFIRMED_SAVE = P_TAG_OPEN + MSG_F_RESET_CONFIG + MSG_DATA + END + BR + BR + MSG_CONFIRM
        + P_TAG_CLOSE;
    const MSG_DEV_RESET = P_TAG_OPEN + MSG_ACTION + MSG_F_RESET + MSG_DATA + P_TAG_CLOSE + P_TAG_OPEN
        + MSG_PRODUCTION + BR + BR + MSG_CONFIRM + P_TAG_CLOSE;
    const MSG_LEGAL = 'As per the EU’s General Data Protection Regulation (GDPR), your customers have a right to' +
        ' be forgotten. By default APSIS as a Data Processor complies to GDPR rules by subscribing to' +
        ' certain delete events from Magento.' + BR + BR + 'If you disable this feature, APSIS will no longer be' +
        ' able to act on these delete events and you must take responsibility for deleting profiles in APSIS.'
        + BR + BR + 'By disabling this feature you are agreeing to take the steps necessary to remove any of the' +
        ' customer’s personal data in APSIS One according to applicable regulations in your region.';
    const MSG_DISABLE_DELETE = P_TAG_OPEN + MSG_LEGAL + BR + BR + MSG_CONFIRM + P_TAG_CLOSE;

    let isWarned = false;
    let isConfirmedOk = false;
    let deleteConfigSelectedIndexOnPageLoad;

    /**
     * @param {String} alertContent
     */
    function showWarning(alertContent) {
        alert({
            title: 'Warning!',
            content: alertContent,
            modalClass: 'alert',
            actions: {
                always: function() {
                    isWarned = true;
                }
            },
            buttons: [{
                text: 'OK',
                class: 'action primary accept',
                click: function () {
                    this.closeModal(true);
                }
            }]
        });
    }

    /**
     * @param {Object} elm
     * @param {String} confirmContext
     * @param {string} trigger
     * @param {string} reloadUrl
     */
    function showConfirmation(elm, confirmContext, trigger, reloadUrl = '') {
        confirmation({
            title: 'Please Confirm',
            content: confirmContext,
            actions: {
                confirm: function() {
                    isConfirmedOk = true;
                    if (trigger === TRIGGER_RELOAD && reloadUrl) {
                        window.location.href = reloadUrl;
                    }
                    if (trigger === TRIGGER_CLICK) {
                        elm.click();
                    }
                },
                cancel: function() {
                    if (trigger === TRIGGER_VALUE) {
                        elm.selectedIndex = deleteConfigSelectedIndexOnPageLoad;
                    }
                },
                always: function(){}
            },
            buttons: [{
                text: 'No',
                class: 'action-primary action-dismiss',
                click: function (event) {
                    this.closeModal(event);
                }
            }, {
                text: 'Yes',
                class: 'action-secondary action-accept',
                click: function (event) {
                    this.closeModal(event, true);
                }
            }]
        });
    }

    /**
     * @param {Object} element
     *
     * @return boolean
     */
    function isElementExist(element) {
        return typeof (element) != 'undefined' && element != null;
    }

    /**
     * @param {string} isSectionAlreadyMapped
     * @param {string} isAccountAlreadyConfigured
     * @param {string} isProfileDeleteEnabled
     * @param {string} resetUrl
     */
    function init(isSectionAlreadyMapped, isAccountAlreadyConfigured, isProfileDeleteEnabled, resetUrl) {
        let sectionConfig = document.getElementById(ID_SECTION);
        let btnReset = document.getElementById(ID_BUTTON_REST);
        let btnSave = document.getElementById(ID_BUTTON_SAVE);
        let deleteEnabled = document.getElementById(ID_DELETE_ENABLED);

        /**
         * Delete enabled config dropdown
         */
        if (isElementExist(deleteEnabled)) {
            deleteConfigSelectedIndexOnPageLoad = deleteEnabled.selectedIndex;
            deleteEnabled.addEventListener('change', function () {
                if (isProfileDeleteEnabled === YES && deleteEnabled.value === NO) {
                    showConfirmation(deleteEnabled, MSG_DISABLE_DELETE, TRIGGER_VALUE);
                }
            });
        }

        /**
         * Reset button
         */
        if (isElementExist(btnReset)) {
            btnReset.addEventListener('click', function (event) {
                if (isConfirmedOk === false) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    showConfirmation(btnReset, MSG_DEV_RESET, TRIGGER_RELOAD, resetUrl);
                }
            });
        }

        /**
         * Save button
         */
        if (isElementExist(btnSave)) {
            btnSave.addEventListener('click', function (event) {
                let confirmContext = '';
                if (isElementExist(document.getElementById(ID_SECTION)) && isWarned) {
                    confirmContext = MSG_SECTION_CONFIRMED_SAVE;
                }
                if (isElementExist(document.getElementById(ID_ACCOUNT_ID)) && isWarned) {
                    confirmContext = MSG_ACCOUNT_CONFIRMED_SAVE;
                }
                if (isConfirmedOk === false && confirmContext) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    showConfirmation(btnSave, confirmContext, TRIGGER_CLICK)
                }
            });
        }

        /**
         * Value change on Section mapping
         */
        if (isElementExist(sectionConfig) && isSectionAlreadyMapped) {
            sectionConfig.addEventListener('change', function () {
                showWarning(MSG_SECTION_VALUE_CHANGE);
            });
        }

        /**
         * Value change on Account elements
         */
        ACCOUNT_ELEMENT_LIST.forEach(function (elementId) {
            let element = document.getElementById(elementId);
            if (isElementExist(element)) {
                element.addEventListener('change', function () {
                    if (isAccountAlreadyConfigured && isWarned === false) {
                        showWarning(MSG_ACCOUNT_VALUE_CHANGE);
                    }
                });
            }
        });
    }

    /**
     * @param {Object} config
     */
    return function (config) {
        init(
            config.isSectionAlreadyMapped,
            config.isAccountAlreadyConfigured,
            config.isProfileDeleteEnabled,
            config.getResetUrl
        );
    };
});
