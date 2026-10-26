<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= $title ?? 'Mon Application' ?></title>
    <link rel="stylesheet" href="<?= dirname($_SERVER['SCRIPT_NAME']) ?>/css/pico.min.css">
    <link rel="stylesheet" href="<?= dirname($_SERVER['SCRIPT_NAME']) ?>/css/style.css">
</head>
<body>
    <header>
        <nav>
            <ul>
                <li></li>
            </ul>
        </nav>
    </header>

    <main class="container">
        <?= $content ?? '' ?>
    </main>

    <footer><!-- Footer global --></footer>
    <script src="<?= dirname($_SERVER['SCRIPT_NAME']) ?>/js/app.js"></script>
</body>
</html>