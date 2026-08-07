<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Financial Tools</p>
                <h2 class="text-3xl font-display text-brand-white">Create Project Budget</h2>
            </div>
            <a href="{{ route('portal.finance.budgets.index') }}" class="rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70 hover:bg-brand-white/10 transition-all">
                Back to Budgets
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <h3 class="text-lg font-semibold text-brand-white mb-6">New Budget Details</h3>

            <form action="{{ route('portal.finance.budgets.store') }}" method="POST" class="space-y-6">
                @csrf

                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Budget Title *</label>
                        <input type="text" id="title" name="title" value="{{ old('title') }}" required placeholder="e.g. Q3 Brand Activation Budget" 
                            class="w-full rounded-xl border border-brand-white/10 bg-brand-black text-brand-white px-4 py-2.5 focus:border-amber-500 focus:outline-none text-sm" />
                    </div>

                    <!-- Currency Selection -->
                    <div>
                        <label for="currency" class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Currency *</label>
                        <select id="currency" name="currency" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/80 text-brand-white px-4 py-2.5 focus:border-amber-500 focus:outline-none text-sm">
                            <option value="GH₵" selected>GH₵ – Ghana Cedi</option>
                            <option value="USD">USD – US Dollar</option>
                            <option value="EUR">EUR – Euro</option>
                            <option value="GBP">GBP – British Pound</option>
                            <option value="NGN">NGN – Nigerian Naira</option>
                            <option value="ZAR">ZAR – South African Rand</option>
                            <option value="XOF">XOF – West African CFA franc</option>
                            <option value="SLE">SLE – Sierra Leonean Leone</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Associate with Task -->
                    <div>
                        <label for="task_id" class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Associate with Task (Optional)</label>
                        <select id="task_id" name="task_id" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/80 text-brand-white px-4 py-2.5 focus:border-amber-500 focus:outline-none text-sm">
                            <option value="">— Select Task —</option>
                            @foreach ($tasks as $task)
                                <option value="{{ $task->id }}" {{ old('task_id') == $task->id ? 'selected' : '' }}>
                                    {{ $task->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Brief Notes -->
                    <div>
                        <label for="notes" class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Notes / Short Description</label>
                        <input type="text" id="notes" name="notes" value="{{ old('notes') }}" placeholder="Brief summary of expense..." 
                            class="w-full rounded-xl border border-brand-white/10 bg-brand-black text-brand-white px-4 py-2.5 focus:border-amber-500 focus:outline-none text-sm" />
                    </div>
                </div>

                <!-- Editor (CKEditor) -->
                <div>
                    <label for="content" class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Budget Description / Detailed Specs (CKEditor)</label>
                    <div class="rounded-xl overflow-hidden border border-brand-white/10 bg-brand-black">
                        <textarea id="content" name="content" rows="12" class="wysiwyg-editor w-full bg-brand-black text-brand-white p-4 focus:outline-none">{{ old('content') }}</textarea>
                    </div>
                </div>

                <!-- File Import System -->
                <div class="glass-panel rounded-xl p-4 border border-brand-white/10 bg-brand-white/[0.02] space-y-3">
                    <div class="flex items-center justify-between border-b border-brand-white/5 pb-2">
                        <label class="block text-xs uppercase tracking-wider text-brand-ash font-bold">📥 Import from Device (Excel / Doc / PPT / Images / PDF / Text)</label>
                        <span class="text-[9px] uppercase tracking-wider bg-brand-white/10 px-2 py-0.5 rounded text-brand-ash">Optional</span>
                    </div>
                    <p class="text-[10px] text-brand-white/50">Upload any file from your device (Excel, Word, PowerPoint, PDF, Images, Text, etc.). The parser will dynamically extract text, render sheets/slides, embed images, or link documents directly inside the CKEditor budget content. Large spreadsheets will also populate the line items table below.</p>
                    <div class="flex items-center gap-3">
                        <input type="file" id="import_file" class="hidden" onchange="handleFileImport(this)">
                        <button type="button" onclick="document.getElementById('import_file').click()" class="rounded bg-brand-white/10 hover:bg-brand-white/20 border border-brand-white/15 px-4 py-2 text-xs font-semibold text-brand-white transition cursor-pointer">
                            📂 Choose File...
                        </button>
                        <div class="flex flex-col">
                            <span id="import_file_name" class="text-xs text-brand-white/40 italic">No file chosen</span>
                            <div id="import_status_msg" class="text-[10px] mt-0.5 hidden"></div>
                        </div>
                        <div id="import_spinner" class="hidden animate-spin rounded-full h-4 w-4 border-2 border-brand-white border-t-transparent"></div>
                    </div>
                </div>

                <!-- Hidden JSON field for imported items -->
                <input type="hidden" name="imported_items" id="imported_items_json" value="">

                <!-- Imported Line Items Preview container -->
                <div class="glass-panel rounded-xl p-4 border border-brand-white/10 bg-brand-white/[0.02] space-y-3">
                    <h4 class="text-xs uppercase tracking-wider text-brand-ash font-bold border-b border-brand-white/5 pb-2">📊 Imported Budget Line Items Preview</h4>
                    <div id="imported_items_preview_container">
                        <p class="text-xs text-brand-white/30 italic">No line items loaded yet. Use the file import tool above to parse spreadsheets.</p>
                    </div>
                </div>

                <!-- Collaborators assignment section -->
                <div class="glass-panel rounded-xl p-4 border border-brand-white/10 bg-brand-white/[0.02] space-y-4"
                     x-data="{
                        collaborators: [],
                        newUserId: '',
                        newPermission: 'view',
                        addCollaborator() {
                            if (!this.newUserId) return;
                            const selectEl = document.getElementById('new_user_select');
                            const userName = selectEl.options[selectEl.selectedIndex].text;
                            
                            if (this.collaborators.some(c => c.user_id == this.newUserId)) {
                                alert('This user is already a collaborator.');
                                return;
                            }
                            
                            this.collaborators.push({
                                user_id: this.newUserId,
                                name: userName,
                                permission: this.newPermission
                            });
                            
                            this.newUserId = '';
                        },
                        removeCollaborator(index) {
                            this.collaborators.splice(index, 1);
                        }
                     }">
                    <h3 class="text-xs uppercase tracking-wider text-brand-ash font-bold border-b border-brand-white/5 pb-2">👥 Collaborators</h3>
                    
                    <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                        <template x-for="(collab, index) in collaborators" :key="collab.user_id">
                            <div class="flex items-center justify-between gap-3 bg-brand-white/5 border border-brand-white/10 rounded-xl p-2.5">
                                <div>
                                    <p class="text-xs font-semibold text-brand-white" x-text="collab.name"></p>
                                    <p class="text-[10px] text-brand-ash capitalize" x-text="collab.permission + ' permission'"></p>
                                </div>
                                
                                <input type="hidden" :name="'collaborators[' + index + '][user_id]'" :value="collab.user_id">
                                <input type="hidden" :name="'collaborators[' + index + '][permission]'" :value="collab.permission">

                                <button type="button" @click="removeCollaborator(index)" class="text-brand-red/80 hover:text-brand-red text-xs">
                                    ✕ Remove
                                </button>
                            </div>
                        </template>
                        <template x-if="collaborators.length === 0">
                            <p class="text-xs text-brand-white/30 italic text-center py-4">No collaborators assigned.</p>
                        </template>
                    </div>

                    <div class="border-t border-brand-white/5 pt-3 space-y-3">
                        <p class="text-[10px] uppercase tracking-wider text-brand-ash">Invite Coworker</p>
                        <div class="grid grid-cols-2 gap-2">
                            <select id="new_user_select" x-model="newUserId" class="rounded-lg border border-brand-white/10 bg-brand-black text-xs text-brand-white p-2">
                                <option value="">Select staff member...</option>
                                @foreach($allStaff as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                            <select x-model="newPermission" class="rounded-lg border border-brand-white/10 bg-brand-black text-xs text-brand-white p-2">
                                <option value="view">View Only</option>
                                <option value="edit">View & Edit</option>
                            </select>
                        </div>
                        <button type="button" @click="addCollaborator()" class="w-full rounded bg-brand-white/10 hover:bg-brand-white/15 py-1.5 text-xs font-semibold text-brand-white transition">
                            + Add to List
                        </button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-6 border-t border-brand-white/10">
                    <a href="{{ route('portal.finance.budgets.index') }}" class="px-5 py-2.5 rounded-xl text-xs uppercase tracking-wider text-brand-white/60 hover:text-brand-white bg-brand-white/5 hover:bg-brand-white/10 transition">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider text-brand-black bg-amber-500 hover:bg-amber-400 transition shadow-lg shadow-amber-500/20">
                        Save & Add Items
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Parser Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <script>
        let importedLineItems = [];

        function cleanAndParseFloat(val) {
            if (typeof val === 'number') return val;
            if (val === undefined || val === null || val === '') return 0.0;
            const cleaned = String(val).replace(/[^0-9.-]/g, '');
            return parseFloat(cleaned) || 0.0;
        }

        function handleFileImport(input) {
            const file = input.files[0];
            if (!file) return;

            document.getElementById('import_file_name').innerText = file.name;
            const spinner = document.getElementById('import_spinner');
            spinner.classList.remove('hidden');

            const ext = file.name.split('.').pop().toLowerCase();
            setTimeout(() => {
                try {
                    if (ext === 'docx') {
                        parseDocx(file);
                    } else if (ext === 'pptx') {
                        parsePptx(file);
                    } else if (['xlsx', 'xls', 'csv', 'xlsm', 'xlsb'].includes(ext)) {
                        parseExcel(file);
                    } else if (['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'].includes(ext) || file.type.startsWith('image/')) {
                        parseImage(file);
                    } else if (['txt', 'md', 'html', 'htm', 'xml', 'json'].includes(ext) || file.type.startsWith('text/')) {
                        parseText(file);
                    } else {
                        parseFallback(file);
                    }
                } catch (err) {
                    console.error(err);
                    alert('Error parsing file: ' + err.message);
                    spinner.classList.add('hidden');
                }
            }, 500);
        }

        function parseImage(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const dataUrl = e.target.result;
                    const html = `<p><img src="${dataUrl}" alt="${file.name}" style="max-width: 100%; height: auto; border-radius: 8px;" /></p><br>`;
                    try {
                        const editor = window.budgetEditor;
                        if (editor) {
                            editor.setData(editor.getData() + html);
                        } else {
                            document.getElementById('content').value += html;
                        }
                    } catch (editorErr) {
                        console.error("CKEditor error:", editorErr);
                        document.getElementById('content').value += html;
                    }
                    
                    const msgEl = document.getElementById('import_status_msg');
                    msgEl.className = 'text-[10px] mt-0.5 text-emerald-400 font-semibold';
                    msgEl.innerHTML = `✅ Successfully embedded image!`;
                    msgEl.classList.remove('hidden');
                } catch (err) {
                    console.error(err);
                    alert("Failed to embed image: " + err.message);
                } finally {
                    document.getElementById('import_spinner').classList.add('hidden');
                }
            };
            reader.readAsDataURL(file);
        }

        function parseText(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const text = e.target.result;
                    const isHtml = file.name.endsWith('.html') || file.name.endsWith('.htm');
                    const html = isHtml ? text : `<pre style="white-space: pre-wrap; font-family: monospace; background: rgba(255,255,255,0.05); padding: 10px; border-radius: 6px;">${text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</pre><br>`;
                    try {
                        const editor = window.budgetEditor;
                        if (editor) {
                            editor.setData(editor.getData() + html);
                        } else {
                            document.getElementById('content').value += html;
                        }
                    } catch (editorErr) {
                        console.error("CKEditor error:", editorErr);
                        document.getElementById('content').value += html;
                    }
                    
                    const msgEl = document.getElementById('import_status_msg');
                    msgEl.className = 'text-[10px] mt-0.5 text-emerald-400 font-semibold';
                    msgEl.innerHTML = `✅ Text file loaded into editor.`;
                    msgEl.classList.remove('hidden');
                } catch (err) {
                    console.error(err);
                    alert("Failed to parse text file: " + err.message);
                } finally {
                    document.getElementById('import_spinner').classList.add('hidden');
                }
            };
            reader.readAsText(file);
        }

        function parseFallback(file) {
            try {
                const sizeKB = (file.size / 1024).toFixed(1);
                const html = `
                    <div style="border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); padding: 12px; border-radius: 8px; margin: 10px 0; display: inline-block;">
                        <strong>📎 Imported Reference File:</strong> ${file.name} (${sizeKB} KB)<br>
                        <span style="font-size: 11px; opacity: 0.6;">Type: ${file.type || 'Unknown'} - Loaded directly from device.</span>
                    </div><br>
                `;
                try {
                    const editor = window.budgetEditor;
                    if (editor) {
                        editor.setData(editor.getData() + html);
                    } else {
                        document.getElementById('content').value += html;
                    }
                } catch (editorErr) {
                    console.error("CKEditor error:", editorErr);
                    document.getElementById('content').value += html;
                }
                
                const msgEl = document.getElementById('import_status_msg');
                msgEl.className = 'text-[10px] mt-0.5 text-amber-400 font-semibold';
                msgEl.innerHTML = `📎 Loaded reference card for ${file.name}.`;
                msgEl.classList.remove('hidden');
            } catch (err) {
                console.error(err);
                alert("Failed to import file reference: " + err.message);
            } finally {
                document.getElementById('import_spinner').classList.add('hidden');
            }
        }

        function parseDocx(file) {
            const reader = new FileReader();
            reader.onload = function(loadEvent) {
                const arrayBuffer = loadEvent.target.result;
                mammoth.convertToHtml({arrayBuffer: arrayBuffer})
                    .then(function(result) {
                        const html = result.value;
                        try {
                            const editor = window.budgetEditor;
                            if (editor) {
                                editor.setData(editor.getData() + html);
                            } else {
                                document.getElementById('content').value += html;
                            }
                        } catch (editorErr) {
                            console.error("CKEditor error:", editorErr);
                            document.getElementById('content').value += html;
                        }
                        
                        const msgEl = document.getElementById('import_status_msg');
                        msgEl.className = 'text-[10px] mt-0.5 text-emerald-400 font-semibold';
                        msgEl.innerHTML = `✅ Word document loaded!`;
                        msgEl.classList.remove('hidden');
                    })
                    .catch(function(err) {
                        console.error(err);
                        alert("Failed to parse Word document: " + err.message);
                        
                        const msgEl = document.getElementById('import_status_msg');
                        msgEl.className = 'text-[10px] mt-0.5 text-red-400 font-semibold';
                        msgEl.innerHTML = `❌ Failed to parse docx.`;
                        msgEl.classList.remove('hidden');
                    })
                    .finally(function() {
                        document.getElementById('import_spinner').classList.add('hidden');
                    });
            };
            reader.readAsArrayBuffer(file);
        }

        function parsePptx(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                JSZip.loadAsync(e.target.result).then(async function(zip) {
                    let slideFiles = [];
                    zip.forEach(function (relativePath, zipEntry) {
                        if (relativePath.match(/ppt\/slides\/slide\d+\.xml/)) {
                            slideFiles.push(zipEntry);
                        }
                    });
                    
                    slideFiles.sort((a, b) => {
                        const numA = parseInt(a.name.match(/\d+/)[0]);
                        const numB = parseInt(b.name.match(/\d+/)[0]);
                        return numA - numB;
                    });

                    let fullHtml = '';
                    for (let slideFile of slideFiles) {
                        const xmlText = await slideFile.async("string");
                        const parser = new DOMParser();
                        const xmlDoc = parser.parseFromString(xmlText, "text/xml");
                        const textElements = xmlDoc.getElementsByTagName("a:t");
                        let slideText = [];
                        for (let textEl of textElements) {
                            if (textEl.textContent.trim()) {
                                slideText.push(textEl.textContent.trim());
                            }
                        }
                        const slideNum = slideFile.name.match(/\d+/)[0];
                        if (slideText.length > 0) {
                            fullHtml += `<h3>Slide ${slideNum}</h3><ul>`;
                            slideText.forEach(txt => {
                                fullHtml += `<li>${txt}</li>`;
                            });
                            fullHtml += `</ul><br>`;
                        }
                    }
                    if (fullHtml) {
                        try {
                            const editor = window.budgetEditor;
                            if (editor) {
                                editor.setData(editor.getData() + fullHtml);
                            } else {
                                document.getElementById('content').value += fullHtml;
                            }
                        } catch (editorErr) {
                            console.error("CKEditor error:", editorErr);
                            document.getElementById('content').value += fullHtml;
                        }
                    }
                    
                    const msgEl = document.getElementById('import_status_msg');
                    msgEl.className = 'text-[10px] mt-0.5 text-emerald-400 font-semibold';
                    msgEl.innerHTML = `✅ PPTX slides parsed!`;
                    msgEl.classList.remove('hidden');
                }).catch(err => {
                    console.error(err);
                    alert("Failed to parse PowerPoint presentation: " + err.message);
                    
                    const msgEl = document.getElementById('import_status_msg');
                    msgEl.className = 'text-[10px] mt-0.5 text-red-400 font-semibold';
                    msgEl.innerHTML = `❌ Failed to parse pptx.`;
                    msgEl.classList.remove('hidden');
                }).finally(() => {
                    document.getElementById('import_spinner').classList.add('hidden');
                });
            };
            reader.readAsArrayBuffer(file);
        }

        function parseExcel(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, {type: 'array'});
                    
                    let fullHtml = '';
                    let allItems = [];
                    workbook.SheetNames.forEach(function(sheetName) {
                        const worksheet = workbook.Sheets[sheetName];
                        if (!worksheet) return;

                        // 1. Convert to HTML for editor preview.
                        // Truncate tables with more than 50 rows to avoid CKEditor maximum call stack size overflow.
                        const range = XLSX.utils.decode_range(worksheet['!ref'] || 'A1:A1');
                        const totalRows = range.e.r - range.s.r + 1;
                        if (totalRows > 50) {
                            const truncatedRange = { s: range.s, e: { r: Math.min(range.s.r + 10, range.e.r), c: range.e.c } };
                            const worksheetTruncated = { ...worksheet, '!ref': XLSX.utils.encode_range(truncatedRange) };
                            const htmlTable = XLSX.utils.sheet_to_html(worksheetTruncated);
                            fullHtml += `<h3>Sheet: ${sheetName} (Truncated Preview)</h3>` + htmlTable + `<p><i>* Only showing first 10 rows of ${totalRows} in the editor preview. All rows have been extracted as budget line items below.</i></p><br>`;
                        } else {
                            const htmlTable = XLSX.utils.sheet_to_html(worksheet);
                            fullHtml += `<h3>Sheet: ${sheetName}</h3>` + htmlTable + `<br>`;
                        }
                        
                        // 2. Parse spreadsheet rows dynamically to extract line items.
                        const jsonRows = XLSX.utils.sheet_to_json(worksheet, {header: 1});
                        if (jsonRows.length > 0) {
                            // Find the header row by scoring candidate rows (0 to 24)
                            let bestRowIdx = -1;
                            let bestScore = 0;
                            
                            for (let r = 0; r < Math.min(25, jsonRows.length); r++) {
                                const row = jsonRows[r];
                                if (!row || row.length === 0) continue;
                                let score = 0;
                                row.forEach(cell => {
                                    const val = String(cell || '').toLowerCase().trim();
                                    if (!val) return;
                                    if (val.includes('desc') || val.includes('item') || val.includes('particular') || val.includes('name') || val.includes('purpose') || val.includes('project') || val.includes('program')) score += 2;
                                    if (val.includes('qty') || val.includes('quant') || val.includes('count') || val.includes('unit')) score += 1;
                                    if (val.includes('price') || val.includes('rate') || val.includes('cost') || val.includes('amount') || val.includes('total') || val.includes('allocation') || val.includes('sum') || val.includes('value') || val.includes('budget')) score += 1.5;
                                    if (val.includes('cat') || val.includes('group') || val.includes('class') || val.includes('dept') || val.includes('ministry') || val.includes('sector')) score += 1;
                                });
                                if (score > bestScore && score >= 2) {
                                    bestScore = score;
                                    bestRowIdx = r;
                                }
                            }
                            
                            // Map columns based on the scored header row
                            let descIdx = -1, qtyIdx = -1, priceIdx = -1, totalIdx = -1, catIdx = -1;
                            let amountCols = [];
                            
                            if (bestRowIdx !== -1) {
                                const headerRow = jsonRows[bestRowIdx];
                                let descScore = 0;
                                let catScore = 0;
                                
                                for (let c = 0; c < headerRow.length; c++) {
                                    const val = String(headerRow[c] || '').toLowerCase().trim();
                                    if (!val) continue;
                                    
                                    // Match Description
                                    let dScore = 0;
                                    if (val.includes('description') || val.includes('particulars') || val.includes('desc')) dScore = 4;
                                    else if (val.includes('item') || val.includes('project description') || val.includes('project_description') || val.includes('program description')) dScore = 3.5;
                                    else if (val.includes('project') || val.includes('program') || val.includes('purpose')) dScore = 3;
                                    else if (val.includes('name') || val.includes('title')) dScore = 2;
                                    
                                    if (dScore > descScore) {
                                        descScore = dScore;
                                        descIdx = c;
                                    }
                                    
                                    // Match Category
                                    let cScore = 0;
                                    if (val.includes('category') || val.includes('classification')) cScore = 4;
                                    else if (val.includes('group') || val.includes('class')) cScore = 3;
                                    else if (val.includes('ministry') || val.includes('dept') || val.includes('department') || val.includes('sector')) cScore = 2;
                                    
                                    if (cScore > catScore) {
                                        catScore = cScore;
                                        catIdx = c;
                                    }
                                    
                                    // Match Quantity
                                    if (qtyIdx === -1 && (val.includes('qty') || val.includes('quant') || val.includes('count') || val.includes('units') || val.includes('volume'))) {
                                        qtyIdx = c;
                                    }
                                    
                                    // Match Price
                                    if (priceIdx === -1 && (val.includes('price') || val.includes('rate') || val.includes('unit cost') || val.includes('unit_price') || val.includes('unit-cost') || val.includes('unitprice'))) {
                                        priceIdx = c;
                                    }
                                    
                                    // Match Total Amount / Allocation
                                    if (totalIdx === -1 && (val.includes('total') || val.includes('amount') || val.includes('allocation') || val.includes('sum') || val.includes('projected') || val.includes('value') || val.includes('budget'))) {
                                        totalIdx = c;
                                    }
                                }
                                
                                // Identify sub-amount columns (like Q1, Q2, Q3, Q4) to sum up if total is empty
                                for (let c = 0; c < headerRow.length; c++) {
                                    const val = String(headerRow[c] || '').toLowerCase().trim();
                                    if (val.includes('allocation') || val.includes('amount') || val.includes('price') || val.includes('cost') || val.includes('value') || val.includes('val')) {
                                        if (c !== totalIdx && c !== priceIdx) {
                                            amountCols.push(c);
                                        }
                                    }
                                }
                            }
                            
                            // Default column indices fallback
                            if (descIdx === -1) descIdx = 0;
                            if (qtyIdx === -1) qtyIdx = 1;
                            if (priceIdx === -1) priceIdx = 2;
                            if (catIdx === -1) catIdx = 3;
                            
                            const startRow = (bestRowIdx !== -1) ? bestRowIdx + 1 : 1;
                            for (let r = startRow; r < jsonRows.length; r++) {
                                const row = jsonRows[r];
                                if (row && row.length > 0) {
                                    const desc = row[descIdx];
                                    if (desc && String(desc).trim()) {
                                        // Parse quantity
                                        let qty = 1;
                                        if (qtyIdx !== -1 && row[qtyIdx] !== undefined && row[qtyIdx] !== '') {
                                            qty = parseInt(String(row[qtyIdx]).replace(/[^0-9-]/g, '')) || 1;
                                        }
                                        
                                        // Parse price
                                        let price = 0.0;
                                        if (priceIdx !== -1 && row[priceIdx] !== undefined && row[priceIdx] !== '') {
                                            price = cleanAndParseFloat(row[priceIdx]);
                                        } else if (totalIdx !== -1 && row[totalIdx] !== undefined && row[totalIdx] !== '' && !isNaN(cleanAndParseFloat(row[totalIdx]))) {
                                            price = cleanAndParseFloat(row[totalIdx]) / qty;
                                        } else if (amountCols.length > 0) {
                                            let sum = 0.0;
                                            amountCols.forEach(c => {
                                                if (row[c] !== undefined && row[c] !== '') {
                                                    sum += cleanAndParseFloat(row[c]);
                                                }
                                            });
                                            price = sum / qty;
                                        }
                                        
                                        const cat = (catIdx !== -1 && row[catIdx]) ? String(row[catIdx]).trim() : 'General';
                                        
                                        allItems.push({
                                            description: String(desc).trim(),
                                            quantity: qty,
                                            unit_price: price,
                                            category: cat
                                        });
                                    }
                                }
                            }
                        }
                    });
                    
                    try {
                        const editor = window.budgetEditor;
                        if (editor) {
                            editor.setData(editor.getData() + fullHtml);
                        } else {
                            document.getElementById('content').value += fullHtml;
                        }
                    } catch (editorErr) {
                        console.error("CKEditor error:", editorErr);
                        document.getElementById('content').value += fullHtml;
                    }
                    
                    if (allItems.length > 0) {
                        addImportedItemsToPreview(allItems);
                    }
                    
                    const msgEl = document.getElementById('import_status_msg');
                    msgEl.className = 'text-[10px] mt-0.5 text-emerald-400 font-semibold';
                    msgEl.innerHTML = `✅ Excel loaded! ${allItems.length} items extracted.`;
                    msgEl.classList.remove('hidden');
                } catch (err) {
                    console.error(err);
                    alert("Failed to parse Excel spreadsheet: " + err.message);
                    
                    const msgEl = document.getElementById('import_status_msg');
                    msgEl.className = 'text-[10px] mt-0.5 text-red-400 font-semibold';
                    msgEl.innerHTML = `❌ Failed to parse sheet.`;
                    msgEl.classList.remove('hidden');
                } finally {
                    document.getElementById('import_spinner').classList.add('hidden');
                }
            };
            reader.readAsArrayBuffer(file);
        }

        function addImportedItemsToPreview(items) {
            importedLineItems = importedLineItems.concat(items);
            updateItemsPreview();
        }

        function updateItemsPreview() {
            const container = document.getElementById('imported_items_preview_container');
            const inputField = document.getElementById('imported_items_json');
            if (!container) return;
            
            inputField.value = JSON.stringify(importedLineItems);
            
            if (importedLineItems.length === 0) {
                container.innerHTML = `<p class="text-xs text-brand-white/30 italic text-center py-4">No line items imported yet.</p>`;
                return;
            }
            
            let html = `
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-brand-white/70">
                        <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                            <tr>
                                <th class="py-2.5">Category</th>
                                <th class="py-2.5">Description</th>
                                <th class="py-2.5">Quantity</th>
                                <th class="py-2.5">Unit Price</th>
                                <th class="py-2.5">Total</th>
                                <th class="py-2.5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5">
            `;
            
            let total = 0;
            importedLineItems.forEach((item, index) => {
                const itemTotal = item.quantity * item.unit_price;
                total += itemTotal;
                html += `
                    <tr>
                        <td class="py-2 font-semibold text-brand-white">${item.category || 'General'}</td>
                        <td class="py-2">${item.description}</td>
                        <td class="py-2">${item.quantity}</td>
                        <td class="py-2 font-mono">${item.unit_price.toFixed(2)}</td>
                        <td class="py-2 text-emerald-400 font-bold font-mono">${itemTotal.toFixed(2)}</td>
                        <td class="py-2 text-right">
                            <button type="button" onclick="removeImportedItem(${index})" class="text-brand-red/70 hover:text-brand-red font-semibold cursor-pointer">✕ Remove</button>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        <tr class="font-bold border-t border-brand-white/10">
                            <td colspan="4" class="py-3 text-right text-brand-ash uppercase">Total Imported:</td>
                            <td class="py-3 text-emerald-400 font-mono">${total.toFixed(2)}</td>
                            <td></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            `;
            
            container.innerHTML = html;
        }

        window.removeImportedItem = function(index) {
            importedLineItems.splice(index, 1);
            updateItemsPreview();
        };
    </script>
</x-app-layout>
