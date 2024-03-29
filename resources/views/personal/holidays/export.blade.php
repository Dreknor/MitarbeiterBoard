<!DOCTYPE html>
<html lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>


    <!-- Bootstrap core CSS -->
    <link href="{{asset('css/bootstrap.min.css')}}" rel="stylesheet">
    <!-- Your custom styles (optional) -->
    <link rel="stylesheet" type="text/css" href="{{asset('css/colors.css')}}">

    <link rel="stylesheet" type="text/css" href="{{asset('fonts/line-awesome/css/line-awesome.css')}}">
    <style>
        .page_break { page-break-before: always; }

    </style>
</head>
<body>

@for($month = $monthStart->copy()->startOfYear(); $month->lessThanOrEqualTo($monthStart->copy()->endOfYear()); $month->addMonth())
    <div class="page_break"></div>
    </div>
        @include('personal.holidays.partials.export-month', [
            'month' => $month,
            'holidays' => $holidays,
            'users' => $users
            ])
@endfor



</body>
</html>
