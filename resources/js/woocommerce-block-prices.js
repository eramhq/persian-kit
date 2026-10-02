/**
 * Persian Kit — Persian digits in prices drawn by the WooCommerce cart and
 * checkout blocks. The blocks format prices in the browser, so the server-side
 * price filter never sees them. Only digits inside price elements change.
 */
(function () {
    'use strict';

    if (typeof MutationObserver === 'undefined') {
        return;
    }

    var PRICE_SELECTOR = '.wc-block-components-formatted-money-amount, .wc-block-components-product-price';
    var PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹';

    function toPersian(text) {
        return text.replace(/[0-9]/g, function (digit) {
            return PERSIAN_DIGITS.charAt(+digit);
        });
    }

    function convertTextNode(node) {
        var converted = toPersian(node.nodeValue);
        // Writing only on change keeps the observer from looping.
        if (converted !== node.nodeValue) {
            node.nodeValue = converted;
        }
    }

    function convertElement(element) {
        var walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, null);
        var node;
        while ((node = walker.nextNode())) {
            convertTextNode(node);
        }
    }

    function convertWithin(root) {
        if (root.nodeType === Node.TEXT_NODE) {
            var parent = root.parentElement;
            if (parent && parent.closest(PRICE_SELECTOR)) {
                convertTextNode(root);
            }
            return;
        }

        if (root.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        if (root.closest(PRICE_SELECTOR)) {
            convertElement(root);
            return;
        }

        var prices = root.querySelectorAll(PRICE_SELECTOR);
        for (var i = 0; i < prices.length; i++) {
            convertElement(prices[i]);
        }
    }

    function start() {
        convertWithin(document.body);

        new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];
                if (mutation.type === 'characterData') {
                    convertWithin(mutation.target);
                    continue;
                }
                for (var j = 0; j < mutation.addedNodes.length; j++) {
                    convertWithin(mutation.addedNodes[j]);
                }
            }
        }).observe(document.body, { childList: true, characterData: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}());
