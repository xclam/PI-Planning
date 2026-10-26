<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use Core\Model;

class TicketCache extends Model
{
    protected static ?string $table = 'ticket_ai_cache';

    /**
     * Le cache est valide tant que le ticket n'a pas bougé dans Jira (champ `updated`),
     * dans la limite de $maxAgeHours. Avant : "1 fois par jour", même si le ticket changeait.
     */
    public static function getValid(string $ticketKey, string $issueUpdated, int $maxAgeHours = 48): ?array
    {
        try {
            $hours = max(1, $maxAgeHours);
            $stmt  = Database::getConnection()->prepare(
                'SELECT ai_summary, ai_action, latest_comment FROM ' . static::getTableName() .
                " WHERE ticket_key = ? AND issue_updated = ? AND updated_at >= (NOW() - INTERVAL {$hours} HOUR) LIMIT 1"
            );
            $stmt->execute([$ticketKey, $issueUpdated]);
            $row = $stmt->fetch();
        } catch (\PDOException $e) {
            error_log('TicketCache::getValid : ' . $e->getMessage()); // ex. migration non appliquée
            return null;
        }

        return $row ? [
            'summary'        => (string) $row['ai_summary'],
            'action'         => (string) $row['ai_action'],
            'latest_comment' => (string) $row['latest_comment'],
        ] : null;
    }

    public static function setCache(string $ticketKey, string $summary, string $action, string $latestComment, string $issueUpdated): void
    {
        try {
            Database::getConnection()->prepare(
                'INSERT INTO ' . static::getTableName() . '
                    (ticket_key, ai_summary, ai_action, latest_comment, issue_updated, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                    ai_summary = VALUES(ai_summary),
                    ai_action = VALUES(ai_action),
                    latest_comment = VALUES(latest_comment),
                    issue_updated = VALUES(issue_updated),
                    updated_at = NOW()'
            )->execute([$ticketKey, $summary, $action, $latestComment, $issueUpdated]);
        } catch (\PDOException $e) {
            error_log('TicketCache::setCache : ' . $e->getMessage());
        }
    }
}