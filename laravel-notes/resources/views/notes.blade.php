@extends('layouts.app')

@section('title', 'Moje notatki')

@section('content')
    {{-- Blade daje sesje i token CSRF, dalej caly CRUD idzie bez przeladowania strony.
         #app jest w layoucie, tutaj wstawiamy sam komponent. --}}
    <note-manager></note-manager>
@endsection
