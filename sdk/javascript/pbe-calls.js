/**
 * PowerByExcellence Call Logic DNI SDK
 * Dynamic number insertion for call tracking campaigns.
 */
(function (window) {
    'use strict';

    var config = window.PbeCallsConfig || {};
    var apiBase = config.apiBase || '/api/v1';
    var accountSlug = config.accountSlug || '';
    var campaignId = config.campaignId || null;
    var selector = config.selector || '[data-pbe-call-number]';
    var cache = null;

    function params() {
        var url = new URL(window.location.href);
        return {
            account_slug: accountSlug,
            campaign_id: campaignId,
            sid: url.searchParams.get('sid') || config.sid || null,
            ssid: url.searchParams.get('ssid') || config.ssid || null,
            pool: config.pool || null,
        };
    }

    function fetchNumber(cb) {
        if (cache) {
            cb(cache);
            return;
        }

        var qs = new URLSearchParams();
        var p = params();
        Object.keys(p).forEach(function (k) {
            if (p[k] != null && p[k] !== '') qs.set(k, p[k]);
        });

        fetch(apiBase + '/dni/resolve?' + qs.toString(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.phone_number) {
                    cache = data.phone_number;
                    cb(cache);
                }
            })
            .catch(function () {});
    }

    function swapNumbers() {
        fetchNumber(function (number) {
            document.querySelectorAll(selector).forEach(function (el) {
                if (el.tagName === 'A') {
                    el.href = 'tel:' + number.replace(/\s/g, '');
                }
                el.textContent = number;
                el.setAttribute('data-pbe-number', number);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', swapNumbers);
    } else {
        swapNumbers();
    }

    window.PbeCalls = { refresh: swapNumbers, resolve: fetchNumber };
})(window);
