<!DOCTYPE html>
<html lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>


    <!-- Bootstrap core CSS -->
    <link href="{{asset('css/bootstrap.min.css')}}" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{asset('css/colors.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('fonts/line-awesome/css/line-awesome.css')}}">
    <style>
        body {
            font-size: larger;
            margin-left: 1cm;
            margin-right: 1cm;
            margin-top: 1cm;
            margin-bottom: 1cm;
        }

        .new-page {
            page-break-after: always;
        }

    </style>
</head>
<body>
<div class="container-fluid">
    @for($x=$startDate; $x<= $targetDate; $x->addDay())
        @include('vertretungsplan.pdf.day')
        @if($x< $targetDate)
            <div class="new-page"></div>
        @endif
    @endfor
</div>

</body>
</html>
