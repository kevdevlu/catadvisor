import keywords from '../../data/sensitive-keywords.json';

// The sensitive-topic hint on the note form. The list is shared
// with the server (app/Support/SensitiveKeywords.php), which runs it again
// on save. Each entry matches from the start of a word, so "counsel" finds
// "counselor" but "died" does not find "studied".
const PATTERN = new RegExp(`\\b(?:${keywords.patterns.join('|')})\\w*`, 'gi');

// The matched words, lowercased, each once.
export function findSensitive(text) {
    return [...new Set((text.match(PATTERN) ?? []).map((word) => word.toLowerCase()))];
}

// For example: "counselor", "stay private"
export function quoteList(words) {
    return words.map((word) => `"${word}"`).join(', ');
}
