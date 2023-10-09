<!DOCTYPE html>
<html lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>


    <!-- Bootstrap core CSS -->
    <link href="{{asset('css/bootstrap.min.css')}}" rel="stylesheet">
    <!-- Your custom styles (optional) -->
    <link rel="stylesheet" type="text/css" href="{{asset('css/colors.css')}}">

    <link rel="stylesheet" type="text/css" href="{{asset('fonts/line-awesome/css/line-awesome.css')}}">

</head>
<body>

@for($month = \Carbon\Carbon::now()->startOfMonth(); $month->lessThanOrEqualTo(\Carbon\Carbon::now()->endOfMonth()); $month->addMonth())
    @include('personal.holidays.partials.export-month', [
    'month' => $month,
    'holidays' => $holidays,
    'users' => $users
    ])
@endfor



</body>
</html>
