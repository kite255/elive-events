<?php

namespace Tests\Unit\Tickets;

use App\Services\Tickets\TicketTemplateDefinitionValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketTemplateDefinitionValidatorTest extends TestCase
{
    public function test_accepts_empty_version_one_definition(): void
    {
        $validator = new TicketTemplateDefinitionValidator();

        $definition = [
            'version' => 1,
            'elements' => [],
        ];

        $result = $validator->validate(
            $definition,
            1080,
            1350
        );

        $this->assertSame(
            $definition,
            $result
        );
    }

    public function test_accepts_supported_text_and_qr_elements(): void
    {
        $validator = new TicketTemplateDefinitionValidator();

        $definition = [
            'version' => 1,
            'elements' => [
                [
                    'id' => 'holder_name_01',
                    'type' => 'text',
                    'binding' => 'holder_name',
                    'x' => 100,
                    'y' => 600,
                    'width' => 600,
                    'height' => 80,
                    'rotation' => 0,
                    'zIndex' => 10,
                    'style' => [
                        'fontFamily' => 'Arial',
                        'fontSize' => 54,
                        'fontWeight' => 700,
                        'color' => '#161943',
                        'textAlign' => 'left',
                        'opacity' => 1,
                    ],
                ],
                [
                    'id' => 'ticket_qr_01',
                    'type' => 'qr',
                    'binding' => 'ticket_qr',
                    'x' => 760,
                    'y' => 950,
                    'width' => 250,
                    'height' => 250,
                    'rotation' => 0,
                    'zIndex' => 20,
                ],
            ],
        ];

        $result = $validator->validate(
            $definition,
            1080,
            1350
        );

        $this->assertSame(
            $definition,
            $result
        );
    }

    public function test_rejects_unsupported_element_type(): void
    {
        $validator = new TicketTemplateDefinitionValidator();

        $this->expectException(
            ValidationException::class
        );

        $validator->validate([
            'version' => 1,
            'elements' => [
                [
                    'id' => 'bad_01',
                    'type' => 'iframe',
                    'x' => 0,
                    'y' => 0,
                    'width' => 100,
                    'height' => 100,
                ],
            ],
        ], 1080, 1350);
    }

    public function test_rejects_duplicate_element_ids(): void
    {
        $validator = new TicketTemplateDefinitionValidator();

        $this->expectException(
            ValidationException::class
        );

        $validator->validate([
            'version' => 1,
            'elements' => [
                [
                    'id' => 'same_id',
                    'type' => 'text',
                    'binding' => 'holder_name',
                    'x' => 0,
                    'y' => 0,
                    'width' => 100,
                    'height' => 50,
                ],
                [
                    'id' => 'same_id',
                    'type' => 'qr',
                    'binding' => 'ticket_qr',
                    'x' => 100,
                    'y' => 100,
                    'width' => 200,
                    'height' => 200,
                ],
            ],
        ], 1080, 1350);
    }

    public function test_rejects_executable_or_html_fields(): void
    {
        $validator = new TicketTemplateDefinitionValidator();

        $this->expectException(
            ValidationException::class
        );

        $validator->validate([
            'version' => 1,
            'elements' => [
                [
                    'id' => 'unsafe_01',
                    'type' => 'text',
                    'binding' => 'holder_name',
                    'x' => 0,
                    'y' => 0,
                    'width' => 200,
                    'height' => 50,
                    'html' => '<script>alert(1)</script>',
                ],
            ],
        ], 1080, 1350);
    }

    public function test_rejects_unsupported_font_family(): void
    {
        $validator = new TicketTemplateDefinitionValidator();

        $this->expectException(
            ValidationException::class
        );

        $validator->validate([
            'version' => 1,
            'elements' => [
                [
                    'id' => 'text_01',
                    'type' => 'text',
                    'binding' => 'holder_name',
                    'x' => 20,
                    'y' => 20,
                    'width' => 400,
                    'height' => 80,
                    'style' => [
                        'fontFamily' => 'Dangerous Remote Font',
                    ],
                ],
            ],
        ], 1080, 1350);
    }

    public function test_rejects_invalid_color(): void
    {
        $validator = new TicketTemplateDefinitionValidator();

        $this->expectException(
            ValidationException::class
        );

        $validator->validate([
            'version' => 1,
            'elements' => [
                [
                    'id' => 'text_01',
                    'type' => 'text',
                    'binding' => 'holder_name',
                    'x' => 20,
                    'y' => 20,
                    'width' => 400,
                    'height' => 80,
                    'style' => [
                        'fontFamily' => 'Arial',
                        'color' => 'javascript:alert(1)',
                    ],
                ],
            ],
        ], 1080, 1350);
    }

    public function test_rejects_extreme_coordinates(): void
    {
        $validator = new TicketTemplateDefinitionValidator();

        $this->expectException(
            ValidationException::class
        );

        $validator->validate([
            'version' => 1,
            'elements' => [
                [
                    'id' => 'text_01',
                    'type' => 'text',
                    'binding' => 'holder_name',
                    'x' => 5000,
                    'y' => 20,
                    'width' => 400,
                    'height' => 80,
                ],
            ],
        ], 1080, 1350);
    }
}
