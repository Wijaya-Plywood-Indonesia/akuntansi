<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Jurnal implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int    $noJurnal,
        public string $noDokumen,
        public string $supplier,
        public string $tanggal,
        public float  $totalNilai,
        public string $keterangan,
    ) {}

    // Channel publik — semua user P2 yang buka halaman jurnal akan terima
    public function broadcastOn(): Channel
    {
        return new Channel('jurnal-masuk');
    }

    // Nama event yang didengarkan di frontend
    public function broadcastAs(): string
    {
        return 'jurnal.baru';
    }

    // Data yang dikirim ke frontend
    public function broadcastWith(): array
    {
        return [
            'no_jurnal'   => $this->noJurnal,
            'no_dokumen'  => $this->noDokumen,
            'supplier'    => $this->supplier,
            'tanggal'     => $this->tanggal,
            'total_nilai' => $this->totalNilai,
            'keterangan'  => $this->keterangan,
        ];
    }
}
