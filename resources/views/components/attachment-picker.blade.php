@props(['label' => '添付資料（任意・1ファイル10MBまで）'])

<div
    x-data="{
        files: [],
        dragging: false,
        addFiles(fileList) {
            Array.from(fileList).forEach((file) => {
                const exists = this.files.some((f) => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified);
                if (! exists) this.files.push(file);
            });
            this.syncInput();
        },
        removeFile(index) {
            this.files.splice(index, 1);
            this.syncInput();
        },
        syncInput() {
            const transfer = new DataTransfer();
            this.files.forEach((file) => transfer.items.add(file));
            this.$refs.input.files = transfer.files;
            this.$nextTick(() => window.refreshIcons());
        },
    }"
>
    <x-input-label :value="$label" />
    <div
        class="border-2 border-dashed rounded-lg p-4 text-center transition-colors relative"
        :class="dragging ? 'border-blue-300 bg-blue-50' : 'border-slate-200 hover:bg-slate-50'"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop="dragging = false"
    >
        <input
            x-ref="input"
            name="attachments[]"
            type="file"
            multiple
            class="absolute inset-0 opacity-0 cursor-pointer"
            @change="
                const picked = Array.from($event.target.files);
                $event.target.value = '';
                addFiles(picked);
            "
        />
        <i data-lucide="upload-cloud" class="w-8 h-8 text-slate-400 mx-auto mb-1"></i>
        <span class="text-xs text-slate-500 block" x-text="files.length > 0 ? files.length + '件のファイルを選択中（クリックまたはドロップで追加）' : 'クリック、またはファイルをドロップして追加'"></span>
        <span class="text-[10px] text-slate-400 block mt-0.5">取得済み見積りPDF、外観画像など</span>
    </div>

    <button
        type="button"
        @click.stop="$refs.cameraInput.click()"
        class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg px-3 py-1.5 transition-colors"
    >
        <i data-lucide="camera" class="w-3.5 h-3.5"></i>
        カメラで撮影
    </button>
    <input
        x-ref="cameraInput"
        type="file"
        accept="image/*"
        capture="environment"
        class="hidden"
        @change="
            const picked = Array.from($event.target.files);
            $event.target.value = '';
            addFiles(picked);
        "
    />

    <ul class="mt-2 space-y-1" x-show="files.length > 0" x-cloak>
        <template x-for="(file, index) in files" :key="file.name + '-' + file.size + '-' + file.lastModified">
            <li class="flex items-center justify-between gap-2 bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs">
                <span class="flex items-center gap-1.5 text-slate-700 min-w-0">
                    <i data-lucide="paperclip" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                    <span x-text="file.name" class="truncate"></span>
                </span>
                <span class="flex items-center gap-2 shrink-0">
                    <span class="text-slate-400" x-text="(file.size / 1024 / 1024).toFixed(2) + 'MB'"></span>
                    <button type="button" @click="removeFile(index)" class="text-slate-400 hover:text-red-600" title="削除">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                    </button>
                </span>
            </li>
        </template>
    </ul>
    <x-input-error class="mt-2" :messages="$errors->get('attachments')" />
    <x-input-error class="mt-2" :messages="$errors->get('attachments.0')" />
</div>
