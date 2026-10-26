<h1><?= htmlspecialchars($title) ?></h1>

<?php if (isset($errorJira) && $errorJira): ?>
    <div style="color: red; background: #ffe6e6; padding: 10px; margin-bottom: 15px;"><?= htmlspecialchars($errorJira) ?></div>
<?php endif; ?>

<?php if (isset($errorSession) && $errorSession): ?>
    <div style="color: red; background: #ffe6e6; border: 1px solid #ffcccc; padding: 12px; margin-bottom: 20px; font-weight: bold;">
        <?= htmlspecialchars($errorSession) ?>
    </div>
<?php endif; ?>

<!-- Formulaire de modification des infos du PI -->
<form method="POST" action="<?= dirname($_SERVER['SCRIPT_NAME']) ?>/pi/update" style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border: 1px solid #ddd;">
    <input type="hidden" name="id" value="<?= $pi->id ?>">
    
    <h3>Modifier le Program Increment</h3>
    
    <div style="margin-bottom: 10px;">
        <label><strong>Nom :</strong></label><br>
        <input type="text" name="name" value="<?= htmlspecialchars($pi->name) ?>" style="width: 100%; padding: 5px;" required>
    </div>

    <div style="display: flex; gap: 15px; margin-bottom: 10px;">
        <div style="flex: 1;">
            <label><strong>Date de début :</strong></label><br>
            <input type="date" name="start_date" value="<?= htmlspecialchars($pi->start_date) ?>" style="width: 100%; padding: 5px;" required>
        </div>
        <div style="flex: 1;">
            <label><strong>Date de fin :</strong></label><br>
            <input type="date" name="end_date" value="<?= htmlspecialchars($pi->end_date) ?>" style="width: 100%; padding: 5px;" required>
        </div>
    </div>

    <div style="display: flex; gap: 15px; margin-bottom: 15px;">
        <div style="flex: 1;">
            <label><strong>Sprint Features :</strong></label><br>
            <select name="feature_sprint" style="width: 100%; padding: 5px;">
                <option value="">-- Aucun --</option>
                <?php foreach ($sprints as $sprint): ?>
                    <?php $val = $sprint['id'] . '|' . $sprint['name']; ?>
                    <option value="<?= $val ?>" <?= $pi->feature_sprint_id == $sprint['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sprint['name']) ?> (<?= htmlspecialchars($sprint['state']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex: 1;">
            <label><strong>Sprint Enablers :</strong></label><br>
            <select name="enabler_sprint" style="width: 100%; padding: 5px;">
                <option value="">-- Aucun --</option>
                <?php foreach ($sprints as $sprint): ?>
                    <?php $val = $sprint['id'] . '|' . $sprint['name']; ?>
                    <option value="<?= $val ?>" <?= $pi->enabler_sprint_id == $sprint['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sprint['name']) ?> (<?= htmlspecialchars($sprint['state']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <button type="submit" style="padding: 8px 15px; background: #28a745; color: white; border: none; cursor: pointer;">Enregistrer les modifications</button>
</form>

<!-- Formulaire global pour la création des US -->
<form method="POST" action="<?= dirname($_SERVER['SCRIPT_NAME']) ?>/pi/create-us">
    <input type="hidden" name="id" value="<?= $pi->id ?>">

    <!-- Bouton global au dessus des tableaux -->
    <div style="margin-bottom: 20px;">
        <button type="submit" style="padding: 10px 15px; background: #007bff; color: white; border: none; cursor: pointer;">Créer toutes les US</button>
    </div>

    <!-- Conteneur côte à côte pour les deux tableaux -->
    <div style="display: flex; gap: 20px; align-items: flex-start;">
        
        <!-- Tableau Features ([B4ALL]) -->
        <div style="flex: 1; background: #fff; border: 1px solid #ddd; padding: 15px;">
            <h3>Features [B4ALL] (Sprint: <?= htmlspecialchars($pi->feature_sprint_name ?? 'N/A') ?>)</h3>
            <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
                <thead>
                    <tr style="background: #f1f1f1;">
                        <th style="width: 40px;">Sel</th>
                        <th>ID</th>
                        <th>Titre</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($features)): ?>
                        <tr><td colspan="3">Aucune feature [B4ALL] trouvée.</td></tr>
                    <?php else: ?>
                        <?php foreach ($features as $issue): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="features[]" value="<?= htmlspecialchars($issue['key']) ?>" checked>
                                </td>
                                <td><?= htmlspecialchars($issue['key']) ?></td>
                                <td><?= htmlspecialchars($issue['fields']['summary'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <button type="submit" style="padding: 8px 12px; background: #28a745; color: white; border: none; cursor: pointer;">Créer les US associées</button>
        </div>

        <!-- Tableau Enablers ([DLK]) -->
        <div style="flex: 1; background: #fff; border: 1px solid #ddd; padding: 15px;">
            <h3>Enablers [DLK] (Sprint: <?= htmlspecialchars($pi->enabler_sprint_name ?? 'N/A') ?>)</h3>
            <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
                <thead>
                    <tr style="background: #f1f1f1;">
                        <th style="width: 40px;">Sel</th>
                        <th>ID</th>
                        <th>Titre</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($enablers)): ?>
                        <tr><td colspan="3">Aucun enabler [DLK] trouvé.</td></tr>
                    <?php else: ?>
                        <?php foreach ($enablers as $issue): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="enablers[]" value="<?= htmlspecialchars($issue['key']) ?>" checked>
                                </td>
                                <td><?= htmlspecialchars($issue['key']) ?></td>
                                <td><?= htmlspecialchars($issue['fields']['summary'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <button type="submit" style="padding: 8px 12px; background: #17a2b8; color: white; border: none; cursor: pointer;">Créer les US enabler associées</button>
        </div>

    </div>
</form>

<p style="margin-top: 20px;">
    <a href="<?= dirname($_SERVER['SCRIPT_NAME']) ?>/">← Retour à la liste des PI</a>
</p>