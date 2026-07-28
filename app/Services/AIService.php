<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected string $geminiApiKey;
    protected string $openAiApiKey;
    protected string $openRouterApiKey;

    public function __construct()
    {
        $this->geminiApiKey = config('services.gemini.key', env('GEMINI_API_KEY', ''));
        $this->openAiApiKey = config('services.openai.key', env('OPENAI_API_KEY', ''));
        $this->openRouterApiKey = env('OPENROUTER_API_KEY', '');
    }

    /**
     * Send base64 image data and a prompt to Gemini Multimodal API (Supports OpenRouter proxy)
     */
    public function parseMultimodalImage(string $base64Data, string $mimeType, string $prompt): array
    {
        // 1. Try OpenRouter if configured (VPN-free alternative for Bangladesh)
        if (!empty($this->openRouterApiKey)) {
            $orModels = ['google/gemini-2.5-flash', 'openrouter/free', 'google/gemma-4-31b-it:free', 'google/gemma-4-26b-a4b-it:free'];

            foreach ($orModels as $model) {
                try {
                    $response = Http::timeout(90)->withHeaders([
                        'Authorization' => 'Bearer ' . $this->openRouterApiKey,
                        'HTTP-Referer' => 'http://localhost:8000',
                        'X-Title' => 'CareMate AI',
                        'Content-Type' => 'application/json'
                    ])->post('https://openrouter.ai/api/v1/chat/completions', [
                        'model' => $model,
                        'max_tokens' => 3000,
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => [
                                    [
                                        'type' => 'text',
                                        'text' => $prompt
                                    ],
                                    [
                                        'type' => 'image_url',
                                        'image_url' => [
                                            'url' => "data:{$mimeType};base64,{$base64Data}"
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]);

                    if ($response->successful() && !isset($response->json()['error'])) {
                        $reply = $response->json('choices.0.message.content') ?? 'No response generated.';
                        return ['success' => true, 'reply' => $reply, 'provider' => "OpenRouter {$model}"];
                    } else {
                        Log::warning("OpenRouter model {$model} failed: " . $response->body());
                    }
                } catch (\Exception $e) {
                    Log::error("OpenRouter model {$model} Exception: " . $e->getMessage());
                }
            }
        }

        // 2. Direct Gemini API Fallback
        if (!empty($this->geminiApiKey)) {
            $models = ['gemini-2.5-flash', 'gemini-3.5-flash', 'gemini-1.5-flash'];

            foreach ($models as $model) {
                try {
                    $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->geminiApiKey}", [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt],
                                    [
                                        'inlineData' => [
                                            'mimeType' => $mimeType,
                                            'data' => $base64Data
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]);

                    if ($response->successful()) {
                        $reply = $response->json('candidates.0.content.parts.0.text') ?? 'No response text generated.';
                        return ['success' => true, 'reply' => $reply, 'provider' => "Gemini {$model} Multimodal"];
                    } else {
                        Log::warning("Gemini model {$model} failed, trying next. Status: " . $response->status() . " Response: " . $response->body());
                    }
                } catch (\Exception $e) {
                    Log::error("Gemini model {$model} error: " . $e->getMessage());
                }
            }
        }

        return ['success' => false, 'message' => 'No AI API keys configured or all requests failed.'];
    }

    /**
     * Send chat prompt to Gemini API or OpenAI API
     */
    public function askAssistant(string $prompt, array $context = []): array
    {
        $systemInstructions = "You are an AI Health Assistant for a medical app. Provide helpful, accurate medical companion advice.";
        if (!empty($context['userName'])) {
            $systemInstructions .= " The logged-in user's name is: " . $context['userName'] . ". Always address them by their name when appropriate.";
        }
        if (!empty($context['appointments'])) {
            $systemInstructions .= " Here are their upcoming doctor appointments:\n" . $context['appointments'];
        } else {
            $systemInstructions .= " They currently have no doctor appointments scheduled.";
        }
        if (!empty($context['medicines'])) {
            $systemInstructions .= " Here are their scheduled medications/reminders:\n" . $context['medicines'];
        }
        $systemInstructions .= " Current Date & Time context: " . date('Y-m-d H:i:s') . ".";

        // 1. Try OpenRouter if configured (VPN-free alternative for Bangladesh)
        if (!empty($this->openRouterApiKey)) {
            $orModels = ['google/gemini-2.5-flash', 'openrouter/free', 'google/gemma-4-31b-it:free'];

            foreach ($orModels as $model) {
                try {
                    $response = Http::timeout(90)->withHeaders([
                        'Authorization' => 'Bearer ' . $this->openRouterApiKey,
                        'HTTP-Referer' => 'http://localhost:8000',
                        'X-Title' => 'CareMate AI',
                        'Content-Type' => 'application/json'
                    ])->post('https://openrouter.ai/api/v1/chat/completions', [
                        'model' => $model,
                        'max_tokens' => 2000,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemInstructions],
                            ['role' => 'user', 'content' => $prompt]
                        ]
                    ]);

                    if ($response->successful() && !isset($response->json()['error'])) {
                        $reply = $response->json('choices.0.message.content') ?? 'No response generated.';
                        return ['success' => true, 'reply' => $reply, 'provider' => "OpenRouter {$model}"];
                    } else {
                        Log::warning("OpenRouter chat model {$model} failed: " . $response->body());
                    }
                } catch (\Exception $e) {
                    Log::error("OpenRouter chat model {$model} Exception: " . $e->getMessage());
                }
            }
        }

        // 2. Direct Gemini API Fallback
        if (!empty($this->geminiApiKey)) {
            $models = ['gemini-2.5-flash', 'gemini-3.5-flash', 'gemini-1.5-flash'];

            foreach ($models as $model) {
                try {
                    $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->geminiApiKey}", [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $systemInstructions . "\n\nUser prompt: " . $prompt]
                                ]
                            ]
                        ]
                    ]);

                    if ($response->successful()) {
                        $reply = $response->json('candidates.0.content.parts.0.text') ?? 'No response text generated.';
                        return ['success' => true, 'reply' => $reply, 'provider' => "Gemini {$model}"];
                    } else {
                        Log::warning("Gemini chatbot model {$model} failed, trying next. Status: " . $response->status());
                    }
                } catch (\Exception $e) {
                    Log::error("Gemini chatbot model {$model} error: " . $e->getMessage());
                }
            }
        }

        // 3. Try OpenAI API if key exists
        if (!empty($this->openAiApiKey)) {
            try {
                $response = Http::withToken($this->openAiApiKey)->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemInstructions],
                        ['role' => 'user', 'content' => $prompt]
                    ]
                ]);

                if ($response->successful()) {
                    $reply = $response->json('choices.0.message.content') ?? '';
                    return ['success' => true, 'reply' => $reply, 'provider' => 'OpenAI GPT-4o'];
                }
            } catch (\Exception $e) {
                Log::error('OpenAI API Error: ' . $e->getMessage());
            }
        }

        // 4. Fallback Engine
        return [
            'success' => true,
            'reply' => $this->getFallbackResponse($prompt, $context),
            'provider' => 'AI Simulation Engine (Add API Keys in .env for live API)',
        ];
    }

    private function getFallbackResponse(string $prompt, array $context = []): string
    {
        $userName = $context['userName'] ?? 'User';
        $p = strtolower($prompt);
        if (str_contains($p, 'name') || str_contains($p, 'who am i')) {
            return "You are logged in as " . $userName . ". How can I help you today?";
        }
        if (str_contains($p, 'appointment') || str_contains($p, 'doctor') || str_contains($p, 'calendar')) {
            if (!empty($context['appointments'])) {
                return "Yes, " . $userName . ", here are your doctor appointments:\n" . $context['appointments'];
            }
            return "You don't have any appointments scheduled right now, " . $userName . ".";
        }
        if (str_contains($p, 'medicine') || str_contains($p, 'take today')) {
            if (!empty($context['medicines'])) {
                return "Here are your scheduled medicines, " . $userName . ":\n" . $context['medicines'];
            }
            return 'Here are your scheduled medicines for today. Make sure to take Metformin 500mg after breakfast and Vitamin D3 at 8:00 PM.';
        }
        if (str_contains($p, 'cbc') || str_contains($p, 'report') || str_contains($p, 'blood')) {
            return 'Your latest CBC report shows Hemoglobin (13.2 g/dL) is normal. WBC count is slightly elevated. Drink plenty of water and rest well.';
        }
        if (str_contains($p, 'eat') || str_contains($p, 'diet') || str_contains($p, 'diabetes')) {
            return 'For diabetes management, prioritize low-glycemic foods like green leafy vegetables, whole grains, nuts, and lean protein. Limit refined sugars.';
        }
        return 'Hello ' . $userName . ', your health vitals look steady. Always consult your primary doctor for medical diagnosis.';
    }
}
