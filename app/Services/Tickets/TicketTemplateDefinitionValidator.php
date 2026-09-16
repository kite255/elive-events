<?php

namespace App\Services\Tickets;

use Illuminate\Validation\ValidationException;

final class TicketTemplateDefinitionValidator
{
    private const SUPPORTED_ELEMENT_TYPES = [
        'text',
        'qr',
        'image',
        'logo',
        'sponsor_logo',
        'shape',
        'line',
    ];

    private const SUPPORTED_BINDINGS = [
        'holder_name',
        'ticket_number',
        'ticket_type',
        'event_name',
        'event_date',
        'event_time',
        'venue',
        'order_number',
        'ticket_qr',
    ];

    private const SUPPORTED_FONTS = [
        'Creato Display',
        'Arial',
        'Helvetica',
        'Georgia',
        'Times New Roman',
    ];

    private const SUPPORTED_TEXT_ALIGNMENTS = [
        'left',
        'center',
        'right',
    ];

    private const FORBIDDEN_KEYS = [
        'html',
        'script',
        'javascript',
        'php',
        'blade',
    ];

    public function validate(
        array $definition,
        int $canvasWidth,
        int $canvasHeight
    ): array {
        if (
            $canvasWidth < 300
            || $canvasWidth > 4000
            || $canvasHeight < 300
            || $canvasHeight > 4000
        ) {
            $this->fail(
                'canvas',
                'Ticket canvas dimensions must be between 300 and 4000 pixels.'
            );
        }

        $this->rejectForbiddenKeys(
            $definition
        );

        if (
            ! array_key_exists('version', $definition)
            || $definition['version'] !== 1
        ) {
            $this->fail(
                'version',
                'Ticket template definition version must be 1.'
            );
        }

        if (
            ! array_key_exists('elements', $definition)
            || ! is_array($definition['elements'])
        ) {
            $this->fail(
                'elements',
                'Ticket template elements must be an array.'
            );
        }

        $usedIds = [];

        foreach (
            $definition['elements']
            as $index => $element
        ) {
            if (! is_array($element)) {
                $this->fail(
                    "elements.{$index}",
                    'Each ticket template element must be an object.'
                );
            }

            $this->validateElement(
                $element,
                $index,
                $canvasWidth,
                $canvasHeight,
                $usedIds
            );
        }

        return $definition;
    }

    private function validateElement(
        array $element,
        int|string $index,
        int $canvasWidth,
        int $canvasHeight,
        array &$usedIds
    ): void {
        $path = "elements.{$index}";

        $id = $element['id'] ?? null;

        if (
            ! is_string($id)
            || trim($id) === ''
        ) {
            $this->fail(
                "{$path}.id",
                'Each ticket template element must have a valid ID.'
            );
        }

        if (in_array($id, $usedIds, true)) {
            $this->fail(
                "{$path}.id",
                'Ticket template element IDs must be unique.'
            );
        }

        $usedIds[] = $id;

        $type = $element['type'] ?? null;

        if (
            ! is_string($type)
            || ! in_array(
                $type,
                self::SUPPORTED_ELEMENT_TYPES,
                true
            )
        ) {
            $this->fail(
                "{$path}.type",
                'Unsupported ticket template element type.'
            );
        }

        $this->validateCoordinate(
            $element,
            'x',
            $path,
            -$canvasWidth,
            $canvasWidth * 2
        );

        $this->validateCoordinate(
            $element,
            'y',
            $path,
            -$canvasHeight,
            $canvasHeight * 2
        );

        $this->validateDimension(
            $element,
            'width',
            $path,
            $canvasWidth * 2
        );

        $this->validateDimension(
            $element,
            'height',
            $path,
            $canvasHeight * 2
        );

        if (array_key_exists('rotation', $element)) {
            if (
                ! is_numeric($element['rotation'])
                || $element['rotation'] < -360
                || $element['rotation'] > 360
            ) {
                $this->fail(
                    "{$path}.rotation",
                    'Element rotation must be between -360 and 360 degrees.'
                );
            }
        }

        if (array_key_exists('zIndex', $element)) {
            if (
                ! is_int($element['zIndex'])
                && ! ctype_digit(
                    (string) $element['zIndex']
                )
            ) {
                $this->fail(
                    "{$path}.zIndex",
                    'Element zIndex must be an integer.'
                );
            }
        }

        $this->validateBinding(
            $element,
            $type,
            $path
        );

        if (array_key_exists('style', $element)) {
            if (! is_array($element['style'])) {
                $this->fail(
                    "{$path}.style",
                    'Element style must be an object.'
                );
            }

            $this->validateStyle(
                $element['style'],
                "{$path}.style"
            );
        }
    }

    private function validateBinding(
        array $element,
        string $type,
        string $path
    ): void {
        if ($type === 'qr') {
            if (
                ($element['binding'] ?? null)
                !== 'ticket_qr'
            ) {
                $this->fail(
                    "{$path}.binding",
                    'QR elements must use the ticket_qr binding.'
                );
            }

            return;
        }

        if (
            ! array_key_exists(
                'binding',
                $element
            )
        ) {
            return;
        }

        $binding = $element['binding'];

        if (
            ! is_string($binding)
            || ! in_array(
                $binding,
                self::SUPPORTED_BINDINGS,
                true
            )
        ) {
            $this->fail(
                "{$path}.binding",
                'Unsupported ticket template binding.'
            );
        }

        if (
            $binding === 'ticket_qr'
            && $type !== 'qr'
        ) {
            $this->fail(
                "{$path}.binding",
                'The ticket_qr binding may only be used by QR elements.'
            );
        }
    }

