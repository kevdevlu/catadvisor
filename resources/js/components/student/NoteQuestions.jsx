import { useEffect, useRef, useState } from 'react';
import { api, errorMessage } from '../../lib/api';

// The questions the AI check can raise after a note is saved, worded as on
// the shared student-detail board. "May be sensitive" comes
// first, and the server holds back what the AI took from the note until it
// is answered: "Mark private" drops it, "Keep as is" applies it. If the
// note is marked private, the contact-preference question is dropped too:
// everyone sees the preference, so it must not come from a private note.
//
// question: { noteId, sensitive, suggestion }. onMessage(text) adds to the
// form's green message; onDone(text) closes the questions and adds text.
export default function NoteQuestions({ question, currentPreference, onUpdate, onReload, onMessage, onDone }) {
    const [step, setStep] = useState(question.sensitive ? 'sensitive' : 'preference');
    const [text, setText] = useState(question.suggestion ?? '');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const headingRef = useRef(null);

    // Move keyboard focus to each question as it appears, so a screen
    // reader reads it out.
    useEffect(() => {
        headingRef.current?.focus();
    }, [step]);

    // Runs one answer: stops a second click while the first is on its way,
    // and shows the error if it fails.
    async function answer(send, failure) {
        if (busy) return;
        setBusy(true);
        setError('');

        try {
            await send();
        } catch (err) {
            if (err.status === 404) {
                onDone('That note was deleted, so there is nothing to answer.');
                return;
            }

            setError(errorMessage(err, failure));
        } finally {
            setBusy(false);
        }
    }

    function markPrivate() {
        answer(async () => {
            await api(`/api/notes/${question.noteId}`, { method: 'PATCH', body: { is_private: true } });
            onDone('Marked private. Only you can read it now. The AI will not use it.');
            onReload();
        }, 'The note was not marked private. Try again.');
    }

    function keepAsIs() {
        answer(async () => {
            const { data } = await api(`/api/notes/${question.noteId}/keep`, { method: 'POST' });
            onMessage(data.messages.join(' '));
            onReload();

            if (question.suggestion) {
                setStep('preference');
            } else {
                onDone('');
            }
        }, 'That did not save. Try again.');
    }

    function acceptPreference(event) {
        event.preventDefault();

        // An empty box would remove the preference; that is what "Edit" on
        // the preference itself is for.
        if (!text.trim()) {
            setError('Write the preference, or choose "Keep current".');
            return;
        }

        answer(async () => {
            await onUpdate('contact-preference', { method: 'PUT', body: { contact_preference: text.trim(), note_id: question.noteId } });
            onDone('Contact preference updated.');
        }, 'The preference did not save. Try again.');
    }

    // Bootstrap's look for a button that can't be used right now.
    const disabled = busy ? ' disabled' : '';

    const errorLine = error && (
        <div className="small text-danger" role="alert" style={{ marginTop: 8 }}>
            {error}
        </div>
    );

    if (step === 'sensitive') {
        return (
            <div className="alert alert-warning" style={{ marginTop: 12, marginBottom: 0 }}>
                <p ref={headingRef} tabIndex={-1} className="fw-semibold mb-1">
                    This note may be sensitive
                </p>
                <p className="small mb-2">
                    Claude Haiku flagged it while checking the note. The AI never gets the student's name, email, or ID. Mark it private? Then
                    only you can read it, and the AI will not use it again.
                </p>
                <div className="d-flex flex-wrap" style={{ gap: 8 }}>
                    <button type="button" className={`btn btn-sm btn-blue${disabled}`} aria-disabled={busy} onClick={markPrivate}>
                        Mark private
                    </button>
                    <button type="button" className={`btn btn-sm btn-outline-blue${disabled}`} aria-disabled={busy} onClick={keepAsIs}>
                        Keep as is
                    </button>
                </div>
                {errorLine}
            </div>
        );
    }

    return (
        <form className="alert alert-info" style={{ marginTop: 12, marginBottom: 0 }} onSubmit={acceptPreference}>
            <p ref={headingRef} tabIndex={-1} className="fw-semibold mb-1">
                Contact preference detected
            </p>
            <p className="small mb-2">This note seems to change how to reach the student. Update the contact preference?</p>
            <p className="small mb-2">
                <span className="fw-semibold">Current:</span> {currentPreference ?? 'None yet'}
            </p>
            <label htmlFor="preference-suggestion" className="form-label small fw-semibold mb-1">
                New (suggested by Claude Haiku; edit it if needed)
            </label>
            <input id="preference-suggestion" type="text" className="form-control" value={text} onChange={(event) => setText(event.target.value)} />
            <div className="d-flex flex-wrap" style={{ gap: 8, marginTop: 10 }}>
                <button type="submit" className={`btn btn-sm btn-blue${disabled}`} aria-disabled={busy}>
                    {currentPreference ? 'Update preference' : 'Add preference'}
                </button>
                <button type="button" className="btn btn-sm btn-outline-blue" onClick={() => onDone('Contact preference kept as it was.')}>
                    Keep current
                </button>
            </div>
            {errorLine}
        </form>
    );
}
