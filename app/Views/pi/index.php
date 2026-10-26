<?php
$total = (int) ($sprintStats['total'] ?? 0);
$done  = (int) ($sprintStats['done'] ?? 0);
$pct   = $total > 0 ? (int) round($done / $total * 100) : 0;
?>
<hgroup>
    <h1>Program Increments</h1>
    <p>Planifiez les PI et générez les US à partir des features et enablers.</p>
</hgroup>

<?php if (!empty($errorJira)): ?>
    <div class="flash flash-warning" role="alert"><?= e($errorJira) ?></div>
<?php endif; ?>

<?php if ($activeSprint): ?>
    <article class="sprint-card">
        <header>
            <small class="muted">Sprint en cours</small>
            <h3>
                <a href="<?= e(url('sprint/show', ['id' => $activeSprint['id'], 'name' => $activeSprint['name']])) ?>">
                    <?= e($activeSprint['name']) ?>
                </a>
            </h3>
            <p class="muted">
                <?= e(fmt_date($activeSprint['startDate'] ?? null)) ?> → <?= e(fmt_date($activeSprint['endDate'] ?? null)) ?>
            </p>
        </header>

        <div class="stats">
            <div class="stat"><span class="stat-value"><?= $total ?></span><span class="stat-label">Tickets</span></div>
            <div class="stat stat-progress"><span class="stat-value"><?= (int) ($sprintStats['in_progress'] ?? 0) ?></span><span class="stat-label">En cours</span></div>
            <div class="stat stat-done"><span class="stat-value"><?= $done ?></span><span class="stat-label">Terminés</span></div>
            <div class="stat stat-alert"><span class="stat-value"><?= (int) ($sprintStats['anomalies'] ?? 0) ?></span><span class="stat-label">Anomalies</span></div>
        </div>

        <progress value="<?= $pct ?>" max="100" title="<?= $pct ?> % terminé"></progress>
        <small class="muted"><?= $pct ?> % terminé</small>
    </article>
<?php endif; ?>

<div class="toolbar">
    <h2>Historique des PI</h2>
    <a href="<?= e(url('pi/create')) ?>" role="button">+ Ajouter un PI</a>
</div>

<figure>
    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Période</th>
                <th>Statut</th>
                <th>Sprint Feature</th>
                <th>Sprint Enabler</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($pis)): ?>
            <tr><td colspan="5">Aucun Program Increment enregistré.</td></tr>
        <?php else: ?>
            <?php foreach ($pis as $pi): $status = $pi->status(); ?>
                <tr>
                    <td><a href="<?= e(url('pi/show', ['id' => $pi->id])) ?>"><strong><?= e($pi->name) ?></strong></a></td>
                    <td><?= e(fmt_date($pi->start_date)) ?> → <?= e(fmt_date($pi->end_date)) ?></td>
                    <td><span class="badge badge-pi-<?= e($status) ?>"><?= e(\Models\Pi::STATUS_LABELS[$status]) ?></span></td>
                    <td><?= e($pi->feature_sprint_name ?? '–') ?></td>
                    <td><?= e($pi->enabler_sprint_name ?? '–') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</figure>