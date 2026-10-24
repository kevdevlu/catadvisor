import { NoteIcon } from '../icons';

// "the Jun 2 email note": the note a line on the student page came from.
// Clicking it scrolls to that note and flashes it. A deleted
// note can't be shown, so its name is plain text instead of a link.
export default function SourceLink({ source, onJump, className = '' }) {
    if (!source.note_id) {
        return <>the {source.note_name}</>;
    }

    function handleClick(event) {
        event.preventDefault();
        onJump(source.note_id);
    }

    return (
        <>
            the{' '}
            <a href={`#note-${source.note_id}`} className={`source-link ${className}`} onClick={handleClick}>
                {source.note_name}
                <NoteIcon />
            </a>
        </>
    );
}
