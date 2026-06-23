import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { HubProject, HubShell } from './Common';

interface Task {
    id: number;
    task_name: string;
    target_date: string;
    status: 'pending' | 'done';
}

const ACCENT = '#7c3aed';

function isOverdue(dateStr: string, status: string) {
    if (status === 'done') return false;
    return new Date(dateStr) < new Date(new Date().toDateString());
}

export default function TodoHub({ project, tasks }: { project: HubProject; tasks: Task[] }) {
    const [taskName,   setTaskName]   = useState('');
    const [targetDate, setTargetDate] = useState('');
    const [adding,     setAdding]     = useState(false);
    const nameRef = useRef<HTMLInputElement>(null);

    const inp: React.CSSProperties = {
        width: '100%', padding: '8px 11px', border: '1.5px solid #e5e7eb',
        borderRadius: '7px', fontSize: '13px', outline: 'none', boxSizing: 'border-box',
        background: '#fff',
    };

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();
        if (!taskName.trim() || !targetDate) return;
        setAdding(true);
        router.post(
            route('hub.todo.store', project.id),
            { task_name: taskName.trim(), target_date: targetDate },
            {
                preserveScroll: true,
                onSuccess: () => { setTaskName(''); setTargetDate(''); nameRef.current?.focus(); },
                onFinish:  () => setAdding(false),
            },
        );
    };

    const handleToggle = (task: Task) => {
        router.patch(route('hub.todo.toggle', { project: project.id, task: task.id }), {}, { preserveScroll: true });
    };

    const handleDelete = (task: Task) => {
        if (!confirm(`Delete "${task.task_name}"?`)) return;
        router.delete(route('hub.todo.destroy', { project: project.id, task: task.id }), { preserveScroll: true });
    };

    const pending = tasks.filter(t => t.status === 'pending');
    const done    = tasks.filter(t => t.status === 'done');

    return (
        <HubShell>
            {/* Header */}
            <div style={{ borderBottom: `2px solid ${ACCENT}`, paddingBottom: '14px', marginBottom: '24px' }}>
                <h3 style={{ color: ACCENT, margin: '0 0 4px', fontSize: '15px', fontWeight: 800 }}>TODO LIST</h3>
                <span style={{ color: '#64748b', fontSize: '12px' }}>Track project tasks and target completion dates.</span>
            </div>

            {/* Add form */}
            <form onSubmit={handleAdd} style={{ display: 'flex', gap: '10px', marginBottom: '28px', alignItems: 'flex-end', background: '#f5f3ff', border: `1.5px solid #ede9fe`, borderRadius: '10px', padding: '16px' }}>
                <div style={{ flex: 1 }}>
                    <label style={{ display: 'block', fontSize: '11px', fontWeight: 700, color: '#6d28d9', marginBottom: '5px', textTransform: 'uppercase', letterSpacing: '0.4px' }}>Task Name</label>
                    <input
                        ref={nameRef}
                        type="text"
                        value={taskName}
                        onChange={e => setTaskName(e.target.value)}
                        placeholder="Enter task description…"
                        style={inp}
                        required
                    />
                </div>
                <div style={{ width: '160px' }}>
                    <label style={{ display: 'block', fontSize: '11px', fontWeight: 700, color: '#6d28d9', marginBottom: '5px', textTransform: 'uppercase', letterSpacing: '0.4px' }}>Target Date</label>
                    <input
                        type="date"
                        value={targetDate}
                        onChange={e => setTargetDate(e.target.value)}
                        style={inp}
                        required
                    />
                </div>
                <button
                    type="submit"
                    disabled={adding}
                    style={{ padding: '8px 20px', borderRadius: '7px', border: 'none', background: adding ? '#a78bfa' : ACCENT, color: '#fff', fontSize: '13px', fontWeight: 700, cursor: adding ? 'not-allowed' : 'pointer', whiteSpace: 'nowrap', height: '36px' }}
                >
                    {adding ? 'Adding…' : '+ Add Task'}
                </button>
            </form>

            {tasks.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '60px 24px', color: '#94a3b8' }}>
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" strokeWidth="1.5" style={{ marginBottom: '12px' }}><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>
                    <div style={{ fontWeight: 700, fontSize: '15px', color: '#64748b', marginBottom: '6px' }}>No tasks yet</div>
                    <div style={{ fontSize: '13px' }}>Add your first task above.</div>
                </div>
            ) : (
                <>
                    {/* Pending tasks */}
                    {pending.length > 0 && (
                        <>
                            <div style={{ fontSize: '11px', fontWeight: 800, color: '#7c3aed', textTransform: 'uppercase', letterSpacing: '0.6px', marginBottom: '10px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                Pending — {pending.length}
                            </div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '6px', marginBottom: '24px' }}>
                                {pending.map(task => (
                                    <TaskRow key={task.id} task={task} onToggle={handleToggle} onDelete={handleDelete} />
                                ))}
                            </div>
                        </>
                    )}

                    {/* Done tasks */}
                    {done.length > 0 && (
                        <>
                            <div style={{ fontSize: '11px', fontWeight: 800, color: '#16a34a', textTransform: 'uppercase', letterSpacing: '0.6px', marginBottom: '10px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                Done — {done.length}
                            </div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                                {done.map(task => (
                                    <TaskRow key={task.id} task={task} onToggle={handleToggle} onDelete={handleDelete} />
                                ))}
                            </div>
                        </>
                    )}
                </>
            )}
        </HubShell>
    );
}

