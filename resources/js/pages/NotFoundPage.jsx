import { Link } from 'react-router';

export default function NotFoundPage() {
    return (
        <main className="container-xl py-4">
            <h1 className="text-blue mt-0">Page not found</h1>
            <p>
                <Link to="/">Back to my students</Link>
            </p>
        </main>
    );
}
