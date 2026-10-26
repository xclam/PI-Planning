<?php
$status = $pi->status();

/** Tableau de sélection réutilisé pour features et enablers. */
$renderTable = static function (string $group, string $inputName, array $issues, array $createdUs, string $emptyMessage): void { ?>
    <figure>
        <table>
            <thead>
                <tr>
                    <th class="col-check"><input type="checkbox" data-select-all="<?= e($group) ?>" aria-label="Tout sélectionner"></th>
                    <th>Ticket</th>
                    <th>Titre</th>
                    <th>Statut</th>
                    <th>US créée</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$issues): ?>
                <tr><td colspan="5"><?= e($emptyMessage) ?></td></tr>
            <?php else: ?>
                <?php foreach ($issues as $issue):
                    $key       = (string) $issue['key'];
                    $usKey     = $createdUs[$key] ?? null;
                    $statusObj = $issue['fields']['status'] ?? [];
                ?>
                    <tr>
                        <td class="col-check">
                            <input type="checkbox" name="<?= e($inputName) ?>[]" value="<?= e($key) ?>"
                                   data-check-group="<?= e($group) ?>" <?= $usKey ? '' : 'checked' ?>>
                        </td>
                        <td><a href="<?= e(jira_url($key)) ?>" target="_blank" rel="noopener noreferrer"><?= e($key) ?></a></td>
                        <td><?= e($issue['fields']['summary'] ?? '') ?></td>
                        <td><span class="badge badge-<?= e(status_class($statusObj)) ?>"><?= e($statusObj['name'] ?? '') ?></span></td>
                        <td>
                            <?php if ($usKey): ?>
                                <a href="<?= e(jira_url($usKey)) ?>" target="_blank" rel="noopener noreferrer"><?= e($usKey) ?></a> ✓
                            <?php else: ?>
                                <span class="muted">–</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </figure>
<?php };
?>
<hgroup>
    <h1><?= e($pi->name) ?> <span class="badge badge-pi-<?= e($status) ?>"><?= e(\Models\Pi::STATUS_LABELS[$status]) ?></span></h1>
    <p><?= e(fmt_date($pi->start_date)) ?> → <?= e(fmt_date($pi->end_date)) ?></p>
</hgroup>

<?php if (!empty($errorJira)): ?>
    <div class="flash flash-error" role="alert"><?= e($errorJira) ?></div>
<?php endif; ?>

<details>
    <summary role="button" class="outline secondary">Modifier le Program Increment</summary>
    <article class="form-card">
        <?php partial('pi/_form', [
            'action'      => url('pi/update'),
            'pi'          => $pi,
            'sprints'     => $sprints,
            'submitLabel' => 'Enregistrer les modifications',
            'cancelUrl'   => url('pi/show', ['id' => $pi->id]),
        ]); ?>
    </article>
</details>

<form method="post" action="<?= e(url('pi/create-us')) ?>" data-confirm="Créer dans Jira les US des tickets sélectionnés ?">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $pi->id ?>">

    <div class="toolbar">
        <h2>Création des US</h2>
        <button type="submit">Créer les US sélectionnées</button>
    </div>

    <div class="grid">
        <article>
            <header>
                <strong>Features <?= e(\Services\UserStoryService::FEATURE_PREFIX) ?></strong>
                <small class="muted">· <?= e($pi->feature_sprint_name ?? 'aucun sprint') ?> · <span data-count-for="features"></span></small>
            </header>
            <?php $renderTable('features', 'features', $features, $createdUs, 'Aucune feature ' . \Services\UserStoryService::FEATURE_PREFIX . ' trouvée.'); ?>
        </article>

        <article>
            <header>
                <strong>Enablers <?= e(\Services\UserStoryService::ENABLER_PREFIX) ?></strong>
                <small class="muted">· <?= e($pi->enabler_sprint_name ?? 'aucun sprint') ?> · <span data-count-for="enablers"></span></small>
            </header>
            <?php $renderTable('enablers', 'enablers', $enablers, $createdUs, 'Aucun enabler ' . \Services\UserStoryService::ENABLER_PREFIX . ' trouvé.'); ?>
        </article>
    </div>
</form>

<p><a href="<?= e(url('/')) ?>">← Retour à la liste des PI</a></p>