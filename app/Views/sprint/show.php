<h1><?= htmlspecialchars($title) ?></h1>

<?php if (isset($errorJira) && $errorJira): ?>
    <div style="color: red; background: #ffe6e6; padding: 10px; margin-bottom: 15px;"><?= htmlspecialchars($errorJira) ?></div>
<?php endif; ?>

<!-- Section : Mes tickets attribués -->
<div style="background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; margin-bottom: 25px; border-radius: 6px;">
    <h3 style="margin-top: 0; color: #856404;">Mes tickets attribués</h3>
    <?php if (empty($myTickets)): ?>
        <p>Aucun ticket ne vous est attribué dans ce sprint.</p>
    <?php else: ?>
        <table cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse; background: #fff;">
            <tbody>
                <?php foreach ($myTickets as $ticket): ?>
                    <!-- Ligne 1 : ID, Titre, Statut -->
                    <tr style="<?= $ticket['is_recent_24h'] ? 'background: #e2f0d9;' : 'background: #fafafa;' ?> border-top: 1px solid #ddd;">
                        <td style="width: 140px; font-weight: bold; vertical-align: top;">
                            <a href="https://gojira.enedis.fr/browse/<?= htmlspecialchars($ticket['key']) ?>" target="_blank" style="color: #0052cc; text-decoration: none;">
                                <?= htmlspecialchars($ticket['key']) ?>
                            </a>
                            <?php if ($ticket['is_recent_24h']): ?>
                                <br><span style="background: #28a745; color: white; font-size: 9px; padding: 1px 4px; border-radius: 3px;">Modifié < 24h</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 500; vertical-align: top;">
                            <?= htmlspecialchars($ticket['fields']['summary'] ?? '') ?>
                        </td>
                        <td style="width: 130px; text-align: right; vertical-align: top;">
                            <span style="background: #e9ecef; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                                <?= htmlspecialchars($ticket['fields']['status']['name'] ?? '') ?>
                            </span>
                        </td>
                    </tr>
                    <!-- Ligne 2 : Dernier commentaire + Synthèse IA -->
                    <tr style="<?= $ticket['is_recent_24h'] ? 'background: #e2f0d9;' : 'background: #fafafa;' ?> border-bottom: 2px solid #e0e0e0;">
                        <td colspan="3" style="font-size: 13px; color: #555; padding-top: 0px; padding-bottom: 10px;">
                            <div style="margin-bottom: 6px;">💬 <em><?= htmlspecialchars($ticket['latest_comment']) ?></em></div>
                            
                            <?php if (!empty($ticket['ai_summary']) || !empty($ticket['ai_action'])): ?>
                                <div style="font-size: 12px; background: #ffffff; border-left: 3px solid #0052cc; padding: 6px 10px; margin-top: 6px; border-radius: 0 4px 4px 0; border-top: 1px solid #f0f0f0; border-right: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0;">
                                    <?php if (!empty($ticket['ai_summary'])): ?>
                                        <div style="color: #172b4d; margin-bottom: 3px;"><strong>📌 En bref :</strong> <?= htmlspecialchars($ticket['ai_summary']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($ticket['ai_action'])): ?>
                                        <div style="color: #0052cc; font-weight: 600;">🎯 Action conseillée : <?= htmlspecialchars($ticket['ai_action']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Section : Tous les tickets du sprint avec cases à cocher -->
<div style="background: #f8f9fa; border: 1px solid #ddd; padding: 15px; border-radius: 6px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin: 0;">Tous les tickets du sprint (Préparation démo)</h3>
        
        <!-- Filtres rapides par statut -->
        <div style="display: flex; gap: 8px; align-items: center; font-size: 13px;">
            <span style="font-weight: bold; color: #555;">Basculer les statuts :</span>
            <button type="button" class="btn-status-toggle" data-status="terminé" style="padding: 4px 10px; border-radius: 12px; border: 1px solid #28a745; background: #e2f0d9; color: #155724; cursor: pointer; font-weight: bold;">
                Terminé
            </button>
            <button type="button" class="btn-status-toggle" data-status="à tester" style="padding: 4px 10px; border-radius: 12px; border: 1px solid #ffc107; background: #fff3cd; color: #856404; cursor: pointer; font-weight: bold;">
                À tester
            </button>
            <button type="button" class="btn-status-toggle" data-status="en réalisation" style="padding: 4px 10px; border-radius: 12px; border: 1px solid #17a2b8; background: #d1ecf1; color: #0c5460; cursor: pointer; font-weight: bold;">
                En réalisation
            </button>
        </div>
    </div>

    <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse; background: #fff;">
        <thead>
            <tr style="background: #f1f1f1;">
                <th style="width: 40px; text-align: center;">
                    <input type="checkbox" id="select-all-demo">
                </th>
                <th>ID</th>
                <th>Type</th>
                <th>Titre</th>
                <th>Statut</th>
                <th>Assigné à</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($issues)): ?>
                <tr><td colspan="6">Aucun ticket trouvé dans ce sprint.</td></tr>
            <?php else: ?>
                <?php foreach ($issues as $issue): 
                    $type = strtolower($issue['fields']['issuetype']['name'] ?? '');
                    $status = strtolower($issue['fields']['status']['name'] ?? '');
                    
                    // Types éligibles : US ou US Enabler / Enabler
                    $isUsType = (str_contains($type, 'us') || str_contains($type, 'user story') || str_contains($type, 'enabler') || str_contains($type, 'feature'));
                    // Statuts cochés par défaut : "À tester" ou "Terminé" / "Fait" / "Done"
                    $isDefaultStatus = (str_contains($status, 'tester') || str_contains($status, 'terminé') || str_contains($status, 'fait') || str_contains($status, 'done'));
                    
                    $shouldCheck = $isUsType && $isDefaultStatus;
                ?>
                    <tr class="demo-ticket-row" data-type="<?= htmlspecialchars($type) ?>" data-status="<?= htmlspecialchars($status) ?>">
                        <td style="text-align: center;">
                            <input type="checkbox" name="demo_tickets[]" class="demo-checkbox" value="<?= htmlspecialchars($issue['key']) ?>" <?= $shouldCheck ? 'checked' : '' ?>>
                        </td>
                        <td>
                            <a href="https://gojira.enedis.fr/browse/<?= htmlspecialchars($issue['key']) ?>" target="_blank" style="color: #0052cc; text-decoration: none; font-weight: bold;">
                                <?= htmlspecialchars($issue['key']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($issue['fields']['issuetype']['name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($issue['fields']['summary'] ?? '') ?></td>
                        <td>
                            <span class="status-badge" style="background: #e9ecef; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                                <?= htmlspecialchars($issue['fields']['status']['name'] ?? '') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($issue['fields']['assignee']['displayName'] ?? 'Non assigné') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Boutons bascule par statut
    const toggleButtons = document.querySelectorAll('.btn-status-toggle');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetStatus = this.getAttribute('data-status');
            const rows = document.querySelectorAll('.demo-ticket-row');
            
            // On vérifie si les tickets de ce statut sont actuellement tous cochés ou non
            let allChecked = true;
            rows.forEach(row => {
                const status = row.getAttribute('data-status');
                const checkbox = row.querySelector('.demo-checkbox');
                if (status.includes(targetStatus) && !checkbox.checked) {
                    allChecked = false;
                }
            });

            // Si tout est déjà coché, on décoche tout ; sinon, on coche tout ce qui correspond à ce statut
            rows.forEach(row => {
                const status = row.getAttribute('data-status');
                const checkbox = row.querySelector('.demo-checkbox');
                if (status.includes(targetStatus)) {
                    checkbox.checked = !allChecked;
                }
            });
        });
    });

    // 2. Case à cocher "Tout sélectionner"
    const selectAllCheckbox = document.getElementById('select-all-demo');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.demo-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    }
});
</script>