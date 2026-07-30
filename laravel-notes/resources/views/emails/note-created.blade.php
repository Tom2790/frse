<x-mail::message>
# Cześć {{ $recipientName }}!

W Twoim koncie pojawiła się nowa notatka.

<x-mail::panel>
**{{ $title }}**

{{ $excerpt }}
</x-mail::panel>

@if ($createdAt)
Data dodania: {{ $createdAt }}
@endif

<x-mail::button :url="route('notes.index')">
Otwórz notatki
</x-mail::button>

Dzięki,<br>
{{ config('app.name') }}
</x-mail::message>
