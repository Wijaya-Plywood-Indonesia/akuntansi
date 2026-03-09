(function () {
    let attempts = 0;
    const maxAttempts = 20; // 20 × 500ms = 10 detik

    const interval = setInterval(function () {
        attempts++;

        if (typeof window.Echo !== 'undefined') {
            clearInterval(interval);
            console.log('[JurnalRealtime] Echo siap, mulai listening...');

            window.Echo.channel('jurnal-masuk')
                .listen('.jurnal.baru', function (data) {
                    console.log('[JurnalRealtime] Data masuk:', data);

                    // ── Format rupiah ────────────────────────────────────
                    const formatter = new Intl.NumberFormat('id-ID', {
                        style:                 'currency',
                        currency:              'IDR',
                        minimumFractionDigits: 0,
                    });

                    const total = formatter.format(data.total_nilai ?? 0);

                    const body = [
                        'No. Jurnal : #' + data.no_jurnal,
                        'Supplier   : ' + data.supplier,
                        'Tanggal    : ' + data.tanggal,
                        'Total      : ' + total,
                    ].join('\n');

                    // ── Notifikasi Filament v5 ───────────────────────────
                    document.dispatchEvent(
                        new CustomEvent('filament-notification-dispatched', {
                            detail: {
                                notification: {
                                    id:        'jurnal-baru-' + data.no_jurnal + '-' + Date.now(),
                                    title:     '📦 Jurnal Baru Masuk!',
                                    body:      body,
                                    color:     'success',
                                    duration:  10000,
                                    icon:      'heroicon-o-check-circle',
                                    iconColor: 'success',
                                }
                            },
                            bubbles: true,
                        })
                    );

                    // ── Auto refresh tabel — Livewire v3 ─────────────────
                    // Di Livewire v3, setiap component punya instance sendiri
                    // Kita loop semua element [wire:id] dan refresh masing-masing
                    setTimeout(function () {
                        try {
                            // Ambil semua Livewire component yang aktif di halaman
                            const components = document.querySelectorAll('[wire\\:id]');

                            if (components.length === 0) {
                                console.warn('[JurnalRealtime] Tidak ada Livewire component ditemukan.');
                                return;
                            }

                            components.forEach(function (el) {
                                const wireId = el.getAttribute('wire:id');
                                if (! wireId) return;

                                // Cari component instance via Livewire.find()
                                const component = Livewire.find(wireId);

                                if (component) {
                                    component.$refresh();
                                    console.log('[JurnalRealtime] Component ' + wireId + ' di-refresh.');
                                }
                            });

                        } catch (err) {
                            console.error('[JurnalRealtime] Gagal refresh tabel:', err);
                        }
                    }, 800); // delay 800ms agar notifikasi muncul dulu

                });

        } else if (attempts >= maxAttempts) {
            clearInterval(interval);
            console.warn('[JurnalRealtime] Echo tidak tersedia setelah 10 detik.');
        }

    }, 500);
})();