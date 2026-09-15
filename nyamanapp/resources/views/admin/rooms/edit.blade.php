@extends('layouts.panel')

@section('title', 'Ubah Booking Kamar (Admin)')

{{-- Gaya zona hapus ikut dilewati bila penggunanya tidak boleh menghapus. --}}
@push('head')
<style>
    /* Zona hapus: sengaja dipisah di bawah form dan diberi warna berbeda,
       supaya tidak bersebelahan dengan tombol simpan. */
    .danger-zone {
        max-width: 680px; margin-top: 18px; padding: 22px;
        border: 1px solid var(--danger-line); border-radius: 18px;
        background: var(--danger-soft);
    }
    .danger-zone h2 {
        margin: 0 0 4px; font-size: .78rem; font-weight: 800;
        letter-spacing: .07em; text-transform: uppercase; color: var(--danger);
    }
    .danger-zone p { margin: 0 0 16px; font-size: .86rem; color: var(--muted); }
    .danger-actions { display: flex; gap: 10px; flex-wrap: wrap; }
</style>
@endpush

@section('content')
    <div class="page-head">
        <h1>Ubah Booking Kamar</h1>
        <p>Kamar <strong>{{ $booking->room?->nomor }}</strong> — {{ $booking->nama_tamu }}</p>
    </div>

    <div class="card form-card">
        <form method="POST" action="{{ route('admin.rooms.update', $booking) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('staff.rooms.form')

            <div class="form-actions">
                <a href="{{ route('admin.rooms.index', ['tanggal' => $booking->check_in->toDateString(), 'bulan' => $booking->check_in->format('Y-m')]) }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-accent">Simpan Perubahan</button>
            </div>
        </form>
    </div>

    {{-- Menghapus booking = menghapus jejak, jadi tombolnya hanya ada untuk
         superadmin. Route-nya pun dijaga terpisah (role:superadmin), sehingga
         menyembunyikan tombol di sini bukan satu-satunya penghalang. --}}
    @if (auth()->user()->bolehHapusBooking())
    <div class="danger-zone">
        <h2>Hapus Booking</h2>
        <p>Tindakan ini tidak bisa dibatalkan.</p>

        <div class="danger-actions">
            <form method="POST" action="{{ route('admin.rooms.destroy', $booking) }}"
                  onsubmit="return confirm('Hapus booking kamar {{ $booking->room?->nomor }} atas nama {{ $booking->nama_tamu }}?');">
                @csrf
                @method('DELETE')
                <input type="hidden" name="scope" value="one">
                <button type="submit" class="btn btn-danger">
                    Hapus Kamar {{ $booking->room?->nomor }}
                </button>
            </form>

            @if ($groupCount > 1)
                <form method="POST" action="{{ route('admin.rooms.destroy', $booking) }}"
                      onsubmit="return confirm('Hapus SELURUH grup pesanan {{ $booking->nama_tamu }} ({{ $groupCount }} kamar)?');">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="scope" value="group">
                    <button type="submit" class="btn btn-danger">
                        Hapus Seluruh Grup ({{ $groupCount }} kamar)
                    </button>
                </form>
            @endif
        </div>
    </div>
    @endif
@endsection
