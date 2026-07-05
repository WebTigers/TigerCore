<?php
/**
 * Default-namespace Core controller.
 *
 * Lives in the `webtigers/tiger-core` package (vendor/), NOT in the app. The app's
 * Bootstrap points ZF1's default-module controller directory here. This is the proof
 * that a request resolves into Tiger-owned code shipped via Composer.
 */
class IndexController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->getResponse()->setHeader('Content-Type', 'text/plain; charset=utf-8');

        $lines = [
            'Hello from Tiger Core.',
            '',
            'Served by : ' . __FILE__,
            'Tiger Core: ' . Tiger_Version::VERSION,
            'ZF engine : ' . Zend_Version::VERSION,
            'PHP       : ' . PHP_VERSION,
            '',
            'This controller ships in vendor/webtigers/tiger-core — the app dir was never touched.',
            'Core CSS asset (via the public/_tiger symlink): /_tiger/css/tiger.css',
        ];
        $this->getResponse()->setBody(implode("\n", $lines) . "\n");
    }
}
