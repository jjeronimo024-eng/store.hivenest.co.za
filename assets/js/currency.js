(function () {
    'use strict';

    const state = {
        base: 'USD',
        selected: localStorage.getItem('hivenestDisplayCurrency') || 'USD',
        rates: { USD: 1 },
        applying: false
    };

    function number(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function format(usdAmount, currency = state.selected) {
        const code = String(currency || 'USD').toUpperCase();
        const rate = number(state.rates[code]) || 1;
        const converted = number(usdAmount) * rate;
        try {
            return new Intl.NumberFormat('en-ZA', {
                style: 'currency',
                currency: code,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(converted);
        } catch (error) {
            return code + ' ' + converted.toFixed(2);
        }
    }

    function inferUsd(element) {
        if (element.dataset.usdPrice !== undefined) return number(element.dataset.usdPrice);
        const ownText = Array.from(element.childNodes)
            .filter(node => node.nodeType === Node.TEXT_NODE)
            .map(node => node.textContent)
            .join(' ');
        const match = ownText.match(/\$\s*([0-9][0-9,]*(?:\.[0-9]+)?)/);
        if (!match) return null;
        const usd = number(match[1].replace(/,/g, ''));
        element.dataset.usdPrice = String(usd);
        return usd;
    }

    function applyElement(element) {
        const usd = inferUsd(element);
        if (usd === null) return;

        const sign = Number(element.dataset.currencySign || 1) < 0 ? -1 : 1;
        const priceText = format(usd * sign);
        const textNode = Array.from(element.childNodes)
            .find(node => node.nodeType === Node.TEXT_NODE);
        if (textNode) {
            textNode.textContent = priceText;
        } else {
            element.insertBefore(document.createTextNode(priceText), element.firstChild);
        }
        element.dataset.displayCurrency = state.selected;
        element.title = state.selected === 'USD'
            ? 'Charged in USD'
            : 'Indicative conversion from USD. Checkout is charged in USD.';
    }

    function apply(root = document) {
        if (state.applying) return;
        state.applying = true;
        root.querySelectorAll('[data-usd-price], .pricing-amount').forEach(applyElement);
        document.querySelectorAll('[data-currency-select]').forEach(select => {
            select.value = state.selected;
        });
        state.applying = false;
    }

    async function choose(currency) {
        const code = String(currency || 'USD').toUpperCase();
        if (!Object.prototype.hasOwnProperty.call(state.rates, code)) return;
        state.selected = code;
        localStorage.setItem('hivenestDisplayCurrency', code);
        apply();
        try {
            await fetch('/api/currency.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ currency: code })
            });
        } catch (error) {
            console.error('Currency preference could not be synchronized.', error);
        }
    }

    async function init() {
        try {
            const response = await fetch('/api/currency.php', {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (response.ok) {
                const data = await response.json();
                state.rates = data.rates || state.rates;
                const local = localStorage.getItem('hivenestDisplayCurrency');
                state.selected = local || data.display_currency || 'USD';
            }
        } catch (error) {
            console.error('Currency display settings could not be loaded.', error);
        }

        document.querySelectorAll('[data-currency-select]').forEach(select => {
            select.value = state.selected;
            select.addEventListener('change', event => choose(event.target.value));
        });
        apply();

        const observer = new MutationObserver(mutations => {
            if (state.applying) return;
            if (mutations.some(mutation => mutation.addedNodes.length > 0)) apply();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    window.HiveNestCurrency = {
        format,
        apply,
        choose,
        get selected() { return state.selected; }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
