import { useEffect, useRef, useState } from 'react';
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
//
// Drag the panel's left edge to resize it, or give the edge keyboard focus
// and use the arrow keys.
const MIN_WIDTH = 420;
const MAX_WIDTH = 1100;
// The list keeps at least this much room on the left.
const LIST_ROOM = 280;
const KEY_STEP = 24;

// The width is kept while the page is open, so closing and reopening the
// panel keeps it. A reload goes back to 700 px; remembering it longer is
// out of scope.
let savedWidth = 700;

export default function StudentPanel({ studentId }) {
    const location = useLocation();
    const navigate = useNavigate();
    const { order: carried, backgroundLocation, openedFromList } = location.state ?? {};

    const order = useStudentOrder(carried);
    const { page, error, newNoteIds, handleUpdate, handleNoteSaved, reload } = useStudentPage(studentId);
    const bodyRef = useRef(null);
    const [width, setWidth] = useState(savedWidth);
    const [dragging, setDragging] = useState(false);

    function resize(next) {
        const max = Math.min(MAX_WIDTH, window.innerWidth - LIST_ROOM);
        savedWidth = Math.round(Math.max(MIN_WIDTH, Math.min(next, max)));
        setWidth(savedWidth);
    }

    function handleResizeKey(event) {
        const change = { ArrowLeft: KEY_STEP, ArrowRight: -KEY_STEP }[event.key];
        if (!change) return;
        event.preventDefault();
        resize(width + change);
    }

    // While dragging, the whole page shows the resize pointer and no text
    // gets selected.
    useEffect(() => {
        document.body.classList.toggle('is-resizing-panel', dragging);
        return () => document.body.classList.remove('is-resizing-panel');
    }, [dragging]);

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
        <aside className="student-panel" aria-label="Student details" style={{ width }}>
            {/* Pointer capture keeps the drag going when the pointer moves
                faster than the edge, or leaves it. */}
            <div
                role="separator"
                aria-orientation="vertical"
                aria-label="Resize panel"
                aria-valuenow={width}
                aria-valuemin={MIN_WIDTH}
                aria-valuemax={MAX_WIDTH}
                tabIndex={0}
                className={`student-panel-resize${dragging ? ' is-dragging' : ''}`}
                onPointerDown={(event) => {
                    event.preventDefault();
                    event.currentTarget.setPointerCapture(event.pointerId);
                    setDragging(true);
                }}
                onPointerMove={(event) => dragging && resize(window.innerWidth - event.clientX)}
                onPointerUp={() => setDragging(false)}
                onPointerCancel={() => setDragging(false)}
                onKeyDown={handleResizeKey}
            />
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
                        key={page.data.id}
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
