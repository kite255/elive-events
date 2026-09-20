<x-filament-panels::page>
    <div class="elive-communication-editor">
        <div class="elive-communication-form">
            {{ $this->form }}
        </div>

        <aside class="elive-communication-preview">
            <div class="elive-communication-preview-sticky">
                <div class="elive-preview-heading">
                    <div>
                        <h2>Live Preview</h2>
                        <p>
                            Updates while you edit. Save before using
                            the full-page Preview action.
                        </p>
                    </div>
                </div>

                @include(
                    'filament.event-communications.live-preview',
                    [
                        'event' => $this->event,
                        'data' => $this->data ?? [],
                    ]
                )
            </div>
        </aside>
    </div>

    <style>
        .elive-communication-editor {
            display: grid;
            grid-template-columns:
                minmax(0, 1.45fr)
                minmax(420px, .9fr);
            gap: 24px;
            align-items: start;
        }

        .elive-communication-form,
        .elive-communication-preview {
            min-width: 0;
        }

        .elive-communication-preview-sticky {
            position: sticky;
            top: 24px;
        }

        .elive-preview-heading {
            margin-bottom: 12px;
            padding: 16px 18px;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #ffffff;
        }

        .elive-preview-heading h2 {
            margin: 0;
            color: #161943;
            font-size: 18px;
            font-weight: 800;
        }

        .elive-preview-heading p {
            margin: 4px 0 0;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        @media (max-width: 1280px) {
            .elive-communication-editor {
                grid-template-columns: 1fr;
            }

            .elive-communication-preview-sticky {
                position: static;
            }
        }
    </style>
</x-filament-panels::page>
