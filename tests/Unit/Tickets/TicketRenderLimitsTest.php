<?php

namespace Tests\Unit\Tickets;

use App\Services\Tickets\TicketTemplateDefinitionValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketRenderLimitsTest extends TestCase
{
    private function definition(int $count, string $type = 'shape'): array
    {
        $elements = [];
        for ($i = 0; $i < $count; $i++) {
            $elements[] = [
                'id' => 'element_'.$i, 'type' => $type,
                'x' => 0, 'y' => 0, 'width' => 100, 'height' => 100,
            ];
        }

        return ['version' => 1, 'elements' => $elements];
    }

    public function test_definition_accepts_element_limit(): void
    {
        $definition = $this->definition(100);
        $this->assertSame($definition, app(TicketTemplateDefinitionValidator::class)->validate($definition, 1080, 1350));
    }

    public function test_definition_rejects_excess_elements(): void
    {
        $this->expectException(ValidationException::class);
        app(TicketTemplateDefinitionValidator::class)->validate($this->definition(101), 1080, 1350);
    }

    public function test_definition_accepts_image_element_limit(): void
    {
        $definition = $this->definition(12, 'image');
        $this->assertSame($definition, app(TicketTemplateDefinitionValidator::class)->validate($definition, 1080, 1350));
    }

    public function test_definition_rejects_excess_image_elements(): void
    {
        $this->expectException(ValidationException::class);
        app(TicketTemplateDefinitionValidator::class)->validate($this->definition(13, 'image'), 1080, 1350);
    }

    public function test_definition_rejects_excess_bytes(): void
    {
        $definition = $this->definition(1);
        $definition['padding'] = str_repeat('a', 262145);
        $this->expectException(ValidationException::class);
        app(TicketTemplateDefinitionValidator::class)->validate($definition, 1080, 1350);
    }

    public function test_definition_rejects_excess_nesting(): void
    {
        $definition = $this->definition(1);
        $nested = [];
        for ($i = 0; $i < 35; $i++) {
            $nested = ['nested' => $nested];
        }
        $definition['metadata'] = $nested;
        $this->expectException(ValidationException::class);
        app(TicketTemplateDefinitionValidator::class)->validate($definition, 1080, 1350);
    }
}
