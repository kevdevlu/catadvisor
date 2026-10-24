import { useEffect, useRef, useState } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router';
import { useAuth } from '../auth/AuthContext';
import { api, ApiError } from '../lib/api';

// The demo login: pick an account, type the one shared password.
export default function LoginPage() {
    const { user, signIn } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const passwordRef = useRef(null);

    const [accounts, setAccounts] = useState([]);
    const [accountsError, setAccountsError] = useState('');
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        api('/login/accounts')
            .then((response) => setAccounts(response.data))
            .catch(() => setAccountsError('The account list did not load. Reload the page to try again.'));
    }, []);

    if (user) {
        return <Navigate to="/" replace />;
    }

    async function handleSubmit(event) {
        event.preventDefault();

        if (busy) {
            return;
        }

        setError('');

        if (!username) {
            setError('Pick an account first.');
            return;
        }

        setBusy(true);

        try {
            const intended = await signIn(username, password);
            // A redirect inside the app (RequireAuth) remembers the page in
            // router state; a signed-out page load is remembered by the server.
            navigate(location.state?.from?.pathname ?? intended, { replace: true });
        } catch (err) {
            if (err instanceof ApiError && err.status === 429) {
                setError('Too many tries. Wait a minute, then try again.');
            } else {
                setError(err.body?.errors?.password?.[0] ?? 'Sign-in failed. Try again.');
            }
            passwordRef.current?.focus();
        } finally {
            setBusy(false);
        }
    }

    return (
        <main className="container-xl py-5">
            <div className="card border-0 shadow-sm mx-auto" style={{ maxWidth: 440 }}>
                <div className="card-body p-4">
                    <h1 className="h3 mt-0 mb-1 text-blue">Sign in</h1>
                    <p className="text-body-secondary">
                        Every demo account uses the same password, which came with the submission.
                    </p>

                    <form onSubmit={handleSubmit} noValidate>
                        <fieldset className="mb-3">
                            <legend className="form-label fw-semibold fs-6">Account</legend>
                            {accountsError && (
                                <div className="alert alert-warning py-2" role="alert">
                                    {accountsError}
                                </div>
                            )}
                            <div className="d-grid gap-2">
                                {accounts.map((account) => (
                                    <div key={account.username}>
                                        <input
                                            type="radio"
                                            className="btn-check"
                                            name="username"
                                            id={`account-${account.username}`}
                                            value={account.username}
                                            checked={username === account.username}
                                            onChange={() => setUsername(account.username)}
                                        />
                                        <label
                                            className="btn btn-outline-blue w-100 text-start"
                                            htmlFor={`account-${account.username}`}
                                        >
                                            {account.display_name}
                                            {account.is_admin && <span className="small ms-2">(read-only, sees all advisors)</span>}
                                        </label>
                                    </div>
                                ))}
                            </div>
                        </fieldset>

                        <div className="mb-3">
                            <label htmlFor="password" className="form-label fw-semibold">
                                Password
                            </label>
                            <input
                                ref={passwordRef}
                                id="password"
                                type="password"
                                className={`form-control${error ? ' is-invalid' : ''}`}
                                autoComplete="current-password"
                                aria-invalid={error ? 'true' : undefined}
                                aria-describedby={error ? 'login-error' : undefined}
                                value={password}
                                onChange={(event) => setPassword(event.target.value)}
                            />
                        </div>

                        {error && (
                            <div id="login-error" className="alert alert-danger py-2" role="alert">
                                {error}
                            </div>
                        )}

                        {/* Not disabled while busy: a disabled button drops keyboard focus. */}
                        <button type="submit" className="btn btn-red w-100" aria-disabled={busy}>
                            {busy ? 'Signing in…' : 'Sign in'}
                        </button>
                    </form>

                    <p className="small text-body-secondary mt-3 mb-0">
                        Prototype with fictional student data. A real version would use NetID sign-in.
                    </p>
                </div>
            </div>
        </main>
    );
}
