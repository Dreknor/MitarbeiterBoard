<html>
<head>
    <style>

        body {
            margin-top: 9mm;
            margin-left: -3mm;
            margin-bottom: -3mm;
            font-size: xx-small;
        }

        table {
            table-layout: fixed;
            width: 191mm;
            border-collapse: collapse;
        }

        td.inhalt {
            width: 46mm;
            max-width: 46mm;
            height: 20.9mm;
            max-height: 20.9mm;
            margin-bottom: 0;
            margin-top: 0;
            margin-left: 2mm;
            border-style: solid;
            border-width: 1px;
            border: #00bbff;
            padding-right: 2mm;
            overflow: hidden;
            word-wrap:break-word;
            box-sizing: border-box;
        }

        td.inhalt2 {
            width: 46mm;
            max-width: 46mm;
            height: 21.1mm;
            max-height: 21.1mm;
            margin-bottom: 0;
            margin-top: 0;
            margin-left: 2mm;
            border-style: solid;
            border-width: 1px;
            border: #00bbff;
            padding-right: 2mm;
            overflow: hidden;
            box-sizing: border-box;
            word-wrap:break-word;
        }

        .text {
            border: 1px solid black;
        }
        .qr {
            float: left;
        }


        img {
            margin: 5px;
            margin-right: 15px;
            float: top;
        }

        p {
            margin-top: -30px;
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
                        <td @if($x%3 == 0) class="inhalt" @else class="inhalt2" @endif>
                                    <img src="data:image/png;base64, {!! base64_encode(QrCode::size(60)->generate(url('inventory/item/'.$items->first()->uuid))) !!} ">
                                    <p>
                                        {{config('inventory.organisation')}}<br>
                                        {{$items->first()->name}}<br>
                                        {{\Illuminate\Support\Str::limit($items->first()->description, 15, $end='...')}}<br>
                                        @if($items->first()->oldInvNumber) {{$items->first()->oldInvNumber}}<br> @else {{$items->first()->uuid}}<br> @endif
                                        {{$items->first()->location->name}} ({{$items->first()->location->kennzeichnung}})<br>
                                    </p>

                            @php($items->forget($items->keys()->first()))
                        </td>
                    @else
                            <td class="inhalt">
                                &nbsp;
                            </td>
                    @endif
                @else
                    <td class="inhalt">
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

