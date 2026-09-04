import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';

const EMPTY_SETTINGS = {
    use_system_settings: false,
    provider: 'custom',
    host: '',
    port: 587,
    username: '',
    password: '',
    password_configured: false,
    clear_password: false,
    encryption: 'tls',
    from_address: '',
    from_name: '',
    timeout: 15,
};

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function api(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
    });

    let body = {};
    try {
        body = await response.json();
    } catch (error) {
        body = { message: 'CashERP received an unexpected server response.' };
    }

    if (!response.ok) {
        const exception = new Error(body.message || 'The request could not be completed.');
        exception.payload = body;
        exception.status = response.status;
        throw exception;
    }

    return body;
}

function FieldError({ errors, name }) {
    const messages = errors[name];
    if (!messages) return null;
    return <span className="casherp-field-error">{Array.isArray(messages) ? messages[0] : messages}</span>;
}

function StatusBanner({ result, onDismiss }) {
    if (!result) return null;
    return (
        <div className={`casherp-smtp-alert is-${result.type}`} role={result.type === 'error' ? 'alert' : 'status'}>
            <i className={`fa ${result.type === 'success' ? 'fa-check-circle' : result.type === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle'}`} aria-hidden="true" />
            <div>
                <strong>{result.title}</strong>
                <p>{result.message}</p>
                {result.reference && <small>Support reference: {result.reference}</small>}
            </div>
            <button type="button" className="casherp-alert-close" onClick={onDismiss} aria-label="Dismiss message">&times;</button>
        </div>
    );
}

function LastEvent({ event }) {
    if (!event) {
        return (
            <div className="casherp-smtp-history is-neutral">
                <i className="fa fa-info-circle" aria-hidden="true" />
                <div><strong>No delivery check recorded</strong><span>Save your settings, then send a test email.</span></div>
            </div>
        );
    }

    const success = event.status === 'success';
    const happened = event.occurred_at ? new Date(event.occurred_at).toLocaleString() : 'Recently';
    return (
        <div className={`casherp-smtp-history ${success ? 'is-success' : 'is-failure'}`}>
            <i className={`fa ${success ? 'fa-check-circle' : 'fa-exclamation-circle'}`} aria-hidden="true" />
            <div>
                <strong>{event.action === 'test_email' ? (success ? 'Last test succeeded' : 'Last test needs attention') : 'Configuration saved'}</strong>
                <span>{happened}{event.duration_ms ? ` · ${event.duration_ms} ms` : ''}{event.recipient_masked ? ` · ${event.recipient_masked}` : ''}</span>
            </div>
        </div>
    );
}

