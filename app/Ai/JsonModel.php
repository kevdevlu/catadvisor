<?php

namespace App\Ai;

/**
 * A language model that answers in JSON matching a schema. The app uses
 * Claude (ClaudeJsonModel); the tests swap in a fake, so they never call the
 * real API.
 */
interface JsonModel
{
    /**
     * The model's answer, decoded, or null when there is no answer: no API
     * key, a failed call, or a refusal. Callers treat null as "change
     * nothing".
     *
     * @param  array<string, mixed>  $schema  A JSON schema for the answer.
     * @return array<string, mixed>|null
     */
    public function json(string $system, string $input, array $schema): ?array;
}
