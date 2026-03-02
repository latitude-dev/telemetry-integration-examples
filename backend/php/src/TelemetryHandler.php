<?php

namespace App;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

class TelemetryHandler
{
    public static function handle(
        string $input,
        string $latitudeApiKey,
        int $latitudeProjectId,
        string $latitudePromptPath,
        string $latitudeVersionUuid,
        string $openaiApiKey,
    ): void {
        $telemetry = new TelemetrySetup($latitudeApiKey);
        $tracer = $telemetry->getTracer();

        $parentSpan = $tracer->spanBuilder("capture-$latitudePromptPath")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $parentSpan->setAttribute('latitude.type', 'unresolved_external');
        $parentSpan->setAttribute('latitude.prompt_path', $latitudePromptPath);
        $parentSpan->setAttribute('latitude.project_id', $latitudeProjectId);

        $parentScope = $parentSpan->activate();

        try {
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

            $openai = \OpenAI::client($openaiApiKey);
            $stream = $openai->chat()->createStreamed([
                'model' => $model,
                'messages' => $messages,
                'stream_options' => ['include_usage' => true],
            ]);

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
    }
}
