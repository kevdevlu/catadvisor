import { useEffect, useId, useRef, useState } from 'react';
import { api } from '../../lib/api';
import { LockIcon } from '../icons';
import NoteEditForm from './NoteEditForm';
import NoteQuestions from './NoteQuestions';

// How long "Note deleted. Undo" stays.
const UNDO_MS = 8000;

// The student's notes, newest first. Someone else's private note
// arrives from the API without its words, so it shows as "Private note
// (hidden)" with the date, type, and author. The author of a
// private note is reminded who can see it.
//
// A search box filters the notes as the advisor types, and marks the
// matching words; a menu sorts them by date. The search reads
// only what this advisor can see, so the words of someone else's private
// note can't be found.
//
// A note's author can edit it in place or delete it, with no "Are you
// sure?": the note goes, and "Note deleted. Undo" shows for a few seconds.
// An edit to a note that isn't private goes to the AI check
// again, like a new note.
export default function NoteList({
    notes,
    newNoteIds,
    highlightId,
    query,
    onQueryChange,
    today,
    currentPreference,
    onUpdate,
    onReload,
    onChecking,
}) {
    const id = useId();
    const [sort, setSort] = useState('desc');
    const [editingId, setEditingId] = useState(null);
    // The gray message above the list: { text, undoId }.
    const [message, setMessage] = useState(null);
    // A question from the AI check of an edited note, as for a new note.
    const [question, setQuestion] = useState(null);
    const undoRef = useRef(null);

    // "Undo" goes away after a few seconds; until then it has keyboard
    // focus, so undoing is one key press.
    useEffect(() => {
        if (!message?.undoId) return;
        undoRef.current?.focus();
        const timer = setTimeout(() => setMessage(null), UNDO_MS);
        return () => clearTimeout(timer);
    }, [message]);

    function focusNote(noteId) {
        requestAnimationFrame(() => document.getElementById(`note-${noteId}`)?.focus());
    }

    function addMessage(text) {
        if (text) setMessage((old) => ({ text: old?.text ? `${old.text} ${text}` : text }));
    }

    async function handleSaved(note) {
        // A question about the note's old words no longer applies.
        setQuestion((old) => (old?.noteId === note.id ? null : old));
        setEditingId(null);
        setMessage({ text: 'Changes saved.' });
        focusNote(note.id);
        onReload();

        // A private note is never sent to the AI.
        if (note.is_private) return;

        onChecking(+1);
        try {
            const { data } = await api(`/api/notes/${note.id}/check`, { method: 'POST' });
            addMessage(data.messages.join(' '));
            if (data.ask_sensitive || data.contact_preference_suggestion) {
                setQuestion({ noteId: note.id, sensitive: data.ask_sensitive, suggestion: data.contact_preference_suggestion });
            }
        } catch {
            // No answer from the AI: nothing changed and there is nothing to
            // ask.
        } finally {
            onChecking(-1);
            onReload();
        }
    }

    function handleCancel(noteId) {
        setEditingId(null);
        focusNote(noteId);
    }

    async function handleDelete(note) {
        try {
            await api(`/api/notes/${note.id}`, { method: 'DELETE' });
            setQuestion((old) => (old?.noteId === note.id ? null : old));
            setMessage({ text: 'Note deleted.', undoId: note.id });
            onReload();
        } catch {
            setMessage({ text: 'The note was not deleted. Try again.' });
        }
    }

    async function handleUndo() {
        const noteId = message.undoId;

        try {
            await api(`/api/notes/${noteId}/restore`, { method: 'POST' });
            setMessage({ text: 'Note restored.' });
            onReload();
            setTimeout(() => focusNote(noteId), 300);
        } catch {
            setMessage({ text: 'The note was not restored. Try again.' });
        }
    }

    // The API sends the notes newest first. The note being edited always
    // stays, so typing a search can't throw away its changes.
    const words = query.trim().toLowerCase();
    const matching = notes.filter((note) => note.id === editingId || !words || searchableText(note).includes(words));
    const shown = sort === 'asc' ? [...matching].reverse() : matching;

    return (
        <>
            <h3 className="h5 mt-0 text-blue">
                Notes <span className="badge text-bg-light">{notes.length}</span>
            </h3>

            {notes.length > 0 && (
                <div className="d-flex flex-wrap align-items-end" style={{ gap: 8, marginBottom: 12 }}>
                    <div style={{ flex: '1 1 220px' }}>
                        <label htmlFor={`${id}-search`} className="form-label small fw-semibold mb-1">
                            Search notes
                        </label>
                        <input
                            id={`${id}-search`}
                            type="search"
                            className="form-control"
                            placeholder="Type a word, like tutoring"
                            value={query}
                            onChange={(event) => onQueryChange(event.target.value)}
                        />
                    </div>
                    <div style={{ flex: '0 0 170px' }}>
                        <label htmlFor={`${id}-sort`} className="form-label small fw-semibold mb-1">
                            Sort by date
                        </label>
                        <select id={`${id}-sort`} className="form-select" value={sort} onChange={(event) => setSort(event.target.value)}>
                            <option value="desc">Newest first</option>
                            <option value="asc">Oldest first</option>
                        </select>
                    </div>
                </div>
            )}

            {message && (
                <div className="alert alert-secondary d-flex align-items-center justify-content-between" style={{ gap: 8, marginBottom: 12, padding: '8px 14px' }}>
                    <span className="small" role="status">
                        {message.text}
                    </span>
                    {message.undoId && (
                        <button ref={undoRef} type="button" className="btn btn-sm btn-link p-0 fw-semibold" onClick={handleUndo}>
                            Undo
                        </button>
                    )}
                </div>
            )}

            {question && (
                <div style={{ marginBottom: 12 }}>
                    <NoteQuestions
                        key={question.noteId}
                        question={question}
                        currentPreference={currentPreference}
                        onUpdate={onUpdate}
                        onReload={onReload}
                        onMessage={addMessage}
                        onDone={(text) => {
                            setQuestion(null);
                            addMessage(text);
                        }}
                    />
                </div>
            )}

            {/* Read out by screen readers as the search narrows the list. */}
            <div role="status">
                {words && shown.length > 0 && (
                    <p className="small text-body-secondary" style={{ marginBottom: 8 }}>
                        Showing {shown.length} of {notes.length} notes.
                    </p>
                )}
                {words && shown.length === 0 && <p className="text-body-secondary mb-0">No notes match "{query.trim()}".</p>}
            </div>

            {notes.length === 0 ? (
                <p className="text-body-secondary mb-0">No notes yet.</p>
            ) : (
                <ol className="list-unstyled mb-0 d-flex flex-column" style={{ gap: 12 }}>
                    {shown.map((note) =>
                        editingId === note.id ? (
                            <li key={note.id} id={`note-${note.id}`} tabIndex={-1} className="note-item">
                                <NoteEditForm note={note} today={today} onSaved={handleSaved} onCancel={() => handleCancel(note.id)} />
                            </li>
                        ) : (
                            <Note
                                key={note.id}
                                note={note}
                                isNew={newNoteIds.includes(note.id)}
                                flashing={highlightId === note.id}
                                words={words}
                                onEdit={() => {
                                    setEditingId(note.id);
                                    setMessage(null);
                                }}
                                onDelete={() => handleDelete(note)}
                            />
                        ),
                    )}
                </ol>
            )}
        </>
    );
}

