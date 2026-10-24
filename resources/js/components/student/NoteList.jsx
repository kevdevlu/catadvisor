import { LockIcon } from '../icons';

// The student's notes, newest first. Someone else's private note
// arrives from the API without its words, so it shows as "Private note
// (hidden)" with the date, type, and author. The author of a
// private note is reminded who can see it.
export default function NoteList({ notes, newNoteIds, highlightId }) {
    return (
        <>
            <h3 className="h5 mt-0 text-blue">
                Notes <span className="badge text-bg-light">{notes.length}</span>
            </h3>

            {notes.length === 0 ? (
                <p className="text-body-secondary mb-0">No notes yet.</p>
            ) : (
                <ol className="list-unstyled mb-0 d-flex flex-column" style={{ gap: 12 }}>
                    {notes.map((note) => (
                        <Note key={note.id} note={note} isNew={newNoteIds.includes(note.id)} flashing={highlightId === note.id} />
                    ))}
                </ol>
            )}
        </>
    );
}

function Note({ note, isNew, flashing }) {
    return (
        // tabIndex lets a source link move keyboard focus here after it
        // scrolls to the note.
        <li id={`note-${note.id}`} tabIndex={-1} className={`note-item${flashing ? ' is-flashing' : ''}`}>
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
