<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-white leading-tight">
            {{ __('Universal Ingestion Wizard') }}
        </h2>
    </x-slot>

<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Status / Feedback Messages -->
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5 text-sm text-emerald-400">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-2xl border border-brand-red/40 bg-brand-red/10 p-5 text-sm text-brand-white/80">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Panel Body -->
        <div class="relative overflow-hidden rounded-2xl border border-brand-white/10 bg-brand-black/40 p-8 backdrop-blur-xl">
            
            <div class="mb-6">
                <span class="rounded-lg bg-brand-red/15 border border-brand-red/30 px-3 py-1 text-xs text-brand-red font-semibold uppercase tracking-wider">
                    Target Table: {{ str_replace('_', ' ', $table) }}
                </span>
            </div>

            @if(isset($headers) && isset($temp_file))
                <!-- STEP 2: Header Column Mapping Screen -->
                <form method="POST" action="{{ route('portal.import.execute', ['table' => $table]) }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="temp_file" value="{{ $temp_file }}">

                    <div>
                        <h3 class="text-lg font-semibold text-brand-white">Step 2: Map Your CSV Headers</h3>
                        <p class="text-xs text-brand-white/60 mt-1">Specify which column in your CSV/TXT file maps to each database column field in the CMIH Portal.</p>
                    </div>

                    <div class="border-t border-brand-white/10 my-4"></div>

                    <div class="space-y-4">
                        @foreach($columns as $col)
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 rounded-xl border border-brand-white/5 bg-brand-white/5">
                                <div>
                                    <label class="text-sm font-semibold text-brand-white uppercase tracking-wider block">
                                        {{ str_replace('_', ' ', $col) }}
                                    </label>
                                    <span class="text-xs text-brand-white/40">Database Field</span>
                                </div>
                                <div class="w-full md:w-72">
                                    <select name="mappings[{{ $col }}]" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                        <option value="">-- Skip Field --</option>
                                        @foreach($headers as $index => $header)
                                            <option value="{{ $index }}" {{ strtolower(trim($header)) === strtolower(str_replace('_', ' ', $col)) || strtolower(trim($header)) === strtolower($col) ? 'selected' : '' }}>
                                                Column {{ $index + 1 }}: "{{ $header }}"
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-between items-center pt-6">
                        <a href="{{ route('portal.import.show', ['table' => $table]) }}" class="rounded-xl border border-brand-white/10 bg-brand-white/5 hover:bg-brand-white/10 px-6 py-3 text-xs uppercase tracking-wider font-semibold text-brand-white transition">
                            Back
                        </a>
                        <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-8 py-3 text-xs uppercase tracking-wider font-semibold text-brand-white transition">
                            🚀 Run Ingestion
                        </button>
                    </div>
                </form>
            @else
                <!-- STEP 1: CSV / TXT Upload Screen -->
                <form method="POST" action="{{ route('portal.import.process', ['table' => $table]) }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <div>
                        <h3 class="text-lg font-semibold text-brand-white">Step 1: Upload CSV or TXT File</h3>
                        <p class="text-xs text-brand-white/60 mt-1">Upload comma-separated values (CSV) or tab-delimited text documents matching the target model attributes.</p>
                    </div>

                    <div class="border-t border-brand-white/10 my-4"></div>

                    <!-- File input -->
                    <div class="flex items-center justify-center w-full">
                        <label class="flex flex-col items-center justify-center w-full h-64 border-2 border-brand-white/10 border-dashed rounded-2xl cursor-pointer bg-brand-black/20 hover:bg-brand-white/5 hover:border-brand-white/20 transition duration-300">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-10 h-10 mb-3 text-brand-white/40 group-hover:text-brand-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"></path>
                                </svg>
                                <p class="mb-2 text-sm text-brand-white font-semibold"><span class="text-brand-red">Click to upload</span> or drag and drop</p>
                                <p class="text-xs text-brand-white/40">CSV or TXT (Max size 4MB)</p>
                            </div>
                            <input id="file" name="file" type="file" required class="hidden" accept=".csv,.txt" onchange="document.getElementById('selected-file-name').textContent = 'Selected: ' + this.files[0].name" />
                        </label>
                    </div>
                    <div id="selected-file-name" class="text-center text-xs font-semibold text-emerald-400"></div>

                    <!-- Table Attributes Hint -->
                    <div class="p-4 rounded-xl border border-brand-white/5 bg-brand-white/5 space-y-2">
                        <h4 class="text-xs font-semibold text-brand-white uppercase tracking-wider">Expected Target Attributes:</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($columns as $col)
                                <span class="rounded bg-brand-black/40 border border-brand-white/10 px-2.5 py-0.5 text-xs text-brand-white/70 font-mono">
                                    {{ $col }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-8 py-3 text-xs uppercase tracking-wider font-semibold text-brand-white transition">
                            Next: Map Columns
                        </button>
                    </div>
                </form>
            @endif

        </div>

    </div>
</div>
</x-app-layout>
