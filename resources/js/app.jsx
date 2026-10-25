import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Route, Routes, useLocation, useParams } from 'react-router';
import { AuthProvider, RequireAuth } from './auth/AuthContext';
import SiteHeader from './components/SiteHeader';
import StudentPanel from './components/student/StudentPanel';
import { StudentChangesProvider } from './lib/studentChanges';
import { PanelOpenContext } from './lib/studentLinks';
import HomePage from './pages/HomePage';
import LoginPage from './pages/LoginPage';
import NotFoundPage from './pages/NotFoundPage';
import StudentPage from './pages/StudentPage';

// The history entry the app first loaded on. The browser keeps a page's
// saved state through a refresh, panel and all, but a refresh should open
// the full page, so that first entry never opens as a panel.
const FIRST_KEY = window.history.state?.key ?? 'default';

// React Router's "modal route" pattern: a student opened from the
// list carries the list's address as backgroundLocation. The list keeps
// showing at that address, and the student opens in a panel over it. A
// typed or pasted address has no background, so the student gets the full
// page.
function AppRoutes() {
    const location = useLocation();
    const background = location.key !== FIRST_KEY ? location.state?.backgroundLocation : null;

    return (
        <PanelOpenContext.Provider value={Boolean(background)}>
            <Routes location={background ?? location}>
                <Route path="/login" element={<LoginPage />} />
                <Route
                    path="/"
                    element={
                        <RequireAuth>
                            <HomePage />
                        </RequireAuth>
                    }
                />
                <Route
                    path="/students/:studentId"
                    element={
                        <RequireAuth>
                            <StudentPage />
                        </RequireAuth>
                    }
                />
                <Route path="*" element={<NotFoundPage />} />
            </Routes>

            {background && (
                <Routes>
                    <Route
                        path="/students/:studentId"
                        element={
                            <RequireAuth>
                                <PanelRoute />
                            </RequireAuth>
                        }
                    />
                </Routes>
            )}
        </PanelOpenContext.Provider>
    );
}

// The panel stays mounted while Previous / Next change the student, so it
// doesn't flash closed and open again.
function PanelRoute() {
    const { studentId } = useParams();

    return <StudentPanel studentId={studentId} />;
}

createRoot(document.getElementById('app')).render(
    <StrictMode>
        <BrowserRouter>
            <AuthProvider>
                <StudentChangesProvider>
                    <SiteHeader />
                    <AppRoutes />
                </StudentChangesProvider>
            </AuthProvider>
        </BrowserRouter>
    </StrictMode>,
);
