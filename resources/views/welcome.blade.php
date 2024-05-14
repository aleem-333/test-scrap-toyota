<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    </head>
    <body class="antialiased">
        <table border="1">
            <thead>
                <tr>
                    <th>#</th>
                    @foreach ($offers[0] as $key => $value)
                        <th>{{ $key }}</th>
                    @endforeach
            </thead>
            <tbody>
                @foreach ($offers as $offer)
                    @if(array_key_exists('year', $offer))
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            @foreach ($offer as $key => $value)
                                <td>{{ $value }}</td>
                            @endforeach
                        </tr>
                    @else
                    <!-- colspan of keys count -->
                        <tr colspan="{{ count($offer) }}">
                            <td>{{ $offer }}</td>
                        </tr>
                    @endif
                            
                @endforeach
            </tbody>
        </table>
                
    </body>
</html>
