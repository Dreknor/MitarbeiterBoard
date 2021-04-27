@extends('layouts.app')
@section('content')
    <div class="container-fluid">
        <a href="{{url($row->wochenplan->group->name).'/wochenplan/'.$row->wochenplan->id}}" class="btn btn-primary">zurück</a>

        <div class="card">
            <div class="card-header">
                <h6>
                    Neue Aufgabe für Bereich {{$row->name}}
                </h6>
            </div>
            <div class="card-body">
                <form action="{{url('wptask/'.$row->id.'/addTask')}}" method="post" class="form-horizontal">
                    @csrf
                    <div class="form-row">
                        <div class="col-sm-12 col-md-12 col-lg-3">
                            <div class="container-fluid">
                                <div class="row">
                                    <label for="protocol">Aufgabe</label>
                                </div>
                            </div>

                        </div>
                        <div class="col-sm-12 col-md-12 col-lg-9">
                            <textarea name="task"  id="task"   class="form-control border-input" >
                                {{old('task')}}
                            </textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success btn-block">Aufgabe speichern</button>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script src="{{asset('js/plugins/tinymce/jquery.tinymce.min.js')}}"></script>
    <script src="{{asset('js/plugins/tinymce/tinymce.min.js')}}"></script>
    <script src="{{asset('js/plugins/tinymce/langs/de.js')}}"></script>
    <script>
        tinymce.init({
            selector: 'textarea',
            lang:'de',
            height: 500,
            menubar: true,
            plugins: [
                'advlist autolink lists link charmap',
                'searchreplace visualblocks code',
                'insertdatetime table paste code wordcount',
                'contextmenu',
            ],
            toolbar: 'undo redo  | bold italic backcolor forecolor  | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link ',
            contextmenu: " link paste inserttable | cell row column deletetable",
        });
    </script>
@endpush
