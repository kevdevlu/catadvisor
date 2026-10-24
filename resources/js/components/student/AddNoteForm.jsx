import { useRef, useState } from 'react';
import { api, errorMessage } from '../../lib/api';
import { findSensitive, quoteList } from '../../lib/sensitiveKeywords';

// The note types, in the order the form lists them. "Note only" records
// something without contacting the student.
const TYPES = [
    { value: 'phone', label: 'Phone' },
    { value: 'email', label: 'Email' },
    { value: 'zoom', label: 'Zoom' },
    { value: 'in_person', label: 'In person' },
    { value: 'note', label: 'Note only (no contact)' },
];

// The "Add a note" form on the student page. While the
// advisor types, a yellow hint suggests marking the note private if it
// mentions a sensitive topic; the advisor decides. typeRef lets the "Add
// note" button at the top of the page jump here.
export default function AddNoteForm({ studentId, today, typeRef, onSaved }) {
    const [date, setDate] = useState(today);
    const [type, setType] = useState('');
    const [text, setText] = useState('');
    const [isPrivate, setIsPrivate] = useState(false);
    const [error, setError] = useState('');
    // Which field the error is about ('date', 'type', or 'text'), so it can
    // be marked invalid and get keyboard focus.
    const [errorField, setErrorField] = useState(null);
    const [saved, setSaved] = useState('');
    const [busy, setBusy] = useState(false);

    const sensitiveWords = isPrivate ? [] : findSensitive(text);
    const fieldRefs = { date: useRef(null), type: typeRef, text: useRef(null) };

    function showError(field, message) {
        setError(message);
        setErrorField(field);
        fieldRefs[field]?.current?.focus();
    }

    // Changing the field an error is about clears the error.
    function change(field, setValue) {
        return (event) => {
            setValue(event.target.value);
            if (errorField === field) {
                setError('');
                setErrorField(null);
            }
        };
    }

    // Marks a field invalid and points it at the error message.
    function invalidProps(field) {
        return errorField === field ? { 'aria-invalid': true, 'aria-describedby': 'note-error' } : {};
    }

    async function handleSubmit(event) {
        event.preventDefault();
        if (busy) return;

        setSaved('');

        if (!type) return showError('type', 'Choose a contact type.');
        if (!text.trim()) return showError('text', 'Write a note before saving.');

        setError('');
        setErrorField(null);
        setBusy(true);

        try {
            const response = await api(`/api/students/${studentId}/notes`, {
                method: 'POST',
                body: { contact_date: date, contact_type: type, note_text: text, is_private: isPrivate },
            });

            setSaved(savedMessage(response.data, response.meta));
            setType('');
            setText('');
            setIsPrivate(false);
            onSaved(response.data);
        } catch (err) {
            const field = { contact_date: 'date', contact_type: 'type', note_text: 'text' }[Object.keys(err.body?.errors ?? {})[0]];
            showError(field ?? null, errorMessage(err, 'The note did not save. Try again.'));
        } finally {
            setBusy(false);
        }
    }

    return (
        // The light gray box, like the follow-up and preference forms, shows
        // that "Save note" saves only this note: the page has no Save button
        // of its own.
        <section id="add-note" className="border rounded" style={{ marginTop: 28, padding: 16, background: '#f8f9fa', scrollMarginTop: 24 }}>
            <h3 className="h5 mt-0 text-blue">Add a note</h3>
            <form onSubmit={handleSubmit} noValidate>
                <div className="d-flex flex-wrap" style={{ gap: 12 }}>
                    <div style={{ flex: '1 1 180px' }}>
                        <label htmlFor="note-date" className="form-label small fw-semibold mb-1">
                            Date
                        </label>
                        <input
                            id="note-date"
                            ref={fieldRefs.date}
                            type="date"
                            className="form-control"
                            min="2000-01-01"
                            max={today}
                            value={date}
                            onChange={change('date', setDate)}
                            {...invalidProps('date')}
                        />
                    </div>
                    <div style={{ flex: '1 1 180px' }}>
                        <label htmlFor="note-type" className="form-label small fw-semibold mb-1">
                            Type
                        </label>
                        <select id="note-type" ref={typeRef} className="form-select" value={type} onChange={change('type', setType)} {...invalidProps('type')}>
                            <option value="">Choose a type</option>
                            {TYPES.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div style={{ marginTop: 12 }}>
                    <label htmlFor="note-text" className="form-label small fw-semibold mb-1">
                        Note
                    </label>
                    <textarea
                        id="note-text"
                        ref={fieldRefs.text}
                        className="form-control"
                        rows={4}
                        placeholder="Example: Called after 5 pm. Student says texting is fine now, but still no email. Needs to submit the tutoring form by Oct 20."
                        value={text}
                        onChange={change('text', setText)}
                        {...invalidProps('text')}
                    />
                </div>

                <div className="form-check" style={{ marginTop: 12 }}>
                    <input
                        id="note-private"
                        className="form-check-input"
                        type="checkbox"
                        aria-describedby="note-private-help"
                        checked={isPrivate}
                        onChange={(event) => setIsPrivate(event.target.checked)}
                    />
                    <label className="form-check-label fw-semibold" htmlFor="note-private">
                        Private note
                    </label>
                    <div id="note-private-help" className="form-text" style={{ marginTop: 2 }}>
                        Only you can read it. The admin and other advisors see "Private note (hidden)" with the date and type. It is never sent to the AI.
                    </div>
                </div>

                {sensitiveWords.length > 0 && (
                    <div
                        className="alert alert-warning d-flex flex-wrap align-items-center justify-content-between"
                        style={{ gap: 8, marginTop: 10, marginBottom: 0, padding: '10px 14px' }}
                    >
                        <div className="small" style={{ flex: '1 1 260px' }}>
                            {/* Only the first sentence is announced, so a screen
                                reader doesn't repeat the hint as each new word
                                appears. */}
                            <span className="fw-semibold" role="status">
                                This note may be sensitive.
                            </span>{' '}
                            It mentions {quoteList(sensitiveWords)}. Consider marking it private.
                        </div>
                        <button type="button" className="btn btn-sm btn-blue" onClick={() => setIsPrivate(true)}>
                            Mark as private
                        </button>
                    </div>
                )}

                {error && (
                    <div id="note-error" className="small text-danger" role="alert" style={{ marginTop: 8 }}>
                        {error}
                    </div>
                )}

                <div className="d-flex justify-content-end" style={{ marginTop: 12 }}>
                    <button type="submit" className="btn btn-sm btn-outline-red" aria-disabled={busy}>
                        {busy ? 'Saving…' : 'Save note'}
                    </button>
                </div>
            </form>

            {saved && (
                <div className="alert alert-success" role="status" style={{ marginTop: 16, marginBottom: 0, padding: '10px 14px' }}>
                    {saved}
                </div>
            )}
        </section>
    );
}

// For example "Note saved. It does not count as a contact."
function savedMessage(note, meta) {
    return [
        note.is_private ? 'Private note saved. The AI did not read it.' : 'Note saved.',
        note.type.value === 'note' && 'It does not count as a contact.',
        meta.waiting_cleared && '"Waiting on a reply" cleared: this was two-way contact.',
    ]
        .filter(Boolean)
        .join(' ');
}
