<x-filament-panels::page>
    @php($draft = $this->draft())
    <div x-data="{
        elements: $wire.entangle('elements'),
        width: @js($draft->width),
        height: @js($draft->height),
        selected: null,
        addImage(id,w,h) {
            if (this.elements.length >= 40) return;
            const scale=Math.min(1,180/w,this.width/w,this.height/h);
            this.elements.push({type:'image',asset_id:Number(id),text:'',x:0,y:0,width:Math.max(20,Math.round(w*scale)),height:Math.max(20,Math.round(h*scale)),font_size:24,color:'#161943'});
            this.selected=this.elements.length-1;
        },
        drag: null,
        fonts: {brand:'inherit',sans:'Arial, sans-serif',serif:'Georgia, serif',mono:'monospace'},
        selectElement(i) {
            this.selected=i;
            const el=this.elements[i];
            if (el.type==='image' || el.type==='qr') return;
            el.font_family ??= 'brand'; el.font_weight ??= 400; el.font_style ??= 'normal'; el.text_align ??= 'left'; el.line_height ??= 1.2;
        },
        labels: {text:'Text',event_name:'Event name',ticket_type:'Ticket type',holder_name:'Ticket holder',ticket_number:'Ticket number',event_date:'Event date',venue:'Venue',qr:'QR placeholder'},
        add(type) {
            if (this.elements.length >= 40) return;
            this.elements.push({type, text:type === 'text' ? 'Your text' : '', x:0,y:0,width:Math.min(type === 'qr' ? 100 : 240,this.width),height:Math.min(type === 'qr' ? 100 : 48,this.height),font_size:24,color:'#161943'});
            this.selectElement(this.elements.length-1);
        },
        start(e,i) {
            this.selectElement(i);
            const el=this.elements[i], rect=this.$refs.canvas.getBoundingClientRect();
            this.drag={i,px:e.clientX,py:e.clientY,x:Number(el.x),y:Number(el.y),sx:this.width/rect.width,sy:this.height/rect.height};
            e.currentTarget.setPointerCapture(e.pointerId);
        },
        move(e) {
            if (!this.drag) return;
            const d=this.drag, el=this.elements[d.i];
            el.x=Math.round(Math.max(0,Math.min(this.width-Number(el.width),d.x+(e.clientX-d.px)*d.sx)));
            el.y=Math.round(Math.max(0,Math.min(this.height-Number(el.height),d.y+(e.clientY-d.py)*d.sy)));
        },
        remove() {
            if (this.selected === null) return;
            this.elements.splice(this.selected,1); this.selected=null;
        }
    }" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">{{ $draft->name }}</h2>
                <p class="text-sm text-gray-500">{{ $draft->width }} × {{ $draft->height }} px · Draft · Front page</p>
            </div>
            <div class="flex gap-2">
                <x-filament::button color="gray" tag="a" :href="\App\Filament\Resources\Events\EventResource::getUrl('edit', ['record'=>$draft->event_id,'relation'=>array_search(\App\Filament\Resources\Events\RelationManagers\TicketTemplatesRelationManager::class,\App\Filament\Resources\Events\EventResource::getRelations(),true)])">Back to templates</x-filament::button>
                <x-filament::button wire:click="saveDesign" wire:loading.attr="disabled" wire:target="saveDesign,backgroundUpload,clearBackground,imageUpload,uploadImage">Save draft</x-filament::button>
            </div>
        </div>
        <p class="text-sm text-gray-500">Preview uses sample labels. The QR placeholder is not scannable and saving does not publish tickets.</p>
        @if ($errors->any())
            <div class="text-sm text-danger-600">{{ $errors->first() }}</div>
        @endif
        <div class="grid gap-4 lg:grid-cols-4">
            <x-filament::section>
                <x-slot name="heading">Elements</x-slot>
                <div class="mb-4 space-y-2">
                    <label class="block text-sm font-medium">Background image</label>
                    <input type="file" wire:model="backgroundUpload" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm">
                    @error('backgroundUpload') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                    <p wire:loading wire:target="backgroundUpload" class="text-xs text-gray-500">Uploading background…</p>
                    <p class="text-xs text-gray-500">PNG, JPG or WebP, up to 5 MB. Save draft to apply the upload.</p>
                    <button type="button" wire:click="clearBackground" class="text-sm text-danger-600">Remove background</button>
                </div>
                <div class="mb-4 space-y-2">
                    <label class="block text-sm font-medium">Logos and images</label>
                    <select wire:model="imageKind" class="w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900">
                        <option value="logo">Logo</option><option value="sponsor_logo">Sponsor logo</option><option value="image">Image</option>
                    </select>
                    <input type="file" wire:model="imageUpload" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm">
                    @error('imageUpload') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                    <x-filament::button size="sm" wire:click="uploadImage" wire:loading.attr="disabled" wire:target="imageUpload,uploadImage">Upload image</x-filament::button>
                    <p class="text-xs text-gray-500">Up to 12 images, 1 MB each. Select an uploaded image to place it on the canvas.</p>
                    @php($images = $this->imagePreviews())
                    @foreach ($images as $id => $image)
                        <button type="button" class="flex w-full items-center gap-2 rounded-lg border border-gray-300 p-2 text-left text-xs dark:border-gray-700" x-on:click="addImage(@js($id), @js($image['width']), @js($image['height']))">
                            <img src="{{ $image['src'] }}" alt="" class="h-8 w-8 object-contain">
                            <span class="min-w-0 truncate">{{ $image['label'] }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="grid gap-2">
                    <template x-for="(label,type) in labels" :key="type">
                        <button type="button" class="rounded-lg border border-gray-300 p-2 text-left text-sm dark:border-gray-700" x-on:click="add(type)" x-text="'Add '+label"></button>
                    </template>
                </div>
            </x-filament::section>
            <div class="lg:col-span-2 min-w-0">
                <div class="rounded-lg bg-gray-100 p-3 dark:bg-gray-900">
                    <div x-ref="canvas" class="relative overflow-hidden bg-white shadow-sm" :style="{aspectRatio:width+'/'+height}" style="container-type:inline-size">
                        @if ($background = $this->backgroundPreview())
                            <img src="{{ $background }}" alt="" class="pointer-events-none absolute inset-0 h-full w-full object-fill" />
                        @endif
                        <template x-for="(el,i) in elements" :key="i">
                            <div class="absolute cursor-move overflow-hidden select-none border" :class="selected===i?'border-blue-500':'border-transparent'" style="touch-action:none" :style="{left:(el.x/width*100)+'%',top:(el.y/height*100)+'%',width:(el.width/width*100)+'%',height:(el.height/height*100)+'%',fontSize:(el.font_size/width*100)+'cqw',color:el.color,fontFamily:fonts[el.font_family] || fonts.brand,fontWeight:el.font_weight || 400,fontStyle:el.font_style || 'normal',textAlign:el.text_align || 'left',lineHeight:el.line_height || 1.2,whiteSpace:'pre-wrap',overflowWrap:'anywhere'}" x-on:pointerdown.prevent="start($event,i)" x-on:pointermove="move($event)" x-on:pointerup="drag=null" x-on:pointercancel="drag=null">
                                <span x-show="el.type!=='qr' && el.type!=='image'" x-text="el.type==='text'?el.text:labels[el.type]"></span>
                                @foreach ($images as $id => $image)
                                    <img x-show="el.type==='image' && Number(el.asset_id) === @js($id)" src="{{ $image['src'] }}" alt="" class="pointer-events-none h-full w-full object-contain" draggable="false">
                                @endforeach
                                <span x-show="el.type==='qr'" class="flex h-full items-center justify-center border border-dashed border-gray-600 text-xs">QR placeholder</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <x-filament::section>
                <x-slot name="heading">Properties</x-slot>
                <p x-show="selected===null" class="text-sm text-gray-500">Select an element on the canvas.</p>
                <template x-if="selected!==null && elements[selected]">
                    <div class="space-y-3">
                        <label class="block text-sm" x-show="elements[selected].type==='text'">Text<input class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900" x-model="elements[selected].text" maxlength="300"></label>
                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="field in ['x','y','width','height']" :key="field">
                                <label class="block text-sm"><span x-text="field.replace('_',' ')"></span><input type="number" class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900" x-model.number="elements[selected][field]"></label>
                            </template>
                        </div>
                        <div x-show="!['image','qr'].includes(elements[selected].type)" class="space-y-2">
                            <label class="block text-sm">Font family
                                <select x-model="elements[selected].font_family" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900">
                                    <option value="brand">Brand / panel font</option><option value="sans">Sans serif</option><option value="serif">Serif</option><option value="mono">Monospace</option>
                                </select>
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="block text-sm">Font size<input type="number" min="8" max="100" x-model.number="elements[selected].font_size" class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900"></label>
                                <label class="block text-sm">Weight<select x-model.number="elements[selected].font_weight" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900"><option value="400">Regular</option><option value="600">Semibold</option><option value="700">Bold</option></select></label>
                                <label class="block text-sm">Style<select x-model="elements[selected].font_style" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900"><option value="normal">Normal</option><option value="italic">Italic</option></select></label>
                                <label class="block text-sm">Alignment<select x-model="elements[selected].text_align" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></label>
                                <label class="block text-sm">Line spacing<input type="number" min="1" max="2" step="0.1" x-model.number="elements[selected].line_height" class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900"></label>
                                <label class="block text-sm">Color<input type="color" class="mt-1 block" x-model="elements[selected].color"></label>
                            </div>
                        </div>
                        <button type="button" class="text-sm text-danger-600" x-on:click="remove()">Remove element</button>
                    </div>
                </template>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
