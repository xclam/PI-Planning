<h1><?= htmlspecialchars($title) ?></h1>

<!-- Stats du sprint en cours -->
<?php if (isset($activeSprint) && $activeSprint): ?>
    <div style="background: #e8f4fd; border: 1px solid #b8daff; padding: 15px; margin-bottom: 25px; border-radius: 6px;">
        <h3 style="margin-top: 0; color: #004085;">
            Sprint en cours : 
            <a href="<?= dirname($_SERVER['SCRIPT_NAME']) ?>/sprint/show?id=<?= $activeSprint['id'] ?>&name=<?= urlencode($activeSprint['name']) ?>" style="color: #0056b3; text-decoration: underline;">
                <?= htmlspecialchars($activeSprint['name']) ?>
            </a>
        </h3>
        <p style="margin: 5px 0; font-size: 14px; color: #333;">
            <strong>Dates :</strong> <?= htmlspecialchars($activeSprint['startDate'] ?? 'N/A') ?> au <?= htmlspecialchars($activeSprint['endDate'] ?? 'N/A') ?> 
            | <strong>Statut :</strong> En cours
        </p>
        <div style="display: flex; gap: 25px; margin-top: 12px; font-weight: bold; font-size: 15px;">
            <span>Total : <?= $sprintStats['total'] ?? 0 ?></span>
            <span style="color: #007bff;">En cours : <?= $sprintStats['in_progress'] ?? 0 ?></span>
            <span style="color: #28a745;">Terminés : <?= $sprintStats['done'] ?? 0 ?></span>
            <span style="color: #dc3545;">Anomalies : <?= $sprintStats['anomalies'] ?? 0 ?></span>
        </div>
    </div>
<?php endif; ?>

<a href="<?= dirname($_SERVER['SCRIPT_NAME']) ?>/pi/create" class="button">+ Ajouter un PI</a>
<table>
    <thead>
        <tr>
            <th>Nom</th>
            <th>Dates</th>
            <th>Sprint Feature</th>
            <th>Sprint Enabler</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($pis)): ?>
            <tr>
                <td colspan="4">Aucun Program Increment enregistré.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($pis as $pi): ?>
                <tr>
                    <td>
                        <a href="<?= dirname($_SERVER['SCRIPT_NAME']) ?>/pi/show?id=<?= $pi->id ?>">
                            <?= htmlspecialchars($pi->name) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($pi->start_date) ?> au <?= htmlspecialchars($pi->end_date) ?></td>
                    <td><?= htmlspecialchars($pi->feature_sprint_name ?? '-') ?></td>
                    <td><?= htmlspecialchars($pi->enabler_sprint_name ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>