<ul class="list-group">
@foreach($appointments as $appointment)
   <li class="d-flex justify-content-between align-items-center">
       <div class="row">

           <div class="col text-end">
                {{$appointment['start_time']->format('H:i')}} - {{$appointment['end_time']->format('H:i')}}
           </div>
           <div class="col text-start">
               {{$appointment['title']}}
           </div>
       </div>
   </li>
@endforeach
</ul>
