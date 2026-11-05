<?php

namespace Tests\Unit\Enums;

use App\Enums\AiProvider;
use Laravel\Ai\Enums\Lab;
use PHPUnit\Framework\TestCase;

class AiProviderTest extends TestCase
{
    public function test_every_supported_provider_is_a_valid_ai_driver(): void
    {
        foreach (AiProvider::cases() as $provider) {
            $this->assertNotNull(Lab::tryFrom($provider->value), "{$provider->value} is not a Lab driver.");
        }
    }
}
