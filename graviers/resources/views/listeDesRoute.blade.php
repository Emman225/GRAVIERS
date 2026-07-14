<!DOCTYPE html>
<html>
<head>
    <title>Liste des Routes</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f4f4f4; }
    </style>
</head>
<body>
    <h2>Liste des Routes Laravel</h2>
    <table>
        <thead>
            <tr>
                {{-- <th>Méthode</th>
                <th>URI</th> --}}
                <th>Nom</th>
                <th>Contrôleur</th>
                {{-- <th>Middleware</th> --}}
            </tr>
        </thead>
        <tbody>
            @foreach($routes as $route)
                <tr>
                    {{-- <td>{{ $route['method'] }}</td>
                    <td>{{ $route['uri'] }}</td> --}}
                    <td>{{ $route['name'] }}</td>
                    <td>{{ $route['action'] }}</td>
                    {{-- <td>{{ $route['middleware'] }}</td> --}}
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
