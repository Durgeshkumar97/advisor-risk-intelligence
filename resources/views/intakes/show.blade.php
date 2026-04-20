<h2>Lead Detail</h2>

<p><strong>Name:</strong> {{ $intake->name }}</p>
<p><strong>Email:</strong> {{ $intake->email }}</p>
<p><strong>Phone:</strong> {{ $intake->phone }}</p>
<p><strong>Score:</strong> {{ $intake->lead_score }}</p>
<p><strong>Concern:</strong> {{ $intake->concern }}</p>
<p><strong>Created:</strong> {{ $intake->created_at }}</p>

<a href="{{ route('admin.intakes.index') }}">Back</a>