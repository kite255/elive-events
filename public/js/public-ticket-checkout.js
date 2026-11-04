function initializePublicTicketCheckout() {
        const form =
            document.getElementById(
                'ticket-checkout-form'
            );

        if (! form) {
            return;
        }

        if (form.dataset.checkoutInitialized === '1') {
            if (typeof form.refreshTicketSummary === 'function') {
                form.refreshTicketSummary();
            }
            return;
        }
        form.dataset.checkoutInitialized = '1';

        const cards =
            Array.from(
                form.querySelectorAll(
                    '[data-ticket-card]'
                )
            );

        const summaryItems =
            document.getElementById(
                'summary-items'
            );

        const summaryEmpty =
            document.getElementById(
                'summary-empty'
            );

        const grandTotal =
            document.getElementById(
                'grand-total'
            );

        const checkoutButton =
            document.getElementById(
                'checkout-button'
            );

        const defaultCurrency =
            form.dataset.currency || 'TZS';

        const formatMoney =
            function (
                currency,
                amount
            ) {
                return (
                    currency
                    + ' '
                    + Number(amount)
                        .toLocaleString(
                            undefined,
                            {
                                maximumFractionDigits: 0,
                            }
                        )
                );
            };

        const normalizeQuantity =
            function (
                input,
                requested
            ) {
                const minimum =
                    Number(
                        input.dataset.minimum
                        || 1
                    );

                const maximum =
                    Number(
                        input.dataset.maximum
                        || 0
                    );

                let value =
                    Number(requested || 0);

                if (
                    ! Number.isFinite(value)
                    || value < 0
                ) {
                    value = 0;
                }

                value =
                    Math.floor(value);

                if (value > maximum) {
                    value = maximum;
                }

                if (
                    value > 0
                    && value < minimum
                ) {
                    value = minimum;
                }

                return value;
            };

        const refreshSummary =
            function () {
                let total = 0;
                let selectedCount = 0;

                summaryItems
                    .querySelectorAll(
                        '.summary-row'
                    )
                    .forEach(
                        function (row) {
                            row.remove();
                        }
                    );

                cards.forEach(
                    function (card) {
                        const input =
                            card.querySelector(
                                '[data-quantity-input]'
                            );

                        if (! input) {
                            return;
                        }

                        const quantity =
                            normalizeQuantity(
                                input,
                                input.value
                            );

                        input.value =
                            quantity;

                        const price =
                            Number(
                                card.dataset.price
                                || 0
                            );

                        const currency =
                            card.dataset.currency
                            || defaultCurrency;

                        const lineTotal =
                            quantity * price;

                        const lineTotalElement =
                            card.querySelector(
                                '[data-line-total]'
                            );

                        if (lineTotalElement) {
                            lineTotalElement
                                .textContent =
                                formatMoney(
                                    currency,
                                    lineTotal
                                );
                        }

                        card.classList.toggle(
                            'selected',
                            quantity > 0
                        );

                        if (quantity <= 0) {
                            return;
                        }

                        selectedCount++;
                        total += lineTotal;

                        const row =
                            document.createElement(
                                'div'
                            );

                        row.className =
                            'summary-row';

                        const name =
                            document.createElement(
                                'div'
                            );

                        name.className =
                            'summary-name';

                        name.textContent =
                            card.dataset.ticketName;

                        const small =
                            document.createElement(
                                'small'
                            );

                        small.textContent =
                            quantity
                            + ' × '
                            + formatMoney(
                                currency,
                                price
                            );

                        name.appendChild(
                            small
                        );

                        const value =
                            document.createElement(
                                'div'
                            );

                        value.className =
                            'summary-value';

                        value.textContent =
                            formatMoney(
                                currency,
                                lineTotal
                            );

                        row.appendChild(
                            name
                        );

                        row.appendChild(
                            value
                        );

                        summaryItems.appendChild(
                            row
                        );
                    }
                );

                summaryEmpty.style.display =
                    selectedCount > 0
                        ? 'none'
                        : 'block';

                grandTotal.textContent =
                    formatMoney(
                        defaultCurrency,
                        total
                    );

                checkoutButton.disabled =
                    selectedCount === 0;
            };

        cards.forEach(
            function (card) {
                const input =
                    card.querySelector(
                        '[data-quantity-input]'
                    );

                const minus =
                    card.querySelector(
                        '[data-quantity-minus]'
                    );

                const plus =
                    card.querySelector(
                        '[data-quantity-plus]'
                    );

                if (! input) {
                    return;
                }

                const minimum =
                    Number(
                        input.dataset.minimum
                        || 1
                    );

                const maximum =
                    Number(
                        input.dataset.maximum
                        || 0
                    );

                if (minus) {
                    minus.addEventListener(
                        'click',
                        function () {
                            const current =
                                normalizeQuantity(
                                    input,
                                    input.value
                                );

                            if (
                                current <= minimum
                            ) {
                                input.value = 0;
                            } else {
                                input.value =
                                    current - 1;
                            }

                            refreshSummary();
                        }
                    );
                }

                if (plus) {
                    plus.addEventListener(
                        'click',
                        function () {
                            const current =
                                normalizeQuantity(
                                    input,
                                    input.value
                                );

                            if (current === 0) {
                                input.value =
                                    Math.min(
                                        minimum,
                                        maximum
                                    );
                            } else {
                                input.value =
                                    Math.min(
                                        maximum,
                                        current + 1
                                    );
                            }

                            refreshSummary();
                        }
                    );
                }

                input.addEventListener(
                    'change',
                    function () {
                        input.value =
                            normalizeQuantity(
                                input,
                                input.value
                            );

                        refreshSummary();
                    }
                );

                input.addEventListener(
                    'input',
                    refreshSummary
                );
            }
        );

        form.addEventListener(
            'submit',
            function (event) {
                let hasTickets = false;

                cards.forEach(
                    function (card) {
                        const input =
                            card.querySelector(
                                '[data-quantity-input]'
                            );

                        if (
                            input
                            && Number(input.value) > 0
                        ) {
                            hasTickets = true;
                        }
                    }
                );

                if (! hasTickets) {
                    event.preventDefault();

                    window.alert(
                        'Please select at least one ticket.'
                    );
                }
            }
        );

        form.refreshTicketSummary = refreshSummary;
        refreshSummary();
    }

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePublicTicketCheckout, { once: true });
} else {
    initializePublicTicketCheckout();
}

// Browser back/forward cache and restored form values may differ from HTML defaults.
window.addEventListener('pageshow', initializePublicTicketCheckout);
