<div class="pos-tab-content">
    <div class="casherp-smtp-tab-heading">
        <div>
            <span class="casherp-smtp-eyebrow">Company email delivery</span>
            <h3>SMTP configuration</h3>
            <p>Send invoices, receipts, reminders and operational notifications from this company&rsquo;s trusted email service.</p>
        </div>
        <span class="casherp-smtp-security-badge"><i class="fa fa-lock" aria-hidden="true"></i> Encrypted credentials</span>
    </div>

    <div
        id="casherp-smtp-settings-root"
        data-show-url="{{ route('business.smtp-settings.show') }}"
        data-update-url="{{ route('business.smtp-settings.update') }}"
        data-test-url="{{ route('business.smtp-settings.test') }}"
    >
        <div class="casherp-smtp-loading" role="status">
            <i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i>
            Loading secure email settings&hellip;
        </div>
    </div>

    <noscript>
        <div class="alert alert-warning">
            JavaScript is required to manage encrypted company email settings. Existing delivery settings remain unchanged.
        </div>
    </noscript>
</div>
