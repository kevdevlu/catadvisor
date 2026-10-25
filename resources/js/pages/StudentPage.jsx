import { Link, useLocation, useNavigate, useParams } from 'react-router';
import { useAuth } from '../auth/AuthContext';
import { ChevronLeftIcon, MinimizeIcon } from '../components/icons';
import StudentDetail from '../components/student/StudentDetail';
import StudentNav from '../components/student/StudentNav';
import { useStudentOrder } from '../lib/studentLinks';
import { useStudentPage } from '../lib/useStudentPage';

// One student's full page, board F: the site header, a link back
// to the list, an icon to switch to the side panel, Previous / Next, and the
// student in two columns. A typed or pasted address, or a refresh, opens
// this page; Previous / Next then follow the list's first order.
export default function StudentPage() {
    const { studentId } = useParams();
    const { user } = useAuth();
    const location = useLocation();
    const navigate = useNavigate();

    const order = useStudentOrder(location.state?.order);
    const { page, error, newNoteIds, handleUpdate, handleNoteSaved, reload } = useStudentPage(studentId);

    // The panel opens over the list in its first order, since the list's
    // earlier filters are gone once the full page replaced it.
    function openAsPanel() {
        navigate(`/students/${studentId}`, { state: { backgroundLocation: { pathname: '/', search: '', hash: '' }, order } });
    }

    return (
        <main className="container-xl" style={{ paddingTop: 16, paddingBottom: 48 }}>
            <div className="d-flex flex-wrap align-items-center justify-content-between" style={{ gap: 8, marginBottom: 12 }}>
                <div className="d-flex align-items-center flex-wrap" style={{ gap: 4 }}>
                    <Link
                        to="/"
                        className="btn btn-link px-0 d-inline-flex align-items-center fw-semibold text-decoration-none gap-1"
                        style={{ minHeight: 44 }}
                    >
                        <ChevronLeftIcon />
                        {user.is_admin ? 'Back to all students' : 'Back to my students'}
                    </Link>
                    <button
                        type="button"
                        className="btn btn-link text-blue panel-icon-button"
                        style={{ marginLeft: 8 }}
                        aria-label="Open as side panel"
                        onClick={openAsPanel}
                    >
                        <MinimizeIcon />
                    </button>
                </div>
                <StudentNav order={order} currentId={studentId} onGo={(id) => navigate(`/students/${id}`, { replace: true, state: { order } })} />
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
