<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5>
                    Urlaubstage
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-hover border table-responsive-sm">
                    <thead>
                    <tr>
                        <th class="border-right">Mitarbeiter</th>
                        <th class="border-right">Urlaub bisher/beantragt</th>
                        <th class="border-right">Rest</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($users as $user)
                        <tr class="@foreach($user->groups_rel as $group) {{$group->name}} @endforeach">
                            <td class="border-right w-25">
                                {{$user->name}}
                            </td>
                            <td class="border-right">
                                {{$user->holidays_date($month->copy()->startOfYear(), \Carbon\Carbon::now())->sum('days')}} / {{$user->holidays_date(\Carbon\Carbon::now()->addDay(), $month->copy()->endOfYear())->sum('days_requested')}}
                            </td>
                            <td class="border-right">

                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
