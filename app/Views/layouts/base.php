<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= e($title ?? 'PI Planning') ?> · PI Planning</title>
    <link rel="stylesheet" href="<?= e(asset('css/pico.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
    <header class="container">
        <nav>
            <ul>
                <li><a href="<?= e(url('/')) ?>" class="brand"><strong>PI Planning</strong></a></li>
            </ul>
            <ul>
                <li><a href="<?= e(url('/')) ?>">Program Increments</a></li>
                <li><a href="<?= e(url('pi/create')) ?>" role="button">+ Nouveau PI</a></li>
            </ul>
        </nav>
    </header>

    <main class="container">
        <?php foreach (pull_flash() as $flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>

        <?= $content ?? '' ?>
    </main>

    <footer class="container">
        <small class="muted">PI Planning · données Jira / Confluence en temps réel</small>
    </footer>

    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>