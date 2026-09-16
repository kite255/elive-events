<?php

namespace Tests\Feature\Tickets;

use App\Livewire\Tickets\TicketDesigner;
use App\Models\Organization;
use App\Models\OrganizationTicketTemplate;
use App\Models\TicketTemplatePage;
use App\Services\Tickets\TicketDesignerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TicketDesignerBackgroundTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_loads_existing_background_for_active_page(): void
    {
        [$type, $template] =
            $this->makeTemplate();

        $page =
            TicketTemplatePage::query()->create([
                'organization_ticket_template_id' =>
                    $template->id,
                'event_ticket_template_id' =>
                    null,
                'page_number' =>
                    1,
                'name' =>
                    'Page 1',
                'background_image_path' =>
                    'ticket-template-backgrounds/existing.jpg',
                'definition' => [
                    'version' => 1,
                    'elements' => [],
                ],
            ]);

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $type,
                'templateId' => $template->id,
            ]
        )
            ->assertSet(
                'activePageId',
                $page->id
            )
            ->assertSet(
                'backgroundImagePath',
                'ticket-template-backgrounds/existing.jpg'
            );
    }

    public function test_background_image_can_be_uploaded_to_active_page(): void
    {
        Storage::fake('public');

        [$type, $template] =
            $this->makeTemplate();

        $file =
            UploadedFile::fake()->image(
                'ticket-background.jpg',
                1080,
                1350
            );

        $component =
            Livewire::test(
                TicketDesigner::class,
                [
                    'templateType' => $type,
                    'templateId' => $template->id,
                ]
            )
                ->set(
                    'backgroundUpload',
                    $file
                )
                ->call(
                    'uploadBackground'
                )
                ->assertHasNoErrors();

        $page =
            TicketTemplatePage::query()
                ->where(
                    'organization_ticket_template_id',
                    $template->id
                )
                ->firstOrFail();

        $this->assertNotNull(
            $page->background_image_path
        );

        Storage::disk('public')->assertExists(
            $page->background_image_path
        );

        $component->assertSet(
            'backgroundImagePath',
            $page->background_image_path
        );
    }

    public function test_upload_rejects_unsupported_background_file(): void
    {
        Storage::fake('public');

        [$type, $template] =
            $this->makeTemplate();

        $file =
            UploadedFile::fake()->create(
                'ticket-background.svg',
                100,
                'image/svg+xml'
            );

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $type,
                'templateId' => $template->id,
            ]
        )
            ->set(
                'backgroundUpload',
                $file
            )
            ->call(
                'uploadBackground'
            )
            ->assertHasErrors(
                'backgroundUpload'
            );

        $page =
            TicketTemplatePage::query()
                ->where(
                    'organization_ticket_template_id',
                    $template->id
                )
                ->firstOrFail();

        $this->assertNull(
            $page->background_image_path
        );
    }

    public function test_background_image_can_be_removed_from_active_page(): void
    {
        Storage::fake('public');

        [$type, $template] =
            $this->makeTemplate();

        $storedPath =
            UploadedFile::fake()
                ->image(
                    'existing.jpg',
                    1080,
                    1350
                )
                ->store(
                    'ticket-template-backgrounds',
                    'public'
                );

        $page =
            TicketTemplatePage::query()->create([
                'organization_ticket_template_id' =>
                    $template->id,
                'event_ticket_template_id' =>
                    null,
                'page_number' =>
                    1,
                'name' =>
                    'Page 1',
                'background_image_path' =>
                    $storedPath,
                'definition' => [
                    'version' => 1,
                    'elements' => [],
                ],
            ]);

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $type,
                'templateId' => $template->id,
            ]
        )
            ->assertSet(
                'backgroundImagePath',
                $storedPath
            )
            ->call(
                'removeBackground'
            )
            ->assertSet(
                'backgroundImagePath',
                null
            );

        $page->refresh();

        $this->assertNull(
            $page->background_image_path
        );

        Storage::disk('public')->assertMissing(
            $storedPath
        );
    }

    public function test_each_ticket_page_keeps_its_own_background(): void
    {
        Storage::fake('public');

        [$type, $template] =
            $this->makeTemplate();

        $component =
            Livewire::test(
                TicketDesigner::class,
                [
                    'templateType' => $type,
                    'templateId' => $template->id,
                ]
            );

        $pageOneId =
            $component->get(
                'activePageId'
            );

        $component
            ->set(
                'backgroundUpload',
                UploadedFile::fake()->image(
                    'front.jpg',
                    1080,
                    1350
                )
            )
            ->call(
                'uploadBackground'
            );

        $pageOneBackground =
            $component->get(
                'backgroundImagePath'
            );

        $component->call(
            'addPage',
            'Back'
        );

        $pageTwoId =
            $component->get(
                'activePageId'
            );

        $component
            ->set(
                'backgroundUpload',
                UploadedFile::fake()->image(
                    'back.jpg',
                    1080,
                    1350
                )
            )
            ->call(
                'uploadBackground'
            );

        $pageTwoBackground =
            $component->get(
                'backgroundImagePath'
            );

        $this->assertNotSame(
            $pageOneId,
            $pageTwoId
        );

        $this->assertNotSame(
            $pageOneBackground,
            $pageTwoBackground
        );

        $component
            ->call(
                'switchPage',
                $pageOneId
            )
            ->assertSet(
                'backgroundImagePath',
                $pageOneBackground
            );

        $component
            ->call(
                'switchPage',
                $pageTwoId
            )
            ->assertSet(
                'backgroundImagePath',
                $pageTwoBackground
            );
    }

    public function test_designer_ui_contains_background_upload_controls(): void
    {
        [$type, $template] =
            $this->makeTemplate();

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $type,
                'templateId' => $template->id,
            ]
        )
            ->assertSeeHtml(
                'data-testid="ticket-designer-background-controls"'
            )
            ->assertSeeHtml(
                'wire:model="backgroundUpload"'
            )
            ->assertSeeHtml(
                'wire:click="uploadBackground"'
            );
    }

    public function test_background_is_rendered_inside_canvas_when_present(): void
    {
        [$type, $template] =
            $this->makeTemplate();

        TicketTemplatePage::query()->create([
            'organization_ticket_template_id' =>
                $template->id,
            'event_ticket_template_id' =>
                null,
            'page_number' =>
                1,
            'name' =>
                'Page 1',
            'background_image_path' =>
                'ticket-template-backgrounds/ticket-front.jpg',
            'definition' => [
                'version' => 1,
                'elements' => [],
            ],
        ]);

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $type,
                'templateId' => $template->id,
            ]
        )
            ->assertSeeHtml(
                'data-testid="ticket-designer-background-image"'
            )
            ->assertSee(
                'ticket-template-backgrounds/ticket-front.jpg',
                false
            );
    }

    public function test_background_upload_button_is_disabled_until_file_is_selected(): void
    {
        [$type, $template] =
            $this->makeTemplate();

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $type,
                'templateId' => $template->id,
            ]
        )
            ->assertSeeHtml(
                'data-testid="ticket-background-upload-button"'
            )
            ->assertSeeHtml(
                'data-upload-ready="false"'
            );
    }

    public function test_selected_background_file_shows_filename_and_temporary_preview(): void
    {
        Storage::fake('public');

        [$type, $template] =
            $this->makeTemplate();

        $file =
            UploadedFile::fake()->image(
                'my-ticket-design.jpg',
                1080,
                1350
            );

        Livewire::test(
            TicketDesigner::class,
            [
                'templateType' => $type,
                'templateId' => $template->id,
            ]
        )
            ->set(
                'backgroundUpload',
                $file
            )
            ->assertSee(
                'my-ticket-design.jpg'
            )
            ->assertSeeHtml(
                'data-testid="ticket-background-temporary-preview"'
            )
            ->assertSeeHtml(
                'data-upload-ready="true"'
            );
    }

    public function test_selecting_background_file_clears_previous_required_error(): void
    {
        Storage::fake('public');

        [$type, $template] =
            $this->makeTemplate();

        $component =
            Livewire::test(
                TicketDesigner::class,
                [
                    'templateType' => $type,
                    'templateId' => $template->id,
                ]
            );

        $component
            ->call(
                'uploadBackground'
            )
            ->assertHasErrors([
                'backgroundUpload' =>
                    'required',
            ]);

        $component
            ->set(
                'backgroundUpload',
                UploadedFile::fake()->image(
                    'selected-ticket.jpg',
                    1080,
                    1350
                )
            )
            ->assertHasNoErrors(
                'backgroundUpload'
            );
    }

    private function makeTemplate(): array
    {
        $organization =
            Organization::query()->create([
                'name' =>
                    'Background Template Organization',
            ]);

        $template =
            OrganizationTicketTemplate::query()->create([
                'organization_id' =>
                    $organization->id,
                'name' =>
                    'Background Designer Template',
                'width' =>
                    1080,
                'height' =>
                    1350,
                'is_active' =>
                    true,
                'is_default' =>
                    false,
            ]);

        return [
            TicketDesignerService::TYPE_ORGANIZATION,
            $template,
        ];
    }
}