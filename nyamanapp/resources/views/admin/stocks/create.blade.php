@extends('layouts.panel')

@section('title', 'Tambah Stok')

@section('content')
    <div class="page-head">
        <h1>Tambah Stok</h1>
        <p>Buat item stok baru.</p>
    </div>

    <div class="card form-card">
        <form method="POST" action="{{ route('admin.stocks.store') }}">
            @csrf
            @include('admin.stocks.form')

            <div class="form-actions">
                <a href="{{ route('admin.stocks.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-accent">Simpan</button>
            </div>
        </form>
    </div>
@endsection
