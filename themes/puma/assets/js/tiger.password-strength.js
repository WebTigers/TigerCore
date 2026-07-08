/**
 * tiger.password-strength.js — a live password strength meter.
 *
 * Put `data-tiger-strength` on a password input and this injects a slim 2px bar beneath it
 * that fills red → yellow → green as the password gets stronger on keyup. On blur, if the
 * password is still weak it elegantly FADES the bar out and reveals the weakness message in
 * its place; the next keystroke fades the message back out and the bar back in. (The server
 * password policy — Tiger_Validate_Password — remains the authority at submit; this is the
 * live UX on top.) Zero markup required beyond the one attribute.
 */
(function (global) {
    'use strict';

    // Score 0..4 by length + character variety.
    function score(pw) {
        var s = 0;
        if (pw.length >= 8) { s++; }
        if (pw.length >= 12) { s++; }
        if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) { s++; }
        if (/\d/.test(pw)) { s++; }
        if (/[^A-Za-z0-9]/.test(pw)) { s++; }
        return Math.min(s, 4);
    }
    // The single most useful thing to fix, or '' when it's strong enough.
    function weakness(pw) {
        if (pw.length < 8) { return 'Use at least 8 characters.'; }
        if (!/\d/.test(pw) && !/[^A-Za-z0-9]/.test(pw)) { return 'Add a number or a symbol.'; }
        if (!/[A-Z]/.test(pw) || !/[a-z]/.test(pw)) { return 'Mix upper- and lower-case for a stronger password.'; }
        return '';
    }
    var COLORS = ['#dc3545', '#dc3545', '#fd7e14', '#ffc107', '#198754'];   // red → green, index = score

    function attach(input) {
        if (input.__tgStrength) { return; }
        input.__tgStrength = true;

        var track = document.createElement('div');
        track.className = 'tg-strength';
        track.style.cssText = 'height:2px;margin-top:6px;border-radius:2px;background:var(--bs-border-color);overflow:hidden;transition:opacity .25s ease;';
        var bar = document.createElement('div');
        bar.style.cssText = 'height:2px;width:0;border-radius:2px;transition:width .25s ease, background-color .25s ease;';
        track.appendChild(bar);

        var msg = document.createElement('div');
        msg.className = 'form-text text-danger';
        msg.style.cssText = 'margin-top:6px;opacity:0;height:0;overflow:hidden;transition:opacity .25s ease;';

        input.insertAdjacentElement('afterend', msg);
        input.insertAdjacentElement('afterend', track);

        function paint() {
            var s = score(input.value);
            bar.style.width = (input.value ? (s / 4) * 100 : 0) + '%';
            bar.style.backgroundColor = COLORS[s];
        }
        function showBar() {                       // keyup: bar in, message out
            msg.style.opacity = '0'; msg.style.height = '0';
            track.style.opacity = '1';
            paint();
        }
        function showMessage(text) {               // blur-weak: message in, bar out
            track.style.opacity = '0';
            msg.textContent = text;
            msg.style.height = 'auto'; msg.style.opacity = '1';
        }

        input.addEventListener('keyup', showBar);
        input.addEventListener('input', showBar);
        input.addEventListener('focus', showBar);
        input.addEventListener('blur', function () {
            var w = input.value ? weakness(input.value) : '';
            if (w) { showMessage(w); } else { showBar(); }
        });
        paint();
    }

    function scan(root) {
        (root || document).querySelectorAll('input[data-tiger-strength]').forEach(attach);
    }
    if (document.readyState !== 'loading') { scan(); }
    else { document.addEventListener('DOMContentLoaded', function () { scan(); }); }

    global.TigerPasswordStrength = { scan: scan };
})(window);
