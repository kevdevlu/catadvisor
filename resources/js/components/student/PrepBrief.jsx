import { useEffect, useState } from 'react';
import { api } from '../../lib/api';

// The "AI prep brief" box on the student page, as on the shared
// student-detail board. It loads on its own, so the page never
// waits on the AI. version changes whenever the brief would (a note saved,
// edited, or made private, or the preference changed), and the brief reloads
// then. paused holds the reload while the note check runs, since the check
// writes the new brief itself.
export default function PrepBrief({ studentId, version, paused }) {
    // status: loading | updating | ok | no_notes | unavailable
    const [state, setState] = useState({ status: 'loading', brief: null });

    useEffect(() => {
        // Keep showing the old brief, marked as updating, until the new one
        // arrives.
        setState((old) => (old.brief ? { ...old, status: 'updating' } : { status: 'loading', brief: null }));

        if (paused) return;

        let current = true;

        api(`/api/students/${studentId}/brief`)
            .then((response) => current && setState({ status: response.data ? 'ok' : response.meta.reason, brief: response.data }))
            .catch(() => current && setState({ status: 'unavailable', brief: null }));

        return () => {
            current = false;
        };
    }, [studentId, version, paused]);

    const { status, brief } = state;

    return (
        <section className="bg-warm-gray" style={{ marginTop: 24, padding: '16px 20px 20px', borderRadius: 6 }} aria-busy={status === 'loading' || status === 'updating'}>
            <div className="d-flex flex-wrap align-items-baseline justify-content-between" style={{ gap: 8 }}>
                <h3 className="h5 mt-0 mb-0 text-blue">AI prep brief</h3>
                {brief && <span className="small text-body-secondary">{status === 'updating' ? 'Updating…' : briefMeta(brief)}</span>}
            </div>

            {brief ? (
                <>
                    <p style={{ marginTop: 12 }}>{brief.summary}</p>
                    {brief.open_items.length > 0 && (
                        <>
                            <p className="fw-semibold mb-1">Open items</p>
                            <ul>
                                {brief.open_items.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                        </>
                    )}
                    <p className="fw-semibold mb-1">Suggested opener</p>
                    <p className="mb-0 fst-italic">"{brief.suggested_opener}"</p>
                </>
            ) : (
                <p className="text-body-secondary mb-0" style={{ marginTop: 12 }}>
                    {EMPTY_TEXT[status]}
                </p>
            )}
        </section>
    );
}

const EMPTY_TEXT = {
    loading: 'Writing the prep brief…',
    updating: 'Writing the prep brief…',
    no_notes: 'No notes yet, so there is nothing to brief from.',
    unavailable: 'The AI prep brief is not available right now.',
};

// "Claude Haiku · from 3 notes · 1 private note not sent · check before you
// use it", as on the board.
function briefMeta(brief) {
    const plural = (count, word) => `${count} ${word}${count === 1 ? '' : 's'}`;

    return [
        'Claude Haiku',
        `from ${plural(brief.notes_used, 'note')}`,
        brief.private_left_out > 0 && `${plural(brief.private_left_out, 'private note')} not sent`,
        'check before you use it',
    ]
        .filter(Boolean)
        .join(' · ');
}
