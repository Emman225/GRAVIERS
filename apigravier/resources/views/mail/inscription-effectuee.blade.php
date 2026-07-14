<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Félicitations pour votre inscription !</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .congrats-container {
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background-color: #f0db4f;
            border-radius: 50%;
            opacity: 0.8;
            animation: fall 5s linear infinite;
        }

        @keyframes fall {
            0% {
                transform: translateY(-100px) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(600px) rotate(360deg);
                opacity: 0;
            }
        }

        .icon-container {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 25px;
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }

        .icon {
            color: white;
            font-size: 50px;
        }

        h1 {
            color: #333;
            margin-bottom: 15px;
            font-size: 2.2rem;
        }

        .subtitle {
            color: #6a11cb;
            font-size: 1.2rem;
            margin-bottom: 25px;
            font-weight: 600;
        }

        .message {
            color: #555;
            line-height: 1.6;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        .next-steps {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }

        .next-steps h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .next-steps ul {
            list-style-type: none;
            padding-left: 0;
        }

        .next-steps li {
            padding: 8px 0;
            color: #555;
            position: relative;
            padding-left: 25px;
        }

        .next-steps li:before {
            content: "✓";
            color: #6a11cb;
            font-weight: bold;
            position: absolute;
            left: 0;
        }

        .btn-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            cursor: pointer;
            border: none;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }

        .btn-secondary {
            background-color: transparent;
            color: #6a11cb;
            border: 2px solid #6a11cb;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 600px) {
            .congrats-container {
                padding: 30px 20px;
            }

            h1 {
                font-size: 1.8rem;
            }

            .btn-container {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="congrats-container">
        <!-- Confetti animation elements -->
        <div class="confetti" style="left: 10%; animation-delay: 0s;"></div>
        <div class="confetti" style="left: 20%; animation-delay: 0.5s;"></div>
        <div class="confetti" style="left: 30%; animation-delay: 1s;"></div>
        <div class="confetti" style="left: 40%; animation-delay: 1.5s;"></div>
        <div class="confetti" style="left: 50%; animation-delay: 2s;"></div>
        <div class="confetti" style="left: 60%; animation-delay: 2.5s;"></div>
        <div class="confetti" style="left: 70%; animation-delay: 3s;"></div>
        <div class="confetti" style="left: 80%; animation-delay: 3.5s;"></div>
        <div class="confetti" style="left: 90%; animation-delay: 4s;"></div>

        <img src="https://graviers.fneconnect.net/backend/assets/imgs/theme/logoAvecFond.jpg" alt="Mon Gravier"
            style="max-width: 200px; width: 100%; height: auto; margin: 0 auto 20px; display: block;" />

        <div class="icon-container">
            <div class="icon">✓</div>
        </div>

        <h1>Félicitations !</h1>
        <p class="subtitle">Votre inscription a été réussie</p>

        <p class="message">
            Bienvenue dans notre communauté {{ $nom }} ! Votre compte a été créé avec succès.
            Nous sommes ravis de vous compter parmi nos membres.
        </p>

    </div>

    <script>
        // Ajouter plus de confettis dynamiquement
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.congrats-container');
            const colors = ['#f0db4f', '#6a11cb', '#2575fc', '#ff6b6b', '#4cd964'];

            for (let i = 0; i < 20; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.animationDelay = Math.random() * 5 + 's';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.width = (Math.random() * 10 + 5) + 'px';
                confetti.style.height = confetti.style.width;
                container.appendChild(confetti);
            }
        });
    </script>
</body>
</html>