// What the notes search looks through: the words (unless hidden), the
// type, the author, and the date, as the note's heading shows them.
function searchableText(note) {
    return [note.text ?? '', note.type.label, note.author, note.date_label].join(' ').toLowerCase();
}

// The note's words, with each match of the search marked in bold yellow.
// Splitting on a pattern in parentheses keeps the matches: they land at the
// odd places in the list.
function Highlighted({ text, words }) {
    if (!words) return text;

    const escaped = words.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const pattern = new RegExp(`(${escaped})`, 'gi');

    return text.split(pattern).map((part, index) =>
        index % 2 === 1 ? (
            <mark key={index} className="fw-semibold" style={{ padding: '0 1px' }}>
                {part}
            </mark>
        ) : (
            part
        ),
    );
}

function Note({ note, isNew, flashing, words, onEdit, onDelete }) {
    return (
        // tabIndex lets a source link, or the end of an edit, move keyboard
        // focus here.
        <li id={`note-${note.id}`} tabIndex={-1} className={`note-item${flashing ? ' is-flashing' : ''}`}>
            <div className="d-flex align-items-start justify-content-between" style={{ gap: 8 }}>
                <div className="small fw-semibold d-flex align-items-center flex-wrap" style={{ gap: 6 }}>
                    <span>
                        {note.date_label} · {note.type.label} · {note.author}
                    </span>
                    {note.edited_label && <span className="fw-normal text-body-secondary">· {note.edited_label}</span>}
                    {note.is_private && (
                        <span className="badge bg-blue d-inline-flex align-items-center gap-1">
                            <LockIcon />
                            Private
                        </span>
                    )}
                    {isNew && <span className="badge text-bg-sky">New</span>}
                </div>
                {/* Only the note's author may change it; the API checks too. */}
                {note.is_mine && (
                    <div className="d-flex flex-shrink-0" style={{ gap: 12 }}>
                        <button
                            type="button"
                            className="btn btn-link btn-sm p-0"
                            style={{ minHeight: 32 }}
                            aria-label={`Edit note from ${note.date_label}`}
                            onClick={onEdit}
                        >
                            Edit
                        </button>
                        <button
                            type="button"
                            className="btn btn-link btn-sm p-0 text-chili"
                            style={{ minHeight: 32 }}
                            aria-label={`Delete note from ${note.date_label}`}
                            onClick={onDelete}
                        >
                            Delete
                        </button>
                    </div>
                )}
            </div>

            {note.text === null ? (
                <div className="fst-italic text-body-secondary">Private note (hidden)</div>
            ) : (
                <div className="note-text">
                    <Highlighted text={note.text} words={words} />
                </div>
            )}

            {note.is_private && note.is_mine && (
                <div className="small text-body-secondary" style={{ marginTop: 4 }}>
                    {note.ai_read
                        ? "Only you can see this note. Before it was marked private, the AI read it without the student's name, email, or ID. It will not use this note again."
                        : 'Only you can see this note. It was not sent to the AI.'}
                </div>
            )}
        </li>
    );
}
