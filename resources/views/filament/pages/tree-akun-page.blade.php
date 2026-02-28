<x-filament-panels::page>

    {{-- Search Bar --}}
    <div class="mb-4">
        <input
            type="text"
            id="searchAkun"
            placeholder="Cari kode atau nama akun..."
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            oninput="filterAkun(this.value)"
        />
    </div>

    {{-- Expand / Collapse All --}}
    <div class="mb-4 flex gap-2">
        <button
            onclick="expandAll()"
            class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700 transition"
        >
            Expand Semua
        </button>
        <button
            onclick="collapseAll()"
            class="rounded-lg bg-gray-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-600 transition"
        >
            Collapse Semua
        </button>
    </div>

    <div id="tree-container" class="space-y-2">
        @foreach ($indukAkuns as $induk)
            @include('filament.components.tree-node-induk', ['induk' => $induk])
        @endforeach
    </div>

    {{-- Styles --}}
    <style>
        .tree-children {
            overflow: hidden;
            transition: max-height 0.3s ease, opacity 0.3s ease;
            max-height: 0;
            opacity: 0;
        }
        .tree-children.open {
            max-height: 99999px;
            opacity: 1;
        }
        .tree-toggle-btn {
            cursor: pointer;
            user-select: none;
        }
        .tree-toggle-icon {
            transition: transform 0.2s ease;
            display: inline-block;
        }
        .tree-toggle-btn.open .tree-toggle-icon {
            transform: rotate(90deg);
        }
        .akun-row {
            transition: background 0.15s;
        }
        .akun-row:hover {
            background: rgba(99,102,241,0.07);
        }
        .badge-debet {
            background: #dbeafe;
            color: #1d4ed8;
            border-radius: 9999px;
            padding: 1px 8px;
            font-size: 11px;
            font-weight: 600;
        }
        .dark .badge-debet {
            background: #1e3a5f;
            color: #93c5fd;
        }
        .badge-kredit {
            background: #fce7f3;
            color: #be185d;
            border-radius: 9999px;
            padding: 1px 8px;
            font-size: 11px;
            font-weight: 600;
        }
        .dark .badge-kredit {
            background: #4a1942;
            color: #f9a8d4;
        }
        .hidden-search {
            display: none !important;
        }
    </style>

    {{-- Scripts --}}
    <script>
        function toggleNode(btn) {
            btn.classList.toggle('open');
            const children = btn.nextElementSibling;
            if (children && children.classList.contains('tree-children')) {
                children.classList.toggle('open');
            }
        }

        function expandAll() {
            document.querySelectorAll('.tree-toggle-btn').forEach(btn => {
                btn.classList.add('open');
            });
            document.querySelectorAll('.tree-children').forEach(el => {
                el.classList.add('open');
            });
        }

        function collapseAll() {
            document.querySelectorAll('.tree-toggle-btn').forEach(btn => {
                btn.classList.remove('open');
            });
            document.querySelectorAll('.tree-children').forEach(el => {
                el.classList.remove('open');
            });
        }

        function filterAkun(query) {
            const q = query.toLowerCase().trim();
            const rows = document.querySelectorAll('[data-akun-search]');

            if (!q) {
                // Reset: tampilkan semua, collapse semuanya
                rows.forEach(row => row.closest('[data-akun-wrapper]')?.classList.remove('hidden-search'));
                document.querySelectorAll('[data-akun-wrapper]').forEach(w => w.classList.remove('hidden-search'));
                collapseAll();
                return;
            }

            // Sembunyikan semua wrapper dulu
            document.querySelectorAll('[data-akun-wrapper]').forEach(w => w.classList.add('hidden-search'));

            // Cari yang cocok, tampilkan beserta parent-nya
            rows.forEach(row => {
                const text = row.getAttribute('data-akun-search');
                if (text.includes(q)) {
                    // Tampilkan elemen ini beserta semua parent-nya
                    let el = row.closest('[data-akun-wrapper]');
                    while (el) {
                        el.classList.remove('hidden-search');
                        // Buka toggle parent
                        const prevSibling = el.previousElementSibling;
                        if (prevSibling && prevSibling.classList.contains('tree-toggle-btn')) {
                            prevSibling.classList.add('open');
                            const children = prevSibling.nextElementSibling;
                            if (children) children.classList.add('open');
                        }
                        // Naik ke parent tree
                        const parentChildren = el.closest('.tree-children');
                        if (parentChildren) {
                            parentChildren.classList.add('open');
                            const parentToggle = parentChildren.previousElementSibling;
                            if (parentToggle) parentToggle.classList.add('open');
                            el = parentChildren.closest('[data-akun-wrapper]');
                        } else {
                            break;
                        }
                    }
                }
            });
        }
    </script>

</x-filament-panels::page>