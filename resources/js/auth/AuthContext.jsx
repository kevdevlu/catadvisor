import { createContext, useContext, useEffect, useState } from 'react';
import { Navigate, useLocation } from 'react-router';
import { api } from '../lib/api';

const AuthContext = createContext(null);

// Holds who is signed in, for every page. user is undefined while the app is
// still asking the server, null when nobody is signed in, and the advisor
// otherwise.
export function AuthProvider({ children }) {
    const [user, setUser] = useState(undefined);

    useEffect(() => {
        api('/api/me')
            .then((response) => setUser(response.data))
            .catch(() => setUser(null));
    }, []);

    async function signIn(username, password) {
        const response = await api('/login', { method: 'POST', body: { username, password } });
        setUser(response.data);
    }

    async function signOut() {
        await api('/logout', { method: 'POST' });
        setUser(null);
    }

    return <AuthContext.Provider value={{ user, signIn, signOut }}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    return useContext(AuthContext);
}

// Wraps pages that need a signed-in user. The server already sends signed-out
// visitors to /login; this covers a session that ends while the app is open.
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
