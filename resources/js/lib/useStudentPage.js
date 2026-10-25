import { useEffect, useRef, useState } from 'react';
import { api } from './api';
import { useStudentChanges } from './studentChanges';

// One student's page data, for both the full page and the side panel:
// loads it, and swaps in the new page after each edit.
export function useStudentPage(studentId) {
    const { changed } = useStudentChanges();

    const [page, setPage] = useState(null);
    const [error, setError] = useState('');
    // Notes added since this student opened get a "New" badge.
    const [newNoteIds, setNewNoteIds] = useState([]);

    // Each request that can replace the page takes the next number, and only
    // the newest one's answer is shown. Rationale: a slow answer for an
    // older request (another student, or a reload from before an edit)
    // must not overwrite a newer one.
    const latestRequest = useRef(0);
    // The student on screen now. A reload started for the student before
    // (when the AI check for their note finishes after Next) must not bring
    // them back: the next note would be saved on them.
    const currentId = useRef(studentId);

    async function show(request) {
        const number = ++latestRequest.current;
        const response = await request;

        if (number === latestRequest.current && response.data.id === currentId.current) {
            setPage(response);
            setError('');
        }
    }

    useEffect(() => {
        currentId.current = studentId;
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
    async function handleUpdate(path, options) {
        await show(api(`/api/students/${studentId}/${path}`, options));
        changed();
    }

    // Reload, for example after the AI check changed the follow-up. A failed
    // reload keeps the old view.
    function reload() {
        show(api(`/api/students/${studentId}`))
            .then(changed)
            .catch(() => {});
    }

    // Reload after a new note, since it can change the last contact, the
    // reasons to reach out, and "waiting on a reply".
    function handleNoteSaved(note) {
        setNewNoteIds((ids) => [...ids, note.id]);
        reload();
    }

    return { page, error, newNoteIds, handleUpdate, handleNoteSaved, reload };
}
