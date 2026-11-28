<x-filament-panels::page>
    @php($draft = $this->draft())
    @php($images = $this->imagePreviews())
    <div x-data="{
        elements: $wire.entangle('elements'),
        width: @js($draft->width),
        height: @js($draft->height),
        selected: null,
        backgroundColor: $wire.entangle('backgroundColor'),
        drag: null,
        addImage(id,w,h,kind) {
            if (this.elements.length >= 50) return;
            const scale=Math.min(this.width/w,this.height/h,kind==='background'?1:Math.min(1,220/w,160/h));
            const el={type:'image',asset_id:Number(id),text:'',x:0,y:0,width:Math.max(20,Math.round(w*scale)),height:Math.max(20,Math.round(h*scale)),font_size:32,color:'#161943',font_family:'brand',font_weight:400,font_style:'normal',text_align:'center',line_height:1.2};
            if (kind==='background') { el.width=this.width; el.height=this.height; this.elements.unshift(el); this.selected=0; }
            else { this.elements.push(el); this.selected=this.elements.length-1; }
        },
        fonts: {brand:'inherit',sans:'Arial, sans-serif',serif:'Georgia, serif',mono:'monospace'},
        selectElement(i) {
            this.selected=i;
            const el=this.elements[i];
            if (el.type==='qr' || el.type==='image') return;
            el.font_family ??= 'brand'; el.font_weight ??= 400; el.font_style ??= 'normal'; el.text_align ??= 'left'; el.line_height ??= 1.2;
        },
        labels: {text:'Custom text',attendee_name:'Attendee name',event_name:'Event name',event_date:'Event date',organization_name:'Organization name',certificate_number:'Certificate number',issued_date:'Issue date',qr:'QR placeholder'},
        add(type) {
            if (this.elements.length >= 50) return;
            this.elements.push({type, text:type === 'text' ? 'Certificate of Participation' : '', x:0,y:0,width:Math.min(type === 'qr' ? 100 : 400,this.width),height:Math.min(type === 'qr' ? 100 : 72,this.height),font_size:32,color:'#161943',font_family:'brand',font_weight:400,font_style:'normal',text_align:'center',line_height:1.2});
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
            this.elements.splice(this.selected,1); this.selected=null; this.drag=null;
        }
    }" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">{{ $draft->name }}</h2>
                <p class="text-sm text-gray-500">{{ $draft->width }} × {{ $draft->height }} px · Certificate draft</p>
            </div>
            <div class="flex gap-2">
                <x-filament::button color="gray" tag="a" :href="\App\Filament\Resources\Events\EventResource::getUrl('certificate-templates', ['record'=>$draft->event_id])">Back to templates</x-filament::button>
                <x-filament::button wire:click="saveDesign" wire:loading.attr="disabled" wire:target="saveDesign,imageUpload,uploadImage">Save draft</x-filament::button>
            </div>
        </div>
        <p class="text-sm text-gray-500">Preview uses sample labels. The QR placeholder is not scannable and saving does not issue certificates.</p>
        @if ($errors->any())
            <div class="text-sm text-danger-600">{{ $errors->first() }}</div>
        @endif
        <div class="grid gap-4 lg:grid-cols-4">
            <x-filament::section>
                <x-slot name="heading">Elements</x-slot>
                <div class="mb-4 space-y-2">
                    <label class="flex items-center justify-between gap-2 text-sm font-medium">Background color
                        <input type="color" x-model="backgroundColor" aria-label="Certificate background color">
                    </label>
                    <p class="text-xs text-gray-500">Add an element, then drag it into position or adjust its properties.</p>
                </div>
                <div class="mb-4 space-y-2">
                    <label class="block text-sm font-medium">Images and signatures</label>
                    <select wire:model="imageKind" class="w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900">
                        <option value="background">Background</option><option value="logo">Logo</option><option value="signature">Signature</option><option value="stamp">Stamp</option><option value="image">Image</option>
                    </select>
                    <input type="file" wire:model="imageUpload" accept="image/png,image/jpeg" class="block w-full text-sm">
                    @error('imageUpload') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                    <x-filament::button size="sm" wire:click="uploadImage" wire:loading.attr="disabled" wire:target="imageUpload,uploadImage">Upload image</x-filament::button>
                    <p class="text-xs text-gray-500">PNG or JPG, up to 2 MB. Select an uploaded asset to add it to the canvas.</p>
                    @foreach ($images as $id => $image)
                        <button type="button" wire:key="certificate-asset-{{ $id }}" class="flex w-full items-center gap-2 rounded-lg border border-gray-300 p-2 text-left text-sm dark:border-gray-700" x-on:click="addImage(@js($id), @js($image['width']), @js($image['height']), @js($image['kind']))">
                            <img src="{{ $image['src'] }}" alt="" class="h-8 w-8 object-contain">
                            <span>{{ ucfirst($image['kind']) }} #{{ $id }}</span>
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
                    <div x-ref="canvas" class="relative overflow-hidden bg-white shadow-sm" :style="{aspectRatio:width+'/'+height,backgroundColor:backgroundColor}" style="container-type:inline-size">
                        <template x-for="(el,i) in elements" :key="i">
                            <div class="absolute cursor-move overflow-hidden select-none border" :class="selected===i?'border-blue-500':'border-transparent'" style="touch-action:none" tabindex="0" x-on:focus="selectElement(i)" :style="{left:(el.x/width*100)+'%',top:(el.y/height*100)+'%',width:(el.width/width*100)+'%',height:(el.height/height*100)+'%',fontSize:(el.font_size/width*100)+'cqw',color:el.color,fontFamily:fonts[el.font_family] || fonts.brand,fontWeight:el.font_weight || 400,fontStyle:el.font_style || 'normal',textAlign:el.text_align || 'left',lineHeight:el.line_height || 1.2,whiteSpace:'pre-wrap',overflowWrap:'anywhere'}" x-on:pointerdown.prevent="start($event,i)" x-on:pointermove="move($event)" x-on:pointerup="drag=null" x-on:pointercancel="drag=null">
                                <span x-show="el.type!=='qr' && el.type!=='image'" x-text="el.type==='text'?el.text:labels[el.type]"></span>
                                @foreach ($images as $id => $image)
                                    <img x-show="el.type==='image' && Number(el.asset_id)===@js($id)" src="{{ $image['src'] }}" alt="" draggable="false" class="pointer-events-none h-full w-full object-contain">
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
                        <label class="block text-sm" x-show="elements[selected].type==='text'">Text<input class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900" x-model="elements[selected].text" maxlength="500"></label>
                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="field in ['x','y','width','height']" :key="field">
                                <label class="block text-sm"><span x-text="field.replace('_',' ')"></span><input type="number" class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900" x-model.number="elements[selected][field]"></label>
                            </template>
                        </div>
                        <div x-show="!['qr','image'].includes(elements[selected].type)" class="space-y-2">
                            <label class="block text-sm">Font family
                                <select x-model="elements[selected].font_family" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900">
                                    <option value="brand">Brand / panel font</option><option value="sans">Sans serif</option><option value="serif">Serif</option><option value="mono">Monospace</option>
                                </select>
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="block text-sm">Font size<input type="number" min="8" max="150" x-model.number="elements[selected].font_size" class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900"></label>
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
