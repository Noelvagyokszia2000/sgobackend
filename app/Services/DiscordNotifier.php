<?php

namespace App\Services;

use App\Models\News;
use App\Models\Robbery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DiscordNotifier
{
    public function sendNews(News $news): void
    {
        $webhookUrl = config('services.discord.news_webhook_url')
            ?: config('services.discord.webhook_url');

        if (!$webhookUrl) {
            return;
        }

        $authorName = $news->author?->IgName
            ?: $news->author?->username
            ?: 'Ismeretlen';

        $embed = [
            'title' => 'Új hír',
            'description' => Str::limit($news->text, 3900),
            'color' => 12653087,
            'fields' => [
                [
                    'name' => 'Kiírta',
                    'value' => $authorName,
                    'inline' => true,
                ],
                [
                    'name' => 'Időpont',
                    'value' => (string) $news->published_at,
                    'inline' => true,
                ],
            ],
        ];

        if ($news->image) {
            $embed['image'] = ['url' => $news->image];
        }

        $this->send($webhookUrl, [
            'embeds' => [$embed],
        ], 'news');
    }

    public function sendRobbery(Robbery $robbery): void
    {
        $webhookUrl = config('services.discord.robbery_webhook_url')
            ?: config('services.discord.webhook_url');

        if (!$webhookUrl) {
            return;
        }

        $authorName = $robbery->author?->IgName
            ?: $robbery->author?->username
            ?: 'Ismeretlen';

        $this->send($webhookUrl, [
            'content' => '@everyone Új rablás lett regisztrálva!',
            'allowed_mentions' => [
                'parse' => ['everyone'],
            ],
            'embeds' => [
                [
                    'title' => $robbery->name,
                    'description' => "{$authorName} regisztrált egy új {$robbery->type} rablást.",
                    'color' => 12653087,
                    'fields' => [
                        [
                            'name' => 'Típus',
                            'value' => $robbery->type,
                            'inline' => true,
                        ],
                        [
                            'name' => 'Regisztrálta',
                            'value' => $authorName,
                            'inline' => true,
                        ],
                    ],
                ],
            ],
        ], 'robbery');
    }

    private function send(string $webhookUrl, array $payload, string $context): void
    {
        try {
            $response = Http::timeout(8)->post($webhookUrl, $payload);

            if ($response->failed()) {
                Log::warning('Discord webhook failed', [
                    'context' => $context,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Discord webhook exception', [
                'context' => $context,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
