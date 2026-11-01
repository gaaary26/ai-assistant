<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_it_returns_a_successful_response(): void
    {
        $this->get(route('home'))->assertOk();
    }
}
