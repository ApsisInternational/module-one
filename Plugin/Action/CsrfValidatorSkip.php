<?php

namespace Apsis\One\Plugin\Action;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\CsrfValidator;
use Magento\Framework\App\RequestInterface;
use Closure;

class CsrfValidatorSkip
{
    /**
     * @param CsrfValidator $subject
     * @param Closure $proceed
     * @param RequestInterface $request
     * @param ActionInterface $action
     */
    public function aroundValidate($subject, Closure $proceed, $request, $action)
    {
        /* Magento 2.1.x, 2.2.x */
        if ($request->getModuleName() == 'apsis') {
            return;
        }

        /* Magento 2.3.x */
        if (strpos($request->getOriginalPathInfo(), 'apsis') !== false) {
            return;
        }

        $proceed($request, $action);
    }
}
