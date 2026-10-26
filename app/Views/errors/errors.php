<hgroup>
    <h1>Erreur <?= (int) $code ?></h1>
    <p><?= e($message ?: 'Une erreur est survenue.') ?></p>
</hgroup>
<p><a href="<?= e(url('/')) ?>" role="button" class="outline">← Retour à l'accueil</a></p>