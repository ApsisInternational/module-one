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
        if ($request->getModuleName() == 'apsis') {
            return;
        }

        if (str_contains($request->getOriginalPathInfo(), 'apsis')) {
            return;
        }

        $proceed($request, $action);
    }
}
