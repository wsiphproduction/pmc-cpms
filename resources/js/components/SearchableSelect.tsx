import { useMemo, useState } from 'react';

export interface SearchableOption {
    id: number;
    name: string;
    description?: string | null;
    /** Fuller dropdown line (e.g. cost code — department — description). */
    label?: string | null;
}

const inputStyle: React.CSSProperties = {
    width: '100%', padding: '8px 34px 8px 12px', borderRadius: '8px',
    border: '1.5px solid #e5e7eb', fontSize: '13px', outline: 'none',
    fontFamily: 'inherit', color: '#374151', background: '#fff',
    boxSizing: 'border-box', transition: 'border-color 0.15s',
};

/** Type-ahead picker over master data; the chosen option's `name` is the value. */
export default function SearchableSelect({
    value, onChange, options, placeholder, required, listId,
}: {
    value: string;
    onChange: (value: string) => void;
    options: SearchableOption[];
    placeholder: string;
    required?: boolean;
    listId: string;
}) {
    const [open, setOpen] = useState(false);
    const optionText = (option: SearchableOption) =>
        option.label ?? (option.description ? `${option.name} — ${option.description}` : option.name);
    const filtered = useMemo(() => {
        const needle = value.trim().toLowerCase();
        return needle
            ? options.filter(option => optionText(option).toLowerCase().includes(needle)).slice(0, 8)
            : options.slice(0, 8);
    }, [options, value]);

    return (
        <div style={{ position: 'relative' }}>
            <input
                value={value}
                onChange={e => { onChange(e.target.value); setOpen(true); }}
                onFocus={e => { e.target.style.borderColor = '#2563eb'; setOpen(true); }}
                onBlur={e => { e.target.style.borderColor = '#e5e7eb'; window.setTimeout(() => setOpen(false), 120); }}
                required={required}
                placeholder={placeholder}
                role="combobox"
                aria-expanded={open}
                aria-controls={listId}
                autoComplete="off"
                style={inputStyle}
            />
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" strokeWidth="2" style={{ position: 'absolute', right: '11px', top: '50%', transform: 'translateY(-50%)', pointerEvents: 'none' }}>
                <polyline points="6 9 12 15 18 9" />
            </svg>
            {open && (
                <div id={listId} role="listbox" style={{ position: 'absolute', top: 'calc(100% + 5px)', left: 0, right: 0, zIndex: 50, background: '#fff', border: '1px solid #dbe3ef', borderRadius: '8px', boxShadow: '0 14px 32px rgba(15,23,42,0.14)', overflow: 'hidden', maxHeight: '220px', overflowY: 'auto' }}>
                    {filtered.length ? filtered.map(option => (
                        <button
                            key={option.id}
                            type="button"
                            onMouseDown={e => e.preventDefault()}
                            onClick={() => { onChange(option.name); setOpen(false); }}
                            style={{ width: '100%', border: 'none', background: option.name === value ? '#eff6ff' : '#fff', padding: '9px 12px', textAlign: 'left', fontSize: '13px', color: '#334155', cursor: 'pointer' }}
                        >
                            {optionText(option)}
                        </button>
                    )) : (
                        <div style={{ padding: '10px 12px', fontSize: '12.5px', color: '#94a3b8' }}>No results found</div>
                    )}
                </div>
            )}
        </div>
    );
}
