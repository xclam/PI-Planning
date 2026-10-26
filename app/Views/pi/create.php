<h1>Créer un nouveau Program Increment</h1>

<?php if (!empty($errorJira)): ?>
    <div style="background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
        <?= htmlspecialchars($errorJira) ?>
    </div>
<?php endif; ?>

<form action="<?= dirname($_SERVER['SCRIPT_NAME']) ?>/pi/store" method="POST" style="background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); max-width: 600px;">
    
    <div style="margin-bottom: 15px;">
        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Nom du PI :</label>
        <input type="text" name="name" value="<?= htmlspecialchars($nextName) ?>" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
    </div>

    <div style="display: flex; gap: 15px; margin-bottom: 15px;">
        <div style="flex: 1;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Date de début :</label>
            <input type="date" name="start_date" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
        </div>
        <div style="flex: 1;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Date de fin :</label>
            <input type="date" name="end_date" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
        </div>
    </div>

    <div style="margin-bottom: 15px;">
        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Sprint Feature :</label>
        <select name="feature_sprint" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
            <option value="">-- Sélectionner un sprint feature --</option>
            <?php foreach ($sprints as $sprint): ?>
                <option value="<?= $sprint['id'] ?>|<?= htmlspecialchars($sprint['name']) ?>">
                    <?= htmlspecialchars($sprint['name']) ?> (<?= $sprint['state'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="margin-bottom: 20px;">
        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Sprint Enabler :</label>
        <select name="enabler_sprint" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
            <option value="">-- Sélectionner un sprint enabler --</option>
            <?php foreach ($sprints as $sprint): ?>
                <option value="<?= $sprint['id'] ?>|<?= htmlspecialchars($sprint['name']) ?>">
                    <?= htmlspecialchars($sprint['name']) ?> (<?= $sprint['state'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="display: flex; justify-content: flex-end; gap: 10px;">
        <a href="<?= dirname($_SERVER['SCRIPT_NAME']) ?>/" style="padding: 10px 16px; background: #e2e8f0; color: #334155; text-decoration: none; border-radius: 4px;">Annuler</a>
        <button type="submit" style="background: #1423DC; color: #fff; border: none; padding: 10px 16px; border-radius: 4px; font-weight: bold; cursor: pointer;">Créer le PI</button>
    </div>
</form>