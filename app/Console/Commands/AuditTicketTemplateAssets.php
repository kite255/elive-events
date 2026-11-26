<?php

namespace App\Console\Commands;

use App\Models\TicketTemplatePage;
use App\Models\TicketTemplateAsset;
use App\Services\Tickets\TicketTemplateDefinitionValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuditTicketTemplateAssets extends Command
{
    protected $signature = 'tickets:audit-template-assets {--limit=100 : Maximum detail rows}';
    protected $description = 'Read-only audit of ticket template image references and storage';

    public function handle(TicketTemplateDefinitionValidator $validator): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('Limit must be between 1 and 1000.');
            return self::INVALID;
        }

        $disk = Storage::disk('public');
        $references = [];
        $details = [];
        $counts = ['Pages' => 0, 'Missing references' => 0, 'Legacy references' => 0,
            'Invalid or foreign paths' => 0, 'Invalid definitions' => 0,
            'Stored files' => 0, 'Unreferenced candidates' => 0, 'Stored bytes' => 0,
            'Invalid image headers or size' => 0];
        $issue = function (string $kind, string $source, string $path = '') use (&$counts, &$details, $limit): void {
            $counts[$kind]++;
            if (count($details) < $limit) {
                $details[] = [$kind, $source, preg_replace('/[\x00-\x1f\x7f]/', '?', substr($path, 0, 240))];
            }
        };

        foreach (TicketTemplatePage::query()->with(['eventTemplate', 'organizationTemplate'])->lazyById(200) as $page) {
            $counts['Pages']++;
            $event = $page->event_ticket_template_id;
            $org = $page->organization_ticket_template_id;
            $template = $event !== null ? $page->eventTemplate : $page->organizationTemplate;
            $source = 'page '.$page->id;
            $parentValid = ($event === null) !== ($org === null) && $template !== null;
            try {
                if (! $parentValid) {
                    $issue('Invalid definitions', $source);
                } else {
                    $validator->validate($page->definition ?? [], (int) $template->width, (int) $template->height);
                }
            } catch (ValidationException) {
                $issue('Invalid definitions', $source);
            }

            $paths = [['ticket-template-backgrounds', $page->background_image_path]];
            foreach (($page->definition['elements'] ?? []) as $element) {
                if (is_array($element)) {
                    $paths[] = ['ticket-template-elements', $element['asset_path'] ?? null];
                }
            }
            foreach ($paths as [$directory, $path]) {
                if ($path === null || $path === '') {
                    continue;
                }
                if (! is_string($path)) {
                    $issue('Invalid or foreign paths', $source);
                    continue;
                }
                $references[$path] = true;
                $filename = '[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)';
                $prefix = $directory.'/'.($event !== null ? 'event/'.$event : 'organization/'.$org).'/';
                if (preg_match('~\A'.preg_quote($directory.'/', '~').$filename.'\z~i', $path)) {
                    $issue('Legacy references', $source, $path);
                } elseif (! $parentValid || ! preg_match('~\A'.preg_quote($prefix, '~').$filename.'\z~i', $path)) {
                    $issue('Invalid or foreign paths', $source, $path);
                    continue;
                }
                if (! $disk->exists($path)) {
                    $issue('Missing references', $source, $path);
                }
            }
        }

        // Protect every registered asset, even if no current page references it.
        foreach (TicketTemplateAsset::query()->lazyById(200) as $asset) {
            if (is_string($asset->file_path) && $asset->file_path !== '') {
                $references[$asset->file_path] = true;
            }
        }

        foreach (['ticket-template-backgrounds', 'ticket-template-elements'] as $directory) {
            foreach ($disk->allFiles($directory) as $path) {
                $counts['Stored files']++;
                $size = $disk->size($path);
                $counts['Stored bytes'] += $size;
                if (! isset($references[$path])) {
                    $issue('Unreferenced candidates', 'storage', $path);
                }
                if ($size < 1 || $size > 10485760) {
                    $issue('Invalid image headers or size', 'storage', $path);
                    continue;
                }
                $bytes = $disk->get($path);
                $image = is_string($bytes) && strlen($bytes) <= 10485760 ? @getimagesizefromstring($bytes) : false;
                $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    default => null,
                };
                if (! $image || ! $mime || ($image['mime'] ?? null) !== $mime
                    || $image[0] < 1 || $image[1] < 1 || $image[0] > 4000 || $image[1] > 4000) {
                    $issue('Invalid image headers or size', 'storage', $path);
                }
            }
        }

        $this->table(['Check', 'Count'], collect($counts)->map(fn ($count, $name) => [$name, $count])->values()->all());
        if ($details !== []) {
            $this->table(['Issue', 'Source', 'Path'], $details);
        }
        $this->info('Read-only audit. No files deleted or records modified. Unreferenced candidates are not approved for deletion.');

        return self::SUCCESS;
    }
}
