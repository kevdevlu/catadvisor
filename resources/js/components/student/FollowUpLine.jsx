import { useEffect, useRef, useState } from 'react';
import { errorMessage } from '../../lib/api';
import SourceLink from './SourceLink';

// "Next follow-up by Oct 15, 2026: FAFSA verification" on the student page,
// with who set it and Edit / Clear. With no follow-up, a
// "+ Set a follow-up date" link opens the same form. The reason shows as
// "(private)" when it came from someone else's private note.
//
// When the form closes, or Clear removes the line, keyboard focus goes to
// Edit or "+ Set a follow-up date", so it isn't lost to the top of the page.
export default function FollowUpLine({ followUp, canWrite, onUpdate, onJump, onAnnounce }) {
    const [editing, setEditing] = useState(false);
    const [error, setError] = useState('');
    const [restoreFocus, setRestoreFocus] = useState(false);
    const triggerRef = useRef(null);

    useEffect(() => {
        if (restoreFocus && !editing) {
            triggerRef.current?.focus();
            setRestoreFocus(false);
        }
    }, [restoreFocus, editing, followUp]);

    function closeForm(message) {
        setEditing(false);
        setRestoreFocus(true);
        if (message) onAnnounce(message);
    }

    if (editing) {
        return <FollowUpForm followUp={followUp} onUpdate={onUpdate} onDone={closeForm} />;
    }

    if (!followUp) {
        return canWrite ? (
            <button
                type="button"
                ref={triggerRef}
                className="btn btn-link btn-sm p-0 align-self-start"
                style={{ minHeight: 32 }}
                onClick={() => setEditing(true)}
            >
                + Set a follow-up date
            </button>
        ) : null;
    }

    async function handleClear() {
        setError('');
        try {
            await onUpdate('follow-up', { method: 'DELETE' });
            closeForm('Next follow-up cleared.');
        } catch {
            setError('The follow-up did not clear. Try again.');
        }
    }

    return (
        <div className="border rounded" style={{ padding: '8px 12px' }}>
            <div className="d-flex flex-wrap align-items-center justify-content-between" style={{ gap: 8 }}>
                <div style={{ flex: '1 1 260px', minWidth: 0 }}>
                    {/* "Next follow-up by Oct 15, 2026: FAFSA verification", or
                        "Next follow-up: <reason>" when there is no date. */}
                    <span className="fw-semibold">Next follow-up</span>
                    {followUp.date_label ? ` by ${followUp.date_label}: ` : ': '}
                    {followUp.reason_private ? '(private)' : followUp.reason}{' '}
                    <span className="small text-body-secondary">
                        · <FollowUpSource source={followUp.source} onJump={onJump} />
                    </span>
                </div>
                {canWrite && (
                    <div className="d-flex flex-shrink-0" style={{ gap: 12 }}>
                        <button type="button" ref={triggerRef} className="btn btn-link btn-sm p-0" style={{ minHeight: 32 }} onClick={() => setEditing(true)}>
                            Edit
                        </button>
                        <button type="button" className="btn btn-link btn-sm p-0 text-chili" style={{ minHeight: 32 }} onClick={handleClear}>
                            Clear
                        </button>
                    </div>
                )}
            </div>
            {error && (
                <div className="small text-danger" role="alert">
                    {error}
                </div>
            )}
        </div>
    );
}

// "Set by AI from the Sep 23 in-person note", "Set by Lindqvist from the …",
// or just "Set by Reyes" when an advisor typed it in.
function FollowUpSource({ source, onJump }) {
    const who = source.set_by ? `Set by ${source.set_by}` : 'Set by AI';

    if (!source.note_name) {
        return who;
    }

    return (
        <>
            {who} from <SourceLink source={source} onJump={onJump} />
        </>
    );
}

function FollowUpForm({ followUp, onUpdate, onDone }) {
    const [date, setDate] = useState(followUp?.date ?? '');
    const [reason, setReason] = useState(followUp?.reason ?? '');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    async function handleSubmit(event) {
        event.preventDefault();
        if (busy) return;

        setBusy(true);
        setError('');

        // Saving with both boxes empty clears the follow-up, as Clear does.
        const empty = !date && !reason.trim();

        try {
            await onUpdate('follow-up', empty ? { method: 'DELETE' } : { method: 'PUT', body: { date: date || null, reason: reason.trim() || null } });
            onDone(empty ? 'Next follow-up cleared.' : 'Next follow-up saved.');
        } catch (err) {
            setError(errorMessage(err, 'The follow-up did not save. Try again.'));
            setBusy(false);
        }
    }

    return (
        <form className="border rounded" style={{ padding: 12, background: '#f8f9fa' }} onSubmit={handleSubmit}>
            <p className="small fw-semibold mb-2">Next follow-up</p>
            <div className="d-flex flex-wrap align-items-end" style={{ gap: 8 }}>
                <div style={{ flex: '0 1 180px' }}>
                    <label htmlFor="follow-up-date" className="form-label small fw-semibold mb-1">
                        Date <span className="fw-normal text-body-secondary">(optional)</span>
                    </label>
                    <input id="follow-up-date" type="date" className="form-control" value={date} onChange={(event) => setDate(event.target.value)} autoFocus />
                </div>
                <div style={{ flex: '1 1 220px' }}>
                    <label htmlFor="follow-up-reason" className="form-label small fw-semibold mb-1">
                        Reason
                    </label>
                    <input
                        id="follow-up-reason"
                        type="text"
                        className="form-control"
                        placeholder="For example, FAFSA verification"
                        aria-describedby="follow-up-help"
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                    />
                </div>
            </div>
            <div id="follow-up-help" className="form-text">
                Everyone who can see this student sees the date. They see the reason too, unless it came from a private note.
            </div>
            {error && (
                <div className="small text-danger" role="alert" style={{ marginTop: 8 }}>
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
