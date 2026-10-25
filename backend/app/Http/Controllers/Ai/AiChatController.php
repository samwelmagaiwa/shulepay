<?php

namespace App\Http\Controllers\Ai;

use App\Ai\AiToolExecutor;
use App\Ai\AiToolRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatController extends Controller
{
    private const GEMINI_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /** Models tried in order; next is used when the current one returns 429/404 or times out. */
    private const MODEL_CHAIN = [
        'gemini-3.6-flash',      // primary — fast, capable
        'gemini-3.5-flash',      // fallback 1
        'gemini-3.5-flash-lite', // fallback 2 — lightest, highest quota
    ];

    private const MAX_TURNS = 8;

    private const MAX_HISTORY_MESSAGES = 20;

    // Gemini free-tier daily limits
    private const DAILY_REQUEST_LIMIT = 1500;

    private const DAILY_TOKEN_LIMIT = 1_000_000;

    public function chat(Request $request): JsonResponse
    {
        // A turn can chain several model calls and data lookups; PHP's default 30s limit is too short.
        set_time_limit(110);

        $request->validate([
            'messages' => 'required|array|min:1|max:200',
            'messages.*.role' => 'required|in:user,assistant',
            'messages.*.content' => 'required|string|max:4000',
        ]);

        $user = $request->user();
        $school = app()->bound('active_school') ? app('active_school') : null;
        $apiKey = config('services.gemini.key');

        if (empty($apiKey)) {
            return response()->json(['error' => 'AI service is not configured yet.'], 503);
        }

        $tools = AiToolRegistry::toolsFor($user);
        $systemPrompt = AiToolRegistry::systemPrompt($user, $school);
        $executor = new AiToolExecutor($user);
        // A tool the user was not offered (their role or permissions) is never run, whatever the model asks for.
        $offered = array_column($tools, 'name');

        // Keep only the last N user/assistant messages to avoid token bloat on long conversations
        $incoming = collect($request->input('messages'))->take(-self::MAX_HISTORY_MESSAGES);

        // Convert frontend messages [{role, content}] to Gemini format [{role, parts:[{text}]}]
        $history = $incoming->map(fn ($m) => [
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ])->toArray();

        // Quota tracking keys scoped to this API key (one key = one school typically)
        $quotaKey = 'gemini_quota_'.date('Y-m-d').'_'.substr(md5($apiKey), 0, 8);
        $quota = Cache::get($quotaKey, ['requests' => 0, 'tokens' => 0]);

        $tokensThisRequest = 0;

        // Agentic loop: Gemini may request tool calls before giving a final answer
        for ($turn = 0; $turn < self::MAX_TURNS; $turn++) {
            $payload = [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => $history,
                'tools' => [['functionDeclarations' => $tools]],
                'generationConfig' => ['maxOutputTokens' => 2048, 'temperature' => 0.2],
            ];

            $response = $this->callGemini($apiKey, $payload);

            if ($response === null) {
                if ($this->lastGeminiStatus === 429) {
                    return response()->json(['error' => 'Daily AI quota reached across all available models. Please try again tomorrow.'], 429);
                }

                return response()->json(['error' => 'AI service temporarily unavailable. Please try again in a moment.'], 502);
            }

            $body = $response->json();

            // Accumulate token usage from Gemini's usageMetadata
            $usage = $body['usageMetadata'] ?? [];
            $tokensThisRequest += (int) ($usage['totalTokenCount'] ?? 0);

            $candidate = $body['candidates'][0] ?? null;
            $content = $candidate['content'] ?? null;

            if (! $content) {
                $finishReason = $candidate['finishReason'] ?? 'UNKNOWN';
                Log::warning('Gemini empty content', ['finishReason' => $finishReason, 'candidate' => $candidate]);

                return response()->json(['reply' => 'I could not generate a response. Please try rephrasing your question.']);
            }

            // Check if Gemini wants to call functions
            $functionCalls = collect($content['parts'] ?? [])->filter(fn ($p) => isset($p['functionCall']));

            if ($functionCalls->isEmpty()) {
                // Final text answer — update quota and return with usage stats
                $quota['requests'] += 1;
                $quota['tokens'] += $tokensThisRequest;
                Cache::put($quotaKey, $quota, now()->endOfDay());

                $text = collect($content['parts'] ?? [])
                    ->filter(fn ($p) => isset($p['text']))
                    ->pluck('text')
                    ->join("\n");

                return response()->json([
                    'reply' => $text ?: 'No response generated.',
                    'usage' => [
                        'requests_today' => $quota['requests'],
                        'requests_limit' => self::DAILY_REQUEST_LIMIT,
                        'tokens_today' => $quota['tokens'],
                        'tokens_limit' => self::DAILY_TOKEN_LIMIT,
                        'tokens_this_message' => $tokensThisRequest,
                    ],
                ]);
            }

            // Gemini returns functionCall.args as {} which PHP decodes as [].
            // When we replay the model turn in history, [] re-encodes as a JSON
            // array and Gemini rejects it — it must be a JSON object (Struct).
            $sanitisedParts = array_map(function ($part) {
                if (isset($part['functionCall']['args']) && $part['functionCall']['args'] === []) {
                    $part['functionCall']['args'] = (object) [];
                }

                return $part;
            }, $content['parts']);

            $history[] = ['role' => 'model', 'parts' => $sanitisedParts];

            $resultParts = $functionCalls->map(function ($part) use ($executor, $offered) {
                $call = $part['functionCall'];
                $result = in_array($call['name'], $offered, true)
                    ? $executor->execute($call['name'], (array) ($call['args'] ?? []))
                    : ['error' => 'You do not have access to this information.'];

                return [
                    'functionResponse' => [
                        'name' => $call['name'],
                        // Gemini requires response to be a JSON object (Struct), never an array.
                        // Indexed PHP arrays encode as JSON arrays and Gemini rejects them.
                        'response' => $this->toJsonObject($result),
                    ],
                ];
            })->values()->toArray();

            $history[] = ['role' => 'user', 'parts' => $resultParts];
        }

        return response()->json(['reply' => 'I reached the limit while processing your request. Please try a simpler question.']);
    }

    /**
     * Call Gemini, walking MODEL_CHAIN on quota exhaustion (429).
     * Within each model: up to 2 retries on transient 5xx errors.
     * Sets $this->lastGeminiStatus and $this->activeModel on exit.
     */
    private int $lastGeminiStatus = 0;

    private string $activeModel = '';

    private function callGemini(string $apiKey, array $payload): ?Response
    {
        foreach (self::MODEL_CHAIN as $model) {
            $url = self::GEMINI_BASE.$model.':generateContent';

            // Two fast attempts per model (no sleep): total worst-case ≈ 5 models × 2 × 20s = 200s
            // but quota/404 skips are instant, so typical failure chain is much faster.
            for ($attempt = 0; $attempt < 2; $attempt++) {
                try {
                    $response = Http::withQueryParameters(['key' => $apiKey])
                        ->timeout(20)
                        ->post($url, $payload);
                } catch (ConnectionException $e) {
                    Log::warning('Gemini connection timeout', ['model' => $model, 'attempt' => $attempt + 1, 'error' => $e->getMessage()]);
                    // Treat timeout like a transient error — try next model
                    break;
                }

                if ($response->successful()) {
                    $this->activeModel = $model;

                    return $response;
                }

                $status = $response->status();
                $this->lastGeminiStatus = $status;
                Log::warning('Gemini model error', ['model' => $model, 'attempt' => $attempt + 1, 'status' => $status]);

                // Hard auth/bad-request errors: no point retrying any model
                if (in_array($status, [400, 401, 403])) {
                    return null;
                }

                // Quota exhausted or model not found → skip to next model immediately
                if ($status === 429 || $status === 404) {
                    Log::info("Gemini skipping model {$model} (status {$status}), trying next.");
                    break;
                }

                // Transient 5xx: one retry immediately, no sleep — nginx timeout is 120s
            }
        }

        // All models exhausted
        return null;
    }

    /**
     * Ensure a tool result is always a JSON object when serialised.
     * Gemini's functionResponse.response must be a Struct (JSON object {}),
     * never a JSON array []. Indexed PHP arrays become JSON arrays, so we
     * wrap them under a "data" key to make them objects.
     */
    private function toJsonObject(array $result): array|object
    {
        if (empty($result)) {
            return (object) [];
        }

        // If the array has sequential integer keys it's a list → wrap it
        if (array_is_list($result)) {
            return ['data' => $result];
        }

        return $result;
    }
}