function TaskRow({ task, onToggle, onDelete }: { task: Task; onToggle: (t: Task) => void; onDelete: (t: Task) => void }) {
    const done    = task.status === 'done';
    const overdue = isOverdue(task.target_date, task.status);

    const fmtDate = (d: string) => {
        const dt = new Date(d + 'T00:00:00');
        return dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
    };

    return (
        <div style={{
            display: 'flex', alignItems: 'center', gap: '12px',
            padding: '11px 14px', borderRadius: '8px',
            border: `1.5px solid ${done ? '#d1fae5' : overdue ? '#fee2e2' : '#e5e7eb'}`,
            background: done ? '#f0fdf4' : overdue ? '#fff7f7' : '#fff',
            transition: 'background 0.15s',
        }}>
            {/* Toggle checkbox */}
            <button
                type="button"
                onClick={() => onToggle(task)}
                title={done ? 'Mark as pending' : 'Mark as done'}
                style={{
                    width: '20px', height: '20px', borderRadius: '5px', flexShrink: 0,
                    border: `2px solid ${done ? '#16a34a' : '#cbd5e1'}`,
                    background: done ? '#16a34a' : '#fff',
                    cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center',
                }}
            >
                {done && (
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3"><polyline points="20 6 9 17 4 12"/></svg>
                )}
            </button>

            {/* Task name */}
            <span style={{ flex: 1, fontSize: '13px', fontWeight: 600, color: done ? '#6b7280' : '#0f172a', textDecoration: done ? 'line-through' : 'none' }}>
                {task.task_name}
            </span>

            {/* Target date */}
            <span style={{
                fontSize: '11.5px', fontWeight: 600, padding: '3px 9px', borderRadius: '5px',
                background: done ? '#dcfce7' : overdue ? '#fee2e2' : '#f1f5f9',
                color: done ? '#15803d' : overdue ? '#dc2626' : '#475569',
                display: 'flex', alignItems: 'center', gap: '4px', whiteSpace: 'nowrap',
            }}>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                {fmtDate(task.target_date)}
                {overdue && ' · Overdue'}
            </span>

            {/* Status badge */}
            <span style={{
                fontSize: '10.5px', fontWeight: 700, padding: '2px 8px', borderRadius: '4px',
                background: done ? '#dcfce7' : '#fef9c3',
                color: done ? '#15803d' : '#854d0e',
                textTransform: 'uppercase', letterSpacing: '0.3px',
            }}>
                {task.status}
            </span>

            {/* Delete */}
            <button
                type="button"
                onClick={() => onDelete(task)}
                title="Delete task"
                style={{ width: '26px', height: '26px', borderRadius: '6px', border: '1px solid #fecaca', background: '#fff5f5', color: '#ef4444', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}
            >
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            </button>
        </div>
    );
}
