import { createContext, useContext, useEffect, useState } from 'react';
import { Navigate, useLocation } from 'react-router';
import { api, setUnauthorizedHandler } from '../lib/api';

const AuthContext = createContext(null);

// Holds who is signed in, for every page. user is undefined while the app is
// still asking the server, null when nobody is signed in, and the advisor
// otherwise.
export function AuthProvider({ children }) {
    const [user, setUser] = useState(undefined);

    useEffect(() => {
        // Any API call that comes back 401 means the session ended, so forget
        // the user and RequireAuth sends the page back to /login.
        setUnauthorizedHandler(() => setUser(null));

        // The server only serves /login to signed-out visitors, so there is no
        // one to look up there.
        if (window.location.pathname === '/login') {
            setUser(null);
            return;
        }

        api('/api/me')
            .then((response) => setUser(response.data))
            .catch(() => setUser(null));
    }, []);

    // Returns where to go next: the page the visitor first asked for before
    // being sent to the login page, or the first screen.
    async function signIn(username, password) {
        const response = await api('/login', { method: 'POST', body: { username, password } });
        setUser(response.data);
        return response.meta?.intended ?? '/';
    }

    async function signOut() {
        try {
            await api('/logout', { method: 'POST' });
        } finally {
            setUser(null);
        }
    }

    return <AuthContext.Provider value={{ user, signIn, signOut }}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    return useContext(AuthContext);
}

// Wraps pages that need a signed-in user. The server already sends signed-out
// visitors to /login when a page loads; this also covers a session that ends
// while the app is open, because any 401 clears the user.
export function RequireAuth({ children }) {
    const { user } = useAuth();
    const location = useLocation();

    if (user === undefined) {
        return <p className="container-xl py-4 text-body-secondary">Loading…</p>;
    }

    if (user === null) {
        return <Navigate to="/login" replace state={{ from: location }} />;
    }

    return children;
}
