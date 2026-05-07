@extends('layouts.app')

@section('content')

<div class="container py-5">

    <h1 class="mb-4">
        Portfolio Upload Center
    </h1>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card p-4 mb-4">

        <form
            method="POST"
            action="{{ route('portfolio.upload.store') }}"
            enctype="multipart/form-data"
        >
            @csrf

            <div class="mb-3">
                <label class="form-label">
                    Select Portfolio
                </label>

                <select
                    name="portfolio_id"
                    class="form-control"
                >
                    <option value="">
                        Default Portfolio
                    </option>

                    @foreach($portfolios as $portfolio)
                        <option value="{{ $portfolio->id }}">
                            {{ $portfolio->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    Upload File
                </label>

                <input
                    type="file"
                    name="file"
                    class="form-control"
                    required
                >
            </div>

            <button class="btn btn-dark">
                Upload Portfolio
            </button>

        </form>

    </div>

    <div class="card p-4">

        <h3 class="mb-4">
            Uploaded Files
        </h3>

        <table class="table">

            <thead>
                <tr>
                    <th>File</th>
                    <th>Status</th>
                    <th>Size</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>

                @forelse($files as $file)

                    <tr>

                        <td>
                            {{ $file->original_name }}
                        </td>

                        <td>
                            {{ strtoupper($file->status) }}
                        </td>

                        <td>
                            {{ round($file->file_size / 1024, 2) }} KB
                        </td>

                        <td>
                            {{ $file->created_at->format('d M Y h:i A') }}
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="4">
                            No uploads yet.
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@endsection