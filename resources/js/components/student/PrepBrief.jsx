import { useEffect, useState } from 'react';
import { api } from '../../lib/api';
import Spinner from '../Spinner';

// The "AI prep brief" box on the student page, as on the shared
// student-detail board. It loads on its own, so the page never
// waits on the AI. version changes whenever the brief would (a note saved,
// edited, or made private, or the preference changed), and the brief reloads
// then. paused holds the reload while the note check runs, since the check
// writes the new brief itself.
export default function PrepBrief({ studentId, version, paused }) {
    // status: loading | updating | ok | no_notes | only_private | held | unavailable
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
    const working = status === 'loading' || status === 'updating';

    return (
        <section className="bg-warm-gray" style={{ marginTop: 24, padding: '16px 20px 20px', borderRadius: 6 }} aria-busy={working}>
            <div className="d-flex flex-wrap align-items-baseline justify-content-between" style={{ gap: 8 }}>
                <h3 className="h5 mt-0 mb-0 text-blue">AI prep brief</h3>
                {brief && (
                    <span className="small text-body-secondary">
                        {status === 'updating' ? (
                            <>
                                <Spinner className="text-blue" />
                                Updating…
                            </>
                        ) : (
                            briefMeta(brief)
                        )}
                    </span>
                )}
            </div>

            {brief ? (
                <>
                    <p style={{ marginTop: 12 }}>{brief.summary}</p>
                    {brief.open_items.length > 0 && (
                        <>
                            <p className="fw-semibold mb-1">Open items</p>
                            <ul>
                                {brief.open_items.map((item, index) => (
                                    // The index: the AI could give the same item twice.
                                    <li key={index}>{item}</li>
                                ))}
                            </ul>
                        </>
                    )}
                    <p className="fw-semibold mb-1">Suggested opener</p>
                    <p className="mb-0 fst-italic">"{brief.suggested_opener}"</p>
                </>
            ) : (
                <p className="text-body-secondary mb-0" style={{ marginTop: 12 }}>
                    {working && <Spinner className="text-blue" />}
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
    only_private: 'Only private notes so far. The AI never reads them, so there is nothing to brief from.',
    // The AI asked whether a new note may be sensitive.
    held: 'The brief will update once the question about the new note is answered.',
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