    private function validateStyle(
        array $style,
        string $path
    ): void {
        if (
            array_key_exists(
                'fontFamily',
                $style
            )
        ) {
            if (
                ! is_string(
                    $style['fontFamily']
                )
                || ! in_array(
                    $style['fontFamily'],
                    self::SUPPORTED_FONTS,
                    true
                )
            ) {
                $this->fail(
                    "{$path}.fontFamily",
                    'Unsupported ticket template font.'
                );
            }
        }

        if (
            array_key_exists(
                'fontSize',
                $style
            )
        ) {
            if (
                ! is_numeric($style['fontSize'])
                || $style['fontSize'] <= 0
                || $style['fontSize'] > 500
            ) {
                $this->fail(
                    "{$path}.fontSize",
                    'Font size must be between 1 and 500.'
                );
            }
        }

        if (
            array_key_exists(
                'fontWeight',
                $style
            )
        ) {
            $fontWeight =
                (int) $style['fontWeight'];

            if (
                $fontWeight < 100
                || $fontWeight > 900
                || $fontWeight % 100 !== 0
            ) {
                $this->fail(
                    "{$path}.fontWeight",
                    'Font weight must be between 100 and 900.'
                );
            }
        }

        if (
            array_key_exists(
                'color',
                $style
            )
        ) {
            if (
                ! is_string($style['color'])
                || ! $this->isValidColor(
                    $style['color']
                )
            ) {
                $this->fail(
                    "{$path}.color",
                    'Invalid ticket template color.'
                );
            }
        }

        if (
            array_key_exists(
                'backgroundColor',
                $style
            )
        ) {
            if (
                ! is_string(
                    $style['backgroundColor']
                )
                || ! $this->isValidColor(
                    $style['backgroundColor']
                )
            ) {
                $this->fail(
                    "{$path}.backgroundColor",
                    'Invalid ticket template background color.'
                );
            }
        }

        if (
            array_key_exists(
                'textAlign',
                $style
            )
        ) {
            if (
                ! is_string(
                    $style['textAlign']
                )
                || ! in_array(
                    $style['textAlign'],
                    self::SUPPORTED_TEXT_ALIGNMENTS,
                    true
                )
            ) {
                $this->fail(
                    "{$path}.textAlign",
                    'Unsupported text alignment.'
                );
            }
        }

        if (
            array_key_exists(
                'opacity',
                $style
            )
        ) {
            if (
                ! is_numeric($style['opacity'])
                || $style['opacity'] < 0
                || $style['opacity'] > 1
            ) {
                $this->fail(
                    "{$path}.opacity",
                    'Opacity must be between 0 and 1.'
                );
            }
        }
    }

    private function validateCoordinate(
        array $element,
        string $key,
        string $path,
        int $minimum,
        int $maximum
    ): void {
        if (
            ! array_key_exists(
                $key,
                $element
            )
            || ! is_numeric(
                $element[$key]
            )
        ) {
            $this->fail(
                "{$path}.{$key}",
                "Element {$key} coordinate is required."
            );
        }

        if (
            $element[$key] < $minimum
            || $element[$key] > $maximum
        ) {
            $this->fail(
                "{$path}.{$key}",
                "Element {$key} coordinate is outside the allowed canvas range."
            );
        }
    }

    private function validateDimension(
        array $element,
        string $key,
        string $path,
        int $maximum
    ): void {
        if (
            ! array_key_exists(
                $key,
                $element
            )
            || ! is_numeric(
                $element[$key]
            )
        ) {
            $this->fail(
                "{$path}.{$key}",
                "Element {$key} is required."
            );
        }

        if (
            $element[$key] <= 0
            || $element[$key] > $maximum
        ) {
            $this->fail(
                "{$path}.{$key}",
                "Element {$key} is outside the allowed range."
            );
        }
    }

    private function rejectForbiddenKeys(
        array $value,
        string $path = 'definition'
    ): void {
        foreach (
            $value
            as $key => $child
        ) {
            $normalizedKey =
                strtolower(
                    (string) $key
                );

            if (
                in_array(
                    $normalizedKey,
                    self::FORBIDDEN_KEYS,
                    true
                )
            ) {
                $this->fail(
                    "{$path}.{$key}",
                    'Executable or raw HTML fields are not allowed in ticket templates.'
                );
            }

            if (is_array($child)) {
                $this->rejectForbiddenKeys(
                    $child,
                    "{$path}.{$key}"
                );
            }
        }
    }

    private function isValidColor(
        string $color
    ): bool {
        return preg_match(
            '/^#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/',
            $color
        ) === 1;
    }

    private function fail(
        string $key,
        string $message
    ): never {
        throw ValidationException::withMessages([
            $key => [$message],
        ]);
    }
}
