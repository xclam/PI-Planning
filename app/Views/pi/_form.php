<?php
/**
 * @var string  $action
 * @var ?object $pi        null à la création
 * @var array   $sprints
 * @var string  $submitLabel
 * @var string  $cancelUrl
 * @var string  $nextName  (création uniquement)
 */
$val = static fn(string $field, mixed $default = ''): mixed => old($field, $pi?->$field ?? $default);

$selectedSprint = static function (string $oldField, string $piField) use ($pi): int {
    $submitted = (string) old($oldField, '');
    if ($submitted !== '') {
        return (int) strtok($submitted, '|');
    }
    return (int) ($pi?->$piField ?? 0);
};
?>
<form method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($pi): ?>
        <input type="hidden" name="id" value="<?= (int) $pi->id ?>">
    <?php endif; ?>

    <label>
        Nom du PI
        <input type="text" name="name" maxlength="100" required value="<?= e($val('name', $nextName ?? '')) ?>">
    </label>

    <div class="grid">
        <label>
            Date de début
            <input type="date" name="start_date" required value="<?= e($val('start_date')) ?>">
        </label>
        <label>
            Date de fin
            <input type="date" name="end_date" required value="<?= e($val('end_date')) ?>">
        </label>
    </div>

    <div class="grid">
        <label>
            Sprint Features
            <select name="feature_sprint">
                <?= sprint_options($sprints, $selectedSprint('feature_sprint', 'feature_sprint_id'), $pi?->feature_sprint_name, '— Sélectionner un sprint —') ?>
            </select>
        </label>
        <label>
            Sprint Enablers
            <select name="enabler_sprint">
                <?= sprint_options($sprints, $selectedSprint('enabler_sprint', 'enabler_sprint_id'), $pi?->enabler_sprint_name, '— Sélectionner un sprint —') ?>
            </select>
        </label>
    </div>

    <div class="form-actions">
        <a href="<?= e($cancelUrl) ?>" role="button" class="secondary outline">Annuler</a>
        <button type="submit"><?= e($submitLabel) ?></button>
    </div>
</form>