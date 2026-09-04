@php
    $advanceGuideId = $advanceGuideId ?? 'advance-deposit-guidance';
    $advanceGuideTotalSelector = $advanceGuideTotalSelector ?? '';
    $advanceGuidePaidSelector = $advanceGuidePaidSelector ?? '';
    $advanceGuideScenarioSelector = $advanceGuideScenarioSelector ?? '';
    $advanceGuideScenarios = $advanceGuideScenarios ?? [];
    $advanceGuideContext = $advanceGuideContext ?? 'reservation or invoice';
@endphp

<div
    id="{{ $advanceGuideId }}"
    class="callout callout-warning js-advance-deposit-guide"
    role="note"
    aria-live="polite"
    data-total-selector="{{ $advanceGuideTotalSelector }}"
    data-paid-selector="{{ $advanceGuidePaidSelector }}"
    data-scenario-selector="{{ $advanceGuideScenarioSelector }}"
    data-scenarios="{{ implode(',', $advanceGuideScenarios) }}"
>
    <h4><i class="fa fa-info-circle"></i> 50% Payment Deposit (Advance) guide — advisory only</h4>
    <p>
        For this {{ $advanceGuideContext }}, the recommended company control is to collect
        at least <strong>50% of the agreed total</strong> before confirming the reservation.
        CashERP displays this guidance but does not block saving, invoicing, or reservation
        because authorized company policy, contract terms, channels, and local requirements may differ.
    </p>
    <p>
        <strong>Do not include a Security Deposit (Refundable) in the 50% calculation.</strong>
        A Security Deposit protects against damage or loss and remains separate from the
        Payment Deposit that reduces the customer balance.
    </p>
    @if($advanceGuideTotalSelector && $advanceGuidePaidSelector)
        <p class="advance-guide-calculation text-muted" style="margin-bottom:0">
            Total: <strong data-guide-total>0.00</strong>
            &nbsp;|&nbsp; Recommended minimum: <strong data-guide-minimum>0.00</strong>
            &nbsp;|&nbsp; Payment Deposit entered: <strong data-guide-paid>0.00</strong>
            &nbsp;|&nbsp; <strong data-guide-status>Enter the reservation or invoice total.</strong>
        </p>
    @endif
</div>

@once
    <script>
        (function () {
            function numericValue(element) {
                if (!element) return 0;
                var raw = typeof element.value !== 'undefined' ? element.value : element.textContent;
                raw = String(raw || '').replace(/[^0-9,.-]/g, '');
                if (raw.indexOf(',') !== -1 && raw.indexOf('.') === -1) raw = raw.replace(',', '.');
                else raw = raw.replace(/,/g, '');
                var value = parseFloat(raw);
                return isFinite(value) ? value : 0;
            }

            function money(value) {
                return Number(value || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            window.refreshAdvanceDepositGuides = function () {
                document.querySelectorAll('.js-advance-deposit-guide').forEach(function (guide) {
                    var scenarioSelector = guide.getAttribute('data-scenario-selector');
                    var allowed = String(guide.getAttribute('data-scenarios') || '')
                        .split(',').filter(Boolean);
                    if (scenarioSelector && allowed.length) {
                        var scenario = document.querySelector(scenarioSelector);
                        guide.style.display = scenario && allowed.indexOf(scenario.value) !== -1 ? '' : 'none';
                        if (guide.style.display === 'none') return;
                    }

                    var totalSelector = guide.getAttribute('data-total-selector');
                    var paidSelector = guide.getAttribute('data-paid-selector');
                    if (!totalSelector || !paidSelector) return;

                    var total = numericValue(document.querySelector(totalSelector));
                    var paid = 0;
                    document.querySelectorAll(paidSelector).forEach(function (element) {
                        if (!element.disabled) paid += numericValue(element);
                    });
                    var minimum = Math.max(0, total * 0.5);
                    var percent = total > 0 ? (paid / total) * 100 : 0;
                    var status = guide.querySelector('[data-guide-status]');

                    guide.querySelector('[data-guide-total]').textContent = money(total);
                    guide.querySelector('[data-guide-minimum]').textContent = money(minimum);
                    guide.querySelector('[data-guide-paid]').textContent = money(paid);
                    guide.classList.remove('callout-warning', 'callout-danger', 'callout-success');

                    if (total <= 0) {
                        guide.classList.add('callout-warning');
                        status.textContent = 'Enter the reservation or invoice total.';
                    } else if (paid + 0.0001 < minimum) {
                        guide.classList.add('callout-danger');
                        status.textContent = money(minimum - paid) + ' below the 50% guide (' + percent.toFixed(1) + '% entered). You may still continue.';
                    } else {
                        guide.classList.add('callout-success');
                        status.textContent = '50% guide reached (' + percent.toFixed(1) + '% entered).';
                    }
                });
            };

            document.addEventListener('input', window.refreshAdvanceDepositGuides);
            document.addEventListener('change', window.refreshAdvanceDepositGuides);
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', window.refreshAdvanceDepositGuides);
            } else {
                window.refreshAdvanceDepositGuides();
            }
        }());
    </script>
@endonce
