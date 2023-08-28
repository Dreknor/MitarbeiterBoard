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
                        <form method="post" action="{{url('settings')}}" class="form-horizontal" enctype='multipart/form-data'>
                            @csrf
                            @foreach($module[$modul] as $setting)
                                <div class="form-row mt-1 p-2 border">
                                    <div class="col-md-6 col-sm-12">
                                        <label class="label-control w-100">
                                            {{$setting->setting_name}}
                                            @if($setting->type != 'image')
                                                <input type = "{{$setting->type}}" class="form-control" name="{{$setting->setting_name}}" value="{{old('value',$setting->value)}}">
                                            @else
                                                <input type = "file" class="form-control" name="{{$setting->setting_name}}" value="{{old('value',$setting->value)}}" accept="image/*">
                                            @endif
                                        </label>
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

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
