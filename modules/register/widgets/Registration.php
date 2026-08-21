<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Register_Widget_Registration — the registration prompt as a **dashboard widget body**. The dashboard shell
 * owns the card chrome (drag handle, collapse, on/off in "Customize", per-user layout); this owns only the
 * body. It renders the current step — register (email), verify domain, verify email, or "registered" — and
 * posts each action to `/api` (`Register_Service_Registration`). It gates nothing; the whole widget is the
 * offer, and switching it off (or deactivating the module) is the opt-out.
 *
 * @api
 */
class Register_Widget_Registration
{
    /**
     * The card body HTML for the current registration step.
     *
     * @return string
     */
    public function render(): string
    {
        // Widgets aren't views, so pull the registered translator directly (fail-soft: returns the key
        // if none is registered). The domain/email are inserted into a %s placeholder so word order
        // localizes correctly; they're html-escaped, the surrounding <strong> is intentional markup.
        $tr = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
        // Active locale → source (en) fallback → key, so a missing locale degrades to English, never a raw key.
        $t  = function ($key) use ($tr) {
            if (!$tr) { return $key; }
            if ($tr->isTranslated($key)) { return $tr->translate($key); }
            return $tr->isTranslated($key, false, 'en') ? $tr->translate($key, 'en') : $key;
        };
        $s  = Register_Service_Status::state();

        if (!empty($s['verified'])) {
            return '<div class="text-center py-2">'
                . '<i class="fa-solid fa-circle-check text-success fs-3 d-block mb-2"></i>'
                . '<div class="fw-semibold">' . htmlspecialchars($t('register.widget.registered')) . '</div>'
                . '<div class="small text-body-secondary mt-1">' . htmlspecialchars($t('register.widget.site_id')) . ' <code>' . htmlspecialchars((string) $s['tsid']) . '</code></div>'
                . '</div>';
        }

        if (empty($s['started'])) {
            $body = '<p class="small text-body-secondary mb-2">' . htmlspecialchars($t('register.widget.intro')) . '</p>'
                . '<div class="input-group input-group-sm">'
                . '<input type="email" data-reg="email" class="form-control" placeholder="you@yourdomain.com" autocomplete="email">'
                . '<button type="button" data-reg="register" class="btn btn-primary">' . htmlspecialchars($t('register.widget.register')) . '</button></div>';
        } elseif (empty($s['domain_verified'])) {
            $body = '<p class="small text-body-secondary mb-2">'
                . sprintf($t('register.widget.confirming'), '<strong>' . htmlspecialchars((string) $s['domain']) . '</strong>') . '</p>'
                . '<button type="button" data-reg="verifyDomain" class="btn btn-sm btn-outline-primary">' . htmlspecialchars($t('register.verify_domain')) . '</button>';
        } else {
            $body = '<p class="small text-body-secondary mb-2">'
                . sprintf($t('register.widget.last_step'), '<strong>' . htmlspecialchars((string) $s['email']) . '</strong>') . '</p>'
                . '<button type="button" data-reg="resendEmail" class="btn btn-sm btn-outline-secondary">' . htmlspecialchars($t('register.widget.resend')) . '</button>';
        }

        return '<div id="reg-w" data-csrf="' . htmlspecialchars($this->_csrf(), ENT_QUOTES) . '">'
            . $body
            . '<div class="reg-err small text-danger mt-2" aria-live="polite"></div>'
            . '<script>' . $this->_js() . '</script>'
            . '</div>';
    }

    /** Self-contained JS: post an action to /api, reload on success. */
    private function _js(): string
    {
        return
            '(function(){var w=document.getElementById("reg-w");if(!w)return;'
            . 'function busy(v){w.querySelectorAll("button,input").forEach(function(e){e.disabled=v;});}'
            . 'function post(m,extra){var b=new URLSearchParams(extra||{});'
            . 'b.set("module","register");b.set("service","registration");b.set("method",m);'
            . 'b.set("_csrf",w.getAttribute("data-csrf"));busy(true);'
            . 'fetch("/api",{method:"POST",headers:{"X-Requested-With":"XMLHttpRequest"},body:b})'
            . '.then(function(r){return r.json().catch(function(){return{};});})'
            . '.then(function(res){if(res&&res.result===1){location.reload();return;}busy(false);'
            . 'var m2=(res&&res.messages&&res.messages[0])?res.messages[0].message:"Please try again.";'
            . 'var el=w.querySelector(".reg-err");if(el){el.textContent=m2;}})'
            . '.catch(function(){busy(false);});}'
            . 'function on(s,f){var e=w.querySelector(s);if(e)e.addEventListener("click",f);}'
            . 'on("[data-reg=register]",function(){var e=w.querySelector("[data-reg=email]");post("register",{email:e?e.value:""});});'
            . 'on("[data-reg=verifyDomain]",function(){post("verifyDomain");});'
            . 'on("[data-reg=resendEmail]",function(){post("resendEmail");});'
            . '})();';
    }

    /** A CSRF token the widget's form can submit (same session the /api form validates against). */
    private function _csrf(): string
    {
        try {
            return (string) (new Register_Form_Register())->getElement('_csrf')->getHash();
        } catch (Throwable $e) {
            return '';
        }
    }
}