function SmtpSettingsApp({ endpoints }) {
    const [settings, setSettings] = useState(EMPTY_SETTINGS);
    const [baseline, setBaseline] = useState(EMPTY_SETTINGS);
    const [presets, setPresets] = useState({});
    const [canUseSystem, setCanUseSystem] = useState(false);
    const [systemSender, setSystemSender] = useState('');
    const [testRecipient, setTestRecipient] = useState('');
    const [lastEvent, setLastEvent] = useState(null);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState('');
    const [errors, setErrors] = useState({});
    const [result, setResult] = useState(null);
    const [showPassword, setShowPassword] = useState(false);

    const dirty = useMemo(() => JSON.stringify(settings) !== JSON.stringify(baseline), [settings, baseline]);
    const usingSystem = Boolean(settings.use_system_settings);
    const currentPreset = presets[settings.provider] || presets.custom || null;

    useEffect(() => {
        let active = true;
        api(endpoints.show)
            .then((response) => {
                if (!active) return;
                const payload = response.data;
                const next = { ...EMPTY_SETTINGS, ...payload.settings, password: '', clear_password: false };
                setSettings(next);
                setBaseline(next);
                setPresets(payload.provider_presets || {});
                setCanUseSystem(Boolean(payload.can_use_system_settings));
                setSystemSender(payload.system_sender || '');
                setTestRecipient(payload.default_test_recipient || payload.settings.from_address || '');
                setLastEvent(payload.last_event || null);
            })
            .catch((error) => setResult({ type: 'error', title: 'Settings could not be loaded', message: error.message }))
            .finally(() => active && setLoading(false));
        return () => { active = false; };
    }, [endpoints.show]);

    useEffect(() => {
        const warn = (event) => {
            if (!dirty) return;
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', warn);
        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);

    function setField(name, value) {
        setSettings((current) => ({ ...current, [name]: value }));
        setErrors((current) => {
            if (!current[name]) return current;
            const next = { ...current };
            delete next[name];
            return next;
        });
    }

    function chooseProvider(provider) {
        const preset = presets[provider];
        setSettings((current) => ({
            ...current,
            provider,
            host: preset?.host || (provider === 'custom' ? current.host : ''),
            port: preset?.port || current.port || 587,
            encryption: preset?.encryption ?? current.encryption,
        }));
    }

    function payload() {
        return {
            ...settings,
            port: Number(settings.port),
            timeout: Number(settings.timeout),
            test_recipient: testRecipient,
        };
    }

    function applyServerErrors(error) {
        const serverErrors = error.payload?.errors || {};
        setErrors(serverErrors);
        const first = Object.values(serverErrors).flat()[0];
        setResult({
            type: 'error',
            title: error.status === 429 ? 'Please wait before trying again' : 'Check the highlighted settings',
            message: first || error.message,
            reference: error.payload?.correlation_id,
        });
    }

    async function save() {
        setBusy('save');
        setErrors({});
        setResult(null);
        try {
            const response = await api(endpoints.update, { method: 'PUT', body: JSON.stringify(payload()) });
            const next = { ...EMPTY_SETTINGS, ...response.data.settings, password: '', clear_password: false };
            setSettings(next);
            setBaseline(next);
            setLastEvent(response.data.last_event || null);
            setResult({ type: 'success', title: 'Email settings saved', message: response.message, reference: response.correlation_id });
        } catch (error) {
            applyServerErrors(error);
        } finally {
            setBusy('');
        }
    }

    async function sendTest() {
        setBusy('test');
        setErrors({});
        setResult(null);
        try {
            const response = await api(endpoints.test, { method: 'POST', body: JSON.stringify(payload()) });
            setLastEvent({
                action: 'test_email',
                status: 'success',
                recipient_masked: testRecipient,
                duration_ms: response.duration_ms,
                occurred_at: new Date().toISOString(),
                correlation_id: response.correlation_id,
            });
            setResult({ type: 'success', title: 'Delivery test succeeded', message: response.message, reference: response.correlation_id });
        } catch (error) {
            setLastEvent({
                action: 'test_email',
                status: 'failure',
                failure_category: error.payload?.failure_category,
                occurred_at: new Date().toISOString(),
                correlation_id: error.payload?.correlation_id,
            });
            applyServerErrors(error);
        } finally {
            setBusy('');
        }
    }

    if (loading) {
        return <div className="casherp-smtp-loading" role="status"><i className="fa fa-circle-o-notch fa-spin" aria-hidden="true" /> Loading secure email settings&hellip;</div>;
    }

    return (
        <div className="casherp-smtp-app">
            <StatusBanner result={result} onDismiss={() => setResult(null)} />

            <div className="casherp-smtp-layout">
                <main>
                    <section className="casherp-smtp-card" aria-labelledby="delivery-source-title">
                        <div className="casherp-smtp-card-heading">
                            <span className="casherp-step">1</span>
                            <div><h4 id="delivery-source-title">Choose the delivery source</h4><p>Use CashERP&rsquo;s shared sender when available, or connect this company&rsquo;s own SMTP service.</p></div>
                        </div>
                        <div className="casherp-delivery-options">
                            {canUseSystem && (
                                <label className={`casherp-choice ${usingSystem ? 'is-selected' : ''}`}>
                                    <input type="radio" name="delivery_source" checked={usingSystem} onChange={() => setField('use_system_settings', true)} />
                                    <span className="casherp-choice-icon"><i className="fa fa-cloud" aria-hidden="true" /></span>
                                    <span><strong>CashERP managed email</strong><small>Ready to use{systemSender ? ` · ${systemSender}` : ''}</small></span>
                                    <i className="fa fa-check-circle casherp-choice-check" aria-hidden="true" />
                                </label>
                            )}
                            <label className={`casherp-choice ${!usingSystem ? 'is-selected' : ''}`}>
                                <input type="radio" name="delivery_source" checked={!usingSystem} onChange={() => setField('use_system_settings', false)} />
                                <span className="casherp-choice-icon"><i className="fa fa-server" aria-hidden="true" /></span>
                                <span><strong>Company SMTP service</strong><small>Send from your own verified domain</small></span>
                                <i className="fa fa-check-circle casherp-choice-check" aria-hidden="true" />
                            </label>
                        </div>
                        <FieldError errors={errors} name="use_system_settings" />
                    </section>

                    {!usingSystem && (
                        <section className="casherp-smtp-card" aria-labelledby="server-details-title">
                            <div className="casherp-smtp-card-heading">
                                <span className="casherp-step">2</span>
                                <div><h4 id="server-details-title">Server and sender details</h4><p>Select a provider preset or enter the exact values supplied by your mail administrator.</p></div>
                            </div>

                            <div className="casherp-field-grid">
                                <div className="casherp-form-field is-wide">
                                    <label htmlFor="smtp-provider">Email provider</label>
                                    <select id="smtp-provider" value={settings.provider} onChange={(event) => chooseProvider(event.target.value)}>
                                        {Object.entries(presets).map(([key, preset]) => <option value={key} key={key}>{preset.label}</option>)}
                                    </select>
                                    {currentPreset?.help_url && <a className="casherp-provider-help" href={currentPreset.help_url} target="_blank" rel="noopener noreferrer">Open provider setup guide <i className="fa fa-external-link" aria-hidden="true" /></a>}
                                    <FieldError errors={errors} name="provider" />
                                </div>

                                <div className="casherp-form-field is-wide">
                                    <label htmlFor="smtp-host">SMTP hostname <span aria-hidden="true">*</span></label>
                                    <input id="smtp-host" value={settings.host} onChange={(event) => setField('host', event.target.value)} placeholder="smtp.example.com" autoComplete="off" />
                                    <small>Hostname only—do not include https:// or a path.</small>
                                    <FieldError errors={errors} name="host" />
                                </div>
                                <div className="casherp-form-field">
                                    <label htmlFor="smtp-port">Port <span aria-hidden="true">*</span></label>
                                    <input id="smtp-port" type="number" min="1" max="65535" value={settings.port} onChange={(event) => setField('port', event.target.value)} inputMode="numeric" />
                                    <FieldError errors={errors} name="port" />
                                </div>
                                <div className="casherp-form-field">
                                    <label htmlFor="smtp-encryption">Connection security</label>
                                    <select id="smtp-encryption" value={settings.encryption || ''} onChange={(event) => {
                                        const encryption = event.target.value;
                                        setSettings((current) => ({
                                            ...current,
                                            encryption,
                                            port: encryption === 'ssl' && [25, 587].includes(Number(current.port)) ? 465 : (encryption === 'tls' && Number(current.port) === 465 ? 587 : current.port),
                                        }));
                                    }}>
                                        <option value="tls">TLS / STARTTLS (recommended)</option>
                                        <option value="ssl">SSL (usually port 465)</option>
                                        <option value="">None (not recommended)</option>
                                    </select>
                                    <FieldError errors={errors} name="encryption" />
                                </div>
                                <div className="casherp-form-field is-wide">
                                    <label htmlFor="smtp-username">Username</label>
                                    <input id="smtp-username" value={settings.username} onChange={(event) => setField('username', event.target.value)} autoCapitalize="none" autoComplete="username" />
                                    <FieldError errors={errors} name="username" />
                                </div>
                                <div className="casherp-form-field is-wide">
                                    <label htmlFor="smtp-password">Password or app password</label>
                                    <div className="casherp-password-wrap">
                                        <input id="smtp-password" type={showPassword ? 'text' : 'password'} value={settings.password} onChange={(event) => setField('password', event.target.value)} placeholder={settings.password_configured ? 'Saved securely · leave blank to keep it' : 'Enter SMTP password'} autoComplete="new-password" />
                                        <button type="button" onClick={() => setShowPassword((value) => !value)} aria-label={showPassword ? 'Hide password' : 'Show password'}><i className={`fa ${showPassword ? 'fa-eye-slash' : 'fa-eye'}`} aria-hidden="true" /></button>
                                    </div>
                                    {settings.password_configured && <label className="casherp-inline-check"><input type="checkbox" checked={settings.clear_password} onChange={(event) => setField('clear_password', event.target.checked)} /> Remove saved password</label>}
                                    <FieldError errors={errors} name="password" />
                                </div>
                                <div className="casherp-form-field is-wide">
                                    <label htmlFor="smtp-from-address">From email <span aria-hidden="true">*</span></label>
                                    <input id="smtp-from-address" type="email" value={settings.from_address} onChange={(event) => setField('from_address', event.target.value)} placeholder="accounts@yourcompany.com" autoComplete="email" />
                                    <small>This address should be verified by your provider.</small>
                                    <FieldError errors={errors} name="from_address" />
                                </div>
                                <div className="casherp-form-field is-wide">
                                    <label htmlFor="smtp-from-name">Sender name <span aria-hidden="true">*</span></label>
                                    <input id="smtp-from-name" value={settings.from_name} onChange={(event) => setField('from_name', event.target.value)} placeholder="Your company name" />
                                    <FieldError errors={errors} name="from_name" />
                                </div>
                                <div className="casherp-form-field">
                                    <label htmlFor="smtp-timeout">Connection timeout</label>
                                    <div className="casherp-input-suffix"><input id="smtp-timeout" type="number" min="5" max="60" value={settings.timeout} onChange={(event) => setField('timeout', event.target.value)} /><span>seconds</span></div>
                                    <FieldError errors={errors} name="timeout" />
                                </div>
                            </div>
                        </section>
                    )}

                    <section className="casherp-smtp-card" aria-labelledby="test-title">
                        <div className="casherp-smtp-card-heading">
                            <span className="casherp-step">{usingSystem ? '2' : '3'}</span>
                            <div><h4 id="test-title">Verify delivery</h4><p>The test uses the values currently shown. Saving is not required first.</p></div>
                        </div>
                        <LastEvent event={lastEvent} />
                        <div className="casherp-test-row">
                            <div className="casherp-form-field">
                                <label htmlFor="smtp-test-recipient">Test recipient</label>
                                <input id="smtp-test-recipient" type="email" value={testRecipient} onChange={(event) => { setTestRecipient(event.target.value); setErrors((current) => ({ ...current, test_recipient: undefined })); }} placeholder="you@example.com" />
                                <FieldError errors={errors} name="test_recipient" />
                            </div>
                            <button type="button" className="casherp-btn is-secondary" onClick={sendTest} disabled={Boolean(busy)}>
                                <i className={`fa ${busy === 'test' ? 'fa-circle-o-notch fa-spin' : 'fa-paper-plane'}`} aria-hidden="true" />
                                {busy === 'test' ? 'Sending test…' : 'Send test email'}
                            </button>
                        </div>
                    </section>

                    <div className="casherp-smtp-actions">
                        <span className={dirty ? 'is-unsaved' : 'is-saved'}><i className={`fa ${dirty ? 'fa-circle' : 'fa-check-circle'}`} aria-hidden="true" /> {dirty ? 'Unsaved email changes' : 'Email settings are saved'}</span>
                        <button type="button" className="casherp-btn is-primary" onClick={save} disabled={Boolean(busy) || !dirty}>
                            <i className={`fa ${busy === 'save' ? 'fa-circle-o-notch fa-spin' : 'fa-lock'}`} aria-hidden="true" />
                            {busy === 'save' ? 'Saving securely…' : 'Save email settings'}
                        </button>
                    </div>
                </main>

                <aside className="casherp-smtp-guide" aria-label="Email delivery guidance">
                    <div className="casherp-guide-card">
                        <h4><i className="fa fa-shield" aria-hidden="true" /> Deliverability checklist</h4>
                        <ol>
                            <li><span>1</span><div><strong>Verify the sender</strong><p>Approve the From address or domain with your mail provider.</p></div></li>
                            <li><span>2</span><div><strong>Publish SPF and DKIM</strong><p>Add the DNS records issued by the provider to prevent spoofing.</p></div></li>
                            <li><span>3</span><div><strong>Add a DMARC policy</strong><p>Start in monitoring mode, then strengthen it after reviewing reports.</p></div></li>
                            <li><span>4</span><div><strong>Use an app password</strong><p>Do not enter your normal mailbox password when multi-factor authentication is enabled.</p></div></li>
                        </ol>
                    </div>
                    <div className="casherp-guide-card is-note">
                        <h4><i className="fa fa-info-circle" aria-hidden="true" /> Safe by design</h4>
                        <p>Passwords are encrypted before storage and are never returned to this page. Private-network SMTP targets are blocked by default.</p>
                    </div>
                </aside>
            </div>
        </div>
    );
}

const rootElement = document.getElementById('casherp-smtp-settings-root');
if (rootElement) {
    createRoot(rootElement).render(<SmtpSettingsApp endpoints={{
        show: rootElement.dataset.showUrl,
        update: rootElement.dataset.updateUrl,
        test: rootElement.dataset.testUrl,
    }} />);
}
