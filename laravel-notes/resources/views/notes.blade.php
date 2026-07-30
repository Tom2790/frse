@extends('layouts.app')

@section('title', 'Moje notatki')

@section('content')
    {{--
        Zadanie 4: widget Vue osadzony w widoku Blade.
        Blade dostarcza sesję i CSRF, cały CRUD dzieje się już bez przeładowania strony.
        `#app` jest zadeklarowane w layoucie — tu wstawiamy sam komponent.
    --}}
    <note-manager></note-manager>
@endsection
