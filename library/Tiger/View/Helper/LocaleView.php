<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_View_Helper_LocaleView — render the LOCALE variant of a view when one ships, else the default.
 *
 * For long-form content a view owns (marketing pages, docs), the localized version is a whole sibling
 * view — `foo.es.phtml` next to `foo.phtml` — not string keys (I18N.md). This helper picks between them
 * off the ONE locale already resolved early in the request (`LANG`, set by `Tiger_Controller_Plugin_
 * LocalePrefix` from URL > cookie > browser > default). It reads nothing from the URL or cookie itself —
 * by the time an action runs, the locale is simply KNOWN.
 *
 * Call it from a controller action:
 *
 *   public function vibeAction() { $this->view->localeView(); }      // index/vibe.<LANG>.phtml, else index/vibe.phtml
 *
 * With no argument it localizes the current action's own view; pass a base (`'index/vibe'`) to localize a
 * specific one. It renders the chosen script directly (via `renderScript`, so ZF1's action inflector never
 * mangles the dotted name) and suppresses the default auto-render — the layout still wraps the result.
 *
 * @api
 * @see Tiger_View_Helper_T  the string-key twin, for UI chrome (this is for whole-view content)
 */
class Tiger_View_Helper_LocaleView extends Zend_View_Helper_Abstract
{
    /**
     * Render the active locale's variant of a view, else the base.
     *
     * @param  string|null $base the base script (`'index/vibe'`, with or without `.phtml`); null = the
     *                           current action's own default view
     * @return string            '' — the chosen script is rendered straight into the response
     */
    public function localeView($base = null)
    {
        $vr = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');

        // Respect an already-suppressed render: an action that self-renders, or a test harness that
        // dispatches the action body with rendering OFF, must not be forced to render a .phtml here.
        // In a normal request rendering is still armed when the action calls this, so we proceed.
        if ($vr->getNoRender()) {
            return '';
        }

        // Default to the current action's own view script (e.g. "index/vibe.phtml").
        $script = ($base === null) ? $vr->getViewScript() : (preg_replace('/\.phtml$/', '', (string) $base) . '.phtml');

        $lang    = defined('LANG') ? (string) LANG : '';
        $variant = preg_replace('/\.phtml$/', '.' . $lang . '.phtml', $script);
        if ($lang !== '' && $variant !== $script && $this->view->getScriptPath($variant)) {
            $script = $variant;   // a translated sibling exists for this locale → use it
        }

        $vr->setNoRender(true);      // don't also auto-render the default
        $vr->renderScript($script);  // exact path — no inflection, no 500 on the dotted name
        return '';
    }
}
