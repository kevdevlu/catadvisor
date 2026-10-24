<?php

namespace App\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Illuminate\Support\Facades\Log;

/**
 * Asks Claude Haiku for a structured (JSON schema) answer, at medium effort.
 * Medium, not low: the note check makes
 * judgment calls (is this sensitive? is a reply still missing?), and Haiku
 * is cheap enough that the extra thinking costs little. The key and model
 * come from config/services.php.
 *
 * Careful: never log the prompt, the input, or the answer. They hold note
 * text, which is a FERPA education record. Failures log only the error type
 * and status.
 */
final class ClaudeJsonModel implements JsonModel
{
    public function json(string $system, string $input, array $schema): ?array
    {
        $key = config('services.anthropic.key');

        if (blank($key)) {
            return null;
        }

        try {
            $client = new Client(apiKey: $key, requestOptions: ['timeout' => 30, 'maxRetries' => 1]);

            $message = $client->messages->create(
                model: config('services.anthropic.model'),
                maxTokens: 4000,
                system: $system,
                messages: [['role' => 'user', 'content' => $input]],
                outputConfig: [
                    'effort' => 'medium',
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
            );
        } catch (APIStatusException $e) {
            Log::warning('Claude API call failed', ['status' => $e->status, 'type' => $e->type?->value]);

            return null;
        } catch (APIConnectionException $e) {
            Log::warning('Claude API could not be reached', ['exception' => $e::class]);

            return null;
        } catch (AnthropicException $e) {
            Log::warning('Claude API call failed', ['exception' => $e::class]);

            return null;
        }

        // A refusal, or an answer cut off at maxTokens, is no answer.
        if ($message->stopReason !== 'end_turn') {
            Log::warning('Claude API gave no usable answer', ['stop_reason' => $message->stopReason]);

            return null;
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $answer = json_decode($block->text, true);

                return is_array($answer) ? $answer : null;
            }
        }

        return null;
    }
}
