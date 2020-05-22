@extends('layouts.app')

@section('content')

    <div class="container-fluid">
        <div class="card">
            <div class="card-header border-bottom">
                <div class="row">
                    <div class="col">
                        <h5 class="card-title">
                            Benutzerkonten
                        </h5>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-hover" id="userTable">
                    <thead>
                        <tr>
                            <td></td>
                            <th>Name</th>
                            <th>E-Mail</th>
                            <th>Rollen</th>
                            <th>Rechte</th>
                            <td></td>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td>
                                    <a href="{{url('/users/').'/'.$user->id}}" class="btn-link">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                                <td>
                                    {{$user->name}}
                                </td>
                                <td>
                                    {{$user->email}}
                                </td>
                                <td>
                                    @foreach($user->roles as $role)
                                        <div class="btn btn-outline-warning btn-sm">
                                            {{$role->name}}
                                        </div>
                                    @endforeach
                                </td>

                                <td>
                                    @foreach($user->permissions as $permission)
                                        <div class="btn btn-outline-danger btn-sm">
                                            {{$permission->name}}
                                        </div>
                                    @endforeach
                                </td>
                                <td>
                                    <div class="btn btn-sm btn-danger user-delete" data-id="{{$user->id}}">
                                        <i class="fas fa-user-slash"></i>
                                    </div>
                                    @role('Admin')
                                        <a href="{{url("showUser/$user->id")}}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    @endrole
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection

@push('js')
 <script src="//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
 <script>
     $(document).ready( function () {
         $('#userTable').DataTable();
     } );
 </script>


 @can('edit user')
     <script src="{{asset('js/plugins/sweetalert2.all.min.js')}}"></script>

     <script>
         $('.user-delete').on('click', function () {
             var userID = $(this).data('id');
             var button = $(this);

             swal.fire({
                 title: "Benutzer wirklich entfernen?",
                 type: "warning",
                 showCancelButton: true,
                 cancelButtonText: "Benutzer behalten",
                 confirmButtonText: "Benutzer entfernen!",
                 confirmButtonColor: "danger"
             }).then((confirmed) => {
                 if (confirmed.value) {
                     $.ajax({
                         url: '{{url("/users/")}}'+'/'+ userID,
                         type: 'DELETE',
                         data: {
                             "_token": "{{csrf_token()}}",
                         },
                         success: function(result) {
                             $(button).parents('tr').fadeOut();
                         }
                     });
                 }
             });
         });
     </script>
 @endcan
@endpush

@section('css')
    <link href="//cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css" rel="stylesheet" />

@endsection