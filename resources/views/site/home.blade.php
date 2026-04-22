@extends('site.layouts.public', ['meta' => $meta ?? []])

@section('content')
    @foreach($sections as $section)
        @php $data = $section['data']; @endphp
        @includeWhen(view()->exists("site.sections.{$section['type']}"), "site.sections.{$section['type']}", ['data' => $data])
    @endforeach
@endsection
