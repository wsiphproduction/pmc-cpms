import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';

// ── Types ──────────────────────────────────────────────────────────────────
interface UserRow {
    id: number;
    name: string;
    email: string;
    department: string | null;
    role: string | null;
    created_at: string;
}

interface TrashedUserRow {
    id: number;
    name: string;
    email: string;
    role: string | null;
    deleted_at: string;
}

interface Props {
    users: UserRow[];
    trashedUsers: TrashedUserRow[];
    roles: string[];
    departments: string[];
}

// ── Helpers ────────────────────────────────────────────────────────────────
const formatDate = (value?: string | null) => {
    if (!value) return '—';
    const d = new Date(value);
    return isNaN(d.getTime()) ? value : d.toLocaleString('en-US', {
        month: 'short', day: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};

// Display labels only — underlying DB role slugs ('requestor'/'approver'/'admin') are unchanged.
const ROLE_LABELS: Record<string, string> = {
    admin:     'Admin',
    approver:  'Project Engineer',
    requestor: 'Department User',
};

const roleLabel = (role: string): string =>
    ROLE_LABELS[role] ?? (role.charAt(0).toUpperCase() + role.slice(1));

function RoleBadge({ role }: { role: string | null }) {
    const map: Record<string, { bg: string; color: string }> = {
        admin:     { bg: '#dbeafe', color: '#1e40af' },
        approver:  { bg: '#dcfce7', color: '#166534' },
        requestor: { bg: '#fef9c3', color: '#854d0e' },
    };
    const s = map[role ?? ''] ?? { bg: '#f3f4f6', color: '#374151' };
    return (
        <span style={{
            padding: '3px 10px', borderRadius: '99px', fontSize: '10.5px',
            fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.3px',
            background: s.bg, color: s.color,
        }}>
            {role ? roleLabel(role) : 'no role'}
        </span>
    );
}

function IconBtn({ onClick, title, color = '#374151', children }: {
    onClick?: () => void; title?: string; color?: string; children: React.ReactNode;
}) {
    return (
        <button
            onClick={onClick}
            title={title}
            style={{
                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                width: '30px', height: '30px', borderRadius: '6px',
                border: '1px solid #e5e7eb', background: '#fff', color,
                cursor: 'pointer', transition: 'all 0.12s', flexShrink: 0,
            }}
            onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
            onMouseLeave={e => (e.currentTarget.style.background = '#fff')}
        >
            {children}
        </button>
    );
}

// ── Modal ──────────────────────────────────────────────────────────────────
function Modal({ title, subtitle, onClose, children }: {
    title: string; subtitle?: string; onClose: () => void; children: React.ReactNode;
}) {
    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 200, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.35)' }} />
            <div style={{
                position: 'relative', background: '#fff', borderRadius: '12px',
                width: '100%', maxWidth: '440px',
                boxShadow: '0 20px 60px rgba(15,23,42,0.25)', zIndex: 201, overflow: 'hidden',
            }}>
                <div style={{ padding: '18px 20px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div>
                        <div style={{ fontSize: '14px', fontWeight: 800, color: '#0f172a' }}>{title}</div>
                        {subtitle && <div style={{ fontSize: '11.5px', color: '#9ca3af', marginTop: '2px' }}>{subtitle}</div>}
                    </div>
                    <button onClick={onClose} style={{ width: '28px', height: '28px', borderRadius: '6px', border: '1px solid #e5e7eb', background: '#fff', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6b7280" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div style={{ padding: '20px' }}>{children}</div>
            </div>
        </div>
    );
}

const inputStyle: React.CSSProperties = {
    width: '100%', padding: '8px 12px', border: '1.5px solid #e5e7eb', borderRadius: '7px',
    fontSize: '13px', outline: 'none', boxSizing: 'border-box', color: '#111827',
    fontFamily: 'inherit', transition: 'border-color 0.15s',
};
const labelStyle: React.CSSProperties = {
    display: 'block', fontSize: '11.5px', fontWeight: 700, color: '#374151', marginBottom: '5px',
};
const fieldStyle: React.CSSProperties = { marginBottom: '14px' };

// ── Main Page ──────────────────────────────────────────────────────────────
export default function UsersIndex({ users, trashedUsers, roles, departments }: Props) {
    const { props } = usePage<{ flash?: { success?: string }; errors?: Record<string, string> }>();
    const flash  = props.flash;
    const errors = props.errors ?? {};

    const [tab,        setTab]        = useState<'active' | 'trash'>('active');
    const [showAdd,    setShowAdd]    = useState(false);
    const [editUser,   setEditUser]   = useState<UserRow | null>(null);
    const [resetUser,  setResetUser]  = useState<UserRow | null>(null);
    const [deleteUser, setDeleteUser] = useState<UserRow | null>(null);
    const [forceUser,  setForceUser]  = useState<TrashedUserRow | null>(null);
    const [search,     setSearch]     = useState('');
    const [filterRole, setFilterRole] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const [addForm,  setAddForm]  = useState({ name: '', email: '', password: '', role: roles[0] ?? '', department: '' });
    const [editForm, setEditForm] = useState({ name: '', email: '', role: '', department: '' });
    const [resetPw,  setResetPw]  = useState({ password: '', password_confirmation: '' });

    const filtered = users.filter(u => {
        const q = search.toLowerCase();
        return (!q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q))
            && (!filterRole || u.role === filterRole);
    });

    const filteredTrashed = trashedUsers.filter(u => {
        const q = search.toLowerCase();
        return !q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q);
    });

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post(route('users.store'), addForm, {
            onFinish:  () => setSubmitting(false),
            onSuccess: () => { setShowAdd(false); setAddForm({ name: '', email: '', password: '', role: roles[0] ?? '', department: '' }); },
        });
    };

    const openEdit = (u: UserRow) => {
        setEditForm({ name: u.name, email: u.email, role: u.role ?? roles[0] ?? '', department: u.department ?? '' });
        setEditUser(u);
    };

    const handleEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editUser) return;
        setSubmitting(true);
        router.put(route('users.update', editUser.id), editForm, {
            onFinish:  () => setSubmitting(false),
            onSuccess: () => setEditUser(null),
        });
    };

    const handleResetPw = (e: React.FormEvent) => {
        e.preventDefault();
        if (!resetUser) return;
        setSubmitting(true);
        router.patch(route('users.reset-password', resetUser.id), resetPw, {
            onFinish:  () => setSubmitting(false),
            onSuccess: () => { setResetUser(null); setResetPw({ password: '', password_confirmation: '' }); },
        });
    };

    const handleDelete = () => {
        if (!deleteUser) return;
        router.delete(route('users.destroy', deleteUser.id), {
            onSuccess: () => { setDeleteUser(null); setTab('active'); },
        });
    };

    const handleRestore = (u: TrashedUserRow) => {
        router.patch(route('users.restore', u.id));
    };

    const handleForceDelete = () => {
        if (!forceUser) return;
        router.delete(route('users.force-delete', forceUser.id), {
            onSuccess: () => setForceUser(null),
        });
    };

    const clearSearch = () => { setSearch(''); setFilterRole(''); };

    const btnPrimary: React.CSSProperties = {
        display: 'inline-flex', alignItems: 'center', gap: '6px',
        padding: '7px 14px', borderRadius: '7px',
        background: '#2563eb', color: '#fff', border: 'none',
        fontSize: '12.5px', fontWeight: 600, cursor: 'pointer',
    };
    const btnSecondary: React.CSSProperties = {
        padding: '8px 16px', borderRadius: '7px',
        border: '1px solid #e5e7eb', background: '#fff',
        color: '#374151', fontSize: '12.5px', fontWeight: 600, cursor: 'pointer',
    };
    const btnDanger: React.CSSProperties = {
        ...btnSecondary, color: '#dc2626', borderColor: '#fca5a5',
    };

    const tabStyle = (active: boolean): React.CSSProperties => ({
        padding: '7px 16px', borderRadius: '7px', fontSize: '12.5px', fontWeight: 600,
        cursor: 'pointer', border: 'none',
        background: active ? '#2563eb' : 'transparent',
        color: active ? '#fff' : '#6b7280',
        transition: 'all 0.12s',
    });

    return (
        <AuthenticatedLayout>
            <Head title="User Management" />

            {/* Flash */}
            {flash?.success && (
                <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '8px', padding: '10px 16px', marginBottom: '14px', color: '#15803d', fontSize: '13px', fontWeight: 500 }}>
                    {flash.success}
                </div>
            )}
            {errors.error && (
                <div style={{ background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '8px', padding: '10px 16px', marginBottom: '14px', color: '#dc2626', fontSize: '13px', fontWeight: 500 }}>
                    {errors.error}
                </div>
            )}

            {/* Header */}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '18px' }}>
                <h1 style={{ fontSize: '20px', fontWeight: 800, color: '#0f172a', margin: 0, letterSpacing: '-0.3px' }}>
                    User Management
                </h1>
                <span style={{ fontSize: '12.5px', color: '#9ca3af' }}>
                    {users.length} active · {trashedUsers.length} trashed
                </span>
            </div>

            {/* Stats cards */}
            <div style={{ display: 'flex', gap: '12px', marginBottom: '18px' }}>
                {[
                    { label: 'Total Users',  value: users.length,                                        color: '#2563eb' },
                    { label: 'Admins',       value: users.filter(u => u.role === 'admin').length,     color: '#7c3aed' },
                    { label: 'Project Engineers', value: users.filter(u => u.role === 'approver').length,  color: '#16a34a' },
                    { label: 'Department Users',  value: users.filter(u => u.role === 'requestor').length, color: '#92400e' },
                ].map(stat => (
                    <div key={stat.label} style={{
                        background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px',
                        padding: '14px 20px', flex: 1,
                    }}>
                        <div style={{ fontSize: '22px', fontWeight: 800, color: stat.color }}>{stat.value}</div>
                        <div style={{ fontSize: '11.5px', color: '#6b7280', fontWeight: 500, marginTop: '2px' }}>{stat.label}</div>
                    </div>
                ))}
            </div>

            {/* Main card */}
            <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '12px', overflow: 'hidden' }}>

                {/* Toolbar */}
                <div style={{ padding: '14px 18px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap', justifyContent: 'space-between' }}>
                    {/* Left: tabs + search */}
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        {/* Tabs */}
                        <div style={{ display: 'flex', gap: '4px', background: '#f3f4f6', padding: '3px', borderRadius: '9px', marginRight: '4px' }}>
                            <button style={tabStyle(tab === 'active')} onClick={() => setTab('active')}>
                                Active
                                {users.length > 0 && (
                                    <span style={{ marginLeft: '5px', background: tab === 'active' ? 'rgba(255,255,255,0.25)' : '#e5e7eb', borderRadius: '99px', padding: '1px 7px', fontSize: '11px' }}>
                                        {users.length}
                                    </span>
                                )}
                            </button>
                            <button style={tabStyle(tab === 'trash')} onClick={() => setTab('trash')}>
                                Trash
                                {trashedUsers.length > 0 && (
                                    <span style={{ marginLeft: '5px', background: tab === 'trash' ? 'rgba(255,255,255,0.25)' : '#fee2e2', color: tab === 'trash' ? 'inherit' : '#dc2626', borderRadius: '99px', padding: '1px 7px', fontSize: '11px', fontWeight: 700 }}>
                                        {trashedUsers.length}
                                    </span>
                                )}
                            </button>
                        </div>

                        {/* Search */}
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', background: '#f8fafc', border: '1.5px solid #e5e7eb', borderRadius: '8px', padding: '7px 12px', minWidth: '220px' }}>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                            <input
                                type="text"
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                placeholder="Search name or email…"
                                style={{ border: 'none', background: 'transparent', outline: 'none', fontSize: '13px', color: '#374151', width: '100%', fontFamily: 'inherit' }}
                            />
                            {search && (
                                <button onClick={clearSearch} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#9ca3af', display: 'flex', padding: 0 }}>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Right controls */}
                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                        {tab === 'active' && (
                            <>
                                <select
                                    value={filterRole}
                                    onChange={e => setFilterRole(e.target.value)}
                                    style={{ padding: '7px 10px', borderRadius: '7px', border: '1.5px solid #e5e7eb', fontSize: '12.5px', outline: 'none', fontFamily: 'inherit', color: '#374151', background: '#fff', cursor: 'pointer' }}
                                >
                                    <option value="">All Roles</option>
                                    {roles.map(r => (
                                        <option key={r} value={r}>{roleLabel(r)}</option>
                                    ))}
                                </select>
                                <button style={btnPrimary} onClick={() => setShowAdd(true)}>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Add User
                                </button>
                            </>
                        )}
                    </div>
                </div>

                {/* ── Active Users Table ── */}
                {tab === 'active' && (
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                            <thead>
                                <tr style={{ background: '#f8fafc' }}>
                                    {['Name', 'Email', 'Role', 'Created At', 'Actions'].map((h, i) => (
                                        <th key={h} style={{
                                            padding: '10px 16px', textAlign: i === 4 ? 'right' : 'left',
                                            fontSize: '10.5px', fontWeight: 700, color: '#9ca3af',
                                            textTransform: 'uppercase', letterSpacing: '0.5px',
                                            borderBottom: '1px solid #f3f4f6', whiteSpace: 'nowrap',
                                        }}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} style={{ padding: '48px', textAlign: 'center' }}>
                                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" strokeWidth="1.5" style={{ display: 'block', margin: '0 auto 10px' }}>
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                                <circle cx="9" cy="7" r="4"/>
                                            </svg>
                                            <span style={{ fontSize: '13px', color: '#9ca3af' }}>No users found.</span>
                                        </td>
                                    </tr>
                                ) : filtered.map(u => (
                                    <tr key={u.id}
                                        style={{ borderBottom: '1px solid #f8fafc', transition: 'background 0.1s' }}
                                        onMouseEnter={e => (e.currentTarget.style.background = '#f8fafc')}
                                        onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                    >
                                        <td style={{ padding: '12px 16px', fontWeight: 600, color: '#0f172a', whiteSpace: 'nowrap' }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                                <div style={{
                                                    width: '30px', height: '30px', borderRadius: '50%',
                                                    background: '#eff6ff', color: '#2563eb', flexShrink: 0,
                                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                    fontSize: '11px', fontWeight: 800,
                                                }}>
                                                    {u.name.slice(0, 2).toUpperCase()}
                                                </div>
                                                {u.name}
                                            </div>
                                        </td>
                                        <td style={{ padding: '12px 16px', color: '#6b7280' }}>{u.email}</td>
                                        <td style={{ padding: '12px 16px' }}><RoleBadge role={u.role} /></td>
                                        <td style={{ padding: '12px 16px', color: '#64748b', fontSize: '12px', whiteSpace: 'nowrap' }}>{formatDate(u.created_at)}</td>
                                        <td style={{ padding: '12px 16px' }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '4px', justifyContent: 'flex-end' }}>
                                                <IconBtn title="Edit user" onClick={() => openEdit(u)}>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </IconBtn>
                                                <IconBtn title="Reset password" color="#92400e" onClick={() => setResetUser(u)}>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                                </IconBtn>
                                                <IconBtn title="Move to trash" color="#dc2626" onClick={() => setDeleteUser(u)}>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                                </IconBtn>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* ── Trash Table ── */}
                {tab === 'trash' && (
                    <div style={{ overflowX: 'auto' }}>
                        {trashedUsers.length > 0 && (
                            <div style={{ padding: '10px 18px', background: '#fef9c3', borderBottom: '1px solid #fde68a', fontSize: '12.5px', color: '#92400e', fontWeight: 500 }}>
                                Soft-deleted users are listed below. You can restore them or permanently delete them.
                            </div>
                        )}
                        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                            <thead>
                                <tr style={{ background: '#fef2f2' }}>
                                    {['Name', 'Email', 'Role', 'Deleted At', 'Actions'].map((h, i) => (
                                        <th key={h} style={{
                                            padding: '10px 16px', textAlign: i === 4 ? 'right' : 'left',
                                            fontSize: '10.5px', fontWeight: 700, color: '#9ca3af',
                                            textTransform: 'uppercase', letterSpacing: '0.5px',
                                            borderBottom: '1px solid #fecaca', whiteSpace: 'nowrap',
                                        }}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {filteredTrashed.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} style={{ padding: '48px', textAlign: 'center' }}>
                                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" strokeWidth="1.5" style={{ display: 'block', margin: '0 auto 10px' }}>
                                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                            </svg>
                                            <span style={{ fontSize: '13px', color: '#9ca3af' }}>Trash is empty.</span>
                                        </td>
                                    </tr>
                                ) : filteredTrashed.map(u => (
                                    <tr key={u.id}
                                        style={{ borderBottom: '1px solid #fef2f2', transition: 'background 0.1s', opacity: 0.85 }}
                                        onMouseEnter={e => (e.currentTarget.style.background = '#fff5f5')}
                                        onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                    >
                                        <td style={{ padding: '12px 16px', fontWeight: 600, color: '#6b7280', whiteSpace: 'nowrap' }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                                <div style={{
                                                    width: '30px', height: '30px', borderRadius: '50%',
                                                    background: '#f3f4f6', color: '#9ca3af', flexShrink: 0,
                                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                    fontSize: '11px', fontWeight: 800,
                                                }}>
                                                    {u.name.slice(0, 2).toUpperCase()}
                                                </div>
                                                <span style={{ textDecoration: 'line-through' }}>{u.name}</span>
                                            </div>
                                        </td>
                                        <td style={{ padding: '12px 16px', color: '#9ca3af', textDecoration: 'line-through' }}>{u.email}</td>
                                        <td style={{ padding: '12px 16px' }}><RoleBadge role={u.role} /></td>
                                        <td style={{ padding: '12px 16px', color: '#dc2626', fontSize: '12px', whiteSpace: 'nowrap' }}>{formatDate(u.deleted_at)}</td>
                                        <td style={{ padding: '12px 16px' }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '4px', justifyContent: 'flex-end' }}>
                                                <IconBtn title="Restore user" color="#16a34a" onClick={() => handleRestore(u)}>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.65"/></svg>
                                                </IconBtn>
                                                <IconBtn title="Permanently delete" color="#dc2626" onClick={() => setForceUser(u)}>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                                </IconBtn>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Footer */}
                <div style={{ padding: '12px 18px', borderTop: '1px solid #f3f4f6' }}>
                    <span style={{ fontSize: '12.5px', color: '#9ca3af' }}>
                        {tab === 'active'
                            ? <>Showing <strong style={{ color: '#374151' }}>{filtered.length}</strong> of <strong style={{ color: '#374151' }}>{users.length}</strong> active users</>
                            : <>Showing <strong style={{ color: '#374151' }}>{filteredTrashed.length}</strong> of <strong style={{ color: '#374151' }}>{trashedUsers.length}</strong> trashed users</>
                        }
                    </span>
                </div>
            </div>

            {/* ── Add User Modal ── */}
            {showAdd && (
                <Modal title="Add New User" onClose={() => setShowAdd(false)}>
                    <form onSubmit={handleAdd}>
                        <div style={fieldStyle}>
                            <label style={labelStyle}>Full Name</label>
                            <input style={inputStyle} value={addForm.name} onChange={e => setAddForm(f => ({ ...f, name: e.target.value }))} required
                                onFocus={e => (e.target.style.borderColor = '#2563eb')} onBlur={e => (e.target.style.borderColor = '#e5e7eb')} />
                            {errors.name && <p style={{ margin: '4px 0 0', color: '#dc2626', fontSize: '12px' }}>{errors.name}</p>}
                        </div>
                        <div style={fieldStyle}>
                            <label style={labelStyle}>Email Address</label>
                            <input style={inputStyle} type="email" value={addForm.email} onChange={e => setAddForm(f => ({ ...f, email: e.target.value }))} required
                                onFocus={e => (e.target.style.borderColor = '#2563eb')} onBlur={e => (e.target.style.borderColor = '#e5e7eb')} />
                            {errors.email && <p style={{ margin: '4px 0 0', color: '#dc2626', fontSize: '12px' }}>{errors.email}</p>}
                        </div>
                        <div style={fieldStyle}>
                            <label style={labelStyle}>Password</label>
                            <input style={inputStyle} type="password" value={addForm.password} onChange={e => setAddForm(f => ({ ...f, password: e.target.value }))} required
                                onFocus={e => (e.target.style.borderColor = '#2563eb')} onBlur={e => (e.target.style.borderColor = '#e5e7eb')} />
                            {errors.password && <p style={{ margin: '4px 0 0', color: '#dc2626', fontSize: '12px' }}>{errors.password}</p>}
                        </div>
                        <div style={fieldStyle}>
                            <label style={labelStyle}>Role</label>
                            <select style={inputStyle} value={addForm.role} onChange={e => setAddForm(f => ({ ...f, role: e.target.value }))} required>
                                {roles.map(r => <option key={r} value={r}>{roleLabel(r)}</option>)}
                            </select>
                            {errors.role && <p style={{ margin: '4px 0 0', color: '#dc2626', fontSize: '12px' }}>{errors.role}</p>}
                        </div>
                        {addForm.role === 'requestor' && (
                            <div style={fieldStyle}>
                                <label style={labelStyle}>Department</label>
                                <select style={inputStyle} value={addForm.department} onChange={e => setAddForm(f => ({ ...f, department: e.target.value }))} required>
                                    <option value="">Select department…</option>
                                    {departments.map(d => <option key={d} value={d}>{d}</option>)}
                                </select>
                                {errors.department && <p style={{ margin: '4px 0 0', color: '#dc2626', fontSize: '12px' }}>{errors.department}</p>}
                            </div>
                        )}
                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end', marginTop: '6px' }}>
                            <button type="button" style={btnSecondary} onClick={() => setShowAdd(false)}>Cancel</button>
                            <button type="submit" style={{ ...btnPrimary, opacity: submitting ? 0.7 : 1 }} disabled={submitting}>
                                {submitting ? 'Creating…' : 'Create User'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* ── Edit User Modal ── */}
            {editUser && (
                <Modal title="Edit User" subtitle={editUser.email} onClose={() => setEditUser(null)}>
                    <form onSubmit={handleEdit}>
                        <div style={fieldStyle}>
                            <label style={labelStyle}>Full Name</label>
                            <input style={inputStyle} value={editForm.name} onChange={e => setEditForm(f => ({ ...f, name: e.target.value }))} required
                                onFocus={e => (e.target.style.borderColor = '#2563eb')} onBlur={e => (e.target.style.borderColor = '#e5e7eb')} />
                            {errors.name && <p style={{ margin: '4px 0 0', color: '#dc2626', fontSize: '12px' }}>{errors.name}</p>}
                        </div>
                        <div style={fieldStyle}>
                            <label style={labelStyle}>Email Address</label>
                            <input style={inputStyle} type="email" value={editForm.email} onChange={e => setEditForm(f => ({ ...f, email: e.target.value }))} required
                                onFocus={e => (e.target.style.borderColor = '#2563eb')} onBlur={e => (e.target.style.borderColor = '#e5e7eb')} />
                            {errors.email && <p style={{ margin: '4px 0 0', color: '#dc2626', fontSize: '12px' }}>{errors.email}</p>}
                        </div>
                        <div style={fieldStyle}>
                            <label style={labelStyle}>Role</label>
                            <select style={inputStyle} value={editForm.role} onChange={e => setEditForm(f => ({ ...f, role: e.target.value }))} required>
                                {roles.map(r => <option key={r} value={r}>{roleLabel(r)}</option>)}
                            </select>
                            {errors.role && <p style={{ margin: '4px 0 0', color: '#dc2626', fontSize: '12px' }}>{errors.role}</p>}
                        </div>
                        {editForm.role === 'requestor' && (
                            <div style={fieldStyle}>
                                <label style={labelStyle}>Department</label>
                                <select style={inputStyle} value={editForm.department} onChange={e => setEditForm(f => ({ ...f, department: e.target.value }))} required>
                                    <option value="">Select department…</option>
                                    {departments.map(d => <option key={d} value={d}>{d}</option>)}
                                </select>
                                {errors.department && <p style={{ margin: '4px 0 0', color: '#dc2626', fontSize: '12px' }}>{errors.department}</p>}
                            </div>
                        )}
                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end', marginTop: '6px' }}>
                            <button type="button" style={btnSecondary} onClick={() => setEditUser(null)}>Cancel</button>
                            <button type="submit" style={{ ...btnPrimary, opacity: submitting ? 0.7 : 1 }} disabled={submitting}>
                                {submitting ? 'Saving…' : 'Save Changes'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* ── Reset Password Modal ── */}
            {resetUser && (
                <Modal title="Reset Password" subtitle={resetUser.name} onClose={() => setResetUser(null)}>
                    <form onSubmit={handleResetPw}>
                        <div style={fieldStyle}>
                            <label style={labelStyle}>New Password</label>
                            <input style={inputStyle} type="password" value={resetPw.password} onChange={e => setResetPw(f => ({ ...f, password: e.target.value }))} required
                                onFocus={e => (e.target.style.borderColor = '#2563eb')} onBlur={e => (e.target.style.borderColor = '#e5e7eb')} />
                            {errors.password && <p style={{ margin: '4px 0 0', color: '#dc2626', fontSize: '12px' }}>{errors.password}</p>}
                        </div>
                        <div style={fieldStyle}>
                            <label style={labelStyle}>Confirm Password</label>
                            <input style={inputStyle} type="password" value={resetPw.password_confirmation} onChange={e => setResetPw(f => ({ ...f, password_confirmation: e.target.value }))} required
                                onFocus={e => (e.target.style.borderColor = '#2563eb')} onBlur={e => (e.target.style.borderColor = '#e5e7eb')} />
                        </div>
                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end', marginTop: '6px' }}>
                            <button type="button" style={btnSecondary} onClick={() => setResetUser(null)}>Cancel</button>
                            <button type="submit" style={{ padding: '8px 16px', borderRadius: '7px', border: 'none', background: submitting ? '#fca5a5' : '#dc2626', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: submitting ? 'not-allowed' : 'pointer' }} disabled={submitting}>
                                {submitting ? 'Resetting…' : 'Reset Password'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* ── Move to Trash Confirm Modal ── */}
            {deleteUser && (
                <Modal title="Move to Trash" subtitle={deleteUser.email} onClose={() => setDeleteUser(null)}>
                    <p style={{ margin: '0 0 20px', fontSize: '13px', lineHeight: 1.6, color: '#475569' }}>
                        Are you sure you want to move <strong>{deleteUser.name}</strong> to the trash? They can be restored later from the Trash tab.
                    </p>
                    <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                        <button style={btnSecondary} onClick={() => setDeleteUser(null)}>Cancel</button>
                        <button style={btnDanger} onClick={handleDelete}>Move to Trash</button>
                    </div>
                </Modal>
            )}

            {/* ── Permanent Delete Confirm Modal ── */}
            {forceUser && (
                <Modal title="Permanently Delete" subtitle={forceUser.email} onClose={() => setForceUser(null)}>
                    <p style={{ margin: '0 0 20px', fontSize: '13px', lineHeight: 1.6, color: '#475569' }}>
                        This will <strong>permanently delete</strong> <strong>{forceUser.name}</strong> and cannot be undone. Are you absolutely sure?
                    </p>
                    <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                        <button style={btnSecondary} onClick={() => setForceUser(null)}>Cancel</button>
                        <button style={{ padding: '8px 16px', borderRadius: '7px', border: 'none', background: '#dc2626', color: '#fff', fontSize: '12.5px', fontWeight: 700, cursor: 'pointer' }} onClick={handleForceDelete}>
                            Permanently Delete
                        </button>
                    </div>
                </Modal>
            )}
        </AuthenticatedLayout>
    );
}
