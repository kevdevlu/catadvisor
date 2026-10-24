import { Link, useNavigate } from 'react-router';
import { useAuth } from '../auth/AuthContext';

// The header on every page: the Arizona red strip, then the blue bar with the
// app name and who is signed in. The "Prototype · fictional data" badge stays
// visible so no one mistakes this for a real university system.
export default function SiteHeader() {
    const { user, signOut } = useAuth();
    const navigate = useNavigate();

    // Leave for the login page even if the server call fails: signOut has
    // already forgotten the user by then.
    async function handleSignOut() {
        try {
            await signOut();
        } finally {
            navigate('/login', { replace: true });
        }
    }

    return (
        <>
            <div className="bg-red" style={{ height: 6 }} />
            <header className="bg-blue text-white">
                <div className="container-xl d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
                    <div className="d-flex flex-wrap align-items-center gap-3">
                        <Link to="/" className="text-white fw-bold text-decoration-none fs-4">
                            Cat Advisor
                        </Link>
                        <span className="badge text-bg-sky">Prototype · fictional data</span>
                    </div>

                    {user && (
                        <div className="d-flex flex-wrap align-items-center gap-3">
                            <span>
                                Signed in as <strong>{user.display_name}</strong>
                            </span>
                            {user.is_admin && <span className="badge bg-white text-blue">Read-only</span>}
                            <button type="button" className="btn btn-sm btn-outline-white" onClick={handleSignOut}>
                                Sign out
                            </button>
                        </div>
                    )}
                </div>
            </header>
        </>
    );
}
