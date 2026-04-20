<!DOCTYPE html>
<html>
<head>
    <title>Admin Leads</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; }
        th { background: #f4f4f4; }
        form input, form select { padding: 5px; margin-right: 10px; }
    </style>
</head>
<body>

<h2>📊 Lead Dashboard</h2>

<form method="GET">
    <input type="number" name="min_score" placeholder="Min Score" value="{{ request('min_score') }}">
    
    <select name="recent">
        <option value="">All Time</option>
        <option value="1" {{ request('recent') ? 'selected' : '' }}>Last 7 Days</option>
    </select>

    <input type="text" name="email" placeholder="Search Email" value="{{ request('email') }}">

    <button type="submit">Filter</button>
</form>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Score</th>
            <th>Created</th>
            <th>View</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($intakes as $intake)
            <tr>
                <td>{{ $intake->id }}</td>
                <td>{{ $intake->name }}</td>
                <td>{{ $intake->email }}</td>
                <td>{{ $intake->phone }}</td>
                <td><strong>{{ $intake->lead_score }}</strong></td>
                <td>{{ $intake->created_at }}</td>
                <td>
                    <a href="{{ route('admin.intakes.show', $intake->id) }}">View</a>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<br>

{{ $intakes->links() }}

</body>
</html>