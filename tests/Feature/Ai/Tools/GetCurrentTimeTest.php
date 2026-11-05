<?php

namespace Tests\Feature\Ai\Tools;

use App\Ai\Tools\GetCurrentTime;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class GetCurrentTimeTest extends TestCase
{
    public function test_it_gets_the_current_time_in_the_requested_timezone(): void
    {
        $this->travelTo('2026-10-29 04:00:00 UTC');

        $result = json_decode((string) (new GetCurrentTime)->handle(new Request(['timezone' => 'Asia/Taipei'])), true);

        $this->assertSame([
            'datetime' => '2026-10-29 12:00:00',
            'weekday' => 'Thursday',
            'timezone' => 'Asia/Taipei',
        ], $result);
    }

    public function test_it_rejects_an_invalid_timezone(): void
    {
        $this->expectException(ValidationException::class);

        (new GetCurrentTime)->handle(new Request(['timezone' => 'Mars/Olympus']));
    }
}
