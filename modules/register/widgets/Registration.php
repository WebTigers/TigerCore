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
        $s = Register_Service_Status::state();

        if (!empty($s['verified'])) {
            return '<div class="text-center py-2">'
                . '<i class="fa-solid fa-circle-check text-success fs-3 d-block mb-2"></i>'
                . '<div class="fw-semibold">Your site is registered</div>'
                . '<div class="small text-body-secondary mt-1">Site ID <code>' . htmlspecialchars((string) $s['tsid']) . '</code></div>'
                . '</div>';
        }

        if (empty($s['started'])) {
            $body = '<p class="small text-body-secondary mb-2">Register this site for a verified Site ID and to join the '
                . 'Tiger network — optional, and it turns nothing on or off. We share only your domain, this email, and '
                . 'your Tiger/PHP versions.</p>'
                . '<div class="input-group input-group-sm">'
                . '<input type="email" data-reg="email" class="form-control" placeholder="you@yourdomain.com" autocomplete="email">'
                . '<button type="button" data-reg="register" class="btn btn-primary">Register</button></div>';
        } elseif (empty($s['domain_verified'])) {
            $body = '<p class="small text-body-secondary mb-2">Confirming you control <strong>'
                . htmlspecialchars((string) $s['domain']) . '</strong>.</p>'
                . '<button type="button" data-reg="verifyDomain" class="btn btn-sm btn-outline-primary">Verify domain</button>';
        } else {
            $body = '<p class="small text-body-secondary mb-2">Last step: click the link we emailed to <strong>'
                . htmlspecialchars((string) $s['email']) . '</strong>.</p>'
                . '<button type="button" data-reg="resendEmail" class="btn btn-sm btn-outline-secondary">Resend email</button>';
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
