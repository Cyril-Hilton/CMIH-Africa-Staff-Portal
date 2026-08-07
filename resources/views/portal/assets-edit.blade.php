<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">DAM & Inventory</p>
                <h2 class="text-3xl font-display text-brand-white">Edit Asset</h2>
            </div>
            <a href="{{ route('portal.assets') }}" class="text-sm text-brand-ash hover:text-white transition">Back to Assets</a>
        </div>
    </x-slot>

    <div class="glass-panel max-w-4xl mx-auto rounded-2xl p-8">
        <form action="{{ route('portal.assets.update', $asset) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PATCH')
            
            <div class="flex flex-col items-center mb-6">
                @if($asset->image_path)
                    <img src="{{ Storage::disk('public')->url($asset->image_path) }}" class="w-24 h-24 rounded-lg object-cover bg-brand-white/10 mb-4" alt="">
                @endif
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50 mb-1.5">Asset Name</label>
                <input type="text" name="name" value="{{ old('name', $asset->name) }}" required class="w-full bg-brand-white/5 border border-brand-white/10 rounded-lg px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-colors placeholder-brand-white/20">
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50 mb-1.5">Description</label>
                <textarea name="description" rows="3" class="wysiwyg-editor w-full bg-brand-white/5 border border-brand-white/10 rounded-lg px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-colors placeholder-brand-white/20">{{ old('description', $asset->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50 mb-1.5">Condition</label>
                    <div class="relative">
                        <select name="condition" class="w-full appearance-none bg-brand-white/5 border border-brand-white/10 rounded-lg px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-colors">
                            @foreach(['New', 'Good', 'Fair', 'Poor'] as $option)
                                <option value="{{ $option }}" {{ $asset->condition === $option ? 'selected' : '' }} class="bg-brand-black text-brand-white">{{ $option }}</option>
                            @endforeach
                        </select>
                         <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-brand-white/50">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50 mb-1.5">Type</label>
                    <div class="relative">
                        <select name="type" class="w-full appearance-none bg-brand-white/5 border border-brand-white/10 rounded-lg px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-colors">
                            @foreach(['Hardware', 'Software', 'Vehicle', 'Other'] as $option)
                                <option value="{{ $option }}" {{ $asset->type === $option ? 'selected' : '' }} class="bg-brand-black text-brand-white">{{ $option }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-brand-white/50">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </div>
                    </div>
                </div>
            </div>

             <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50 mb-1.5">Status</label>
                    <div class="relative">
                        <select name="status" class="w-full appearance-none bg-brand-white/5 border border-brand-white/10 rounded-lg px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-colors">
                            @foreach(['Available', 'In Use', 'Maintenance', 'Retired'] as $option)
                                <option value="{{ $option }}" {{ $asset->status === $option ? 'selected' : '' }} class="bg-brand-black text-brand-white">{{ $option }}</option>
                            @endforeach
                        </select>
                         <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-brand-white/50">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </div>
                    </div>
                </div>
                <div>
                     <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50 mb-1.5">Assigned To</label>
                    <div class="relative">
                        <select name="assigned_to" class="w-full appearance-none bg-brand-white/5 border border-brand-white/10 rounded-lg px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-colors">
                            <option value="" class="bg-brand-black text-brand-white/50">Unassigned</option>
                            @foreach($staff as $user)
                                <option value="{{ $user->id }}" {{ $asset->assigned_to == $user->id ? 'selected' : '' }} class="bg-brand-black text-brand-white">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-brand-white/50">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/50 mb-1.5">Update Image (Optional)</label>
                <input type="file" name="image" accept="image/*" class="w-full text-xs text-brand-white file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-brand-red file:text-white hover:file:bg-brand-red-dark transition">
            </div>

            <div class="flex justify-end gap-3 pt-6 border-t border-brand-white/10">
                <a href="{{ route('portal.assets') }}" class="px-4 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/60 hover:text-brand-white transition">Cancel</a>
                <button type="submit" class="rounded-full bg-brand-red px-6 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-white hover:bg-brand-red-dark hover:shadow-lg hover:shadow-brand-red/25 transition-all">Update Asset</button>
            </div>
        </form>
    </div>
</x-app-layout>
