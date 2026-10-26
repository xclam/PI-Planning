<?php
namespace Models;

use Core\Model;

class TicketCache extends Model {
    protected static ?string $table = 'ticket_ai_cache';
    protected static string $primaryKey = 'id';

    /**
     * Récupère le cache IA s'il a été généré aujourd'hui pour un ticket donné
     */
    public static function getTodayCache(string $ticketKey): ?array {
        $db = \Core\Database::getConnection();
        $table = static::getTableName();
        
        $stmt = $db->prepare("SELECT ai_summary, ai_action FROM {$table} WHERE ticket_key = ? AND DATE(updated_at) = CURDATE() LIMIT 1");
        $stmt->execute([$ticketKey]);
        $row = $stmt->fetch();

        if ($row) {
            return [
                'summary' => $row['ai_summary'],
                'action' => $row['ai_action']
            ];
        }

        return null;
    }

    /**
     * Enregistre ou met à jour le cache IA pour un ticket (upsert)
     */
    public static function setCache(string $ticketKey, string $summary, string $action): void {
        $db = \Core\Database::getConnection();
        $table = static::getTableName();
        
        $stmt = $db->prepare("
            INSERT INTO {$table} (ticket_key, ai_summary, ai_action, updated_at) 
            VALUES (?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
            ai_summary = VALUES(ai_summary), 
            ai_action = VALUES(ai_action), 
            updated_at = NOW()
        ");
        $stmt->execute([$ticketKey, $summary, $action]);
    }
}