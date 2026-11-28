<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\User;
use App\Services\EventCertificateAccess;
use App\Services\EventCertificateTemplates;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

class EventCertificateTemplateManager extends Page
{
    use WithPagination;

    protected static string $resource = EventResource::class;
    protected string $view = 'filament.resources.events.pages.event-certificate-template-manager';
    protected static ?string $title = 'Certificate templates';

    #[Locked]
    public int $eventId;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';
    public int $width = 1123;
    public int $height = 794;
    public bool $showForm = false;

    public function mount(int|string $record): void
    {
        abort_unless(ctype_digit((string) $record), 404);
        $this->eventId = (int) $record;
        $this->event();
    }

    protected function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function event(): Event
    {
        return app(EventCertificateAccess::class)->authorize($this->user(), $this->eventId);
    }

    public function templates(): LengthAwarePaginator
    {
        return $this->event()->certificateTemplates()
            ->withCount(['assets', 'certificates'])->latest('id')->paginate(15);
    }

    public function newDraft(): void
    {
        $this->event();
        $this->resetValidation();
        $this->editingId = null;
        $this->name = '';
        $this->width = 1123;
        $this->height = 794;
        $this->showForm = true;
    }

    public function editDraft(int $id): void
    {
        $draft = $this->event()->certificateTemplates()->whereKey($id)
            ->where('is_active', false)->firstOrFail();
        abort_if($draft->certificates()->exists(), 422);
        $this->resetValidation();
        $this->editingId = $draft->id;
        $this->name = $draft->name;
        $this->width = $draft->width;
        $this->height = $draft->height;
        $this->showForm = true;
    }

    public function cancelEdit(): void
    {
        $this->event();
        $this->showForm = false;
        $this->editingId = null;
        $this->resetValidation();
    }

    public function saveDraft(): void
    {
        $this->event();
        app(EventCertificateTemplates::class)->save(
            $this->user(), $this->eventId, $this->editingId,
            ['name' => trim($this->name), 'width' => $this->width, 'height' => $this->height]
        );
        $this->cancelEdit();
        $this->resetPage();
        Notification::make()->title('Certificate draft saved')->success()->send();
    }

    public function duplicateDraft(int $id): void
    {
        app(EventCertificateTemplates::class)->duplicate($this->user(), $this->eventId, $id);
        $this->resetPage();
        Notification::make()->title('Certificate draft duplicated')->success()->send();
    }
}
