<html>
<head>
    <style>

        body {
            margin-top: 9mm;
            margin-left: -3mm;
            margin-bottom: -3mm;
            font-size: x-small;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border-width: 1px;
            border-style: solid;
            border-color: black;
        }


        img {
            margin: 10px;
            width: 50px;
        }

        td {
            height: 55px;
            border-width: 1px;
            border-style: solid;
            border-color: black;
            padding-left: 15px;
        }

        tr:first-child{
         background-color: rgba(56, 255, 34, 0.44);
        }

        tr:nth-child(2n){
            background-color: rgba(42, 87, 136, 0.16);
        }

        .page_break { page-break-before: always; }

    </style>
</head>
<body>
@foreach($locations as $location)
    <table>
        <tr >
            <th colspan="6">
                {{$location->name}}
            </th>
        </tr>
        <tr>
            <th></th>
            <th>
                Name
            </th>
            <th>
                Beschreibung
            </th>
            <th>
                Zustand
            </th>
            <th>
                Nummer
            </th>
            <td></td>
        </tr>
        @foreach($location->items as $item)
            <tr>
                <td>
                    <img src="data:image/png;base64, {!! base64_encode(QrCode::size(60)->generate(url('inventory/item/'.$item->uuid))) !!} ">
                </td>
                <td>
                    {{$item->name}}
                </td>
                <td>
                    {{$item->description}}
                </td>
                <td>
                    {{$item->status}}
                </td>
                <td>
                    {{$item->oldInvNumber}} @if($item->oldInvNumber) - @endif  {{$item->uuid}}
                </td>
                <td>
                    @if(count($item->getMedia())>0)
                        <img src="data:image/png;base64, {!! base64_encode($data   = file_get_contents($item->getMedia()->first()->getPath())) !!}" >


                    @endif
                </td>
            </tr>
        @endforeach
    </table>
    @if(!$loop->last)
        <div class="page_break"></div>
    @endif
@endforeach
</body>
</html>

