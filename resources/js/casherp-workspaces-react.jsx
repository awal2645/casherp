import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';

let csrfToken = '';
let workspaceContext = null;

async function api(url, options = {}) {
    const isForm = options.body instanceof FormData;
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            ...(!isForm ? { 'Content-Type': 'application/json' } : {}),
            ...(options.headers || {}),
        },
    });
    let payload;
    try { payload = await response.json(); } catch { payload = { message: 'CashERP received an unexpected response.' }; }
    if (!response.ok) {
        const first = payload.errors ? Object.values(payload.errors).flat()[0] : null;
        const error = new Error(first || payload.message || 'The request could not be completed.');
        error.status = response.status;
        throw error;
    }
    return payload;
}

function useWorkspace(url) {
    const [state, setState] = useState({ loading: true, data: null, error: '' });
    const load = async () => {
        setState(current => ({ ...current, loading: true, error: '' }));
        try {
            const payload = await api(url);
            csrfToken = payload.data?.csrf_token || csrfToken;
            workspaceContext = payload.data?.workspace || workspaceContext;
            setState({ loading: false, data: payload.data, error: '' });
        } catch (error) {
            if (error.status === 401 || error.status === 419) window.location.assign('/login');
            else setState({ loading: false, data: null, error: error.message });
        }
    };
    useEffect(() => { load(); }, [url]);
    return { ...state, reload: load };
}

function objectFromForm(form) {
    const result = {};
    new FormData(form).forEach((value, key) => {
        if (key.endsWith('[]')) {
            const clean = key.slice(0, -2);
            result[clean] = [...(result[clean] || []), value];
        } else result[key] = value;
    });
    return result;
}

function fullName(user) {
    return [user?.surname, user?.first_name, user?.last_name].filter(Boolean).join(' ') || 'CashERP user';
}

function Button({ children, primary = false, danger = false, ...props }) {
    return <button className={`cw-btn ${primary ? 'cw-btn-primary' : ''} ${danger ? 'cw-btn-danger' : ''}`} {...props}>{children}</button>;
}

