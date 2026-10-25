import { useState } from 'react';
import { api, errorMessage } from '../../lib/api';
import { NOTE_TYPES } from '../../lib/noteTypes';
import SensitiveHint from './SensitiveHint';

// Editing one note in place: the note turns into a small form, with the same
// fields and sensitive-topic hint as "Add a note". A modal was rejected: it
// would sit on top of the side panel and cover the other notes.
export default function NoteEditForm({ note, today, onSaved, onCancel }) {
    const [date, setDate] = useState(note.contact_date);
    const [type, setType] = useState(note.type.value);
    const [text, setText] = useState(note.text);
    const [isPrivate, setIsPrivate] = useState(note.is_private);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    async function handleSubmit(event) {
        event.preventDefault();
        if (busy) return;

        if (!text.trim()) {
            setError('Write a note before saving.');
            return;
        }

        setBusy(true);
        setError('');

        try {
            const response = await api(`/api/notes/${note.id}`, {
                method: 'PATCH',
                body: { contact_date: date, contact_type: type, note_text: text, is_private: isPrivate },
            });
            onSaved(response.data);
        } catch (err) {
            setError(errorMessage(err, 'The changes did not save. Try again.'));
            setBusy(false);
        }
    }

    // Each note's form needs its own ids, in case another form is open.
    const id = (field) => `edit-${field}-${note.id}`;

    return (
        <form className="border rounded" style={{ padding: 12, background: '#f8f9fa' }} onSubmit={handleSubmit} noValidate>
            <p className="small fw-semibold mb-2">Edit note</p>
            <div className="d-flex flex-wrap" style={{ gap: 8 }}>
                <div style={{ flex: '1 1 150px' }}>
                    <label htmlFor={id('date')} className="form-label small fw-semibold mb-1">
                        Date
                    </label>
                    <input
                        id={id('date')}
                        type="date"
                        className="form-control"
                        min="2000-01-01"
                        max={today}
                        value={date}
                        onChange={(event) => setDate(event.target.value)}
                    />
                </div>
                <div style={{ flex: '1 1 150px' }}>
                    <label htmlFor={id('type')} className="form-label small fw-semibold mb-1">
                        Type
                    </label>
                    <select id={id('type')} className="form-select" value={type} onChange={(event) => setType(event.target.value)}>
                        {NOTE_TYPES.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
            </div>
            <label htmlFor={id('text')} className="form-label small fw-semibold mb-1" style={{ marginTop: 8 }}>
                Note
            </label>
            <textarea
                id={id('text')}
                className="form-control"
                rows={4}
                value={text}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? id('error') : undefined}
                onChange={(event) => setText(event.target.value)}
                autoFocus
            />
            <div className="form-check" style={{ marginTop: 8 }}>
                <input
                    id={id('private')}
                    className="form-check-input"
                    type="checkbox"
                    checked={isPrivate}
                    onChange={(event) => setIsPrivate(event.target.checked)}
                />
                <label className="form-check-label fw-semibold" htmlFor={id('private')}>
                    Private note
                </label>
            </div>
            <SensitiveHint text={text} isPrivate={isPrivate} onMarkPrivate={() => setIsPrivate(true)} />
            {error && (
                <div id={id('error')} className="small text-danger" role="alert" style={{ marginTop: 8 }}>
                    {error}
                </div>
            )}
            <div className="d-flex flex-wrap" style={{ gap: 8, marginTop: 10 }}>
                <button type="submit" className={`btn btn-sm btn-blue${busy ? ' disabled' : ''}`} aria-disabled={busy}>
                    {busy ? 'Saving…' : 'Save changes'}
                </button>
                <button type="button" className="btn btn-sm btn-outline-blue" onClick={onCancel}>
                    Cancel
                </button>
            </div>
        </form>
    );
}
