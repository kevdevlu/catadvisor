import { useState } from 'react';
import { api, errorMessage } from '../../lib/api';

// The questions the AI check can raise after a note is saved, worded as on
// the shared student-detail board. "May be sensitive" comes
// first. If the advisor marks the note private, the contact-preference
// question is dropped: everyone sees the preference, so it must not come
// from a private note.
//
// question: { noteId, sensitive, suggestion }. onDone(message) closes the
// questions and shows the message, if any, in the form's green box.
export default function NoteQuestions({ question, currentPreference, onUpdate, onReload, onDone }) {
    const [step, setStep] = useState(question.sensitive ? 'sensitive' : 'preference');
    const [text, setText] = useState(question.suggestion ?? '');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    async function markPrivate() {
        if (busy) return;
        setBusy(true);
        setError('');

        try {
            await api(`/api/notes/${question.noteId}`, { method: 'PATCH', body: { is_private: true } });
            onDone('Marked private. Only you can read it now. The AI will not use it.');
            onReload();
        } catch {
            setError('The note was not marked private. Try again.');
            setBusy(false);
        }
    }

    function keepAsIs() {
        if (question.suggestion) {
            setStep('preference');
        } else {
            onDone('');
        }
    }

    async function acceptPreference(event) {
        event.preventDefault();
        if (busy) return;
        setBusy(true);
        setError('');

        try {
            await onUpdate('contact-preference', {
                method: 'PUT',
                body: { contact_preference: text.trim() || null, note_id: question.noteId },
            });
            onDone('Contact preference updated.');
        } catch (err) {
            setError(errorMessage(err, 'The preference did not save. Try again.'));
            setBusy(false);
        }
    }

    const errorLine = error && (
        <div className="small text-danger" role="alert" style={{ marginTop: 8 }}>
            {error}
        </div>
    );

    if (step === 'sensitive') {
        return (
            <div className="alert alert-warning" role="status" style={{ marginTop: 12, marginBottom: 0 }}>
                <p className="fw-semibold mb-1">This note may be sensitive</p>
                <p className="small mb-2">
                    Claude Haiku flagged it while checking the note. The AI never gets the student's name, email, or ID. Mark it private? Then
                    only you can read it, and the AI will not use it again.
                </p>
                <div className="d-flex flex-wrap" style={{ gap: 8 }}>
                    <button type="button" className="btn btn-sm btn-blue" aria-disabled={busy} onClick={markPrivate}>
                        Mark private
                    </button>
                    <button type="button" className="btn btn-sm btn-outline-blue" onClick={keepAsIs}>
                        Keep as is
                    </button>
                </div>
                {errorLine}
            </div>
        );
    }

    return (
        <form className="alert alert-info" role="status" style={{ marginTop: 12, marginBottom: 0 }} onSubmit={acceptPreference}>
            <p className="fw-semibold mb-1">Contact preference detected</p>
            <p className="small mb-2">This note seems to change how to reach the student. Update the contact preference?</p>
            <p className="small mb-2">
                <span className="fw-semibold">Current:</span> {currentPreference ?? 'None yet'}
            </p>
            <label htmlFor="preference-suggestion" className="form-label small fw-semibold mb-1">
                New (suggested by Claude Haiku; edit it if needed)
            </label>
            <input id="preference-suggestion" type="text" className="form-control" value={text} onChange={(event) => setText(event.target.value)} />
            <div className="d-flex flex-wrap" style={{ gap: 8, marginTop: 10 }}>
                <button type="submit" className="btn btn-sm btn-blue" aria-disabled={busy}>
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
