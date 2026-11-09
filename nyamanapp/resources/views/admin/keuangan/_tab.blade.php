{{-- Tab bagian Keuangan: saldo rekening harian dan keuntungan bulanan. --}}
<nav class="keu-tab" aria-label="Bagian keuangan">
    <a href="{{ route('admin.keuangan.saldo') }}" class="{{ request()->routeIs('admin.keuangan.saldo') ? 'on' : '' }}">
        @include('partials.icon', ['name' => 'chart', 'class' => 'ic-sm']) Saldo Rekening
    </a>
    <a href="{{ route('admin.keuangan.index') }}" class="{{ request()->routeIs('admin.keuangan.index') ? 'on' : '' }}">
        @include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-sm']) Keuntungan &amp; Bonus
    </a>
    <a href="{{ route('admin.keuangan.pajak') }}" class="{{ request()->routeIs('admin.keuangan.pajak*') ? 'on' : '' }}">
        @include('partials.icon', ['name' => 'check', 'class' => 'ic-sm']) Pajak
    </a>
    <a href="{{ route('admin.keuangan.buku-besar') }}" class="{{ request()->routeIs('admin.keuangan.buku-besar*') ? 'on' : '' }}">
        @include('partials.icon', ['name' => 'inbox', 'class' => 'ic-sm']) Buku Besar
    </a>
</nav>

@once
@push('head')
<style>
    .keu-tab {
        display: inline-flex; gap: 4px; padding: 4px; margin-bottom: 22px;
        border: 1px solid var(--border-2); border-radius: 999px; background: var(--bg);
        max-width: 100%; overflow-x: auto;
    }
    .keu-tab a {
        display: inline-flex; align-items: center; gap: 8px; white-space: nowrap;
        padding: 9px 16px; border-radius: 999px; text-decoration: none;
        color: var(--muted); font-weight: 700; font-size: .88rem;
    }
    .keu-tab a:hover { color: var(--text); background: var(--hover); }
    .keu-tab a.on { background: var(--grad); color: #fff; }
    @media (max-width: 480px) {
        .keu-tab { display: grid; grid-template-columns: 1fr 1fr; width: 100%; border-radius: 18px; }
        .keu-tab a { justify-content: center; padding: 9px 8px; font-size: .82rem; }
        .keu-tab a .ic-sm { display: none; }
    }
</style>
@endpush
@endonce
