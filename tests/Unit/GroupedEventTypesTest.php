<?php

namespace Tests\Unit;

use App\Services\EventPresetService;
use Tests\TestCase;

class GroupedEventTypesTest extends TestCase
{
    public function test_grouped_types_preserve_all_existing_keys_and_labels(): void
    {
        $flattened = [];

        foreach (EventPresetService::groupedEventTypes() as $types) {
            foreach ($types as $key => $label) {
                $this->assertArrayNotHasKey($key, $flattened);
                $flattened[$key] = $label;
            }
        }

        $expected = EventPresetService::eventTypes();
        ksort($expected);
        ksort($flattened);

        $this->assertSame($expected, $flattened);
    }
}
