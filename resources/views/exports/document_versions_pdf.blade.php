<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Document Versions Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #dddddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        h2 {
            text-align: center;
        }
    </style>
</head>

<body>
    <h2>Document Versions</h2>
    <table>
        <thead>
            <tr>
                <th>Document</th>
                <th>Version</th>
                <th>File Name</th>
                <th>Uploaded By</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($records as $record)
                <tr>
                    <td>{{ $record->document?->title ?? '-' }}</td>
                    <td>{{ $record->version_number }}</td>
                    <td>{{ $record->original_filename }}</td>
                    <td>{{ $record->uploader?->name ?? '-' }}</td>
                    <td>{{ $record->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
