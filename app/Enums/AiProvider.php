<?php

namespace App\Enums;

/**
 * The AI providers a visitor may use with their own API key.
 *
 * Values match the corresponding Laravel\Ai\Enums\Lab driver names.
 */
enum AiProvider: string
{
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case Groq = 'groq';
    case XAI = 'xai';
    case DeepSeek = 'deepseek';
    case Mistral = 'mistral';
    case OpenRouter = 'openrouter';
}
