<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Newsletter_Service_Render — the server-rendered embeddable subscribe form the `[newsletter_form]`
 * shortcode emits.
 *
 * Renders a real `<form>` (so it is present for a crawler and works as a progressive-enhancement
 * target); newsletter.form.js intercepts submit and POSTs to `Newsletter_Service_Subscribe` over
 * `/api`, then swaps in the "check your inbox" message. Two anti-bot fields ride along: a `_t` render
 * timestamp (too-fast gate) and a `_hp` honeypot hidden with the standard `hidden` attribute (no
 * inline style — the skeleton's reset supplies `[hidden]{display:none}`).
 *
 * Not a `Tiger_Service_Service`: shortcodes call this in-process; the browser talks to the /api service.
 */
class Newsletter_Service_Render
{
    /**
     * The subscribe form.
     *
     * @param  array $options `title`, `intro`, `button`, `source` (shortcode attributes)
     * @return string         the HTML
     */
    public function form(array $options = [])
    {
        $view = $this->_view();

        $title  = (string) ($options['title']  ?? $this->_t('newsletter.form.title'));
        $intro  = (string) ($options['intro']  ?? $this->_t('newsletter.form.intro'));
        $button = (string) ($options['button'] ?? $this->_t('newsletter.form.submit'));
        $source = preg_replace('/[^a-z0-9_.\-]/i', '', (string) ($options['source'] ?? 'form')) ?: 'form';

        $js = $view->escape($view->asset('/_modules/newsletter/js/newsletter.form.js'));

        return '<section class="tiger-newsletter" data-newsletter-source="' . $view->escape($source) . '">'
             . ($title !== '' ? '<h2 class="h5 mb-2">' . $view->escape($title) . '</h2>' : '')
             . ($intro !== '' ? '<p class="mb-3">' . $view->escape($intro) . '</p>' : '')
             . '<div class="tiger-newsletter-feedback"></div>'
             . '<form class="tiger-newsletter-form" method="post" action="/api" novalidate>'
             .   '<input type="hidden" name="module" value="newsletter">'
             .   '<input type="hidden" name="service" value="subscribe">'
             .   '<input type="hidden" name="method" value="subscribe">'
             .   '<input type="hidden" name="source" value="' . $view->escape($source) . '">'
             .   '<input type="hidden" name="_t" value="' . $view->escape((string) time()) . '">'
             .   '<input type="text" name="_hp" hidden tabindex="-1" autocomplete="off" aria-hidden="true">'
             .   '<div class="mb-2">'
             .     '<label class="form-label" for="tiger-newsletter-name">' . $view->escape($this->_t('newsletter.form.name')) . '</label>'
             .     '<input type="text" class="form-control" id="tiger-newsletter-name" name="name" autocomplete="name">'
             .   '</div>'
             .   '<div class="mb-2">'
             .     '<label class="form-label" for="tiger-newsletter-email">' . $view->escape($this->_t('newsletter.form.email')) . '</label>'
             .     '<input type="email" class="form-control" id="tiger-newsletter-email" name="email" autocomplete="email" required>'
             .   '</div>'
             .   '<button type="submit" class="btn btn-primary">' . $view->escape($button) . '</button>'
             . '</form>'
             . '<script src="' . $js . '" defer></script>'
             . '</section>';
    }

    /**
     * The themed view (helpers + escaping), or a bare one outside a request — the same seam
     * Comment_Service_Render uses so a CLI/queued/test render still has asset()/escape().
     */
    protected function _view()
    {
        if (Zend_Registry::isRegistered('Tiger_View')) { return Zend_Registry::get('Tiger_View'); }

        $view = new Zend_View();
        if (defined('TIGER_CORE_PATH')) {
            $view->addHelperPath(TIGER_CORE_PATH . '/library/Tiger/View/Helper', 'Tiger_View_Helper');
        }
        return $view;
    }

    /** Translate a key, falling back to the key itself. */
    protected function _t($key)
    {
        if (!Zend_Registry::isRegistered('Zend_Translate')) { return $key; }
        $t = Zend_Registry::get('Zend_Translate');
        return $t->isTranslated($key) ? $t->translate($key) : $key;
    }
}
