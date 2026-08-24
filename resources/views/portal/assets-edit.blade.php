<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $asset->is_warehouse_tracked ? 'Warehouse Assets Manager' : 'Office Asset Manager' }}</p>
                <h2 class="text-3xl font-display text-brand-white">Edit Asset</h2>
            </div>
            <a href="{{ $asset->is_warehouse_tracked ? route('portal.assets.warehouse.index') : route('portal.assets') }}" class="text-sm text-brand-ash hover:text-white transition">Back to Assets</a>
        </div>
    </x-slot>

    <div class="glass-panel mx-auto max-w-5xl rounded-2xl p-5 sm:p-8">
        <form action="{{ route('portal.assets.update', $asset) }}" method="POST" enctype="multipart/form-data" class="space-y-6" data-asset-edit-form>
            @csrf
            @method('PATCH')
            <div data-asset-edit-status class="hidden rounded-2xl border px-4 py-3 text-sm font-semibold"></div>

            <div class="flex flex-col gap-5 md:flex-row md:items-start">
                @if($asset->image_path)
                    <img src="{{ Storage::disk('public')->url($asset->image_path) }}" class="h-28 w-28 rounded-xl object-cover bg-brand-white/10" alt="">
                @else
                    <div class="flex h-28 w-28 items-center justify-center rounded-xl bg-brand-white/10 text-xs font-bold uppercase tracking-wider text-brand-white/40">Asset</div>
                @endif
                <div class="flex-1">
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Update Image</label>
                    <input type="file" name="image" accept="image/*" class="w-full text-xs text-brand-white file:mr-4 file:rounded-full file:border-0 file:bg-brand-red file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-brand-red-dark transition">
                    <p class="mt-2 text-xs text-brand-white/45">Upload a clear current image of the item. Existing request evidence images are kept separately.</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Asset Name</label>
                    <input type="text" name="name" value="{{ old('name', $asset->name) }}" required class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white placeholder-brand-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Brand</label>
                    <input type="text" name="brand" value="{{ old('brand', $asset->brand) }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white placeholder-brand-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Asset Tag</label>
                    <input type="text" name="asset_tag" value="{{ old('asset_tag', $asset->asset_tag) }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white placeholder-brand-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Serial Number</label>
                    <input type="text" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white placeholder-brand-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Category</label>
                    <input type="text" name="category" value="{{ old('category', $asset->category) }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white placeholder-brand-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Asset Value</label>
                    <input type="number" step="0.01" min="0" name="asset_value" value="{{ old('asset_value', $asset->asset_value) }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white placeholder-brand-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">PO Quantity</label>
                    <input type="number" min="0" name="po_quantity" value="{{ old('po_quantity', $asset->po_quantity ?? 0) }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Quantity Procured</label>
                    <input type="number" min="0" name="quantity_procured" value="{{ old('quantity_procured', $asset->quantity_procured ?? 0) }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Owner</label>
                    <input type="text" name="owner" value="{{ old('owner', $asset->owner) }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white placeholder-brand-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Use Type</label>
                    <input type="text" name="asset_use_type" value="{{ old('asset_use_type', $asset->asset_use_type) }}" placeholder="Fixed / Current" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white placeholder-brand-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Description</label>
                <textarea name="description" rows="3" class="wysiwyg-editor w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white placeholder-brand-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red">{{ old('description', $asset->description) }}</textarea>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Condition</label>
                    <select name="condition" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                        @foreach(['New', 'Excellent', 'Good', 'Fair', 'Poor'] as $option)
                            <option value="{{ $option }}" @selected(old('condition', $asset->condition) === $option) class="bg-brand-black text-brand-white">{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Type</label>
                    <input type="text" name="type" value="{{ old('type', $asset->type) }}" list="asset-type-options" required class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                    <datalist id="asset-type-options">
                        @foreach(['Hardware', 'Software', 'Vehicle', 'POSM', 'Visibility', 'Staging', 'Machinery', 'Monitors', 'Other'] as $option)
                            <option value="{{ $option }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Availability</label>
                    <select name="status" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                        @foreach(['Available', 'In Use', 'Deployed', 'Pending Approval', 'Maintenance', 'In Repair', 'Under Remodel', 'Retired'] as $option)
                            <option value="{{ $option }}" @selected(old('status', $asset->status) === $option) class="bg-brand-black text-brand-white">{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Warehouse Qty</label>
                    <input type="number" name="warehouse_quantity" value="{{ old('warehouse_quantity', $asset->warehouse_quantity ?? 1) }}" min="0" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Assigned To</label>
                    <select name="assigned_to" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                        <option value="" class="bg-brand-black text-brand-white/50">Unassigned</option>
                        @foreach($staff as $user)
                            <option value="{{ $user->id }}" @selected((string) old('assigned_to', $asset->assigned_to) === (string) $user->id) class="bg-brand-black text-brand-white">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Safe Keeping Location</label>
                    <input type="text" name="warehouse_location" value="{{ old('warehouse_location', $asset->warehouse_location) }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white placeholder-brand-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">General Location</label>
                    <input type="text" name="location" value="{{ old('location', $asset->location) }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white placeholder-brand-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                </div>
                <div class="flex items-end">
                    <label class="flex w-full cursor-pointer items-center gap-3 rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm font-semibold text-brand-white/70">
                        <input type="checkbox" name="is_warehouse_tracked" value="1" @checked(old('is_warehouse_tracked', $asset->is_warehouse_tracked)) class="rounded border-brand-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                        Track this asset in the warehouse
                    </label>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Warehouse Notes</label>
                    <textarea name="warehouse_notes" rows="3" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white placeholder-brand-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red">{{ old('warehouse_notes', $asset->warehouse_notes) }}</textarea>
                </div>
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Remodel Status</label>
                    <input type="text" name="remodel_status" value="{{ old('remodel_status', $asset->remodel_status) }}" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white placeholder-brand-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red">
                    <textarea name="remodel_notes" rows="2" class="mt-3 w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white placeholder-brand-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red" placeholder="Remodel / maintenance notes">{{ old('remodel_notes', $asset->remodel_notes) }}</textarea>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50">Internal Notes</label>
                    <textarea name="notes" rows="3" class="w-full rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-sm text-brand-white placeholder-brand-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red">{{ old('notes', $asset->notes) }}</textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-brand-white/10 pt-6">
                <a href="{{ route($asset->is_warehouse_tracked ? 'portal.assets.warehouse.index' : 'portal.assets') }}" class="px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/60 hover:text-brand-white transition">Cancel</a>
                <button type="submit" data-asset-edit-submit class="rounded-full bg-brand-red px-6 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-white hover:bg-brand-red-dark hover:shadow-lg hover:shadow-brand-red/25 transition-all">Update Asset</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-asset-edit-form]');
            if (!form) return;

            const status = form.querySelector('[data-asset-edit-status]');
            const submit = form.querySelector('[data-asset-edit-submit]');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            function setStatus(message, type = 'success') {
                if (!status) return;
                status.textContent = message;
                status.classList.remove('hidden', 'border-emerald-500/30', 'bg-emerald-500/10', 'text-emerald-200', 'border-red-500/30', 'bg-red-500/10', 'text-red-200');
                if (type === 'error') {
                    status.classList.add('border-red-500/30', 'bg-red-500/10', 'text-red-200');
                } else {
                    status.classList.add('border-emerald-500/30', 'bg-emerald-500/10', 'text-emerald-200');
                }
            }

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                const originalText = submit?.textContent;
                if (submit) {
                    submit.disabled = true;
                    submit.classList.add('opacity-60');
                    submit.textContent = 'Saving...';
                }

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        credentials: 'same-origin',
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const firstError = data.errors ? Object.values(data.errors).flat().find(Boolean) : null;
                        setStatus(firstError || data.message || 'Please check the form entries and try again.', 'error');
                        return;
                    }

                    setStatus(data.message || 'Asset updated.');
                } catch (error) {
                    console.debug('Asset edit save failed:', error);
                    setStatus('Network error while saving the asset. Please try again.', 'error');
                } finally {
                    if (submit) {
                        submit.disabled = false;
                        submit.classList.remove('opacity-60');
                        submit.textContent = originalText || 'Update Asset';
                    }
                }
            });
        });
    </script>
</x-app-layout>
