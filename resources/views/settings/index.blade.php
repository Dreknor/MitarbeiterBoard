@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="w-100">
            <div class="card">
                <div class="card-header border-bottom">
                    <ul class="nav nav-tabs">
                        @foreach($module as $modul_name => $settings)
                            <li class="nav-item">
                                <a class="nav-link border-top border-left border-right" href="{{url('settings/'.$modul_name)}}">{{$modul_name}}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="card-body">
                    <div class="container-fluid">
                        @if(!is_null($modul))
                            <form method="post" action="{{url('settings')}}" class="form-horizontal" enctype='multipart/form-data'>
                            @csrf
                            @foreach($module[$modul] as $setting)
                                <div class="form-row mt-1 p-2 border">
                                    <div class="col-md-6 col-sm-12">

                                            @switch($setting->type)
                                                @case('image')
                                                 <label class="label-control w-100 ">
                                                    {{$setting->setting_name}}
                                                    <input type = "file" class="form-control" name='setting[{{$setting->setting}}]' value="{{old('value',$setting->value)}}" accept="image/*">
                                                 </label>
                                                    @break
                                                @case('boolean')
                                                    <div class="custom-control custom-switch">
                                                        <input type='hidden' value='0' name='setting[{{$setting->setting}}]'>

                                                        <input class="custom-control-input" type="checkbox" role="switch" id="setting[{{$setting->setting}}]" name='setting[{{$setting->setting}}]' @if($setting->value == 1) checked="checked" @endif>
                                                        <label class="custom-control-label" for="setting[{{$setting->setting}}]">{{$setting->setting_name}}</label>
                                                    </div>
                                                    @break
                                                @default
                                                <label class="label-control w-100 ">

                                                    {{$setting->setting_name}}
                                                    <input type = "{{$setting->type}}" class="form-control" name='setting[{{$setting->setting}}]' value="{{old('value',$setting->value)}}">
                                                </label>
                                            @endswitch

                                    </div>
                                    <div class="col-md-6 col-sm-12 m-auto">
                                        <div class="small">
                                            {{$setting->description}}
                                        </div>
                                    </div>

                                </div>
                            @endforeach
                            <div class="form-row">
                                <button type="submit" class="btn btn-success btn-block">
                                    Einstellungen speichern
                                </button>
                            </div>
                        </form>
                        @else
                            <p>
                                Kein Modul gefunden
                            </p>
                        @endif

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
