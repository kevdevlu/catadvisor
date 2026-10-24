import { useParams } from 'react-router';

// One student's full page. Build step 5 fills this in.
export default function StudentPage() {
    const { studentId } = useParams();

    return (
        <main className="container-xl py-4">
            <h1 className="text-blue mt-0">Student</h1>
            <p className="text-body-secondary">Student {studentId} comes in build step 5.</p>
        </main>
    );
}
