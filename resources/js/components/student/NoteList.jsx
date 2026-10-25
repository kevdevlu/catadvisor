import { useEffect, useRef, useState } from 'react';
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
// A note's author can edit it in place or delete it, with no "Are you
// sure?": the note goes, and "Note deleted. Undo" shows for a few seconds.
// An edit to a note that isn't private goes to the AI check
// again, like a new note.
export default function NoteList({ notes, newNoteIds, highlightId, today, currentPreference, onUpdate, onReload, onChecking }) {
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

    return (
        <>
            <h3 className="h5 mt-0 text-blue">
                Notes <span className="badge text-bg-light">{notes.length}</span>
            </h3>

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

            {notes.length === 0 ? (
                <p className="text-body-secondary mb-0">No notes yet.</p>
            ) : (
                <ol className="list-unstyled mb-0 d-flex flex-column" style={{ gap: 12 }}>
                    {notes.map((note) =>
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

function Note({ note, isNew, flashing, onEdit, onDelete }) {
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

            {note.text === null ? <div className="fst-italic text-body-secondary">Private note (hidden)</div> : <div className="note-text">{note.text}</div>}

            {note.is_private && note.is_mine && (
                <div className="small text-body-secondary" style={{ marginTop: 4 }}>
                    {note.ai_read
                        ? "Only you can see this note. Before it was marked private, the AI checked it once without the student's name, email, or ID. It will not use this note again."
                        : 'Only you can see this note. It was not sent to the AI.'}
                </div>
            )}
        </li>
    );
}
