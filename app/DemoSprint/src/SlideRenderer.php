<?php

declare(strict_types=1);

namespace DemoSprint;

use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\AutoShape;
use PhpOffice\PhpPresentation\Shape\RichText;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Border;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;

/** Une slide par ticket. Coordonnées en pixels (layout 16:9 = 960x540). */
final class SlideRenderer
{
    private array $c;
    private array $f;

    public function __construct(
        array $theme,
        private readonly SiCatalog $catalog,
        private readonly int $maxObjective = 300,
    ) {
        $this->c = $theme['colors'];
        $this->f = $theme['fonts'];
    }

    public function render(SprintReport $report): PhpPresentation
    {
        $ppt = new PhpPresentation();
        $ppt->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_SCREEN_16X9);
        $ppt->removeSlideByIndex(0);

        $this->addRecapSlide($ppt->createSlide(), $report);
        foreach ($report->tickets as $ticket) {
            $this->addTicketSlide($ppt->createSlide(), $ticket);
        }
        return $ppt;
    }

    // ───────────────────────── Slide récapitulative ─────────────────────────

    private function addRecapSlide(Slide $slide, SprintReport $r): void
    {
        $s = $r->stats;

        $this->text($slide, 40, 30, 900, 40, 'Revue de sprint – ' . ($r->sprint['name'] ?? 'Sprint'), 24, $this->c['blue'], $this->f['title'], bold: true);

        $sub  = $this->period($r->sprint);
        $goal = trim((string) ($r->sprint['goal'] ?? ''));
        if ($goal !== '') {
            $sub .= ($sub !== '' ? "\n" : '') . 'Objectif : ' . (mb_strlen($goal) > 160 ? mb_substr($goal, 0, 159) . '…' : $goal);
        }
        if ($sub !== '') {
            $this->text($slide, 40, 75, 900, 45, $sub, 12, $this->c['greyText'], $this->f['body']);
        }

        $tiles = [
            ['Tickets',                      $s['total'],       $this->c['blue']],
            ['À faire',                      $s['todo'],        $this->c['grey']],
            ['En cours',                     $s['in_progress'], $this->c['purple']],
            ['Terminés',                     $s['done'],        $this->c['teal']],
            ['Ajoutés en cours de sprint',   $s['added'],       $this->c['file']],
            ['Sortis du sprint',             $s['removed'],     $this->c['greyText']],
        ];
        foreach ($tiles as $i => [$label, $value, $color]) {
            $this->tile($slide, 40 + $i * 152, 130, 140, 90, $label, $value === null ? 'n/d' : (string) $value, $color);
        }

        $this->addProgressBar($slide, $s);
        $this->addScopePanel($slide, $r);
        $this->addDevPanel($slide, $s['by_dev']);

        $this->text($slide, 40, 505, 300, 20, 'ENEDIS', 11, $this->c['blue'], $this->f['title'], bold: true);
    }

    private function addProgressBar(Slide $slide, array $s): void
    {
        if ($s['total'] <= 0) {
            return;
        }
        $ratio = $s['done'] / $s['total'];
        $label = sprintf('Avancement : %d %% (%d / %d tickets)', (int) round($ratio * 100), $s['done'], $s['total']);
        $this->text($slide, 40, 236, 560, 24, $label, 14, $this->c['black'], $this->f['body'], bold: true);

        if ($s['points_total'] !== null) {
            $fmt = static fn(float $v): string => rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',');
            $pts = $this->text($slide, 600, 236, 340, 24, 'Points : ' . $fmt($s['points_done']) . ' / ' . $fmt($s['points_total']), 14, $this->c['black'], $this->f['body'], bold: true);
            $pts->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $this->rect($slide, 40, 266, 900, 14, $this->c['track']);
        if ($ratio > 0) {
            $this->rect($slide, 40, 266, max(8, (int) round(900 * $ratio)), 14, $this->c['teal']);
        }
    }

    private function addScopePanel(Slide $slide, SprintReport $r): void
    {
        $presented = count($r->tickets);
        $noDev     = count($r->excluded);
        $other     = max(0, $r->stats['total'] - $presented - $noDev);
        $inferred  = count(array_filter($r->tickets, static fn(Ticket $t): bool => $t->assigneeInferred));

        $lines = [
            "Présentés en démo : {$presented}",
            "Éligibles sans dev identifiable : {$noDev}",
            "Hors périmètre démo (autres types / statuts) : {$other}",
        ];
        if ($inferred > 0) {
            $lines[] = "Dont attribués via l'historique : {$inferred}";
        }

        $this->text($slide, 40, 305, 430, 22, 'Périmètre de la démo', 14, $this->c['blue'], $this->f['title'], bold: true);
        $this->text($slide, 40, 332, 430, 140, implode("\n", $lines), 13, $this->c['black'], $this->f['body']);
    }

    private function addDevPanel(Slide $slide, array $byDev): void
    {
        $this->text($slide, 490, 305, 450, 22, 'Répartition par développeur', 14, $this->c['blue'], $this->f['title'], bold: true);

        if ($byDev === []) {
            $this->text($slide, 490, 332, 450, 24, 'Aucun ticket présenté', 13, $this->c['grey'], $this->f['body'], italic: true);
            return;
        }
        uasort($byDev, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);

        $lines = [];
        foreach (array_slice($byDev, 0, 7, true) as $name => $d) {
            $lines[] = sprintf('%s (%s) : %d ticket%s, dont %d terminé%s', $name, $d['role'], $d['total'], $d['total'] > 1 ? 's' : '', $d['done'], $d['done'] > 1 ? 's' : '');
        }
        if (count($byDev) > 7) {
            $lines[] = '+ ' . (count($byDev) - 7) . ' autre(s)';
        }
        $this->text($slide, 490, 332, 450, 160, implode("\n", $lines), 13, $this->c['black'], $this->f['body']);
    }

    private function period(array $sprint): string
    {
        $start = Changelog::date($sprint['startDate'] ?? null);
        $end   = Changelog::date($sprint['endDate'] ?? null);
        if ($start === null || $end === null) {
            return '';
        }
        return 'Du ' . $start->format('d/m/Y') . ' au ' . $end->format('d/m/Y');
    }

    private function tile(Slide $slide, int $x, int $y, int $w, int $h, string $label, string $value, string $color): void
    {
        $shape = $slide->createAutoShape()->setType(AutoShape::TYPE_ROUNDED_RECTANGLE)
            ->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
        $shape->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($color));
        $shape->getBorder()->getColor()->setARGB($color);

        $this->center($this->text($slide, $x, $y + 6, $w, 44, $value, 28, $this->c['white'], $this->f['body'], bold: true));
        $this->center($this->text($slide, $x + 6, $y + 52, $w - 12, 34, $label, 11, $this->c['white'], $this->f['body']));
    }

    private function rect(Slide $slide, int $x, int $y, int $w, int $h, string $color): void
    {
        $shape = $slide->createAutoShape()->setType(AutoShape::TYPE_ROUNDED_RECTANGLE)
            ->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
        $shape->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($color));
        $shape->getBorder()->getColor()->setARGB($color);
    }

    private function center(RichText $shape): RichText
    {
        $shape->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        return $shape;
    }

    // ───────────────────────── Slides par ticket ─────────────────────────

    private function addTicketSlide(Slide $slide, Ticket $t): void
    {
        $this->addHeader($slide, $t);
        $this->addBadges($slide, $t);
        $this->addBody($slide, $t);

        $this->addCodeBox($slide, 315, 'PATH', $t->field('path'), $this->c['pathFill'], $this->c['pathBorder']);
        $this->addCodeBox($slide, 410, 'HIVE', $t->field('hive'), $this->c['hiveFill'], $this->c['hiveBorder']);

        $this->addFooter($slide, $t);
    }

    private function addHeader(Slide $slide, Ticket $t): void
    {
        $label = ($t->si ?? 'SI ?') . ' – ' . $t->title;
        $this->text($slide, 40, 30, 900, 40, $label, 24, $this->c['blue'], $this->f['title'], bold: true);

        $siDescription = $t->si !== null ? $this->catalog->describe($t->si) : null;
        if ($siDescription !== null) {
            $this->text($slide, 40, 75, 900, 50, $siDescription, 12, $this->c['greyText'], $this->f['body']);
        }
    }

    private function addBadges(Slide $slide, Ticket $t): void
    {
        $this->badge($slide, 40, 140, $t->isNew ? 'Nouveau' : 'Evolution', $t->isNew ? $this->c['new'] : $this->c['teal']);
        $this->badge($slide, 40, 185, $t->isDone ? 'Terminé' : 'Recette', $t->isDone ? $this->c['grey'] : $this->c['purple']);

        $transportColor = match ($t->transport) {
            'KAFKA' => $this->c['kafka'],
            'FILE'  => $this->c['file'],
            default => $this->c['grey'],
        };
        $this->badge($slide, 40, 240, $t->transport, $transportColor);
    }

    private function addBody(Slide $slide, Ticket $t): void
    {
        $objectif = $t->field('objectif') ?: $t->field('type_flux');
        if ($objectif !== '') {
            $size = mb_strlen($objectif) > 160 ? 12 : 14;   // pas d'autofit dans PhpPresentation
            $this->text($slide, 200, 140, 700, 90, $this->truncate($objectif), $size, $this->c['black'], $this->f['body']);
        } else {
            $this->text($slide, 200, 140, 700, 90, 'Objectif non renseigné dans le ticket', 14, $this->c['grey'], $this->f['body'], italic: true);
        }

        $this->text($slide, 200, 235, 700, 30, 'Consommateurs : ' . ($t->field('consommateurs') ?: 'Non spécifié'), 13, $this->c['black'], $this->f['body']);
        $this->text($slide, 200, 270, 700, 30, 'OM : ' . ($t->field('choree') ?: 'Non spécifié'), 13, $this->c['black'], $this->f['body']);
    }

    private function addFooter(Slide $slide, Ticket $t): void
    {
        $this->text($slide, 40, 505, 300, 20, 'ENEDIS', 11, $this->c['blue'], $this->f['title'], bold: true);
        $who   = $t->key . '  ·  ' . $t->assignee;
        $right = $this->text($slide, 440, 505, 480, 20, $who, 11, $this->c['greyText'], $this->f['body']);
        $right->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function addCodeBox(Slide $slide, int $y, string $title, string $content, string $fill, string $border): void
    {
        $x = 40; $w = 900; $h = 75;

        $box = $slide->createAutoShape()->setType(AutoShape::TYPE_ROUNDED_RECTANGLE)
            ->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
        $box->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($fill));
        $box->getBorder()->setLineStyle(Border::LINE_SINGLE)->setLineWidth(1.5)->getColor()->setARGB($border);

        // Plus de valeur factice : un champ vide est affiché comme tel
        if ($content !== '') {
            $this->text($slide, $x + 15, $y + 22, $w - 30, $h - 25, $content, 12, $this->c['black'], $this->f['mono']);
        } else {
            $this->text($slide, $x + 15, $y + 22, $w - 30, $h - 25, 'Non renseigné', 12, $this->c['grey'], $this->f['mono'], italic: true);
        }

        $tab = $slide->createAutoShape()->setType(AutoShape::TYPE_ROUNDED_RECTANGLE)
            ->setOffsetX($x + 25)->setOffsetY($y - 10)->setWidth(75)->setHeight(22);
        $tab->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($this->c['white']));
        $tab->getBorder()->setLineStyle(Border::LINE_SINGLE)->setLineWidth(1.5)->getColor()->setARGB($border);

        $label = $this->text($slide, $x + 25, $y - 10, 75, 22, $title, 11, $this->c['black'], $this->f['title'], bold: true);
        $label->getActiveParagraph()->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function badge(Slide $slide, int $x, int $y, string $label, string $bg): void
    {
        $shape = $slide->createAutoShape()->setType(AutoShape::TYPE_ROUNDED_RECTANGLE)
            ->setOffsetX($x)->setOffsetY($y)->setWidth(100)->setHeight(32);
        $shape->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($bg));
        $shape->getBorder()->getColor()->setARGB($bg);

        $txt = $this->text($slide, $x, $y, 100, 32, $label, 12, $this->c['white'], $this->f['body'], bold: true);
        $txt->getActiveParagraph()->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    }

    /** Zone de texte, avec retours à la ligne préservés. */
    private function text(
        Slide $slide, int $x, int $y, int $w, int $h, string $content,
        int $size, string $color, string $font, bool $bold = false, bool $italic = false,
    ): RichText {
        $shape = $slide->createRichTextShape()->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
        foreach (preg_split('/\R/', $content) ?: [''] as $i => $line) {
            if ($i > 0) {
                $shape->createBreak();
            }
            $shape->createTextRun($line)->getFont()
                ->setBold($bold)->setItalic($italic)->setSize($size)->setColor(new Color($color))->setName($font);
        }
        return $shape;
    }

    /** Coupe à la limite d'un mot (et non au milieu). */
    private function truncate(string $text): string
    {
        if (mb_strlen($text) <= $this->maxObjective) {
            return $text;
        }
        $cut   = mb_substr($text, 0, $this->maxObjective - 1);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > $this->maxObjective * 0.6) {
            $cut = mb_substr($cut, 0, $space);
        }
        return rtrim($cut, " ,;:.-") . '…';
    }
}