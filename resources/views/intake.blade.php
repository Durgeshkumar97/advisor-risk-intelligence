@extends('layouts.app')

@section('content')

<h1>Portfolio Intake Form</h1>

@if(session('success'))
    <p style="color: green; font-weight: bold;">
        {{ session('success') }}
    </p>
@endif

<form action="{{ route('intake.submit') }}" method="POST" enctype="multipart/form-data"> 
    @csrf

    <label>Name:</label><br>
    <input type="text" name="name"><br><br>

    <label>Email:</label><br>
    <input type="email" name="email"><br><br>

    <label>Portfolio Value:</label><br>
    <select name="portfolio_value">
        <option>1L–5L</option>
        <option>5L–25L</option>
        <option>25L+</option>
    </select><br><br>

    <label>Upload Holdings:</label><br>

    <input type="file" name="holdings" accept=".pdf,.xlsx,.xls,.csv,.jpg,.jpeg,.png,.webp">
    <br><br>
    <button type="submit">Submit</button>

    @if(session('success'))
        <p style="color: green; font-weight: bold;">
            {{ session('success') }}
        </p>
    @endif
</form>

@endsection