function Modal({ title, onClose, children, wide = false }) {
    const dialogRef = useRef(null);
    useEffect(() => {
        const previous = document.activeElement;
        const oldOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        const focusable = () => [...(dialogRef.current?.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])') || [])];
        window.setTimeout(() => focusable()[0]?.focus(), 0);
        const close = event => {
            if (event.key === 'Escape') onClose();
            if (event.key === 'Tab') {
                const items = focusable(); if (!items.length) return;
                const first = items[0], last = items[items.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        };
        window.addEventListener('keydown', close);
        return () => { window.removeEventListener('keydown', close); document.body.style.overflow = oldOverflow; previous?.focus?.(); };
    }, [onClose]);
    return <div className="cw-modal-backdrop" role="presentation" onMouseDown={event => event.target === event.currentTarget && onClose()}>
        <div ref={dialogRef} className={`cw-modal ${wide ? 'is-wide' : ''}`} role="dialog" aria-modal="true" aria-label={title}>
            <div className="cw-card-head"><h2 className="cw-card-title">{title}</h2><button className="cw-icon-btn" onClick={onClose} aria-label="Close">×</button></div>
            <div className="cw-card-body">{children}</div>
        </div>
    </div>;
}

function Empty({ icon = 'fa-inbox', title, text }) {
    return <div className="cw-empty"><i className={`fa ${icon}`} aria-hidden="true"/><strong>{title}</strong><p>{text}</p></div>;
}

function Alert({ type = 'error', children, onClose }) {
    if (!children) return null;
    return <div className={`cw-alert is-${type}`} role={type === 'error' ? 'alert' : 'status'}><span>{children}</span>{onClose && <button onClick={onClose}>×</button>}</div>;
}

function WorkspaceFrame({ children, area, workspace = workspaceContext }) {
    const active = workspace?.active_business;
    return <div className="cw-app">
        <header className="cw-app-header">
            <a className="cw-logo" href="/home"><span>C</span><strong>CashERP</strong></a>
            <nav aria-label="Workspace navigation">
                {(workspace?.navigation || []).map(item => <a key={item.key} className={area === item.key ? 'active' : ''} href={item.url}><i className={`fa ${item.icon}`}/> {item.label}</a>)}
            </nav>
            <div className="cw-company-context">
                <span><small>Active company</small><strong>{active?.name || 'CashERP'}</strong><em>{active?.industry?.name || 'Industry not assigned'}</em></span>
                {workspace?.businesses?.length > 1 && <form method="post" action={workspace.switch_url}><input type="hidden" name="_token" value={csrfToken}/><select name="business_id" value={active?.id || ''} onChange={event => event.currentTarget.form.submit()} aria-label="Switch active company">{workspace.businesses.map(company => <option value={company.id} key={company.id}>{company.name} · {company.industry?.name || 'No industry'}</option>)}</select></form>}
            </div>
            <HeaderNotifications workspace={workspace}/>
            {area !== 'home' && <a className="cw-back" href={workspace?.home_url || '/home'}><i className="fa fa-th-large"/> Main ERP</a>}
            <details className="cw-user-menu"><summary aria-label="Open user menu"><span>{workspace?.user?.name?.split(' ').map(part=>part[0]).join('').slice(0,2)||'CU'}</span></summary><div><strong>{workspace?.user?.name||'CashERP user'}</strong><a href={workspace?.profile_url||'/user/profile'}><i className="fa fa-user-o"/> My profile</a><form method="post" action={workspace?.logout_url||'/logout'}><input type="hidden" name="_token" value={csrfToken}/><button type="submit"><i className="fa fa-sign-out"/> Sign out</button></form></div></details>
        </header>
        {children}
    </div>;
}

function HeaderNotifications({workspace}) {
    const [open,setOpen]=useState(false),[items,setItems]=useState([]),[unread,setUnread]=useState(workspace?.unread_notifications||0),[criticalUnread,setCriticalUnread]=useState(0),[loading,setLoading]=useState(false),[error,setError]=useState(''),[filter,setFilter]=useState('all'),[settingsOpen,setSettingsOpen]=useState(false),[preferences,setPreferences]=useState(null),[policy,setPolicy]=useState(null),[saving,setSaving]=useState(false),[notice,setNotice]=useState('');
    const panel=useRef(null);
    const load=async(currentFilter=filter)=>{if(!workspace?.notifications_url)return;setLoading(true);setError('');try{const query=new URLSearchParams({per_page:'10'});if(currentFilter==='unread')query.set('unread_only','1');if(currentFilter==='critical')query.set('severity','critical');const result=await api(`${workspace.notifications_url}?${query}`);setItems(result.data||[]);setUnread(result.meta?.unread||0);setCriticalUnread(result.meta?.critical_unread||0);}catch(e){setError(e.message)}finally{setLoading(false)}};
    useEffect(()=>{if(open)load(filter);},[open,filter]);
    useEffect(()=>{const close=e=>{if(open&&!settingsOpen&&panel.current&&!panel.current.contains(e.target))setOpen(false)};document.addEventListener('pointerdown',close);return()=>document.removeEventListener('pointerdown',close)},[open,settingsOpen]);
    const visit=async item=>{if(!item.read){try{await api(`/notifications/in-app/${item.id}/read`,{method:'PATCH',body:'{}'});setUnread(value=>Math.max(0,value-1));if(item.severity==='critical')setCriticalUnread(value=>Math.max(0,value-1));}catch{}}if(item.link&&item.link!=='#')window.location.assign(item.link);else load(filter);};
    const readAll=async()=>{try{await api(workspace.notifications_read_all_url,{method:'PATCH',body:'{}'});setItems(current=>current.map(item=>({...item,read:true})));setUnread(0);setCriticalUnread(0);}catch(e){setError(e.message)}};
    const openSettings=async()=>{setSettingsOpen(true);setNotice('');setError('');try{const result=await api('/notifications/preferences');setPreferences(result.data);if(result.data.can_manage_company_policy){const company=await api('/notifications/settings');setPolicy(company.data);}}catch(e){setError(e.message)}};
    const savePreferences=async()=>{setSaving(true);setError('');try{await api('/notifications/preferences',{method:'PUT',body:JSON.stringify({database_enabled:!!preferences.database_enabled,email_enabled:!!preferences.email_enabled,digest:'immediate',timezone:preferences.timezone})});setNotice('Your delivery preferences were saved.');}catch(e){setError(e.message)}finally{setSaving(false)}};
    const updatePolicy=(key,field,value)=>setPolicy(current=>({...current,catalog:{...current.catalog,events:current.catalog.events.map(event=>event.key===key?{...event,[field]:value}:event)}}));
    const savePolicy=async()=>{setSaving(true);setError('');try{const result=await api('/notifications/settings',{method:'PUT',body:JSON.stringify({events:policy.catalog.events.map(({key,is_enabled,database_enabled,email_enabled,lead_days,overdue_after_hours})=>({key,is_enabled,database_enabled,email_enabled,lead_days,overdue_after_hours}))})});setPolicy(current=>({...current,catalog:result.data}));setNotice('Company notification policy was saved.');}catch(e){setError(e.message)}finally{setSaving(false)}};
    return <div className="cw-notifications" ref={panel}>
        <button className="cw-header-icon" onClick={()=>setOpen(value=>!value)} aria-expanded={open} aria-label={`${unread} unread notifications`}><i className="fa fa-bell-o"/>{unread>0&&<b>{unread>99?'99+':unread}</b>}</button>
        {open&&<div className="cw-notification-panel" role="dialog" aria-label="Notifications">
            <div className="cw-notification-head"><span><strong>Notifications</strong><small>{unread} unread{criticalUnread>0?` · ${criticalUnread} critical`:''}</small></span>{unread>0&&<button onClick={readAll}>Mark all read</button>}</div>
            <div className="cw-notification-filters" role="tablist" aria-label="Filter notifications">{[['all','All'],['unread','Unread'],['critical','Critical']].map(([key,label])=><button key={key} role="tab" aria-selected={filter===key} className={filter===key?'active':''} onClick={()=>setFilter(key)}>{label}</button>)}</div>
            {error&&!settingsOpen&&<div className="cw-notification-error" role="alert">{error}<button onClick={()=>load(filter)}>Retry</button></div>}
            {loading?<div className="cw-mini-state"><i className="fa fa-circle-o-notch fa-spin"/> Loading…</div>:items.length===0?<div className="cw-mini-state"><i className="fa fa-bell-slash-o"/> No {filter==='all'?'new':filter} notifications.</div>:<div className="cw-notification-list">{items.map(item=><button key={item.id} className={`cw-notification-item is-${item.severity||'info'} ${item.read?'':'is-unread'}`} onClick={()=>visit(item)}><i className={item.icon_class||'fa fa-bell'}/><span><span className="cw-notification-meta"><em>{(item.category||'general').replace(/_/g,' ')}</em><time>{item.created_at_human}</time></span><strong>{item.title||'CashERP update'}</strong><small>{item.message||item.event||'Review this update in CashERP.'}</small>{item.due_at&&<time className="cw-notification-due">Due {formatDateTime(item.due_at)}</time>}</span></button>)}</div>}
            <div className="cw-notification-foot"><button onClick={openSettings}><i className="fa fa-sliders"/> Notification controls</button><small>Showing this active company only</small></div>
        </div>}
        {settingsOpen&&<Modal title="Notification controls" onClose={()=>{setSettingsOpen(false);setError('');setNotice('')}} wide>
            {error&&<Alert onClose={()=>setError('')}>{error}</Alert>}{notice&&<Alert type="success" onClose={()=>setNotice('')}>{notice}</Alert>}
            {!preferences?<Loading/>:<div className="cw-notification-settings">
                <section><div><h3>My delivery preferences</h3><p>These choices apply only to you in the active company. Critical in-app alerts always remain visible.</p></div><label className="cw-switch"><input type="checkbox" checked={!!preferences.database_enabled} onChange={e=>setPreferences({...preferences,database_enabled:e.target.checked})}/><span/> In-app notifications</label><label className="cw-switch"><input type="checkbox" checked={!!preferences.email_enabled} onChange={e=>setPreferences({...preferences,email_enabled:e.target.checked})}/><span/> Email notifications</label><Button primary disabled={saving} onClick={savePreferences}>{saving?'Saving…':'Save my preferences'}</Button></section>
                {policy&&<section className="cw-notification-policy"><div className="cw-policy-head"><div><h3>Company notification policy</h3><p>Company owners and authorised administrators can control optional events and email delivery. Critical alerts cannot be disabled.</p></div><span><b>{policy.health.open_events}</b> open · <b>{policy.health.critical_open}</b> critical · <b>{policy.health.failed_deliveries}</b> failed deliveries</span></div><div className="cw-policy-table"><div className="cw-policy-row is-head"><span>Event</span><span>Active</span><span>In app</span><span>Email</span></div>{policy.catalog.events.map(event=><div className={`cw-policy-row is-${event.severity}`} key={event.key}><span><strong>{event.label}</strong><small>{event.category.replace(/_/g,' ')} · {event.severity}{event.mandatory?' · required':''}</small></span>{['is_enabled','database_enabled','email_enabled'].map(field=><label className="cw-compact-check" key={field}><input type="checkbox" checked={!!event[field]} disabled={event.mandatory&&field!=='email_enabled'} onChange={e=>updatePolicy(event.key,field,e.target.checked)}/><span className="tw-sr-only">{field.replace(/_/g,' ')}</span></label>)}</div>)}</div><Button primary disabled={saving} onClick={savePolicy}>{saving?'Saving…':'Save company policy'}</Button></section>}
            </div>}
        </Modal>}
    </div>;
}

function HubNav({ active, canSettings = false }) {
    const links = [
        ['/company-hub', 'home', 'Home'], ['/company-hub/resources', 'resources', 'Knowledge & documents'],
        ['/company-hub/events', 'events', 'Calendar'], ['/company-hub/directory', 'directory', 'People'],
    ];
    if (canSettings || active === 'settings') links.push(['/company-hub/settings', 'settings', 'Settings']);
    return <nav className="cw-nav" aria-label="Company Hub sections">{links.map(([href, key, label]) => <a key={key} href={href} className={active === key ? 'active' : ''}>{label}</a>)}</nav>;
}

function PageHead({ eyebrow, title, subtitle, children }) {
    return <div className="cw-topbar"><div><div className="cw-eyebrow">{eyebrow}</div><h1 className="cw-title">{title}</h1><p className="cw-subtitle">{subtitle}</p></div>{children && <div className="cw-actions">{children}</div>}</div>;
}

function AudienceFields({ data }) {
    const [type, setType] = useState('company');
    const sources = { locations: data.locations, departments: data.departments, roles: data.roles, users: data.companyUsers };
    return <>
        <div className="form-group"><label>Audience</label><select name="audience_type" className="form-control" value={type} onChange={e => setType(e.target.value)}>
            <option value="company">Everyone in this company</option><option value="locations">Selected locations</option><option value="departments">Selected departments</option><option value="roles">Selected roles</option><option value="users">Selected people</option>
        </select></div>
        {type !== 'company' && <div className="form-group"><label>Select recipients</label><select className="form-control" name={`audience_${type === 'users' ? 'user' : type.slice(0, -1)}_ids[]`} multiple required size="5">
            {Object.entries(sources[type] || {}).map(([id, name]) => <option key={id} value={id}>{name}</option>)}
        </select><small className="cw-help">Use Ctrl or Command to select more than one.</small></div>}
    </>;
}

function HubFeed() {
    const query = window.location.search;
    const { data, loading, error, reload } = useWorkspace(`/company-hub${query}`);
    const [modal, setModal] = useState('');
    const [notice, setNotice] = useState(null);
    const [busy, setBusy] = useState(false);
    const submit = async (url, body, method = 'POST') => {
        setBusy(true); setNotice(null);
        try { const result = await api(url, { method, body: JSON.stringify(body) }); setNotice({ type: 'success', text: result.message }); setModal(''); await reload(); }
        catch (e) { setNotice({ type: 'error', text: e.message }); }
        finally { setBusy(false); }
    };
    useEffect(() => {
        if (!data?.posts || !('IntersectionObserver' in window)) return undefined;
        const observer = new IntersectionObserver(entries => entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            const uuid = entry.target.getAttribute('data-post-uuid');
            const unread = entry.target.getAttribute('data-unread') === '1';
            if (uuid && unread) api(`/company-hub/posts/${uuid}/opened`, { method: 'POST', body: '{}' }).catch(() => {});
            observer.unobserve(entry.target);
        }), { threshold: .55 });
        const timer = window.setTimeout(() => document.querySelectorAll('.cw-post[data-post-uuid]').forEach(node => observer.observe(node)), 50);
        return () => { window.clearTimeout(timer); observer.disconnect(); };
    }, [data]);
    if (loading) return <Loading/>;
    if (error) return <Failure error={error} retry={reload}/>;
    const target = new URLSearchParams(query).get('post');
    return <WorkspaceFrame area="hub"><main className="cw-page"><div className="cw-shell">
        <PageHead eyebrow="Company Hub" title="One company. One clear conversation." subtitle="Announcements, collaboration and company knowledge stay private to the active business.">
            {data.permissions.post && <Button primary onClick={() => setModal('post')}><i className="fa fa-plus"/> Create post</Button>}
        </PageHead><HubNav active="home" canSettings={data.permissions.manage_settings}/><Alert type={notice?.type} onClose={() => setNotice(null)}>{notice?.text}</Alert>
        <div className="cw-metrics">
            <Metric label="Needs acknowledgement" value={data.unacknowledged_count}/><Metric label="Visible channels" value={data.channels.length}/>
            <Metric label="Unread announcements" value={data.unread_announcement_count}/><Metric label="Privacy" value="Company scoped" compact/>
        </div>
        <div className="cw-grid">
            <aside className="cw-card cw-sticky"><div className="cw-card-head"><h2 className="cw-card-title">Channels</h2>{data.permissions.manage_channels && <button className="cw-icon-btn" onClick={() => setModal('channel')} aria-label="Add channel">+</button>}</div><div className="cw-card-body">
                <a className={`cw-channel ${!data.channel_id ? 'active' : ''}`} href="/company-hub"><span># All updates</span></a>
                {data.channels.map(channel => <div className="cw-channel-row" key={channel.id}><a className={`cw-channel ${Number(data.channel_id) === channel.id ? 'active' : ''}`} href={`/company-hub?channel=${channel.id}`}><span># {channel.name}</span><small>{channel.posts_count}</small></a>{data.permissions.manage_channels&&<button className="cw-row-action" title="Archive channel" onClick={()=>window.confirm(`Archive #${channel.name}? Existing posts remain retained.`)&&submit(`/company-hub/channels/${channel.uuid}/archive`,{},'PATCH')}><i className="fa fa-archive"/></button>}</div>)}
            </div></aside>
            <section>{data.posts.length === 0 ? <Empty icon="fa-comments-o" title="No updates here yet" text="Start a useful company conversation or publish the first announcement."/> : data.posts.map(post => <article id={`post-${post.uuid}`} data-post-uuid={post.uuid} data-unread={post.opened_by_me ? '0' : '1'} key={post.uuid} className={`cw-card cw-post ${target === post.uuid ? 'is-target' : ''}`}>
                <div className="cw-card-body"><div className="cw-post-meta"><span className="cw-avatar">{fullName(post.author).slice(0,2).toUpperCase()}</span><strong>{fullName(post.author)}</strong><span>{post.channel ? `# ${post.channel.name}` : 'Company-wide'}</span><span>{formatDate(post.published_at)}</span>
                    <span className={`cw-badge ${post.priority !== 'normal' ? `cw-badge-${post.priority}` : ''}`}>{post.type}</span>{post.acknowledgement_required && <span className="cw-badge cw-badge-success">Acknowledgement</span>}
                </div>{post.title && <h2>{post.title}</h2>}<div className="cw-post-body">{post.body}</div>
                {post.acknowledgement_required && !post.acknowledged_by_me && <div className="cw-inline-action"><Button primary disabled={busy} onClick={() => submit(`/company-hub/posts/${post.uuid}/acknowledge`, {})}><i className="fa fa-check"/> I have read this</Button></div>}
                {post.acknowledgement_required && post.acknowledged_by_me && <div className="cw-read-confirm"><i className="fa fa-check-circle"/> Acknowledged</div>}
                {post.comments_enabled && <div className="cw-comments">{post.comments?.map(comment => <div className="cw-comment" key={comment.id}><strong>{fullName(comment.user)}</strong><p>{comment.body}</p></div>)}
                    {data.permissions.comment && <form className="cw-comment-form" onSubmit={e => { e.preventDefault(); const body = new FormData(e.currentTarget).get('body'); submit(`/company-hub/posts/${post.uuid}/comments`, { body }); e.currentTarget.reset(); }}><input className="form-control" name="body" maxLength="5000" required placeholder="Add a constructive comment…"/><Button disabled={busy} type="submit">Send</Button></form>}
                </div>}
                {data.permissions.view_acknowledgements && post.acknowledgement_required && <small className="cw-help">{post.acknowledgement_count} acknowledgement(s) recorded</small>}
            </div></article>)}</section>
            <aside><div className="cw-card"><div className="cw-card-head"><h2 className="cw-card-title">Good internal practice</h2></div><div className="cw-card-body"><ul className="cw-list"><li><strong>Use announcements</strong><br/><small>for official, time-sensitive updates.</small></li><li><strong>Target carefully</strong><br/><small>by company, location, department, role or person.</small></li><li><strong>Keep sensitive records</strong><br/><small>inside their HR, payroll, hotel or property module.</small></li>{data.integrations?.project_tasks&&<li><strong>Assign structured work</strong><br/><small>Tasks reuse CashERP Projects instead of creating a duplicate task ledger.</small><br/><a className="cw-btn" href={data.integrations.project_tasks}>Open my tasks</a></li>}</ul></div></div></aside>
        </div>
        {modal === 'post' && <Modal title="Create Company Hub post" onClose={() => setModal('')} wide><form className="cw-form" onSubmit={e => { e.preventDefault(); const body = objectFromForm(e.currentTarget); body.comments_enabled = e.currentTarget.comments_enabled.checked; body.acknowledgement_required = e.currentTarget.acknowledgement_required.checked; body.is_pinned = e.currentTarget.is_pinned.checked; submit('/company-hub/posts', body); }}>
            <div className="cw-field-row"><div className="form-group"><label>Type</label><select className="form-control" name="type"><option value="discussion">Discussion</option>{data.permissions.publish_announcements && <option value="announcement">Official announcement</option>}<option value="recognition">Recognition</option></select></div><div className="form-group"><label>Priority</label><select className="form-control" name="priority"><option value="normal">Normal</option><option value="important">Important</option><option value="urgent">Urgent</option></select></div></div>
            <div className="form-group"><label>Channel</label><select className="form-control" name="channel_id"><option value="">No channel / company-wide</option>{data.channels.map(c => <option value={c.id} key={c.id}>{c.name}</option>)}</select></div>
            <div className="form-group"><label>Title</label><input className="form-control" name="title" maxLength="191" placeholder="Required for announcements"/></div><div className="form-group"><label>Message</label><textarea className="form-control" name="body" maxLength="20000" required/></div>
            <AudienceFields data={data}/><div className="cw-checks"><label><input type="checkbox" name="comments_enabled" defaultChecked/> Allow comments</label><label><input type="checkbox" name="acknowledgement_required"/> Require acknowledgement</label>{data.permissions.publish_announcements&&<label><input type="checkbox" name="is_pinned"/> Pin update</label>}</div>
            <div className="cw-modal-actions"><Button type="button" onClick={() => setModal('')}>Cancel</Button><Button primary disabled={busy} type="submit">{busy ? 'Publishing…' : 'Publish'}</Button></div>
        </form></Modal>}
        {modal === 'channel' && <Modal title="Create channel" onClose={() => setModal('')}><form className="cw-form" onSubmit={e => { e.preventDefault(); submit('/company-hub/channels', objectFromForm(e.currentTarget)); }}><div className="form-group"><label>Name</label><input name="name" className="form-control" required maxLength="120"/></div><div className="form-group"><label>Description</label><textarea name="description" className="form-control" maxLength="1000"/></div><div className="form-group"><label>Visibility</label><select name="type" className="form-control"><option value="open">Open to company</option><option value="private">Private membership</option><option value="announcement">Announcement channel</option></select></div><div className="form-group"><label>Initial members</label><select name="member_ids[]" className="form-control" multiple size="6">{Object.entries(data.companyUsers).map(([id,name]) => <option value={id} key={id}>{name}</option>)}</select></div><div className="cw-modal-actions"><Button type="button" onClick={() => setModal('')}>Cancel</Button><Button primary disabled={busy}>Create</Button></div></form></Modal>}
    </div></main></WorkspaceFrame>;
}

function Resources() {
    const { data, loading, error, reload } = useWorkspace('/company-hub/resources');
    const [open, setOpen] = useState(''); const [selected, setSelected] = useState(null); const [notice, setNotice] = useState(null); const [busy, setBusy] = useState(false);
    if (loading) return <Loading/>; if (error) return <Failure error={error} retry={reload}/>;
    const save = async (form, url='/company-hub/resources') => { setBusy(true); setNotice(null); try { const result = await api(url, { method: 'POST', body: new FormData(form) }); setNotice({type:'success',text:result.message}); setOpen(''); setSelected(null); await reload(); } catch(e){setNotice({type:'error',text:e.message});} finally{setBusy(false);} };
    return <WorkspaceFrame area="hub"><main className="cw-page"><div className="cw-shell"><PageHead eyebrow="Company Hub" title="Knowledge & documents" subtitle="A private, versioned source of truth for policies, guides, templates and internal files.">{data.canManage && <Button primary onClick={() => setOpen('new')}><i className="fa fa-plus"/> Add resource</Button>}</PageHead><HubNav active="resources" canSettings={data.can_settings}/><Alert type={notice?.type}>{notice?.text}</Alert>
        {data.resources.length === 0 ? <Empty icon="fa-folder-open-o" title="Your library is ready" text="Publish the first policy, guide, template or internal document."/> : <div className="cw-resource-grid">{data.resources.map(item => <article className="cw-card cw-resource" key={item.uuid}><div className="cw-card-body"><span className="cw-resource-icon"><i className={`fa ${item.type === 'knowledge' ? 'fa-lightbulb-o' : 'fa-file-text-o'}`}/></span><div className="cw-post-meta"><span className="cw-badge">{item.type}</span><span>v{item.version}</span><span>{item.status}</span></div><h2>{item.title}</h2><p>{item.summary || item.body?.slice(0,160) || 'Internal resource'}</p></div><div className="cw-card-foot"><div className="cw-actions">{item.file_path&&<a className="cw-btn" href={`/company-hub/resources/${item.uuid}/download`}><i className="fa fa-download"/> Download</a>}{((['knowledge','policy'].includes(item.type)&&data.permissions.manage_knowledge)||(!['knowledge','policy'].includes(item.type)&&data.permissions.manage_documents))&&<Button onClick={()=>{setSelected(item);setOpen('revise')}}><i className="fa fa-code-fork"/> New version</Button>}</div><small>{item.review_date ? `Review ${formatDate(item.review_date)}` : ''}</small></div></article>)}</div>}
        {open==='new' && <Modal title="Add internal resource" onClose={() => setOpen('')} wide><form className="cw-form" encType="multipart/form-data" onSubmit={e => {e.preventDefault(); save(e.currentTarget);}}><div className="cw-field-row"><div className="form-group"><label>Resource type</label><select name="type" className="form-control">{data.permissions.manage_knowledge&&<><option value="knowledge">Knowledge article</option><option value="policy">Policy</option></>}{data.permissions.manage_documents&&<><option value="document">Document</option><option value="template">Template</option></>}</select></div><div className="form-group"><label>Status</label><select name="status" className="form-control"><option value="published">Published</option><option value="draft">Draft</option></select></div></div><div className="form-group"><label>Title</label><input name="title" className="form-control" required maxLength="191"/></div><div className="form-group"><label>Summary</label><textarea name="summary" className="form-control" maxLength="2000"/></div><div className="form-group"><label>Article content</label><textarea name="body" className="form-control" maxLength="50000"/></div><div className="form-group"><label>Private file</label><input name="file" type="file" className="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.webp"/><small className="cw-help">Files are stored outside the public web directory.</small></div><AudienceFields data={data}/><div className="cw-modal-actions"><Button type="button" onClick={() => setOpen('')}>Cancel</Button><Button primary disabled={busy}>{busy?'Saving…':'Save resource'}</Button></div></form></Modal>}
        {open==='revise'&&selected&&<Modal title={`New version · ${selected.title}`} onClose={()=>setOpen('')} wide><form className="cw-form" encType="multipart/form-data" onSubmit={e=>{e.preventDefault();save(e.currentTarget,`/company-hub/resources/${selected.uuid}/revisions`)}}><div className="form-group"><label>Revision summary</label><textarea name="summary" className="form-control" defaultValue={selected.summary||''}/></div><div className="form-group"><label>Revised article content</label><textarea name="body" className="form-control" defaultValue={selected.body||''}/></div><div className="cw-field-row"><div className="form-group"><label>Status</label><select name="status" className="form-control" defaultValue="published"><option value="published">Published</option><option value="draft">Draft</option><option value="archived">Archived</option></select></div><div className="form-group"><label>Next review date</label><input name="review_date" type="date" className="form-control"/></div></div><div className="form-group"><label>Replacement file (optional)</label><input name="file" type="file" className="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.webp"/></div><div className="cw-modal-actions"><Button type="button" onClick={()=>setOpen('')}>Cancel</Button><Button primary disabled={busy}>{busy?'Publishing…':'Publish new version'}</Button></div></form></Modal>}
    </div></main></WorkspaceFrame>;
}

function Events() {
    const {data,loading,error,reload}=useWorkspace('/company-hub/events'); const[open,setOpen]=useState(false);const[notice,setNotice]=useState(null);const[busy,setBusy]=useState(false);
    if(loading)return <Loading/>;if(error)return <Failure error={error} retry={reload}/>;
    const submit=async form=>{setBusy(true);try{const result=await api('/company-hub/events',{method:'POST',body:JSON.stringify(objectFromForm(form))});setNotice({type:'success',text:result.message});setOpen(false);await reload();}catch(e){setNotice({type:'error',text:e.message});}finally{setBusy(false);}};
    const cancel=async event=>{if(!window.confirm(`Cancel ${event.title}?`))return;setBusy(true);try{const result=await api(`/company-hub/events/${event.uuid}/cancel`,{method:'PATCH',body:'{}'});setNotice({type:'success',text:result.message});await reload();}catch(e){setNotice({type:'error',text:e.message});}finally{setBusy(false);}};
    return <WorkspaceFrame area="hub"><main className="cw-page"><div className="cw-shell"><PageHead eyebrow="Company Hub" title="Internal calendar" subtitle="Coordinate meetings, training, deadlines and company moments without exposing customer bookings.">{data.can_manage&&<Button primary onClick={()=>setOpen(true)}><i className="fa fa-calendar-plus-o"/> Schedule event</Button>}</PageHead><HubNav active="events" canSettings={data.can_settings}/><Alert type={notice?.type}>{notice?.text}</Alert><div className="cw-grid-wide"><section className="cw-card"><div className="cw-card-head"><h2 className="cw-card-title">Upcoming events</h2></div><div className="cw-card-body">{data.events.length===0?<Empty icon="fa-calendar-o" title="No upcoming events" text="Use the calendar for internal company coordination."/>:<div className="cw-timeline">{data.events.map(event=><div className="cw-timeline-item" key={event.uuid}><strong>{event.title}</strong><div>{formatDateTime(event.starts_at)} — {formatDateTime(event.ends_at)}</div><small>{event.location_text||'Online / location to be confirmed'} · {fullName(event.owner)}</small>{data.can_manage&&<button className="cw-link-btn cw-danger-link" disabled={busy} onClick={()=>cancel(event)}>Cancel event</button>}</div>)}</div>}</div></section><aside className="cw-card"><div className="cw-card-head"><h2 className="cw-card-title">Calendar boundary</h2></div><div className="cw-card-body"><p>This calendar is for staff collaboration. Hotel reservations, restaurant bookings, property viewings and customer events stay in their operational modules.</p></div></aside></div>
    {open&&<Modal title="Schedule internal event" onClose={()=>setOpen(false)}><form className="cw-form" onSubmit={e=>{e.preventDefault();submit(e.currentTarget)}}><div className="form-group"><label>Event title</label><input className="form-control" name="title" required/></div><div className="form-group"><label>Description</label><textarea className="form-control" name="description"/></div><div className="cw-field-row"><div className="form-group"><label>Starts</label><input className="form-control" name="starts_at" type="datetime-local" required/></div><div className="form-group"><label>Ends</label><input className="form-control" name="ends_at" type="datetime-local" required/></div></div><div className="form-group"><label>Time zone</label><input className="form-control" name="timezone" defaultValue={Intl.DateTimeFormat().resolvedOptions().timeZone||'UTC'} required/></div><div className="form-group"><label>Place or meeting link</label><input className="form-control" name="location_text"/></div><AudienceFields data={data}/><div className="cw-modal-actions"><Button type="button" onClick={()=>setOpen(false)}>Cancel</Button><Button primary disabled={busy}>{busy?'Scheduling…':'Schedule'}</Button></div></form></Modal>}</div></main></WorkspaceFrame>;
}

function Directory() {
    const{data,loading,error,reload}=useWorkspace('/company-hub/directory');if(loading)return <Loading/>;if(error)return <Failure error={error} retry={reload}/>;const people=data.users.data||[];
    return <WorkspaceFrame area="hub"><main className="cw-page"><div className="cw-shell"><PageHead eyebrow="Company Hub" title="People directory" subtitle="Find colleagues in the active company without mixing staff from another business."/><HubNav active="directory" canSettings={data.can_settings}/>{people.length===0?<Empty title="No active staff found" text="Add users with login access from User Management."/>:<div className="cw-directory">{people.map(user=><article className="cw-card cw-person" key={user.id}><span className="cw-avatar">{fullName(user).slice(0,2).toUpperCase()}</span><div><strong>{fullName(user)}</strong>{user.email&&<div><a href={`mailto:${user.email}`}>{user.email}</a></div>}<small>{data.departments[user.essentials_department_id]||'Company team'}</small></div></article>)}</div>}</div></main></WorkspaceFrame>;
}

function Settings() {
    const{data,loading,error,reload}=useWorkspace('/company-hub/settings');const[notice,setNotice]=useState(null);const[busy,setBusy]=useState(false);if(loading)return <Loading/>;if(error)return <Failure error={error} retry={reload}/>;
    const save=async form=>{setBusy(true);try{const body=objectFromForm(form);body.comments_enabled=form.comments_enabled.checked;body.email_important_announcements=form.email_important_announcements.checked;const result=await api('/company-hub/settings',{method:'PUT',body:JSON.stringify(body)});setNotice({type:'success',text:result.message});await reload();}catch(e){setNotice({type:'error',text:e.message});}finally{setBusy(false);}};
    return <WorkspaceFrame area="hub"><main className="cw-page"><div className="cw-shell"><PageHead eyebrow="Company Hub" title="Hub settings" subtitle="Set practical governance defaults for this company’s private collaboration space."/><HubNav active="settings"/><Alert type={notice?.type}>{notice?.text}</Alert><div className="cw-card"><div className="cw-card-body"><form className="cw-form cw-settings-form" onSubmit={e=>{e.preventDefault();save(e.currentTarget)}}><div className="form-group"><label>Retention period (days)</label><input className="form-control" name="retention_days" type="number" min="30" max="3650" defaultValue={data.settings.retention_days}/><small className="cw-help">Retention cleanup should follow your company’s legal and employment obligations.</small></div><div className="form-group"><label>Maximum internal attachment (MB)</label><input className="form-control" name="max_attachment_mb" type="number" min="1" max="25" defaultValue={data.settings.max_attachment_mb}/></div><div className="cw-checks"><label><input type="checkbox" name="comments_enabled" defaultChecked={data.settings.comments_enabled}/> Enable comments by default</label><label><input type="checkbox" name="email_important_announcements" defaultChecked={data.settings.email_important_announcements}/> Allow email delivery for important announcements</label></div><Button primary disabled={busy}>{busy?'Saving…':'Save settings'}</Button></form></div></div></div></main></WorkspaceFrame>;
}

function MainDashboard() {
    const search=window.location.search;const{data,loading,error,reload}=useWorkspace(`/home/workspace${search}`);const[customize,setCustomize]=useState(false);const[notice,setNotice]=useState(null);const[busy,setBusy]=useState(false);const[preset,setPreset]=useState(new URLSearchParams(search).get('preset')||'');
    useEffect(()=>{if(data&&!preset)setPreset(data.period.preset)},[data,preset]);
    if(loading)return <Loading/>;if(error)return <Failure error={error} retry={reload}/>;
    const hidden=data.preferences.hidden_sections||[];const visible=key=>!hidden.includes(key);const currency=data.currency.code||'';
    const filter=e=>{e.preventDefault();const form=objectFromForm(e.currentTarget);const params=new URLSearchParams();Object.entries(form).forEach(([key,value])=>{if(value)params.set(key,value)});window.location.assign(`/home?${params.toString()}`)};
    const savePreferences=async form=>{setBusy(true);setNotice(null);try{const body=objectFromForm(form);body.hidden_sections=[...form.querySelectorAll('input[name="hidden_sections[]"]:checked')].map(input=>input.value);const result=await api('/home/workspace/preferences',{method:'PUT',body:JSON.stringify(body)});setNotice({type:'success',text:result.message});setCustomize(false);await reload();}catch(e){setNotice({type:'error',text:e.message});}finally{setBusy(false)}};
    return <WorkspaceFrame area="home"><main className={`cw-page cw-dashboard is-${data.preferences.density} accent-${data.preferences.accent}`}><div className="cw-shell">
        <section className="cw-dashboard-hero"><div><div className="cw-eyebrow">{data.company.industry?.name||'Business workspace'}</div><h1>Welcome back, {data.workspace.user.name.split(' ')[0]}.</h1><p>Here is the live position for <strong>{data.company.name}</strong>. Every figure and task belongs only to this active company.</p></div><div className="cw-hero-actions">{data.quick_actions[0]&&<a className="cw-btn cw-btn-primary" href={data.quick_actions[0].url}><i className={`fa ${data.quick_actions[0].icon}`}/> {data.quick_actions[0].label}</a>}<Button onClick={()=>setCustomize(true)}><i className="fa fa-sliders"/> Personalise</Button></div></section>
        <Alert type={notice?.type} onClose={()=>setNotice(null)}>{notice?.text}</Alert>
        <form className="cw-dashboard-filters" onSubmit={filter}><div><label htmlFor="dashboard-period">Reporting period</label><select id="dashboard-period" name="preset" value={preset||data.period.preset} onChange={e=>setPreset(e.target.value)}><option value="today">Today</option><option value="last_7_days">Last 7 days</option><option value="last_30_days">Last 30 days</option><option value="this_month">This month</option><option value="this_quarter">This quarter</option><option value="this_year">This year</option><option value="custom">Custom dates</option></select></div>{preset==='custom'&&<><div><label htmlFor="dashboard-start">From</label><input id="dashboard-start" type="date" name="start" defaultValue={data.period.start} required/></div><div><label htmlFor="dashboard-end">To</label><input id="dashboard-end" type="date" name="end" defaultValue={data.period.end} required/></div></>}<div><label htmlFor="dashboard-location">Location</label><select id="dashboard-location" name="location_id" defaultValue={data.selected_location_id||''}><option value="">All permitted locations</option>{data.locations.map(location=><option value={location.id} key={location.id}>{location.name}</option>)}</select></div><Button primary><i className="fa fa-refresh"/> Apply</Button><span>{data.period.label}</span></form>
        {visible('onboarding')&&data.onboarding&&!data.onboarding.complete&&<OnboardingPanel onboarding={data.onboarding}/>} 
        {visible('metrics')&&(data.metrics?<section aria-label="Business performance" className="cw-dashboard-metrics"><DashboardMetric label="Net sales" value={money(data.metrics.net_sales,currency)} icon="fa-line-chart" tone="blue"/><DashboardMetric label="Sales less due & expenses" value={money(data.metrics.sales_less_due_expenses,currency)} icon="fa-money" tone="green"/><DashboardMetric label="Sales outstanding" value={money(data.metrics.sales_due,currency)} icon="fa-clock-o" tone="amber"/><DashboardMetric label="Purchases" value={money(data.metrics.purchases,currency)} icon="fa-truck" tone="violet"/><DashboardMetric label="Supplier balances" value={money(data.metrics.purchase_due,currency)} icon="fa-credit-card" tone="sunset"/><DashboardMetric label="Expenses" value={money(data.metrics.expenses,currency)} icon="fa-arrow-circle-o-down" tone="red"/></section>:<div className="cw-card cw-financial-lock"><div className="cw-card-body"><i className="fa fa-lock"/><div><strong>Financial summary is restricted</strong><p>Your role can use its assigned workspaces without seeing company-wide financial figures.</p></div></div></div>)}
        <div className="cw-dashboard-layout">
            <div>
                {visible('trend')&&data.permissions.financials&&<section className="cw-card cw-dashboard-section"><div className="cw-card-head"><div><div className="cw-section-kicker">Performance</div><h2 className="cw-card-title">Net sales trend</h2></div><a href="/reports/profit-loss">Open financial reports <i className="fa fa-angle-right"/></a></div><div className="cw-card-body"><SalesTrend points={data.trend} currency={currency}/></div></section>}
                {visible('quick_actions')&&<section className="cw-card cw-dashboard-section"><div className="cw-card-head"><div><div className="cw-section-kicker">Your workspaces</div><h2 className="cw-card-title">Continue working</h2></div><small>Based on industry, package and permissions</small></div><div className="cw-card-body"><div className="cw-action-grid">{data.quick_actions.map(action=><a href={action.url} className={`cw-action-card tone-${action.tone}`} key={action.key}><span><i className={`fa ${action.icon}`}/></span><div><strong>{action.label}</strong><small>{action.description}</small></div><i className="fa fa-angle-right"/></a>)}</div></div></section>}
                {visible('recent')&&data.permissions.financials&&<section className="cw-card cw-dashboard-section"><div className="cw-card-head"><div><div className="cw-section-kicker">Audit-friendly activity</div><h2 className="cw-card-title">Recent transactions</h2></div><a href="/sells">View transactions <i className="fa fa-angle-right"/></a></div><div className="cw-table-wrap">{data.recent_transactions.length===0?<Empty icon="fa-exchange" title="No transactions in this period" text="Adjust the reporting period or create the first business transaction."/>:<table className="cw-table cw-dashboard-table"><thead><tr><th>Document</th><th>Party</th><th>Location</th><th>Date</th><th>Status</th><th className="cw-number">Amount</th></tr></thead><tbody>{data.recent_transactions.map(item=><tr key={item.id}><td><a href={item.url}><strong>{item.reference}</strong><small>{item.type_label}</small></a></td><td>{item.contact||'—'}</td><td>{item.location||'—'}</td><td>{formatDate(item.date)}</td><td><span className={`cw-status is-${item.payment_status||'na'}`}>{item.payment_status||'—'}</span></td><td className="cw-number"><strong>{money(item.amount,currency)}</strong></td></tr>)}</tbody></table>}</div></section>}
            </div>
            <aside>
                {visible('attention')&&<section className="cw-card cw-dashboard-section cw-sticky"><div className="cw-card-head"><div><div className="cw-section-kicker">Action centre</div><h2 className="cw-card-title">Needs attention</h2></div></div><div className="cw-card-body cw-attention-list">{data.attention.map(item=><a href={item.url} key={item.key} className={`cw-attention tone-${item.tone} ${item.count===0?'is-clear':''}`}><span>{item.count===0?<i className="fa fa-check"/>:item.count}</span><div><strong>{item.label}</strong><small>{item.count===0?'Nothing outstanding':'Review and resolve'}</small></div><i className="fa fa-angle-right"/></a>)}</div></section>}
                {visible('onboarding')&&data.onboarding?.complete&&<section className="cw-card cw-dashboard-section cw-ready-card"><div className="cw-card-body"><span><i className="fa fa-check"/></span><strong>Company workspace ready</strong><p>Your required setup for {data.onboarding.industry} is complete.</p></div></section>}
            </aside>
        </div>
        {customize&&<Modal title="Personalise your dashboard" onClose={()=>setCustomize(false)} wide><form className="cw-form" onSubmit={e=>{e.preventDefault();savePreferences(e.currentTarget)}}><div className="cw-field-row"><div className="form-group"><label>Default reporting period</label><select name="date_preset" className="form-control" defaultValue={data.preferences.date_preset}><option value="today">Today</option><option value="last_7_days">Last 7 days</option><option value="last_30_days">Last 30 days</option><option value="this_month">This month</option><option value="this_quarter">This quarter</option><option value="this_year">This year</option></select></div><div className="form-group"><label>Default location</label><select name="default_location_id" className="form-control" defaultValue={data.preferences.default_location_id||''}><option value="">All permitted locations</option>{data.locations.map(location=><option value={location.id} key={location.id}>{location.name}</option>)}</select></div></div><div className="cw-field-row"><div className="form-group"><label>Display density</label><select name="density" className="form-control" defaultValue={data.preferences.density}><option value="comfortable">Comfortable</option><option value="compact">Compact</option></select></div><div className="form-group"><label>Accent</label><select name="accent" className="form-control" defaultValue={data.preferences.accent}><option value="ocean">Ocean blue</option><option value="violet">Royal violet</option><option value="emerald">Emerald</option><option value="sunset">Sunset</option></select></div></div><fieldset className="cw-section-picker"><legend>Hide sections you do not need</legend>{[['metrics','Performance metrics'],['trend','Sales trend'],['attention','Action centre'],['quick_actions','Workspace shortcuts'],['recent','Recent transactions'],['onboarding','Setup progress']].map(([key,label])=><label key={key}><input type="checkbox" name="hidden_sections[]" value={key} defaultChecked={hidden.includes(key)}/><span>{label}</span></label>)}</fieldset><div className="cw-modal-actions"><a className="cw-btn" href={data.legacy_dashboard_url}>Open legacy dashboard</a><Button type="button" onClick={()=>setCustomize(false)}>Cancel</Button><Button primary disabled={busy}>{busy?'Saving…':'Save preferences'}</Button></div></form></Modal>}
    </div></main></WorkspaceFrame>;
}

function DashboardMetric({label,value,icon,tone}) {return <article className={`cw-dashboard-metric tone-${tone}`}><span><i className={`fa ${icon}`}/></span><div><small>{label}</small><strong>{value}</strong></div></article>}

function SalesTrend({points,currency}) {
    if(!points.length)return <Empty icon="fa-line-chart" title="No sales trend yet" text="There are no completed sales in this reporting period."/>;
    const width=760,height=220,pad=24;const values=points.map(point=>Number(point.value||0));const min=Math.min(0,...values),max=Math.max(1,...values);const range=max-min||1;const coords=points.map((point,index)=>{const x=pad+(index*(width-pad*2)/Math.max(1,points.length-1));const y=height-pad-((Number(point.value)-min)/range)*(height-pad*2);return{x,y,...point}});const poly=coords.map(point=>`${point.x},${point.y}`).join(' ');const area=`${pad},${height-pad} ${poly} ${width-pad},${height-pad}`;
    return <div className="cw-trend"><div className="cw-trend-summary"><span><small>Highest point</small><strong>{money(max,currency)}</strong></span><span><small>Period total</small><strong>{money(values.reduce((sum,value)=>sum+value,0),currency)}</strong></span></div><svg viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Net sales trend chart"><defs><linearGradient id="cwTrendFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stopColor="var(--cw-brand)" stopOpacity=".26"/><stop offset="1" stopColor="var(--cw-brand)" stopOpacity=".02"/></linearGradient></defs>{[0,.25,.5,.75,1].map(level=><line key={level} x1={pad} x2={width-pad} y1={pad+level*(height-pad*2)} y2={pad+level*(height-pad*2)} className="cw-chart-grid"/>)}<polygon points={area} fill="url(#cwTrendFill)"/><polyline points={poly} className="cw-chart-line"/>{coords.map((point,index)=><circle key={index} cx={point.x} cy={point.y} r="4"><title>{point.label}: {money(point.value,currency)}</title></circle>)}</svg><div className="cw-chart-labels"><span>{points[0].label}</span><span>{points[Math.floor(points.length/2)].label}</span><span>{points[points.length-1].label}</span></div></div>;
}

function OnboardingPanel({onboarding}) {return <section className="cw-onboarding"><div className="cw-onboarding-progress"><span style={{'--progress':`${onboarding.percentage}%`}}><strong>{onboarding.percentage}%</strong></span><div><div className="cw-eyebrow">Guided setup</div><h2>Finish configuring {onboarding.industry}</h2><p>Complete the required foundations before inviting the wider team.</p></div></div><div className="cw-onboarding-steps">{onboarding.steps.map(step=><a href={step.url} key={step.key} className={step.complete?'is-complete':''}><i className={`fa ${step.complete?'fa-check':'fa-angle-right'}`}/><span><strong>{step.label}</strong><small>{step.help}</small></span></a>)}</div></section>}

const commercialLabels={overview:'Overview',invoices:'Invoices',orders:'Sales orders',quotations:'Quotations',drafts:'Drafts',returns:'Returns & credits',fulfilment:'Fulfilment',payments:'Payments & receipts',documents:'Industry documents',customers:'Customers'};

function commercialViewFromLocation(){
    const path=window.location.pathname.replace(/\/$/,'');
    if(path==='/sells')return 'invoices';
    if(path==='/sales-order')return 'orders';
    if(path==='/sells/quotations')return 'quotations';
    if(path==='/sells/drafts')return 'drafts';
    if(path==='/sell-return')return 'returns';
    if(path==='/shipments')return 'fulfilment';
    if(path==='/smart-documents')return 'documents';
    if(path==='/contacts'&&new URLSearchParams(window.location.search).get('type')==='customer')return 'customers';
    return new URLSearchParams(window.location.search).get('view')||'overview';
}

function CommercialWorkspace(){
    const initialView=commercialViewFromLocation();
    const sourceParams=new URLSearchParams(window.location.search);sourceParams.delete('legacy');sourceParams.delete('type');
    if(!sourceParams.get('view'))sourceParams.set('view',initialView);
    const endpoint=`/sales/workspace?${sourceParams.toString()}`;
    const{data,loading,error,reload}=useWorkspace(endpoint);
    const[customerOpen,setCustomerOpen]=useState(false);const[customerBusy,setCustomerBusy]=useState(false);const[notice,setNotice]=useState(null);
    if(loading)return <Loading/>;if(error)return <Failure error={error} retry={reload}/>;
    const currency=data.currency?.code||'';
    const setView=view=>window.location.assign(`/sales?view=${encodeURIComponent(view)}`);
    const applyFilters=form=>{const params=new URLSearchParams();params.set('view',data.selected_view);new FormData(form).forEach((value,key)=>{if(String(value).trim()!=='')params.set(key,value)});params.delete('page');window.location.assign(`/sales?${params.toString()}`)};
    const reset=()=>window.location.assign(`/sales?view=${data.selected_view}`);
    const createCustomer=async form=>{setCustomerBusy(true);setNotice(null);try{const result=await api(data.links.create_customer,{method:'POST',body:JSON.stringify(objectFromForm(form))});setNotice({type:'success',text:result.message||'Customer created.'});setCustomerOpen(false);await reload();}catch(error){setNotice({type:'error',text:error.message})}finally{setCustomerBusy(false)}};
    return <WorkspaceFrame area="sales"><main className="cw-page cw-commercial cw-sales"><div className="cw-shell">
        <section className="cw-commercial-hero"><div><div className="cw-eyebrow">{data.experience.eyebrow} · {data.company.industry?.name||'Company workspace'}</div><h1>{data.experience.title}</h1><p>{data.experience.description}</p></div><div className="cw-hero-actions">{data.links.create_invoice&&<a className="cw-btn cw-btn-primary" href={data.links.create_invoice}><i className="fa fa-plus"/> New {String(data.experience.invoice_label||'invoice').toLowerCase()}</a>}{data.links.pos&&<a className="cw-btn" href={data.links.pos}><i className="fa fa-shopping-cart"/> Open POS</a>}{data.links.create_quotation&&<a className="cw-btn" href={data.links.create_quotation}><i className="fa fa-file-text-o"/> New quotation</a>}</div></section>
        <CommercialSummary summary={data.summary} currency={currency}/>
        <nav className="cw-commercial-tabs" aria-label="Sales sections">{data.available_views.map(view=><button type="button" key={view} className={data.selected_view===view?'active':''} onClick={()=>setView(view)}><i className={`fa ${{overview:'fa-dashboard',invoices:'fa-file-text-o',orders:'fa-list-alt',quotations:'fa-file-o',drafts:'fa-pencil-square-o',returns:'fa-reply',fulfilment:'fa-truck',payments:'fa-credit-card',documents:'fa-files-o',customers:'fa-address-book-o'}[view]}`}/><span>{commercialLabels[view]}</span></button>)}</nav>
        <Alert type={notice?.type} onClose={()=>setNotice(null)}>{notice?.text}</Alert>
        <CommercialFilters data={data} onApply={applyFilters} onReset={reset}/>
        <div className="cw-commercial-heading"><div><span>{commercialLabels[data.selected_view]}</span><small>{data.records.meta?.total||0} matching record{data.records.meta?.total===1?'':'s'}</small></div><div className="cw-actions">{data.selected_view==='documents'&&data.links.document_settings&&<a className="cw-btn" href={data.links.document_settings}><i className="fa fa-cog"/> Document settings</a>}{data.selected_view==='customers'&&data.links.create_customer&&<Button primary onClick={()=>setCustomerOpen(true)}><i className="fa fa-plus"/> Add {String(data.experience.party_label||'customer').toLowerCase()}</Button>}{data.selected_view==='customers'&&data.links.import_customers&&<a className="cw-btn" href={data.links.import_customers}><i className="fa fa-upload"/> Import</a>}{data.selected_view==='invoices'&&data.links.import_sales&&<a className="cw-btn" href={data.links.import_sales}><i className="fa fa-upload"/> Import sales</a>}{data.links.create_document&&<a className="cw-btn" href={data.links.create_document}><i className="fa fa-magic"/> Create industry document</a>}<a className="cw-btn cw-subtle-btn" href={data.legacy_url}><i className="fa fa-history"/> Legacy tools</a></div></div>
        {data.selected_view==='payments'&&<div className="cw-commercial-note"><i className="fa fa-shield"/><span><strong>Accounting boundary protected.</strong> Refundable security deposits are held liabilities in a separate operational register; they remain excluded from sales income, ordinary payment totals and the accounting ledger.</span></div>}
        <CommercialResults view={data.selected_view} records={data.records} currency={currency} data={data}/>
        <CommercialPagination meta={data.records.meta}/>
        {customerOpen&&<Modal title="Add customer" onClose={()=>setCustomerOpen(false)} wide><form className="cw-form" onSubmit={event=>{event.preventDefault();createCustomer(event.currentTarget)}}><input type="hidden" name="type" value="customer"/><div className="cw-field-row"><div className="form-group"><label>Customer type</label><select name="contact_type_radio" className="form-control" defaultValue="individual"><option value="individual">Individual</option><option value="business">Business / organisation</option></select></div><div className="form-group"><label>Business name (optional)</label><input name="supplier_business_name" className="form-control" maxLength="191"/></div></div><div className="cw-field-row"><div className="form-group"><label>First or primary name</label><input name="first_name" className="form-control" maxLength="191" required autoComplete="given-name"/></div><div className="form-group"><label>Last name</label><input name="last_name" className="form-control" maxLength="191" autoComplete="family-name"/></div></div><div className="cw-field-row"><div className="form-group"><label>Mobile</label><input name="mobile" className="form-control" maxLength="30" required inputMode="tel" autoComplete="tel"/></div><div className="form-group"><label>Email</label><input name="email" type="email" className="form-control" maxLength="191" autoComplete="email"/></div></div><div className="cw-field-row"><div className="form-group"><label>City</label><input name="city" className="form-control" maxLength="100" autoComplete="address-level2"/></div><div className="form-group"><label>Country</label><input name="country" className="form-control" maxLength="100" autoComplete="country-name"/></div></div><div className="cw-modal-actions"><Button type="button" onClick={()=>setCustomerOpen(false)}>Cancel</Button><Button primary disabled={customerBusy}>{customerBusy?'Saving…':'Create customer'}</Button></div></form></Modal>}
    </div></main></WorkspaceFrame>;
}

function CommercialSummary({summary,currency}){
    const cards=[
        ['Invoices',summary.invoice_count,'fa-file-text-o','ocean'],
        ['Invoice value',summary.invoice_total===null?'Restricted':money(summary.invoice_total,currency),'fa-line-chart','emerald'],
        ['Requires payment',summary.amount_due===null?'Restricted':money(summary.amount_due,currency),'fa-clock-o','amber'],
        ['Open orders',summary.order_count,'fa-list-alt','violet'],
        ['Drafts',summary.draft_count,'fa-pencil-square-o','slate'],
        ['To fulfil',summary.fulfilment_count,'fa-truck','sunset'],
    ];
    return <section className="cw-commercial-summary">{cards.map(([label,value,icon,tone])=><article key={label} className={`tone-${tone}`}><span><i className={`fa ${icon}`}/></span><div><small>{label}</small><strong>{value===null?'Restricted':value}</strong></div></article>)}</section>;
}

function CommercialFilters({data,onApply,onReset}){
    const view=data.selected_view;const filters=data.filters;
    const statuses=view==='invoices'?[['paid','Paid'],['partial','Part paid'],['due','Due']]:view==='orders'?[['ordered','Ordered'],['partial','Part fulfilled'],['completed','Completed']]:view==='quotations'?[['quotation','Quotation'],['proforma','Proforma']]:view==='returns'?[['final','Completed'],['draft','Draft']]:view==='fulfilment'?[['ordered','Ordered'],['packed','Packed'],['shipped','Shipped'],['delivered','Delivered'],['cancelled','Cancelled']]:view==='customers'?[['active','Active'],['inactive','Inactive']]:view==='documents'?[['draft','Draft'],['issued','Issued'],['sent','Sent'],['accepted','Accepted'],['completed','Completed'],['void','Void']]:[];
    return <form className="cw-commercial-filters" onSubmit={event=>{event.preventDefault();onApply(event.currentTarget)}}>
        <div className="cw-search-field"><label htmlFor="commercial-q">Search</label><span><i className="fa fa-search"/><input id="commercial-q" name="q" defaultValue={filters.q} maxLength="100" placeholder={view==='customers'?'Name, phone, email or ID':'Number, customer or title'}/></span></div>
        {view!=='customers'&&<div><label htmlFor="commercial-location">Location</label><select id="commercial-location" name="location_id" defaultValue={filters.location_id||''}><option value="">All permitted locations</option>{data.locations.map(location=><option key={location.id} value={location.id}>{location.name}</option>)}</select></div>}
        {view==='documents'&&<div><label htmlFor="commercial-type">Document type</label><select id="commercial-type" name="document_type_id" defaultValue={filters.document_type_id||''}><option value="">All document types</option>{data.document_types.map(type=><option key={type.id} value={type.id}>{type.name}</option>)}</select></div>}
        {statuses.length>0&&<div><label htmlFor="commercial-status">Status</label><select id="commercial-status" name="status" defaultValue={filters.status||''}><option value="">All statuses</option>{statuses.map(([value,label])=><option value={value} key={value}>{label}</option>)}</select></div>}
        {!['customers'].includes(view)&&<><div><label htmlFor="commercial-start">From</label><input id="commercial-start" name="start" type="date" defaultValue={filters.start||''}/></div><div><label htmlFor="commercial-end">To</label><input id="commercial-end" name="end" type="date" defaultValue={filters.end||''}/></div></>}
        <div><label htmlFor="commercial-per-page">Rows</label><select id="commercial-per-page" name="per_page" defaultValue={filters.per_page||25}><option value="10">10</option><option value="25">25</option><option value="50">50</option></select></div>
        <div className="cw-filter-actions"><Button primary type="submit"><i className="fa fa-filter"/> Apply</Button><Button type="button" onClick={onReset}>Reset</Button></div>
    </form>;
}

function CommercialResults({view,records,currency,data}){
    const rows=records.data||[];
    if(view==='overview')return <SalesOverview data={data} rows={rows} currency={currency}/>;
    if(view==='payments')return <ReceiptResults documents={rows} payments={records.payments||[]} currency={currency}/>;
    if(rows.length===0)return <section className="cw-card"><Empty icon={view==='customers'?'fa-address-book-o':'fa-file-o'} title={`No matching ${commercialLabels[view].toLowerCase()}`} text="Adjust the filters or create the first record using the actions above."/></section>;
    if(view==='customers')return <div className="cw-commercial-cards">{rows.map(row=><article className="cw-customer-card" key={row.id}><div className="cw-customer-avatar">{row.name.split(/\s+/).map(part=>part[0]).join('').slice(0,2).toUpperCase()}</div><div className="cw-customer-main"><span className="cw-status is-active">{row.status}</span><h2>{row.name}</h2>{row.person_name&&<p>{row.person_name}</p>}<div className="cw-customer-contact">{row.mobile&&<a href={`tel:${row.mobile}`}><i className="fa fa-phone"/> {row.mobile}</a>}{row.email&&<a href={`mailto:${row.email}`}><i className="fa fa-envelope-o"/> {row.email}</a>}{row.location&&<span><i className="fa fa-map-marker"/> {row.location}</span>}</div></div><div className="cw-customer-foot"><span><small>Customer ID</small><strong>{row.number}</strong></span><span><small>Advance balance</small><strong>{money(row.advance_balance,currency)}</strong></span><div className="cw-actions"><a className="cw-btn" href={row.actions.view}>Open</a><a className="cw-btn" href={row.actions.ledger}>Ledger</a></div></div></article>)}</div>;
    return <section className="cw-card cw-commercial-table-card"><div className="cw-table-wrap"><table className="cw-table cw-commercial-table"><thead><tr><th>Document</th><th>Customer / client</th><th>Location</th><th>Date</th><th className="cw-number">Total</th><th>Status</th><th>Actions</th></tr></thead><tbody>{rows.map(row=><tr key={row.id}><td><a className="cw-document-number" href={row.actions.view}>{row.number}</a><small>{row.kind}{row.title?` · ${row.title}`:''}</small></td><td><strong>{row.customer}</strong>{row.customer_mobile&&<small>{row.customer_mobile}</small>}</td><td>{row.location}</td><td>{formatDate(row.date)}</td><td className="cw-number"><strong>{money(row.total,currency)}</strong>{row.payment_status&&<small>{money(row.due,currency)} due</small>}</td><td><span className={`cw-status is-${row.payment_status||row.status}`}>{String(row.payment_status||row.status).replaceAll('_',' ')}</span></td><td><CommercialActions actions={row.actions}/></td></tr>)}</tbody></table></div></section>;
}

function SalesOverview({data,rows,currency}){
    const work=[
        ['Sales orders',data.summary.order_count,'Confirm and fulfil approved customer demand.','orders','fa-list-alt'],
        ['Draft invoices',data.summary.draft_count,'Complete, validate and issue unfinished sales.','drafts','fa-pencil-square-o'],
        ['Open quotations',data.summary.quotation_count,'Follow up and convert accepted offers.','quotations','fa-file-o'],
        ['Fulfilment queue',data.summary.fulfilment_count,'Pack, dispatch and confirm delivery.','fulfilment','fa-truck'],
        ['Returns and credits',data.summary.return_count,'Review product returns and customer credits.','returns','fa-reply'],
        ['Documents requiring action',data.summary.document_action_count,'Complete industry agreements and supporting records.','documents','fa-files-o'],
    ].filter(([,value])=>value!==null);
    return <div className="cw-sales-overview">
        <section className="cw-work-queue">{work.map(([label,value,help,view,icon])=><a href={`/sales?view=${view}`} key={view}><span><i className={`fa ${icon}`}/></span><div><strong>{label}</strong><p>{help}</p></div><b>{value}</b><i className="fa fa-angle-right"/></a>)}</section>
        {data.experience.shortcuts?.length>0&&<section className="cw-card"><div className="cw-card-head"><div><h2 className="cw-card-title">Industry operations</h2><small>Operational workspaces stay separate from financial documents</small></div></div><div className="cw-card-body cw-industry-shortcuts">{data.experience.shortcuts.map(item=><a className="cw-btn" href={item.url} key={item.url}><i className={`fa ${item.icon}`}/> {item.label}</a>)}</div></section>}
        <section className="cw-card cw-commercial-table-card"><div className="cw-card-head"><div><h2 className="cw-card-title">Recent invoices</h2><small>Latest accessible finalized sales</small></div><a className="cw-btn" href="/sales?view=invoices">View all invoices</a></div>{rows.length===0?<Empty icon="fa-file-text-o" title="No finalized invoices" text="Create an invoice or use POS to record the first completed sale."/>:<div className="cw-table-wrap"><table className="cw-table cw-commercial-table"><thead><tr><th>Invoice</th><th>Customer / client</th><th>Date</th><th className="cw-number">Total</th><th>Status</th><th>Actions</th></tr></thead><tbody>{rows.map(row=><tr key={row.id}><td><a className="cw-document-number" href={row.actions.view}>{row.number}</a><small>{row.kind}</small></td><td><strong>{row.customer}</strong><small>{row.location}</small></td><td>{formatDate(row.date)}</td><td className="cw-number"><strong>{money(row.total,currency)}</strong><small>{money(row.due,currency)} due</small></td><td><span className={`cw-status is-${row.payment_status}`}>{String(row.payment_status||row.status).replaceAll('_',' ')}</span></td><td><CommercialActions actions={row.actions}/></td></tr>)}</tbody></table></div>}</section>
    </div>;
}

function ReceiptResults({documents,payments,currency}){
    if(documents.length===0&&payments.length===0)return <section className="cw-card"><Empty icon="fa-credit-card" title="No matching receipts or payments" text="Recorded customer payments and issued receipt documents will appear here."/></section>;
    return <div className="cw-receipt-layout"><section className="cw-card"><div className="cw-card-head"><div><h2 className="cw-card-title">Issued receipt documents</h2><small>Printable and shareable evidence of payment</small></div></div><div className="cw-card-body">{documents.length===0?<Empty icon="fa-file-o" title="No receipt documents" text="Create a receipt from an eligible payment or invoice."/>:<div className="cw-receipt-list">{documents.map(row=><article key={row.id}><span><i className="fa fa-file-pdf-o"/></span><div><a href={row.actions.view}>{row.number}</a><strong>{row.kind}</strong><small>{row.customer} · {formatDate(row.date)}</small></div><b>{money(row.total,currency)}</b><CommercialActions actions={row.actions}/></article>)}</div>}</div></section><section className="cw-card"><div className="cw-card-head"><div><h2 className="cw-card-title">Recent customer payments</h2><small>Security deposits excluded</small></div></div><div className="cw-card-body">{payments.length===0?<Empty icon="fa-credit-card" title="No recent payments" text="Invoice and advance payments will appear here after posting."/>:<div className="cw-payment-list">{payments.map(row=><article key={row.id}><span><i className="fa fa-check"/></span><div><strong>{row.number}</strong><small>{row.customer} · {row.invoice_number}</small><em>{formatDateTime(row.date)} · {String(row.method).replaceAll('_',' ')}</em></div><b>{money(row.amount,currency)}</b><div className="cw-actions"><a className="cw-btn" target="_blank" rel="noopener" href={row.actions.invoice}>Invoice</a><a className="cw-btn" target="_blank" rel="noopener" href={row.actions.view}>Details</a></div></article>)}</div>}</div></section></div>;
}

function CommercialActions({actions}){
    const secondary=[['preview','Preview','fa-eye'],['print','Print','fa-print'],['download','Download','fa-download'],['share','Share','fa-share-alt'],['edit','Edit','fa-pencil'],['a4_invoice','Create A4 invoice','fa-file-pdf-o'],['pos_receipt','Create 80mm POS receipt','fa-receipt'],['smart_document','Smart document','fa-magic']].filter(([key])=>actions?.[key]);
    return <div className="cw-document-actions"><a className="cw-btn" href={actions.view}>Open</a>{secondary.length>0&&<details><summary aria-label="More document actions"><i className="fa fa-ellipsis-h"/></summary><div>{secondary.map(([key,label,icon])=><a key={key} href={actions[key]} target={['preview','print','download'].includes(key)?'_blank':undefined} rel={['preview','print','download'].includes(key)?'noopener':undefined}><i className={`fa ${icon}`}/> {label}</a>)}</div></details>}</div>;
}

function CommercialPagination({meta}){
    if(!meta||meta.last_page<=1)return null;
    const go=page=>{const params=new URLSearchParams(window.location.search);params.delete('legacy');params.delete('type');params.set('view',commercialViewFromLocation());params.set('page',String(page));window.location.assign(`/sales?${params.toString()}`)};
    return <nav className="cw-pagination" aria-label="Sales results pages"><span>Showing {meta.from||0}–{meta.to||0} of {meta.total}</span><div><Button type="button" disabled={meta.current_page<=1} onClick={()=>go(meta.current_page-1)}><i className="fa fa-angle-left"/> Previous</Button><strong>Page {meta.current_page} of {meta.last_page}</strong><Button type="button" disabled={meta.current_page>=meta.last_page} onClick={()=>go(meta.current_page+1)}>Next <i className="fa fa-angle-right"/></Button></div></nav>;
}

const accountingLabels={overview:'Overview',accounts:'Accounts',ledger:'Ledger',receivables:'Receivables',payables:'Payables'};

function AccountingWorkspace(){
    const params=new URLSearchParams(window.location.search);params.delete('legacy');if(!params.get('view'))params.set('view','overview');
    const endpoint=`/accounting/workspace?${params.toString()}`;const{data,loading,error,reload}=useWorkspace(endpoint);
    if(loading)return <Loading/>;if(error)return <Failure error={error} retry={reload}/>;
    const currency=data.currency?.code||'';
    const setView=view=>window.location.assign(`/accounting?view=${encodeURIComponent(view)}`);
    const apply=form=>{const next=new URLSearchParams();next.set('view',data.selected_view);new FormData(form).forEach((value,key)=>{if(String(value).trim()!=='')next.set(key,value)});window.location.assign(`/accounting?${next.toString()}`)};
    return <WorkspaceFrame area="accounting"><main className="cw-page cw-accounting"><div className="cw-shell">
        <section className="cw-commercial-hero cw-accounting-hero"><div><div className="cw-eyebrow">Accounting · {data.company.industry?.name||'Active company'}</div><h1>Cash, accounts and outstanding balances</h1><p>Review real payment-account movements, customer receivables and supplier payables for the active company and permitted locations.</p></div><div className="cw-hero-actions"><a className="cw-btn cw-btn-primary" href={data.links.create_account}><i className="fa fa-plus"/> New account</a><a className="cw-btn" href={data.links.balance_sheet}><i className="fa fa-balance-scale"/> Balance sheet</a><a className="cw-btn" href={data.links.trial_balance}><i className="fa fa-list-ol"/> Trial balance</a></div></section>
        <AccountingSummary data={data} currency={currency}/>
        <nav className="cw-commercial-tabs" aria-label="Accounting sections">{data.available_views.map(view=><button type="button" key={view} className={data.selected_view===view?'active':''} onClick={()=>setView(view)}><i className={`fa ${{overview:'fa-dashboard',accounts:'fa-university',ledger:'fa-book',receivables:'fa-arrow-circle-down',payables:'fa-arrow-circle-up'}[view]}`}/><span>{accountingLabels[view]}</span></button>)}</nav>
        <div className="cw-commercial-note"><i className="fa fa-shield"/><span><strong>Refundable deposits stay separate.</strong> {data.governance.message} {data.governance.pending_security_deposits>0&&<a href="/notifications/in-app">{data.governance.pending_security_deposits} deposit record{data.governance.pending_security_deposits===1?' requires':'s require'} attention.</a>}</span></div>
        <AccountingFilters data={data} onApply={apply}/>
        <div className="cw-commercial-heading"><div><span>{accountingLabels[data.selected_view]}</span><small>{data.records.meta?.total||0} matching record{data.records.meta?.total===1?'':'s'}</small></div><div className="cw-actions"><a className="cw-btn" href={data.links.cash_flow}><i className="fa fa-exchange"/> Cash-flow report</a><a className="cw-btn" href={data.links.payment_account_report}><i className="fa fa-file-text-o"/> Account report</a><a className="cw-btn cw-subtle-btn" href={data.links.legacy_accounts}><i className="fa fa-history"/> Legacy tools</a></div></div>
        <AccountingResults data={data} currency={currency}/>
        <AccountingPagination meta={data.records.meta} view={data.selected_view}/>
    </div></main></WorkspaceFrame>;
}

function AccountingSummary({data,currency}){
    const cards=[['Account balance',data.summary.account_balance,'fa-university','ocean'],['Period inflow',data.summary.period_inflow,'fa-arrow-down','emerald'],['Period outflow',data.summary.period_outflow,'fa-arrow-up','sunset'],['Receivables',data.summary.receivables,'fa-clock-o','amber'],['Payables',data.summary.payables,'fa-file-text-o','violet']];
    return <section className="cw-commercial-summary cw-accounting-summary">{cards.map(([label,value,icon,tone])=><article key={label} className={`tone-${tone}`}><span><i className={`fa ${icon}`}/></span><div><small>{label}</small><strong>{money(value,currency)}</strong></div></article>)}</section>;
}

function AccountingFilters({data,onApply}){
    const view=data.selected_view,f=data.filters;
    return <form className="cw-commercial-filters cw-accounting-filters" onSubmit={event=>{event.preventDefault();onApply(event.currentTarget)}}>
        <div className="cw-search-field"><label htmlFor="accounting-q">Search</label><span><i className="fa fa-search"/><input id="accounting-q" name="q" defaultValue={f.q} maxLength="100" placeholder="Account, reference, note or party"/></span></div>
        {['overview','ledger'].includes(view)&&<div><label htmlFor="accounting-account">Account</label><select id="accounting-account" name="account_id" defaultValue={f.account_id||''}><option value="">All permitted accounts</option>{data.accounts.filter(a=>!a.is_closed).map(a=><option value={a.id} key={a.id}>{a.name} · {a.number}</option>)}</select></div>}
        {view==='ledger'&&<div><label htmlFor="accounting-type">Movement</label><select id="accounting-type" name="type" defaultValue={f.type||''}><option value="">Inflow and outflow</option><option value="credit">Inflow</option><option value="debit">Outflow</option></select></div>}
        {view==='accounts'&&<div><label htmlFor="accounting-status">Status</label><select id="accounting-status" name="status" defaultValue={f.status||''}><option value="">Open and closed</option><option value="open">Open</option><option value="closed">Closed</option></select></div>}
        {['receivables','payables'].includes(view)&&<div><label htmlFor="accounting-status">Payment status</label><select id="accounting-status" name="status" defaultValue={f.status||''}><option value="">Due and part paid</option><option value="due">Due</option><option value="partial">Part paid</option></select></div>}
        {view!=='accounts'&&<><div><label htmlFor="accounting-start">From</label><input id="accounting-start" name="start" type="date" defaultValue={f.start}/></div><div><label htmlFor="accounting-end">To</label><input id="accounting-end" name="end" type="date" defaultValue={f.end}/></div></>}
        <div><label htmlFor="accounting-rows">Rows</label><select id="accounting-rows" name="per_page" defaultValue={f.per_page}><option value="10">10</option><option value="25">25</option><option value="50">50</option></select></div>
        <div className="cw-filter-actions"><Button primary><i className="fa fa-filter"/> Apply</Button><Button type="button" onClick={()=>window.location.assign(`/accounting?view=${view}`)}>Reset</Button></div>
    </form>;
}

function AccountingResults({data,currency}){
    const view=data.selected_view,rows=data.records.data||[];
    if(view==='overview')return <AccountingOverview data={data} rows={rows} currency={currency}/>;
    if(rows.length===0)return <section className="cw-card"><Empty icon="fa-book" title={`No matching ${accountingLabels[view].toLowerCase()}`} text="Adjust the filters or verify the active company and permitted locations."/></section>;
    if(view==='accounts')return <section className="cw-card cw-commercial-table-card"><div className="cw-table-wrap"><table className="cw-table"><thead><tr><th>Account</th><th>Type</th><th>Status</th><th className="cw-number">Balance</th><th>Actions</th></tr></thead><tbody>{rows.map(row=><tr key={row.id}><td><a className="cw-document-number" href={row.actions.ledger}>{row.name}</a><small>{row.number}</small></td><td>{row.type}</td><td><span className={`cw-status is-${row.status}`}>{row.status}</span></td><td className="cw-number"><strong>{money(row.balance,currency)}</strong></td><td><div className="cw-actions"><a className="cw-btn" href={row.actions.ledger}>Open ledger</a>{row.actions.edit&&<a className="cw-btn" href={row.actions.edit}>Edit account</a>}</div></td></tr>)}</tbody></table></div></section>;
    if(['receivables','payables'].includes(view))return <section className="cw-card cw-commercial-table-card"><div className="cw-table-wrap"><table className="cw-table"><thead><tr><th>Reference</th><th>{view==='receivables'?'Customer / client':'Supplier'}</th><th>Location</th><th>Date</th><th className="cw-number">Total</th><th className="cw-number">Outstanding</th><th>Status</th></tr></thead><tbody>{rows.map(row=><tr key={row.id}><td><a className="cw-document-number" href={row.actions.view}>{row.number}</a><small>{row.kind}</small></td><td><strong>{row.party}</strong></td><td>{row.location}</td><td>{formatDate(row.date)}</td><td className="cw-number">{money(row.total,currency)}</td><td className="cw-number"><strong>{money(row.due,currency)}</strong></td><td><span className={`cw-status is-${row.status}`}>{String(row.status).replaceAll('_',' ')}</span></td></tr>)}</tbody></table></div></section>;
    return <LedgerTable rows={rows} currency={currency}/>;
}

function AccountingOverview({data,rows,currency}){
    const max=Math.max(1,...data.trend.flatMap(item=>[Number(item.inflow),Number(item.outflow)]));
    return <div className="cw-accounting-overview"><section className="cw-card"><div className="cw-card-head"><div><h2 className="cw-card-title">Cash movement</h2><small>Transfers between company accounts are excluded from inflow and outflow</small></div></div><div className="cw-card-body">{data.trend.length===0?<Empty icon="fa-bar-chart" title="No account movement in this period" text="Posted payment-account entries will appear here."/>:<div className="cw-cash-bars">{data.trend.map(item=><div key={item.period}><strong>{item.period}</strong><span><i className="is-inflow" style={{width:`${Number(item.inflow)/max*100}%`}}/><em>{money(item.inflow,currency)} in</em></span><span><i className="is-outflow" style={{width:`${Number(item.outflow)/max*100}%`}}/><em>{money(item.outflow,currency)} out</em></span></div>)}</div>}</div></section><section className="cw-card cw-commercial-table-card"><div className="cw-card-head"><div><h2 className="cw-card-title">Recent ledger entries</h2><small>Most recent entries within the selected period</small></div><a className="cw-btn" href="/accounting?view=ledger">Open ledger</a></div>{rows.length===0?<Empty icon="fa-book" title="No ledger entries" text="Use an authorized transaction workflow to post financial activity."/>:<LedgerTable rows={rows} currency={currency} embedded/>}</section></div>;
}

function LedgerTable({rows,currency,embedded=false}){
    const table=<div className="cw-table-wrap"><table className="cw-table"><thead><tr><th>Date</th><th>Account</th><th>Reference</th><th>Movement</th><th className="cw-number">Amount</th><th>Recorded by</th></tr></thead><tbody>{rows.map(row=><tr key={row.id}><td>{formatDateTime(row.date)}</td><td><a className="cw-document-number" href={row.actions.account}>{row.account}</a><small>{row.account_number}</small></td><td><strong>{row.reference}</strong>{row.note&&<small>{row.note}</small>}</td><td><span className={`cw-status is-${row.type}`}>{row.type==='credit'?'Inflow':'Outflow'}</span></td><td className="cw-number"><strong>{money(row.amount,currency)}</strong></td><td>{row.created_by||'System'}</td></tr>)}</tbody></table></div>;
    return embedded?table:<section className="cw-card cw-commercial-table-card">{table}</section>;
}

function AccountingPagination({meta,view}){
    if(!meta||meta.last_page<=1)return null;const go=page=>{const params=new URLSearchParams(window.location.search);params.delete('legacy');params.set('view',view);params.set('page',String(page));window.location.assign(`/accounting?${params.toString()}`)};
    return <nav className="cw-pagination" aria-label="Accounting results pages"><span>Showing {meta.from||0}–{meta.to||0} of {meta.total}</span><div><Button disabled={meta.current_page<=1} onClick={()=>go(meta.current_page-1)}>Previous</Button><strong>Page {meta.current_page} of {meta.last_page}</strong><Button disabled={meta.current_page>=meta.last_page} onClick={()=>go(meta.current_page+1)}>Next</Button></div></nav>;
}

function CrmWorkspace() {
    const endpoint=`/crm/dashboard${window.location.search}`;const{data,loading,error,reload}=useWorkspace(endpoint);const[modal,setModal]=useState('');const[selected,setSelected]=useState(null);const[notice,setNotice]=useState(null);const[busy,setBusy]=useState(false);
    if(loading)return <Loading/>;if(error)return <Failure error={error} retry={reload}/>;
    const submit=async(url,body,method='POST')=>{setBusy(true);setNotice(null);try{const result=await api(url,{method,body:JSON.stringify(body)});setNotice({type:'success',text:result.message});setModal('');setSelected(null);await reload();}catch(e){setNotice({type:'error',text:e.message});}finally{setBusy(false);}};
    const moveDeal=(deal,stageId)=>{const stage=data.pipeline.stages.find(item=>Number(item.id)===Number(stageId));let note='';if(stage?.is_lost){note=window.prompt('Why was this opportunity lost? This will be retained in stage history.')||'';if(!note)return;}submit(`/crm/workspace/opportunities/${deal.uuid}/stage`,{stage_id:stageId,note},'PATCH');};
    const complete=item=>{const outcome=window.prompt('Completion outcome or note (optional):')||'';submit(`/crm/workspace/activities/${item.uuid}/complete`,{outcome},'PATCH');};
    const archive=deal=>window.confirm(`Archive ${deal.title}? Its history will be retained.`)&&submit(`/crm/workspace/opportunities/${deal.uuid}`,{},'DELETE');
    const currency=data.opportunities.find(o=>o.currency_code)?.currency_code||'';
    const openStages=data.pipeline.stages.filter(stage=>!stage.is_won&&!stage.is_lost);
    return <WorkspaceFrame area="crm"><main className="cw-page"><div className="cw-shell"><PageHead eyebrow="Customer relationships" title="CRM pipeline workspace" subtitle="Turn leads and customer conversations into owned, measurable next actions.">{data.permissions.manage_opportunities&&<Button primary onClick={()=>setModal('deal')}><i className="fa fa-plus"/> New opportunity</Button>}{data.permissions.manage_pipeline&&<Button onClick={()=>setModal('pipeline')}><i className="fa fa-sliders"/> Configure</Button>}</PageHead>
        <div className="cw-nav"><a className="active" href="/crm/dashboard">Pipeline</a><a href="/crm/leads?lead_view=list_view">Leads</a><a href="/crm/follow-ups">Follow-ups</a><a href="/crm/proposals">Proposals</a><a href="/crm/campaigns">Campaigns</a><a href="/crm/reports">Reports</a></div><Alert type={notice?.type} onClose={()=>setNotice(null)}>{notice?.text}</Alert>
        <div className="cw-metrics"><Metric label="Open opportunities" value={data.metrics.open_count}/><Metric label="Pipeline value" value={money(data.metrics.pipeline_value,currency)}/><Metric label="Weighted forecast" value={money(data.metrics.weighted_value,currency)}/><Metric label="Overdue actions" value={data.metrics.overdue_activities}/></div>
        <div className="cw-toolbar"><div><label htmlFor="pipeline-select">Pipeline</label><select id="pipeline-select" value={data.pipeline.id} onChange={e=>window.location.assign(`/crm/dashboard?pipeline=${e.target.value}`)}>{data.pipelines.map(p=><option value={p.id} key={p.id}>{p.name}</option>)}</select></div><span>{data.metrics.won_count} won in this view</span></div>
        <div className="cw-grid-wide"><section className="cw-board-wrap"><div className="cw-board" style={{'--cw-columns':Math.max(3,data.pipeline.stages.length)}}>{data.pipeline.stages.map(stage=><section className="cw-column" key={stage.id}><div className="cw-column-head"><span><i className="cw-stage-dot" style={{background:stage.color}}/> {stage.name}</span><small>{data.opportunities.filter(o=>o.crm_pipeline_stage_id===stage.id).length}</small></div>{data.opportunities.filter(o=>o.crm_pipeline_stage_id===stage.id).map(deal=><article className="cw-deal" key={deal.uuid}><h3 className="cw-deal-title">{deal.title}</h3><div className="cw-deal-value">{money(deal.estimated_value,deal.currency_code)}</div><div className="cw-deal-meta">{deal.contact?.name||'No contact'} · {fullName(deal.owner)}</div>{deal.expected_close_date&&<div className="cw-deal-meta"><i className="fa fa-calendar"/> {formatDate(deal.expected_close_date)}</div>}{data.permissions.manage_opportunities&&<select value={stage.id} onChange={e=>moveDeal(deal,e.target.value)} aria-label={`Move ${deal.title}`} disabled={busy}>{data.pipeline.stages.map(s=><option value={s.id} key={s.id}>{s.name}</option>)}</select>}<div className="cw-deal-actions">{data.permissions.manage_activities&&<button className="cw-link-btn" onClick={()=>{setSelected(deal);setModal('activity')}}><i className="fa fa-plus-circle"/> Activity</button>}{data.permissions.manage_opportunities&&<><button className="cw-link-btn" onClick={()=>{setSelected(deal);setModal('edit')}}><i className="fa fa-pencil"/> Edit</button><button className="cw-link-btn cw-danger-link" onClick={()=>archive(deal)}><i className="fa fa-archive"/> Archive</button></>}</div></article>)}</section>)}</div></section>
        <aside className="cw-card cw-sticky"><div className="cw-card-head"><h2 className="cw-card-title">Next actions</h2></div><div className="cw-card-body">{data.activities.length===0?<Empty icon="fa-check-circle-o" title="Action list clear" text="Add the next call, email, meeting or task from an opportunity."/>:<div className="cw-timeline">{data.activities.map(item=><div className="cw-timeline-item" key={item.uuid}><strong>{item.subject}</strong><div>{item.opportunity?.title}</div><small className={item.due_at&&new Date(item.due_at)<new Date()?'cw-overdue':''}>{item.due_at?formatDateTime(item.due_at):'No due date'} · {fullName(item.owner)}</small>{data.permissions.manage_activities&&<button className="cw-link-btn" onClick={()=>complete(item)}>Mark complete</button>}</div>)}</div>}</div></aside></div>
        {modal==='deal'&&<Modal title="Create opportunity" onClose={()=>setModal('')} wide><form className="cw-form" onSubmit={e=>{e.preventDefault();submit('/crm/workspace/opportunities',objectFromForm(e.currentTarget));}}><input type="hidden" name="pipeline_id" value={data.pipeline.id}/><div className="form-group"><label>Opportunity title</label><input name="title" className="form-control" required/></div><div className="cw-field-row"><div className="form-group"><label>Contact or lead</label><select name="contact_id" className="form-control"><option value="">Not linked yet</option>{Object.entries(data.contacts).map(([id,name])=><option value={id} key={id}>{name}</option>)}</select></div><div className="form-group"><label>Owner</label><select name="owner_id" className="form-control" required><option value="">Choose owner</option>{Object.entries(data.opportunity_users).map(([id,name])=><option value={id} key={id}>{name}</option>)}</select></div></div><div className="cw-field-row"><div className="form-group"><label>Starting stage</label><select name="stage_id" className="form-control">{openStages.map(s=><option value={s.id} key={s.id}>{s.name}</option>)}</select></div><div className="form-group"><label>Location</label><select name="business_location_id" className="form-control"><option value="">All / not assigned</option>{Object.entries(data.locations).map(([id,name])=><option value={id} key={id}>{name}</option>)}</select></div></div><div className="cw-field-row"><div className="form-group"><label>Estimated value</label><input name="estimated_value" type="number" min="0" step="0.01" className="form-control" defaultValue="0"/></div><div className="form-group"><label>Currency (ISO)</label><input name="currency_code" className="form-control" maxLength="3" placeholder="USD"/></div></div><div className="cw-field-row"><div className="form-group"><label>Expected close</label><input name="expected_close_date" type="date" className="form-control"/></div><div className="form-group"><label>Source</label><input name="source" className="form-control" placeholder="Referral, website, event…"/></div></div><div className="form-group"><label>Context</label><textarea name="description" className="form-control"/></div><div className="cw-modal-actions"><Button type="button" onClick={()=>setModal('')}>Cancel</Button><Button primary disabled={busy}>{busy?'Creating…':'Create opportunity'}</Button></div></form></Modal>}
        {modal==='edit'&&selected&&<Modal title={`Edit opportunity · ${selected.title}`} onClose={()=>setModal('')} wide><form className="cw-form" onSubmit={e=>{e.preventDefault();submit(`/crm/workspace/opportunities/${selected.uuid}`,objectFromForm(e.currentTarget),'PUT')}}><div className="form-group"><label>Opportunity title</label><input name="title" className="form-control" defaultValue={selected.title} required/></div><div className="form-group"><label>Context</label><textarea name="description" className="form-control" defaultValue={selected.description||''}/></div><div className="cw-field-row"><div className="form-group"><label>Owner</label><select name="owner_id" className="form-control" defaultValue={selected.owner_id} required>{Object.entries(data.opportunity_users).map(([id,name])=><option value={id} key={id}>{name}</option>)}</select></div><div className="form-group"><label>Location</label><select name="business_location_id" className="form-control" defaultValue={selected.business_location_id||''}><option value="">All / not assigned</option>{Object.entries(data.locations).map(([id,name])=><option value={id} key={id}>{name}</option>)}</select></div></div><div className="cw-field-row"><div className="form-group"><label>Estimated value</label><input name="estimated_value" type="number" min="0" step="0.01" className="form-control" defaultValue={Number(selected.estimated_value||0)}/></div><div className="form-group"><label>Currency (ISO)</label><input name="currency_code" className="form-control" maxLength="3" defaultValue={selected.currency_code||''}/></div></div><div className="form-group"><label>Expected close</label><input name="expected_close_date" type="date" className="form-control" defaultValue={(selected.expected_close_date||'').slice(0,10)}/></div><div className="cw-modal-actions"><Button type="button" onClick={()=>setModal('')}>Cancel</Button><Button primary disabled={busy}>{busy?'Saving…':'Save changes'}</Button></div></form></Modal>}
        {modal==='activity'&&selected&&<Modal title={`Add activity · ${selected.title}`} onClose={()=>setModal('')}><form className="cw-form" onSubmit={e=>{e.preventDefault();submit(`/crm/workspace/opportunities/${selected.uuid}/activities`,objectFromForm(e.currentTarget));}}><div className="cw-field-row"><div className="form-group"><label>Activity</label><select name="type" className="form-control"><option value="task">Task</option><option value="call">Call</option><option value="email">Email</option><option value="meeting">Meeting</option><option value="note">Communication note</option></select></div><div className="form-group"><label>Direction</label><select name="direction" className="form-control"><option value="internal">Internal</option><option value="outbound">Outbound</option><option value="inbound">Inbound</option></select></div></div><div className="form-group"><label>Subject</label><input name="subject" className="form-control" required/></div><div className="form-group"><label>Details</label><textarea name="details" className="form-control"/></div><div className="form-group"><label>Owner</label><select name="owner_id" className="form-control" required>{Object.entries(data.activity_users).map(([id,name])=><option value={id} key={id}>{name}</option>)}</select></div><div className="cw-field-row"><div className="form-group"><label>Due</label><input type="datetime-local" name="due_at" className="form-control"/></div><div className="form-group"><label>Remind at</label><input type="datetime-local" name="remind_at" className="form-control"/></div></div><div className="cw-modal-actions"><Button type="button" onClick={()=>setModal('')}>Cancel</Button><Button primary disabled={busy}>Save activity</Button></div></form></Modal>}
        {modal==='pipeline'&&<Modal title="Configure pipeline" onClose={()=>setModal('')} wide><div className="cw-split"><form className="cw-form" onSubmit={e=>{e.preventDefault();submit('/crm/workspace/pipelines',objectFromForm(e.currentTarget));}}><h3>New pipeline</h3><div className="form-group"><label>Name</label><input name="name" className="form-control" required/></div><Button primary disabled={busy}>Create pipeline</Button></form><form className="cw-form" onSubmit={e=>{e.preventDefault();submit(`/crm/workspace/pipelines/${data.pipeline.id}/stages`,objectFromForm(e.currentTarget));}}><h3>Add stage to {data.pipeline.name}</h3><div className="form-group"><label>Stage name</label><input name="name" className="form-control" required/></div><div className="cw-field-row"><div className="form-group"><label>Probability %</label><input name="probability" className="form-control" type="number" min="0" max="100" defaultValue="50"/></div><div className="form-group"><label>Colour</label><input name="color" className="form-control" type="color" defaultValue="#1769aa"/></div></div><div className="form-group"><label>Outcome</label><select name="outcome" className="form-control"><option value="open">Open</option><option value="won">Won</option><option value="lost">Lost</option></select></div><Button primary disabled={busy}>Add stage</Button></form></div></Modal>}
    </div></main></WorkspaceFrame>;
}

function Metric({label,value,compact}) { return <div className="cw-metric"><span className="cw-metric-label">{label}</span><strong className={`cw-metric-value ${compact?'is-compact':''}`}>{value}</strong></div>; }
function Loading(){return <div className="cw-full-state" role="status"><i className="fa fa-circle-o-notch fa-spin"/><strong>Preparing your secure workspace…</strong></div>}
function Failure({error,retry}){return <div className="cw-full-state is-error"><i className="fa fa-exclamation-triangle"/><strong>Workspace unavailable</strong><p>{error}</p><Button onClick={retry}>Try again</Button></div>}
function formatDate(value){if(!value)return '—';return new Intl.DateTimeFormat(undefined,{dateStyle:'medium'}).format(new Date(value));}
function formatDateTime(value){if(!value)return '—';return new Intl.DateTimeFormat(undefined,{dateStyle:'medium',timeStyle:'short'}).format(new Date(value));}
function money(value,currency){const amount=Number(value||0);try{return new Intl.NumberFormat(undefined,currency?{style:'currency',currency}:{maximumFractionDigits:2}).format(amount)}catch{return amount.toLocaleString()}}

function App(){
    const path=window.location.pathname.replace(/\/$/,'');
    const isCommercial=['/sales','/commercial','/sells','/sales-order','/sells/quotations','/sells/drafts','/sell-return','/shipments','/smart-documents'].includes(path)||(path==='/contacts'&&new URLSearchParams(window.location.search).get('type')==='customer');
    const isAccounting=path==='/accounting'||path==='/account/account';
    useEffect(()=>{document.title=path==='/home'?'Dashboard · CashERP':isCommercial?'Sales · CashERP':isAccounting?'Accounting · CashERP':path.startsWith('/crm')?'CRM · CashERP':'Company Hub · CashERP'},[path,isCommercial,isAccounting]);
    if(path==='/home')return <MainDashboard/>;
    if(isCommercial)return <CommercialWorkspace/>;
    if(isAccounting)return <AccountingWorkspace/>;
    if(path.startsWith('/crm'))return <CrmWorkspace/>;
    if(path==='/company-hub/resources')return <Resources/>;
    if(path==='/company-hub/events')return <Events/>;
    if(path==='/company-hub/directory')return <Directory/>;
    if(path==='/company-hub/settings')return <Settings/>;
    return <HubFeed/>;
}

class ErrorBoundary extends React.Component {
    constructor(props){super(props);this.state={error:null};}
    static getDerivedStateFromError(error){return{error};}
    componentDidCatch(error,info){console.error('CashERP workspace rendering failure',error,info);}
    render(){return this.state.error?<Failure error="The interface could not be rendered. Refresh the page or contact your administrator." retry={()=>window.location.reload()}/>:this.props.children;}
}

const root=document.getElementById('casherp-workspace-root');
if(root)createRoot(root).render(<ErrorBoundary><App/></ErrorBoundary>);
