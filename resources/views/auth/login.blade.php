@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        @if(config('config.auth.saml2'))
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <a href="{{route('saml2_login', 'idp1')}}" class="btn btn-block">
                            {{config('config.auth.saml2_btn')}}
                        </a>
                    </div>
                    @if(session()->has('saml2_error_detail'))
                        <div class="card-body">
                            @foreach(session('saml2_error_detail') as $error)
                                <p class="text-danger">
                                    {{$error}}
                                </p>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif
            @if(config('config.auth.keycloak'))
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <a href="{{url('/auth/redirect')}}" class="btn btn-block btn-bg-gradient-x-blue-cyan">
                                {{config('config.auth.keycloak_btn')}}
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        @if(config('config.auth.auth_local'))
            <div class="col-md-8">
                <div class="card">
                <div class="card-header">{{ __('Login') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="form-group row">
                            <label for="email" class="col-md-4 col-form-label text-md-right">{{ __('E-Mail Address') }}</label>

                            <div class="col-md-6">
                                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>

                                @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="password" class="col-md-4 col-form-label text-md-right">{{ __('Password') }}</label>

                            <div class="col-md-6">
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">

                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-6 offset-md-4">

                                <div class="">
                                    <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>

                                    <label class="form-check-label" for="remember">
                                        {{ __('Remember Me') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group row mb-0">
                            <div class="col-md-8 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Login') }}
                                </button>

                                @if (Route::has('password.request'))
                                    <a class="btn btn-link" href="{{ route('password.request') }}">
                                        {{ __('Forgot Your Password?') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            </div>
        @endif
    </div>
</div>
@endsection
