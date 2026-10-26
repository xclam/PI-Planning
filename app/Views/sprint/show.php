<hgroup>
    <h1><?= e($sprintName) ?></h1>
    <p class="muted"><?= count($issues) ?> ticket(s)</p>
</hgroup>

<p><a href="<?= e(url('/')) ?>">← Retour</a></p>

<?php if (!empty($errorJira)): ?>
    <div class="flash flash-error" role="alert"><?= e($errorJira) ?></div>
<?php endif; ?>

<?php if ($statusCounts): ?>
    <p class="status-summary">
        <?php foreach ($statusCounts as $name => $count): ?>
            <span class="badge badge-neutral"><?= e($name) ?> · <?= (int) $count ?></span>
        <?php endforeach; ?>
    </p>
<?php endif; ?>

<section>
    <h2>Mes tickets</h2>

    <?php if (!empty($missingUser)): ?>
        <div class="flash flash-info">Renseignez <code>jira.username</code> dans la configuration pour afficher vos tickets.</div>
    <?php elseif (empty($myTickets)): ?>
        <p class="muted">Aucun ticket ne vous est attribué dans ce sprint.</p>
    <?php else: ?>
        <?php foreach ($myTickets as $ticket): ?>
            <article class="ticket<?= $ticket['is_recent_24h'] ? ' ticket-recent' : '' ?>">
                <header class="ticket-head">
                    <a href="<?= e(jira_url($ticket['key'])) ?>" target="_blank" rel="noopener noreferrer"><strong><?= e($ticket['key']) ?></strong></a>
                    <span class="ticket-title"><?= e($ticket['fields']['summary'] ?? '') ?></span>
                    <?php if ($ticket['is_recent_24h']): ?><span class="badge badge-new">Modifié &lt; 24 h</span><?php endif; ?>
                    <span class="badge badge-<?= e(status_class($ticket['fields']['status'] ?? [])) ?>"><?= e($ticket['fields']['status']['name'] ?? '') ?></span>
                </header>

                <p class="ticket-comment">💬 <em><?= e($ticket['latest_comment']) ?></em></p>

                <?php if ($ticket['ai_summary'] !== '' || $ticket['ai_action'] !== ''): ?>
                    <div class="ai-box">
                        <?php if ($ticket['ai_summary'] !== ''): ?>
                            <div><strong>📌 En bref :</strong> <?= e($ticket['ai_summary']) ?></div>
                        <?php endif; ?>
                        <?php if ($ticket['ai_action'] !== ''): ?>
                            <div class="ai-action">🎯 Action conseillée : <?= e($ticket['ai_action']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section>
    <div class="toolbar">
        <h2>Préparation de la démo <small class="muted" data-count-for="demo"></small></h2>
        <div class="toolbar-actions">
            <button type="button" class="outline secondary btn-status-toggle" data-status="terminé">Terminé</button>
            <button type="button" class="outline secondary btn-status-toggle" data-status="à tester">À tester</button>
            <button type="button" class="outline secondary btn-status-toggle" data-status="en réalisation">En réalisation</button>
            <button type="button" class="outline" data-copy-keys="demo" data-format="list">Copier les clés</button>
            <button type="button" class="outline" data-copy-keys="demo" data-format="jql">Copier le JQL</button>
        </div>
    </div>

    <figure>
        <table>
            <thead>
                <tr>
                    <th class="col-check"><input type="checkbox" data-select-all="demo" aria-label="Tout sélectionner"></th>
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
                <?php foreach ($issues as $issue): $f = $issue['fields'] ?? []; ?>
                    <tr class="demo-ticket-row" data-status="<?= e(mb_strtolower((string) ($f['status']['name'] ?? ''))) ?>">
                        <td class="col-check">
                            <input type="checkbox" data-check-group="demo" value="<?= e($issue['key']) ?>" <?= $issue['_preselect'] ? 'checked' : '' ?>>
                        </td>
                        <td><a href="<?= e(jira_url($issue['key'])) ?>" target="_blank" rel="noopener noreferrer"><strong><?= e($issue['key']) ?></strong></a></td>
                        <td><?= e($f['issuetype']['name'] ?? '') ?></td>
                        <td><?= e($f['summary'] ?? '') ?></td>
                        <td><span class="badge badge-<?= e($issue['_status_class']) ?>"><?= e($f['status']['name'] ?? '') ?></span></td>
                        <td><?= e($f['assignee']['displayName'] ?? 'Non assigné') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </figure>
</section>