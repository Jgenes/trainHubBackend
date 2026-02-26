<!DOCTYPE html>
<html>
<head>
    <title>{{ $reportTitle }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>{{ $reportTitle }}</h2>
    <p>Provider: {{ $user->name }}</p>
    <p>Total Students: {{ $total }}</p>

    <table>
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Course</th>
                <th>Date Enrolled</th>
            </tr>
        </thead>
        <tbody>
            @foreach($enrollments as $enrollment)
            <tr>
                <td>{{ $enrollment->user->name ?? 'N/A' }}</td>
                <td>{{ $enrollment->course->title ?? 'N/A' }}</td>
                <td>{{ $enrollment->created_at->format('d M Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>