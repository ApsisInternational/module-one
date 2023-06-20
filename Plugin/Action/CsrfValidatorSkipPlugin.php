<?php

namespace Apsis\One\Plugin\Action;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\CsrfValidator;
use Magento\Framework\App\RequestInterface;
use Closure;

class CsrfValidatorSkipPlugin
{
    /**
     * @param CsrfValidator $subject
     * @param Closure $proceed
     * @param RequestInterface $request
     * @param ActionInterface $action
     *
     * @return void
     */
    public function aroundValidate(
        CsrfValidator $subject,
        Closure $proceed,
        RequestInterface $request,
        ActionInterface $action
    ): void {
        if ($request->getModuleName() == 'apsis') {
            return;
        }

        if (str_contains($request->getOriginalPathInfo(), 'apsis')) {
            return;
        }

        $proceed($request, $action);
    }
}
