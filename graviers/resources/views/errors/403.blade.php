<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Accès non autorisé - 403</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
    }

    body {
      background-color: #f9fafb;
      color: #1f2937;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 2rem;
    }

    .logo {
      width: 80px;
      /* margin-bottom: 1rem; */
    }

    .error-container {
      text-align: center;
      max-width: 480px;
      background-color: #fff;
      padding: 2rem;
      border-radius: 1rem;
      box-shadow: 0 5px 15px rgba(0,0,0,0.05);
      border: 1px solid #e5e7eb;
    }

    .error-title {
      font-size: 3rem;
      font-weight: 600;
      color: #15579c;
      margin-bottom: 1rem;
    }

    .error-message {
      font-size: 1.1rem;
      margin-bottom: 1.5rem;
      color: #6b7280;
    }

    .btn-home {
      background-color: #15579c;
      color: white;
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 0.75rem;
      font-size: 1rem;
      cursor: pointer;
      transition: background-color 0.3s;
      text-decoration: none;
      display: inline-block;
    }

    .btn-home:hover {
      background-color: #15579c;
    }

    .gravel-image {
      width: 100%;
      height: 400px;
      /* : url('/frontend/assets/imgs/logo/omer 1.png') center/cover no-repeat; */
      border-radius: 0.75rem;
      margin-bottom: 1.5rem;
    }
  </style>
</head>
<body>

  <div class="error-container">
    {{-- <img src="{{asset('frontend/assets/imgs/logo/logoMAJ.jpg')}}" alt="Logo" class="logo"> --}}
    <div class="gravel-image" style="background: url('/{{ config('constantes.logo') }}')center/cover"></div>
    <h1 class="error-title">403</h1>
         {{-- @dd($errors->first('type') != null) --}}

    @if ($errors->has('access'))
        <div class="alert alert-danger">
            {{ $errors->first('access') }}
        </div>
        @if ($errors->first('type') != null)
            @switch($errors->first('type'))
                @case('Client')
                    <a href="/" class="btn-home">Retour à l'accueil</a>
                    @break

                @case('Fournisseur')
                    <a href="{{ route('sellers.home') }}" class="btn-home">Retour à l'accueil</a>
                    @break

                @case('Livreur')
                    <a href="{{ route('livreur.home') }}" class="btn-home">Retour à l'accueil</a>
                    @break

                @case('Apporteur')
                    <a href="{{ route('apporteur.home') }}" class="btn-home">Retour à l'accueil</a>
                    @break
                @default

            @endswitch
        @else
            <p class="error-message">Accès refusé. Vous n'avez pas les permissions nécessaires pour accéder à cette page.</p>
        @endif
    @else
        <p class="error-message">Accès refusé. Vous n'avez pas les permissions nécessaires pour accéder à cette page.</p>
        <a href="{{ route('supprimerSession') }}" class="btn-home">Retour à l'accueil</a>
    @endif

  </div>

</body>
</html>
