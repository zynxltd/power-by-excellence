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
    var fallbackNumber = config.fallbackNumber || null;
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

    function applyNumber(number) {
        document.querySelectorAll(selector).forEach(function (el) {
            if (el.tagName === 'A') {
                el.href = 'tel:' + number.replace(/\s/g, '');
            }
            el.textContent = number;
            el.setAttribute('data-pbe-number', number);
            el.classList.remove('pbe-calls-loading');
        });
    }

    function showLoading() {
        document.querySelectorAll(selector).forEach(function (el) {
            el.classList.add('pbe-calls-loading');
        });
    }

    function fetchNumber(cb, onError) {
        if (cache) {
            cb(cache);
            return;
        }

        showLoading();

        var qs = new URLSearchParams();
        var p = params();
        Object.keys(p).forEach(function (k) {
            if (p[k] != null && p[k] !== '') qs.set(k, p[k]);
        });

        fetch(apiBase + '/dni/resolve?' + qs.toString(), { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('DNI resolve failed');
                return r.json();
            })
            .then(function (data) {
                if (data.phone_number) {
                    cache = data.phone_number;
                    cb(cache);
                } else if (fallbackNumber) {
                    cb(fallbackNumber);
                } else if (onError) {
                    onError(new Error('No tracking number returned'));
                }
            })
            .catch(function (err) {
                if (fallbackNumber) {
                    cb(fallbackNumber);
                } else if (onError) {
                    onError(err);
                }
            });
    }

    function swapNumbers() {
        fetchNumber(applyNumber, function () {
            document.querySelectorAll(selector).forEach(function (el) {
                el.classList.remove('pbe-calls-loading');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', swapNumbers);
    } else {
        swapNumbers();
    }

    window.PbeCalls = {
        refresh: function () {
            cache = null;
            swapNumbers();
        },
        resolve: fetchNumber,
    };
})(window);
