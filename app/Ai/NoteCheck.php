<?php

namespace App\Ai;

/**
 * What the note check found, for the advisor who just saved the note:
 * what to ask them, and what changed in the background.
 */
final class NoteCheck
{
    /**
     * @param  list<string>  $messages  For example "Next follow-up set by AI: Oct 20, 2026."
     */
    public function __construct(
        // False when there was no AI answer (no key, a failed call, or a
        // private note, which is never sent). Then nothing changed.
        public readonly bool $ran,
        public readonly bool $askSensitive = false,
        public readonly ?string $contactPreferenceSuggestion = null,
        public readonly array $messages = [],
    ) {}

    public function toArray(): array
    {
        return [
            'ran' => $this->ran,
            'ask_sensitive' => $this->askSensitive,
            'contact_preference_suggestion' => $this->contactPreferenceSuggestion,
            'messages' => $this->messages,
        ];
    }
}
