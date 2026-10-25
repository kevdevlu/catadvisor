import { useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router';
import { useAuth } from '../../auth/AuthContext';
import AddNoteForm from './AddNoteForm';
import ContactPreference from './ContactPreference';
import FollowUpLine from './FollowUpLine';
import NoteList from './NoteList';
import PrepBrief from './PrepBrief';
import WaitingLine from './WaitingLine';
import WhyDue from './WhyDue';

const RISK_BADGE = { high: 'text-bg-chili', medium: 'text-bg-warning', low: 'text-bg-light' };

// How long a note stays highlighted after a source link jumps to it.
const FLASH_MS = 1600;

// One student, as on the shared student-detail board. The full
// page shows it in two columns (wide); the side panel (build step 8) will
// show the same thing in one. The admin gets no write controls;
// the API refuses those writes anyway.
//
// onUpdate(path, options) sends an edit to /api/students/{id}/{path} and
// shows the page the API sends back. onNoteSaved reloads the page after a
// new note.
export default function StudentDetail({ student, today, wide, newNoteIds, onUpdate, onNoteSaved, onReload }) {
    const { user } = useAuth();
    const typeRef = useRef(null);
    const whyDueRef = useRef(null);
    const [highlightId, setHighlightId] = useState(null);
    // The notes search box. It lives here so a source link can clear it.
    const [noteQuery, setNoteQuery] = useState('');
    const [jump, setJump] = useState(null);
    // How many AI checks are running for notes just saved. The brief waits
    // until none are, since each check writes the brief itself.
    const [checking, setChecking] = useState(0);
    // Read out by screen readers after an edit, for example "Next follow-up
    // cleared." The change itself is visible on screen.
    const [announcement, setAnnouncement] = useState('');

    useEffect(() => {
        if (!highlightId) return;
        const timer = setTimeout(() => setHighlightId(null), FLASH_MS);
        return () => clearTimeout(timer);
    }, [highlightId]);

    // A source link ("the Jun 2 email note") clears the notes search, so the
    // note is sure to be in the list, then scrolls to that note, moves
    // keyboard focus to it, and flashes it pale yellow.
    function jumpToNote(noteId) {
        setNoteQuery('');
        setHighlightId(noteId);
        setJump({ noteId });
    }

    // Runs after the list has drawn every note again.
    useEffect(() => {
        if (!jump) return;
        const element = document.getElementById(`note-${jump.noteId}`);
        element?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        element?.focus({ preventScroll: true });
    }, [jump]);

    // A link from a list popover arrives as /students/{id}#note-{noteId}:
    // jump to that note once the student is on screen. This runs again only
    // for another student or another note, not after each edit.
    const { hash } = useLocation();

    useEffect(() => {
        if (hash.startsWith('#note-')) jumpToNote(hash.slice('#note-'.length));
    }, [hash, student.id]);

    // "Add note" at the top jumps down to the form, ready to pick a type.
    function jumpToForm(event) {
        event.preventDefault();
        document.getElementById('add-note')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        typeRef.current?.focus({ preventScroll: true });
    }

    const canWrite = student.can_write;

    return (
        <div>
            <div className="d-flex flex-wrap align-items-start justify-content-between" style={{ gap: 12 }}>
                <div style={{ minWidth: 0 }}>
                    <div className="d-flex align-items-center flex-wrap" style={{ gap: 10 }}>
                        <h2 className="h3 mt-0 mb-0 text-blue">
                            {student.last_name}, {student.first_name}
                        </h2>
                        <span className={`badge ${RISK_BADGE[student.risk.value]}`}>{student.risk.label} risk</span>
                    </div>
                    <p className="text-body-secondary mb-0" style={{ marginTop: 4 }}>
                        {student.sis_id} · {student.major} · {student.class_year} · {student.credits_earned} credits · GPA {student.gpa}
                    </p>
                    <p className="small text-body-secondary mb-0">{student.email}</p>
                </div>
                {canWrite && (
                    <a href="#add-note" className="btn btn-red" onClick={jumpToForm}>
                        Add note
                    </a>
                )}
            </div>

            <div className={`student-detail-grid${wide ? ' is-wide' : ''}`}>
                <div style={{ minWidth: 0 }}>
                    {student.possible_duplicate && (
                        // Names no one and links nowhere: the other record
                        // belongs to another advisor. The
                        // admin is the office, so isn't told to ask it.
                        <div className="alert alert-secondary small" style={{ padding: '10px 14px', marginBottom: 12 }}>
                            Possible duplicate record under another advisor.{!user.is_admin && ' Ask the office to review.'}
                        </div>
                    )}

                    <ContactPreference
                        preference={student.contact_preference}
                        canWrite={canWrite}
                        onUpdate={onUpdate}
                        onJump={jumpToNote}
                        onAnnounce={setAnnouncement}
                    />

                    <div className="d-flex flex-column" style={{ gap: 8, marginTop: 12 }}>
                        <FollowUpLine
                            followUp={student.follow_up}
                            canWrite={canWrite}
                            onUpdate={onUpdate}
                            onJump={jumpToNote}
                            onAnnounce={setAnnouncement}
                        />
                        <WaitingLine
                            waiting={student.waiting}
                            canWrite={canWrite}
                            onUpdate={onUpdate}
                            onJump={jumpToNote}
                            onAnnounce={setAnnouncement}
                            onCleared={() => whyDueRef.current?.focus()}
                        />
                    </div>

                    <WhyDue due={student.due} headingRef={whyDueRef} />

                    <PrepBrief studentId={student.id} version={student.brief_version} paused={checking > 0} />

                    {canWrite && (
                        <AddNoteForm
                            studentId={student.id}
                            today={today}
                            typeRef={typeRef}
                            currentPreference={student.contact_preference?.text ?? null}
                            onSaved={onNoteSaved}
                            onUpdate={onUpdate}
                            onChecking={(change) => setChecking((count) => count + change)}
                            onReload={onReload}
                        />
                    )}
                </div>

                <section className="notes-column" style={{ minWidth: 0 }}>
                    <NoteList
                        notes={student.notes}
                        newNoteIds={newNoteIds}
                        highlightId={highlightId}
                        query={noteQuery}
                        onQueryChange={setNoteQuery}
                        today={today}
                        currentPreference={student.contact_preference?.text ?? null}
                        onUpdate={onUpdate}
                        onReload={onReload}
                        onChecking={(change) => setChecking((count) => count + change)}
                    />
                </section>
            </div>

            <div className="visually-hidden" role="status">
                {announcement}
            </div>
        </div>
    );
}
