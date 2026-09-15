@php
    /**
     * Tombol "Hapus Data Bulan" — pasangan tombol Unduh Excel.
     *
     * Konfirmasinya sengaja cerewet: menyebut bulan yang akan dihapus dan
     * mengingatkan untuk mengunduh arsip Excel lebih dulu, karena tindakan
     * ini tidak bisa dibatalkan dan ikut menghapus berkas foto dari disk.
     *
     * Parameter:
     *   action - route tujuan (mis. route('admin.purge.bookings'))
     *   bulan  - 'YYYY-MM' yang dihapus (biasanya bulan yang sedang tampil)
     *   label  - nama data untuk kalimat konfirmasi (mis. 'booking kamar')
     */
    $namaBulan = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $bulan . '-01')->translatedFormat('F Y');
@endphp

@once
@push('head')
<style>
    .btn-purge {
        display: inline-flex; align-items: center; gap: 8px; cursor: pointer;
        font-family: inherit; font-weight: 700; font-size: .88rem;
        background: transparent; color: var(--danger);
        border: 1px solid var(--danger-line); border-radius: 12px; padding: 11px 18px;
        transition: background .15s;
    }
    .btn-purge:hover { background: var(--danger-soft); }
</style>
@endpush
@endonce

{{-- Hanya superadmin. Route purge dijaga terpisah (role:superadmin), jadi
     penjaga sebenarnya ada di sana; ini supaya tombolnya tidak menggoda
     untuk ditekan oleh peran yang pasti ditolak. --}}
@if (auth()->user()?->bolehHapusDataBulanan())
<form method="POST" action="{{ $action }}"
      onsubmit="return confirm('Hapus SEMUA data {{ $label }} bulan {{ $namaBulan }}?\n\nBerkas foto terkait ikut terhapus dari server dan TIDAK BISA dikembalikan.\n\nPastikan Anda sudah menekan Unduh Excel untuk arsip terlebih dahulu.')">
    @csrf
    <input type="hidden" name="bulan" value="{{ $bulan }}">
    <button type="submit" class="btn-purge">
        @include('partials.icon', ['name' => 'x', 'class' => 'ic-sm'])
        Hapus Data {{ $namaBulan }}
    </button>
</form>
@endif
