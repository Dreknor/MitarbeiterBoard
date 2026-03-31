<div @class(['card'])>
    <div @class(['card-header'])>
        <div class="d-flex justify-content-between align-items-center">
            <div></div>
            @can('create roster')
                <a href="{{route('roster.autoPlan',$roster->id)}}" class="btn btn-sm btn-outline-primary">
                    <i class="la la-magic"></i> Auto-Umplanung Vorschlag
                </a>
            @endcan
        </div>
    </div>
    <div @class(['card-body'])>
        <div @class(['row'])>
            @foreach($employes as $employe)
                <div @class(['col'])>
                    <p>
                        <div class=" "> {{$employe->vorname}}
                            <a href="{{route('roster.export.employe.pdf', [$roster->id, $employe->id])}}" class="card-link">
                                <i class="fa fa-file-pdf"></i>
                            </a>
                        </div>
                    </p>
                    <p>
                        ({{(calculateWorkingTime($working_times->where('employe_id', $employe->id), $events->where('employe_id', $employe->id)))->format('%H:%I')}}
                        /{{ round($employe->employments()->where('department_id', $department->id)->active()->get()->sum('hours'), 2) }}
                        h)
                    </p>
                </div>
            @endforeach

        </div>

        <div @class(['row'])>
            <div class="col">
                @if($roster->news->count() > 0)
                    <ul class="list-group">
                        @foreach($roster->news as $news)
                            <li class="list-group-item">
                                {{$news->news}}
                                <span class="d-inline pull-right">
                                <a href="{{route('roster.news.delete', $news->id)}}" class="card-link">
                                    <i class="la la-trash-o"></i>
                                </a>
                            </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <a href="#" class="card-link" id="addNews">
                    <i class="la la-plus"></i>
                    Nachricht erstellen
                </a>
                <form class="form-horizontal d-none w-100" method="post" id="addNewsForm"
                      action="{{route('roster.news.add', $roster->id)}}">
                    @csrf
                    <label class="danger">Nachricht</label>
                    <input type="text" name="news" class="form-control w-100" required>

                    <button type="submit" class="btn btn-block btn-sm btn-bg-gradient-x-blue-green">
                        <i class="la la-save"></i>
                        <div class="d-none d-md-block">speichern</div>
                    </button>
                </form>
            </div>


        </div>
    </div>
</div>
