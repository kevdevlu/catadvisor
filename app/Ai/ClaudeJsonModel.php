<?php

namespace App\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Log;
use Psr\Http\Client\ClientInterface;

/**
 * Asks Claude Haiku for a structured (JSON schema) answer, at medium effort.
 * Medium, not low: the note check makes
 * judgment calls (is this sensitive? is a reply still missing?), and Haiku
 * is cheap enough that the extra thinking costs little. The key and model
 * come from config/services.php.
 *
 * Careful: never log the prompt, the input, or the answer. They hold note
 * text, which is a FERPA education record. Failures log only the error type
 * and status, and the SDK's OpenTelemetry tracing is off, so no trace can
 * carry the prompt either.
 */
final class ClaudeJsonModel implements JsonModel
{
    // Seconds. Real calls take 2 to 8 seconds; a stalled one must not hold
    // a PHP worker for minutes.
    public const TIMEOUT = 20;

    public const CONNECT_TIMEOUT = 5;

    /**
     * $transport is the HTTP client the SDK sends through. Tests pass a fake
     * one; the app uses defaultTransport(), which has the timeouts. Careful:
     * the SDK's own "timeout" option is advisory only, and nothing reads it.
     */
    public function __construct(private readonly ?ClientInterface $transport = null) {}

    public static function defaultTransport(): HttpClient
    {
        return new HttpClient(['timeout' => self::TIMEOUT, 'connect_timeout' => self::CONNECT_TIMEOUT]);
    }

    public function json(string $system, string $input, array $schema): ?array
    {
        $key = config('services.anthropic.key');

        if (blank($key)) {
            return null;
        }

        try {
            $client = new Client(
                apiKey: $key,
                requestOptions: ['transporter' => $this->transport ?? self::defaultTransport(), 'maxRetries' => 0],
                openTelemetry: false,
            );

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
