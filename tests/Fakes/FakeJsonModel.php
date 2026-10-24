<?php

namespace Tests\Fakes;

use App\Ai\JsonModel;

/**
 * Stands in for Claude in tests: gives a fixed answer (or null, meaning "no
 * answer") and records every input, so a test can check what would have
 * been sent.
 */
final class FakeJsonModel implements JsonModel
{
    /** @var list<array{system: string, input: string}> */
    public array $calls = [];

    public function __construct(private readonly ?array $answer) {}

    public function json(string $system, string $input, array $schema): ?array
    {
        $this->calls[] = ['system' => $system, 'input' => $input];

        return $this->answer;
    }
}
