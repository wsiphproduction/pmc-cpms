<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #fff;
            color: #334155;
        }

        .wrap {
            padding: 28px;
        }

        .panel {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 22px;
            background: #f8fafc;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 18px;
            color: #0f172a;
        }

        p {
            margin: 0;
            font-size: 13px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="panel">
            <h1>{{ $title }}</h1>
            <p>{{ $project->project_no }} - {{ $project->title }}</p>
            <p>This workspace is ready for the {{ strtolower($title) }} workflow.</p>
        </div>
    </div>
</body>
</html>
