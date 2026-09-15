@extends('layouts.panel')

@section('title', 'Tambah Booking Kamar')

@section('content')
    <div class="page-head">
        <h1>Tambah Booking Kamar</h1>
        <p>Centang kamar yang dipesan (boleh lebih dari satu), isi data tamu sekali, lalu simpan. Foto KTP diunggah saat tamu check-in.</p>
    </div>

    <div class="card form-card">
        {{-- multipart: formulir ini kini bisa membawa foto bukti pembayaran. --}}
        <form method="POST" action="{{ \App\Support\Panel::rute('rooms.store') }}" enctype="multipart/form-data">
            @csrf
            @include('staff.rooms.form')

            <div class="form-actions">
                <a href="{{ \App\Support\Panel::rute('rooms.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-accent">Simpan</button>
            </div>
        </form>
    </div>
@endsection
