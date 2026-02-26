<?php

error_reporting(E_ALL & ~E_DEPRECATED);

require __DIR__ . '/../vendor/autoload.php';

use App\LatitudeClient;
use App\PromptParser;
use App\TelemetrySetup;
use Dotenv\Dotenv;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../..');
$dotenv->load();

$latitudeApiKey = $_ENV['LATITUDE_API_KEY'];
$latitudeProjectId = (int) $_ENV['LATITUDE_PROJECT_ID'];
$latitudePromptPath = $_ENV['LATITUDE_PROMPT_PATH'];
$latitudeVersionUuid = $_ENV['LATITUDE_PROMPT_VERSION_UUID'] ?? 'live';
$openaiApiKey = $_ENV['OPENAI_API_KEY'];

// ---------------------------------------------------------------------------
// Slim app
// ---------------------------------------------------------------------------
$app = AppFactory::create();
$app->addBodyParsingMiddleware();

// CORS middleware
$app->add(function (Request $request, $handler) {
    if ($request->getMethod() === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
        return $response
            ->withHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withStatus(200);
    }

    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
        ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
});

// ---------------------------------------------------------------------------
// POST /generate-wikipedia-article
// ---------------------------------------------------------------------------
$app->post('/generate-wikipedia-article', function (
    Request $request,
    Response $response,
) use (
    $latitudeApiKey,
    $latitudeProjectId,
    $latitudePromptPath,
    $latitudeVersionUuid,
    $openaiApiKey,
) {
    $body = $request->getParsedBody();
    $input = $body['input'] ?? '';

    // 1. Initialise OpenTelemetry – exporter points at Latitude's OTLP
    //    endpoint so spans appear in the Latitude dashboard.
    $telemetry = new TelemetrySetup($latitudeApiKey);
    $tracer = $telemetry->getTracer();

    // 2. Start the PARENT span (the "capture" wrapper).
    //    This mirrors the Python SDK's @telemetry.capture() decorator,
    //    which creates an "unresolved_external" span that Latitude
    //    resolves against the prompt path.
    $parentSpan = $tracer->spanBuilder("capture-$latitudePromptPath")
        ->setSpanKind(SpanKind::KIND_CLIENT)
        ->startSpan();

    $parentSpan->setAttribute('latitude.type', 'unresolved_external');
    $parentSpan->setAttribute('latitude.prompt_path', $latitudePromptPath);
    $parentSpan->setAttribute('latitude.project_id', $latitudeProjectId);

    $parentScope = $parentSpan->activate();

    try {
        // 3. Fetch prompt from Latitude HTTP API
        $latitude = new LatitudeClient($latitudeApiKey);
        $prompt = $latitude->getPrompt(
            $latitudeProjectId,
            $latitudeVersionUuid,
            $latitudePromptPath,
        );

        $model = $prompt['config']['model'] ?? 'gpt-4.1';
        $messages = PromptParser::parse($prompt['content'], [
            'user_input' => $input,
        ]);

        // 4. Start CHILD span for the OpenAI completion.
        //    Attributes follow the OpenTelemetry GenAI semantic
        //    conventions that Latitude's trace parser expects.
        $completionSpan = $tracer->spanBuilder("chat $model")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $completionSpan->setAttribute('latitude.type', 'completion');
        $completionSpan->setAttribute('gen_ai.operation.name', 'completion');
        $completionSpan->setAttribute('gen_ai.system', 'openai');
        $completionSpan->setAttribute('gen_ai.request.model', $model);

        foreach ($messages as $i => $msg) {
            $completionSpan->setAttribute("gen_ai.prompt.$i.role", $msg['role']);
            $completionSpan->setAttribute("gen_ai.prompt.$i.content", $msg['content']);
        }
        $completionSpan->setAttribute('gen_ai.request.messages', json_encode($messages));
        $completionSpan->setAttribute('gen_ai.request.configuration', json_encode([
            'model' => $model,
        ]));

        $completionScope = $completionSpan->activate();

        // 5. Call OpenAI with streaming enabled
        $openai = \OpenAI::client($openaiApiKey);
        $stream = $openai->chat()->createStreamed([
            'model' => $model,
            'messages' => $messages,
            'stream_options' => ['include_usage' => true],
        ]);

        // 6. Stream response back to the frontend as text/plain.
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        header('Access-Control-Allow-Origin: http://localhost:5173');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Methods: POST, OPTIONS');

        while (ob_get_level()) {
            ob_end_flush();
        }

        $fullContent = '';
        $finishReason = null;
        $promptTokens = null;
        $outputTokens = null;
        $responseModel = null;

        foreach ($stream as $chunk) {
            $delta = $chunk->choices[0]->delta->content ?? null;
            if ($delta !== null) {
                $fullContent .= $delta;
                echo $delta;
                flush();
            }

            $finishReason = $chunk->choices[0]->finishReason ?? $finishReason;
            $responseModel = $chunk->model ?? $responseModel;

            if (isset($chunk->usage)) {
                $promptTokens = $chunk->usage->promptTokens ?? $promptTokens;
                $outputTokens = $chunk->usage->completionTokens ?? $outputTokens;
            }
        }

        // 7. Enrich and close the completion span
        $outputMessages = [['role' => 'assistant', 'content' => $fullContent]];
        $completionSpan->setAttribute('gen_ai.completion.0.role', 'assistant');
        $completionSpan->setAttribute('gen_ai.completion.0.content', $fullContent);
        $completionSpan->setAttribute('gen_ai.response.messages', json_encode($outputMessages));

        if ($responseModel !== null) {
            $completionSpan->setAttribute('gen_ai.response.model', $responseModel);
        }
        if ($finishReason !== null) {
            $completionSpan->setAttribute('gen_ai.response.finish_reasons', [$finishReason]);
        }
        if ($promptTokens !== null) {
            $completionSpan->setAttribute('gen_ai.usage.input_tokens', $promptTokens);
            $completionSpan->setAttribute('gen_ai.usage.prompt_tokens', $promptTokens);
        }
        if ($outputTokens !== null) {
            $completionSpan->setAttribute('gen_ai.usage.output_tokens', $outputTokens);
            $completionSpan->setAttribute('gen_ai.usage.completion_tokens', $outputTokens);
        }

        $completionSpan->setStatus(StatusCode::STATUS_OK);
        $completionScope->detach();
        $completionSpan->end();

        $parentSpan->setStatus(StatusCode::STATUS_OK);
    } catch (\Throwable $e) {
        $parentSpan->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        $parentSpan->recordException($e);
        throw $e;
    } finally {
        $parentScope->detach();
        $parentSpan->end();
        $telemetry->shutdown();
    }

    exit(0);
});

$app->run();
