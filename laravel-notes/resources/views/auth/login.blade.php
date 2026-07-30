@extends('layouts.app')

@section('title', 'Zaloguj się')

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Zaloguj się</div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger py-2">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="email">E-mail</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email', 'demo@example.com') }}"
                                required
                                autofocus
                            >
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">Hasło</label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                value="password"
                                required
                            >
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-3">
                            <input id="remember" class="form-check-input" type="checkbox" name="remember">
                            <label class="form-check-label" for="remember">Zapamiętaj mnie</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Zaloguj się</button>
                    </form>
                </div>
                <div class="card-footer text-body-secondary small">
                    Konta z seedera: <code>demo@example.com</code> oraz <code>obcy@example.com</code>,
                    hasło <code>password</code>. Pola są wstępnie wypełnione, żeby dało się
                    od razu sprawdzić izolację danych między kontami.
                </div>
            </div>
        </div>
    </div>
@endsection
