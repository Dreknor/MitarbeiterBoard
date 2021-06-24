<html>
<head>
    <style>
        body {
            margin-top: 9mm;
            margin-left: -3mm;
            margin-bottom: -8mm;
            font-size: xx-small;
        }

        table {
            table-layout: fixed;
            width: 191mm;
            border-collapse: collapse;
        }


        td {
            width: 46mm;
            max-width: 46mm;
            height: 80px;
            max-height: 80px;
            margin-bottom: 0;
            margin-top: 2px;
            margin-left: 2mm;
            border-style: solid;
            border-width: 1px;
            border: rgba(0, 187, 255, 0);
            padding-right: 2mm;
            overflow: hidden;
            white-space: nowrap;
        }

        td.reihe3{
            height: 77px;
            max-height: 77px;
        }


        td.reihe4{
            height: 78px;
            max-height: 78px;
        }


        td.reihe5{
            height: 77px;
            max-height: 77px;
        }


        td.reihe6{
            height: 77px;
            max-height: 77px;
        }

        td.reihe7{
            height: 77px;
            max-height: 77px;
        }

        td.reihe8{
            height: 77px;
            max-height: 77px;
        }
        td.reihe9{
            height: 77px;
            max-height: 77px;
        }
        td.reihe10{
            height: 77px;
            max-height: 77px;
        }
        td.reihe11{
            height: 77px;
            max-height: 77px;
        }

        td.reihe12{
            height: 77px;
            max-height: 77px;
        }



        img {
            margin: 5px;
            margin-right: 15px;
            float: top;
        }

        p {
            margin-top: -5px;
            margin-bottom: 0;
            margin-left: 42%;
            margin-right: 0;
        }
        .page_break { page-break-before: auto; }

    </style>
</head>
<body>
<table style="border: 1px solid black">
    @while(count($items)>0)
        @for($x=1; $x<=12; $x++)
        <tr>
            @for($y=1; $y<=4; $y++)
                @if(($x == $reihe and $y>=$spalte) or ($x > $reihe)))
                    @if(!is_null($items) and count($items)>0)
                        <td class="reihe{{$x}}">
                                    <img src="data:image/png;base64, {!! base64_encode(QrCode::size(60)->generate(url('inventory/item/'.$items->first()->uuid))) !!} ">
                                    <p>
                                        {{config('inventory.organisation')}}<br>
                                        {{$items->first()->name}}<br>
                                        {{\Illuminate\Support\Str::limit($items->first()->description, 20, $end='...')}}<br>
                                        @if($items->first()->oldInvNumber) {{$items->first()->oldInvNumber}}<br> @else {{$items->first()->uuid}}<br> @endif
                                        {{$items->first()->location->name}} ({{$items->first()->location->kennzeichnung}})<br>
                                    </p>

                            @php($items->forget($items->keys()->first()))
                        </td>
                    @else
                            <td class="reihe{{$x}}">
                                &nbsp;
                            </td>
                    @endif
                @else
                    <td class="reihe{{$x}}">
                        &nbsp;
                    </td>

                @endif
            @endfor
        </tr>
    @endfor
            <div class="page_break"></div>
            @php($reihe =1)
            @php($spalte =1)
    @endwhile

</table>
</body>
</html>

