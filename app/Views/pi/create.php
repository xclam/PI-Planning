<hgroup>
    <h1>Nouveau Program Increment</h1>
    <p>Choisissez les sprints du board ART qui portent les features et les enablers.</p>
</hgroup>

<?php if (!empty($errorJira)): ?>
    <div class="flash flash-error" role="alert"><?= e($errorJira) ?></div>
<?php endif; ?>

<article class="form-card">
    <?php partial('pi/_form', [
        'action'      => url('pi/store'),
        'pi'          => null,
        'sprints'     => $sprints,
        'nextName'    => $nextName,
        'submitLabel' => 'Créer le PI',
        'cancelUrl'   => url('/'),
    ]); ?>
</article>