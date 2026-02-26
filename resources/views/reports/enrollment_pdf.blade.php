<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .header { text-align: center; margin-bottom: 40px; }
        .badge { background: #28a745; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        th { border-bottom: 2px solid #333; padding: 12px; text-align: left; }
        tr.data-row td { background: #fdfdfd; padding: 15px; border-top: 1px solid #eee; border-bottom: 1px solid #eee; }
        .student-name { font-weight: bold; color: #333; }
        .course-tag { color: #666; font-size: 11px; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h2 style="margin-bottom: 5px;">Student Enrollment List</h2>
        <p style="color: #666;">{{ Auth::user()->name }} | {{ strtoupper($period) }} Intake</p>
        <span class="badge">Total Enrolled: {{ $list->count() }} Students</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Student Details</th>
                <th>Course & Cohort</th>
                <th>Enrollment Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($courses as $course)
    <div style="margin-bottom: 30px;">
        <h3 style="color: #0a2e67; border-bottom: 1px solid #ddd;">{{ $course->title }}</h3>
        <table width="100%" border="1" style="border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th>Student Name</th>
                    <th>Date Joined</th>
                </tr>
            </thead>
            <tbody>
                @foreach($course->enrollments as $enroll)
                <tr>
                    <td>{{ $enroll->user->name }}</td>
                    <td>{{ $enroll->created_at->format('d/M/Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach
        </tbody>
    </table>
</body>
</html>