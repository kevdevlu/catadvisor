import { useEffect, useRef, useState } from 'react';
import { errorMessage } from '../../lib/api';
import { PhoneIcon } from '../icons';
import SourceLink from './SourceLink';

// The yellow contact-preference warning at the top of the student page,
// for example "Phone only, best after 5 pm." It says which note
// it came from, or who set it and when. The student's advisor can edit it by
// hand; saving an empty box removes it.
//
// When the form closes, keyboard focus goes back to the Edit (or "+ Add")
// button, so it isn't lost to the top of the page.
export default function ContactPreference({ preference, canWrite, onUpdate, onJump, onAnnounce }) {
    const [editing, setEditing] = useState(false);
    const [restoreFocus, setRestoreFocus] = useState(false);
    const triggerRef = useRef(null);

    useEffect(() => {
        if (restoreFocus && !editing) {
            triggerRef.current?.focus();
            setRestoreFocus(false);
        }
    }, [restoreFocus, editing]);

    function closeForm(message) {
        setEditing(false);
        setRestoreFocus(true);
        if (message) onAnnounce(message);
    }

    if (editing) {
        return <PreferenceForm preference={preference} onUpdate={onUpdate} onDone={closeForm} />;
    }

    if (!preference) {
        return canWrite ? (
            <button type="button" ref={triggerRef} className="btn btn-link btn-sm p-0" style={{ minHeight: 32 }} onClick={() => setEditing(true)}>
                + Add a contact preference
            </button>
        ) : null;
    }

    const { source } = preference;

    return (
        <div className="alert alert-warning d-flex align-items-start mb-0" style={{ gap: 10, padding: '12px 14px' }}>
            <PhoneIcon size={20} strokeWidth={2} style={{ flex: '0 0 auto', marginTop: 2 }} />
            <div className="flex-grow-1" style={{ minWidth: 0 }}>
                <strong>Contact preference:</strong> {preference.text}
                <div className="small">
                    {source.note_id ? (
                        <>
                            From <SourceLink source={source} onJump={onJump} className="alert-link fw-semibold" />
                        </>
                    ) : (
                        source.set_by && `Set by ${source.set_by} on ${source.set_on}`
                    )}
                </div>
            </div>
            {canWrite && (
                <button
                    type="button"
                    ref={triggerRef}
                    className="btn btn-link btn-sm p-0 alert-link flex-shrink-0"
                    style={{ minHeight: 32 }}
                    onClick={() => setEditing(true)}
                >
                    Edit
                </button>
            )}
        </div>
    );
}

function PreferenceForm({ preference, onUpdate, onDone }) {
    const [text, setText] = useState(preference?.text ?? '');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    async function handleSubmit(event) {
        event.preventDefault();
        if (busy) return;

        setBusy(true);
        setError('');

        const value = text.trim() || null;

        try {
            await onUpdate('contact-preference', { method: 'PUT', body: { contact_preference: value } });
            onDone(value ? 'Contact preference saved.' : 'Contact preference removed.');
        } catch (err) {
            setError(errorMessage(err, 'The preference did not save. Try again.'));
            setBusy(false);
        }
    }

    return (
        <form className="border rounded" style={{ padding: 12, background: '#f8f9fa' }} onSubmit={handleSubmit}>
            <label htmlFor="preference-text" className="form-label small fw-semibold mb-1">
                Contact preference
            </label>
            <input
                id="preference-text"
                type="text"
                className="form-control"
                placeholder="For example, Phone only, best after 5 pm"
                aria-describedby={error ? 'preference-help preference-error' : 'preference-help'}
                aria-invalid={Boolean(error)}
                value={text}
                onChange={(event) => setText(event.target.value)}
                autoFocus
            />
            <div id="preference-help" className="form-text">
                Everyone who can see this student sees it. Leave it empty to remove it.
            </div>
            {error && (
                <div id="preference-error" className="small text-danger" role="alert" style={{ marginTop: 8 }}>
                    {error}
                </div>
            )}
            <div className="d-flex flex-wrap" style={{ gap: 8, marginTop: 10 }}>
                <button type="submit" className="btn btn-sm btn-blue" aria-disabled={busy}>
                    {busy ? 'Saving…' : 'Save'}
                </button>
                <button type="button" className="btn btn-sm btn-outline-blue" onClick={() => onDone()}>
                    Cancel
                </button>
            </div>
        </form>
    );
}
