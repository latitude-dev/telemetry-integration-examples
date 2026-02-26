<?php

namespace App;

class PromptParser
{
    /**
     * Turn a PromptL document into an OpenAI messages array.
     *
     * @param string $content    Raw PromptL content from the Latitude API
     * @param array  $parameters Key-value pairs to substitute (e.g. ['user_input' => 'Quantum Physics'])
     * @return array OpenAI messages array
     */
    public static function parse(string $content, array $parameters = []): array
    {
        $body = self::stripFrontmatter($content);

        foreach ($parameters as $key => $value) {
            $body = str_replace('{{' . $key . '}}', (string) $value, $body);
        }

        return self::extractMessages($body);
    }

    private static function stripFrontmatter(string $content): string
    {
        if (preg_match('/^---\s*\n.*?\n---\s*\n(.*)/s', $content, $m)) {
            return $m[1];
        }
        return $content;
    }

    /**
     * Extract <role>...</role> blocks into an ordered messages array.
     * Supports system, user, and assistant roles.
     */
    private static function extractMessages(string $body): array
    {
        $messages = [];
        $roles = ['system', 'user', 'assistant'];

        $pattern = '/<(' . implode('|', $roles) . ')>(.*?)<\/\1>/si';

        if (preg_match_all($pattern, $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $messages[] = [
                    'role' => strtolower($match[1]),
                    'content' => trim($match[2]),
                ];
            }
        }

        return $messages;
    }
}
