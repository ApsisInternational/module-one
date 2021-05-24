<?php

namespace Apsis\One\Block\Adminhtml;

use Magento\Backend\Block\Widget\Container;
use Apsis\One\Model\Service\File;
use Magento\Backend\Block\Widget\Context;

/**
 * Log viewer block
 *
 * @api
 */
class Logviewer extends Container
{

    /**
     * @var string
     */
    public $_template = 'log.phtml';

    /**
     * @var File
     */
    public $file;

    /**
     * Logviewer constructor.
     *
     * @param Context $context
     * @param File $file
     * @param array $data
     */
    public function __construct(Context $context, File $file, array $data = [])
    {
        $this->file = $file;
        parent::__construct($context, $data);
    }

    /**
     * @return void
     */
    public function _construct()
    {
        $this->_controller = 'adminhtml_logviewer';
        $this->_headerText = __('Log Viewer');
        parent::_construct();
    }

    /**
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('apsis_one/logviewer/ajaxlogcontent');
    }
}
