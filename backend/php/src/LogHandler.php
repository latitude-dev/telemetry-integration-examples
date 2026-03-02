<?php

namespace App;

class LogHandler
{
    public static function handle(
        string $input,
        string $latitudeApiKey,
        int $latitudeProjectId,
        string $latitudePromptPath,
        string $latitudeVersionUuid,
        string $openaiApiKey,
    ): void {
        $model = 'gpt-4.1';
        // Adding the message as a string in the text instead of pulling it from Latitude.
        $messages = [
            ['role' => 'system', 'content' => 'You are an expert writer, and I need your help writing wikipedia articles.'],
            ['role' => 'user', 'content' => $input],
        ];

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

        foreach ($stream as $chunk) {
            $delta = $chunk->choices[0]->delta->content ?? null;
            if ($delta !== null) {
                $fullContent .= $delta;
                echo $delta;
                flush();
            }
        }

        // Convert messages to PromptL format for the Latitude Log API
        $promptLMessages = array_map(function (array $msg): array {
            return [
                'role' => $msg['role'],
                'content' => [['type' => 'text', 'text' => $msg['content']]],
            ];
        }, $messages);

        $latitude = new LatitudeClient($latitudeApiKey);
        $latitude->createLog(
            $latitudeProjectId,
            $latitudeVersionUuid,
            $latitudePromptPath,
            $promptLMessages,
            $fullContent,
        );
    }
}
