@extends('layouts.panel')

@section('title', 'Ubah Stok')

@section('content')
    <div class="page-head">
        <h1>Ubah Stok</h1>
        <p>Perbarui data untuk <strong>{{ $stock->nama }}</strong>.</p>
    </div>

    <div class="card form-card">
        <form method="POST" action="{{ route('admin.stocks.update', $stock) }}">
            @csrf
            @method('PUT')
            @include('admin.stocks.form')

            <div class="form-actions">
                <a href="{{ route('admin.stocks.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-accent">Simpan Perubahan</button>
            </div>
        </form>
    </div>
@endsection
