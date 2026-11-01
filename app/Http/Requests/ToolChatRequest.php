<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Laravel\Ai\Enums\Lab;

class ToolChatRequest extends FormRequest
{
    /**
     * The providers a visitor may use with their own API key.
     *
     * @var list<Lab>
     */
    public const SUPPORTED_PROVIDERS = [
        Lab::OpenAI,
        Lab::Anthropic,
        Lab::Gemini,
        Lab::Groq,
        Lab::xAI,
        Lab::DeepSeek,
        Lab::Mistral,
        Lab::OpenRouter,
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(Lab::class)->only(self::SUPPORTED_PROVIDERS)],
            'api_key' => ['required', 'string', 'max:500'],
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['present', 'array', 'max:20'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:10000'],
        ];
    }
}
