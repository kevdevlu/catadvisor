<?php

namespace Tests\Feature;

use App\Ai\ClaudeJsonModel;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

/**
 * The real Claude client class, with a fake HTTP connection: what it sends,
 * and how it reads the answer. Nothing here
 * reaches the API.
 */
class ClaudeJsonModelTest extends TestCase
{
    /** @var list<array{request: RequestInterface}> */
    private array $sent = [];

    /**
     * A model whose HTTP connection answers with these responses in turn.
     */
    private function modelAnswering(Response ...$responses): ClaudeJsonModel
    {
        config(['services.anthropic.key' => 'test-key']);

        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->sent));

        return new ClaudeJsonModel(new Client(['handler' => $stack]));
    }

    private static function message(string $text, string $stopReason = 'end_turn'): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-haiku-5-5',
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]));
    }

    public function test_it_sends_the_model_effort_and_schema_and_reads_the_json(): void
    {
        $schema = ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false];

        $answer = $this->modelAnswering(self::message('{"ok": true}'))->json('The system prompt.', 'The input.', $schema);

        $this->assertSame(['ok' => true], $answer);

        $body = json_decode((string) $this->sent[0]['request']->getBody(), true);
        $this->assertSame('claude-haiku-5-5', $body['model']);
        $this->assertSame('medium', $body['output_config']['effort']);
        $this->assertSame(['type' => 'json_schema', 'schema' => $schema], $body['output_config']['format']);
        $this->assertSame('The system prompt.', $body['system']);
        $this->assertSame([['role' => 'user', 'content' => 'The input.']], $body['messages']);
    }

    public function test_a_refusal_or_a_cut_off_answer_is_no_answer(): void
    {
        $this->assertNull($this->modelAnswering(self::message('{"ok": true}', 'refusal'))->json('s', 'i', []));
        $this->assertNull($this->modelAnswering(self::message('{"ok": tr', 'max_tokens'))->json('s', 'i', []));
    }

    public function test_text_that_is_not_json_is_no_answer(): void
    {
        $this->assertNull($this->modelAnswering(self::message('Sorry, here is a summary.'))->json('s', 'i', []));
    }

    public function test_an_api_error_is_no_answer_with_no_retry(): void
    {
        // A retry would let a stalled call run twice the timeout.
        $error = fn () => new Response(500, ['Content-Type' => 'application/json'], '{"type":"error","error":{"type":"api_error","message":"boom"}}');

        $this->assertNull($this->modelAnswering($error(), $error())->json('s', 'i', []));
        $this->assertCount(1, $this->sent);
    }

    public function test_the_default_connection_has_real_timeouts(): void
    {
        // The SDK's own "timeout" option does nothing; the HTTP client's does.
        $client = ClaudeJsonModel::defaultTransport();

        $this->assertSame(ClaudeJsonModel::TIMEOUT, $client->getConfig('timeout'));
        $this->assertSame(ClaudeJsonModel::CONNECT_TIMEOUT, $client->getConfig('connect_timeout'));
    }
}
