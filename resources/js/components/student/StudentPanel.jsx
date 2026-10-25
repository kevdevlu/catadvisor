import { useEffect, useRef } from 'react';
import { useLocation, useNavigate } from 'react-router';
import { useStudentOrder } from '../../lib/studentLinks';
import { useStudentPage } from '../../lib/useStudentPage';
import { ChevronsRightIcon, MaximizeIcon } from '../icons';
import StudentDetail from './StudentDetail';
import StudentNav from './StudentNav';

// A student in a panel on the right, over the list, like Notion's page peek
// (board E). The list stays visible and usable on the left. The
// address is the student's own (/students/{id}), so Back closes the panel.
// The panel shows the same student component as the full page, in one
// column.
//
// Previous / Next walk through the list as it is filtered and sorted, and
// replace the panel's history entry, so Back still closes the panel.
export default function StudentPanel({ studentId }) {
    const location = useLocation();
    const navigate = useNavigate();
    const { order: carried, backgroundLocation, openedFromList } = location.state ?? {};

    const order = useStudentOrder(carried);
    const { page, error, newNoteIds, handleUpdate, handleNoteSaved, reload } = useStudentPage(studentId);
    const bodyRef = useRef(null);

    // Each student starts at the top, with keyboard focus in the panel.
    useEffect(() => {
        bodyRef.current?.scrollTo(0, 0);
        bodyRef.current?.focus({ preventScroll: true });
    }, [studentId]);

    // Escape closes the panel, unless it was meant for something else: a
    // popover that handled it, or a form field being typed in.
    useEffect(() => {
        function handleKey(event) {
            const typing = event.target.closest?.('input, textarea, select, [contenteditable="true"]');
            if (event.key === 'Escape' && !event.defaultPrevented && !typing) close();
        }

        document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    });

    // Back to the list, with keyboard focus on the student's name there.
    function close() {
        if (openedFromList) {
            navigate(-1);
        } else {
            navigate(backgroundLocation ?? '/');
        }

        requestAnimationFrame(() => document.querySelector(`.student-link[href="/students/${studentId}"]`)?.focus());
    }

    function go(id) {
        navigate(`/students/${id}`, { replace: true, state: location.state });
    }

    return (
        <aside className="student-panel" aria-label="Student details">
            <div className="student-panel-bar d-flex align-items-center justify-content-between flex-wrap">
                <div className="d-flex align-items-center" style={{ gap: 2 }}>
                    <button type="button" className="btn btn-link text-blue panel-icon-button" aria-label="Close panel" onClick={close}>
                        <ChevronsRightIcon />
                    </button>
                    <button
                        type="button"
                        className="btn btn-link text-blue panel-icon-button"
                        aria-label="Open as full page"
                        onClick={() => navigate(`/students/${studentId}`, { state: { order } })}
                    >
                        <MaximizeIcon />
                    </button>
                </div>
                <StudentNav order={order} currentId={studentId} onGo={go} />
            </div>

            <div ref={bodyRef} className="student-panel-body" tabIndex={-1}>
                {error && <div className="alert alert-danger">{error}</div>}
                {!page && !error && <p className="text-body-secondary">Loading…</p>}
                {page && (
                    <StudentDetail
                        student={page.data}
                        today={page.meta.today}
                        wide={false}
                        newNoteIds={newNoteIds}
                        onUpdate={handleUpdate}
                        onNoteSaved={handleNoteSaved}
                        onReload={reload}
                    />
                )}
            </div>
        </aside>
    );
}
