<!DOCTYPE html>
<html>
<head>
    <title>Übersicht neue Nachrichten</title>
</head>
<body>
<p>
    Im <a href="{{config('app.url')}}">{{config('app.name')}}</a> wurden folgende neue Mitteilungen veröffentlicht:
    <br><br>
</p>
<p>
    <ul>
        @foreach($posts as $post)
            <li style="margin-bottom: 8px;">
                <a href="{{ route('posts.show', $post) }}"><strong>{{ $post->header }}</strong></a>
                @if($post->groups->isNotEmpty())
                    <span style="color: #6b7280;">({{ $post->groups->pluck('name')->join(', ') }})</span>
                @endif
                @if($post->kurztext !== '')
                    <br><span style="color: #374151;">{{ $post->kurztext }}</span>
                @endif
            </li>
        @endforeach
    </ul>
</p>
<p>
    <br>
    <a href="{{config('app.url')}}">{{config('app.url')}}</a>
</p>

</body>
</html>
