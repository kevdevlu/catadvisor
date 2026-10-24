import { Link } from 'react-router';

// The header on every page: the Arizona red strip, then the blue bar with the
// app name. The "Prototype · fictional data" badge stays visible so no one
// mistakes this for a real university system.
export default function SiteHeader() {
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
                </div>
            </header>
        </>
    );
}
