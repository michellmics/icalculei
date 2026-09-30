<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Mensagens do formulário de contato.
 */
class ContactMessage
{
    public const SUBJECTS = [
        'erro' => 'Encontrei um erro em uma calculadora',
        'sugestao' => 'Sugestão de calculadora ou conteúdo',
        'anuncie' => 'Quero anunciar',
        'privacidade' => 'Privacidade e meus dados (LGPD)',
        'outro' => 'Outro assunto',
    ];

    public static function create(array $messageData): int
    {
        return Database::insert(
            'INSERT INTO contact_messages (name, email, subject, tool_id, message) VALUES (:name, :email, :subject, :tool_id, :message)',
            [
                'name' => $messageData['name'],
                'email' => $messageData['email'],
                'subject' => $messageData['subject'],
                'tool_id' => $messageData['tool_id'],
                'message' => $messageData['message'],
            ]
        );
    }

    public static function latest(int $limit): array
    {
        return Database::fetchAll('SELECT * FROM contact_messages ORDER BY id DESC LIMIT ' . (int) $limit);
    }

    public static function countNew(): int
    {
        return (int) Database::fetchValue("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
    }

    public static function setStatus(int $messageId, string $status): void
    {
        Database::execute('UPDATE contact_messages SET status = :status WHERE id = :id', ['status' => $status, 'id' => $messageId]);
    }
}
