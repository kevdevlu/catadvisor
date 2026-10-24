import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router';
import { useAuth } from '../auth/AuthContext';
import { ChevronLeftIcon } from '../components/icons';
import StudentDetail from '../components/student/StudentDetail';
import { api } from '../lib/api';

// One student's full page, board F: the site header, a link back
// to the list, and the student in two columns. The side-panel switch and
// Previous / Next come with the side panel in build step 8.
export default function StudentPage() {
    const { studentId } = useParams();
    const { user } = useAuth();

    const [page, setPage] = useState(null);
    const [error, setError] = useState('');
    // Notes added since this page opened get a "New" badge.
    const [newNoteIds, setNewNoteIds] = useState([]);

    // Each request that can replace the page takes the next number, and only
    // the newest one's answer is shown. Rationale: a slow answer for an
    // older request (another student, or a reload from before an edit)
    // must not overwrite a newer one.
    const latestRequest = useRef(0);

    async function show(request) {
        const number = ++latestRequest.current;
        const response = await request;

        if (number === latestRequest.current) {
            setPage(response);
            setError('');
        }
    }

    useEffect(() => {
        setPage(null);
        setNewNoteIds([]);

        const number = latestRequest.current + 1;

        show(api(`/api/students/${studentId}`)).catch((err) => {
            if (number !== latestRequest.current) return;

            setError(
                err.status === 403 || err.status === 404
                    ? 'This student is not on your list, or the link is wrong.'
                    : 'The student did not load. Reload the page to try again.',
            );
        });
        // show() only reads a ref and calls state setters, so it doesn't
        // need to be a dependency.
    }, [studentId]);

    // The edit endpoints answer with the whole student page, so the view
    // just swaps it in. Errors go back to the form that sent the edit.
    function handleUpdate(path, options) {
        return show(api(`/api/students/${studentId}/${path}`, options));
    }

    // Reload, for example after the AI check changed the follow-up. A failed
    // reload keeps the old view.
    function reload() {
        show(api(`/api/students/${studentId}`)).catch(() => {});
    }

    // Reload after a new note, since it can change the last contact, the
    // reasons to reach out, and "waiting on a reply".
    function handleNoteSaved(note) {
        setNewNoteIds((ids) => [...ids, note.id]);
        reload();
    }

    return (
        <main className="container-xl" style={{ paddingTop: 16, paddingBottom: 48 }}>
            <div className="d-flex flex-wrap align-items-center justify-content-between" style={{ gap: 8, marginBottom: 12 }}>
                <Link to="/" className="btn btn-link px-0 d-inline-flex align-items-center fw-semibold text-decoration-none gap-1" style={{ minHeight: 44 }}>
                    <ChevronLeftIcon />
                    {user.is_admin ? 'Back to all students' : 'Back to my students'}
                </Link>
            </div>

            {error && <div className="alert alert-danger">{error}</div>}

            {!page && !error && <p className="text-body-secondary">Loading…</p>}

            {page && (
                <div className="card border-0 shadow-sm">
                    <div className="card-body" style={{ padding: 28 }}>
                        <StudentDetail
                            student={page.data}
                            today={page.meta.today}
                            wide
                            newNoteIds={newNoteIds}
                            onUpdate={handleUpdate}
                            onNoteSaved={handleNoteSaved}
                            onReload={reload}
                        />
                    </div>
                </div>
            )}
        </main>
    );
}
