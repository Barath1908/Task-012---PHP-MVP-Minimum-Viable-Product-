<?php

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/AES.php';

class MessageService
{
    private PDO $db;
    private AES $aes;

    public function __construct()
    {
        $this->db  = Database::getConnection();
        $this->aes = new AES();
    }

    // CREATE MESSAGE

    public function createMessage(
        array $data,
        array $authUser
    ): array {

        $tenantId = (int)$authUser['tenant_id'];
        $userId   = (int)$authUser['user_id'];

        $content = $this->aes->encrypt(
            $data['content']
        );

        $stmt = $this->db->prepare("
            INSERT INTO messages
            (
                tenant_id,
                appointment_id,
                sender_id,
                receiver_id,
                content,
                is_read
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $tenantId,
            $data['appointment_id'] ?? null,
            $userId,
            $data['receiver_id'] ?? null,
            $content,
            $data['is_read'] ?? 0
        ]);

        return [
            'message_id' => (int)$this->db->lastInsertId()
        ];
    }

    // GET SINGLE MESSAGE

    public function getMessage(
        int $messageId,
        int $tenantId
    ): array {

        $stmt = $this->db->prepare("
            SELECT *
            FROM messages
            WHERE
                id = ?
                AND tenant_id = ?
                AND deleted_at IS NULL
        ");

        $stmt->execute([
            $messageId,
            $tenantId
        ]);

        $message = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$message) {
            throw new RuntimeException(
                'Message not found.',
                HTTP_NOT_FOUND
            );
        }

        $message['content']
            = $this->aes->decrypt(
                $message['content']
            );

        return $message;
    }

    // APPOINTMENT MESSAGE HISTORY

    public function getAppointmentMessages(
        int $appointmentId,
        int $tenantId
    ): array {

        $stmt = $this->db->prepare("
            SELECT *
            FROM messages
            WHERE
                appointment_id = ?
                AND tenant_id = ?
                AND deleted_at IS NULL
            ORDER BY created_at ASC
        ");

        $stmt->execute([
            $appointmentId,
            $tenantId
        ]);

        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($messages as &$message) {

            $message['content']
                = $this->aes->decrypt(
                    $message['content']
                );
        }

        return $messages;
    }

    // MARK MESSAGE AS READ

    public function markAsRead(
        int $messageId,
        array $authUser
    ): array {

        $tenantId = (int)$authUser['tenant_id'];
        $userId   = (int)$authUser['user_id'];

        $stmt = $this->db->prepare("
            SELECT *
            FROM messages
            WHERE
                id = ?
                AND tenant_id = ?
                AND deleted_at IS NULL
        ");

        $stmt->execute([
            $messageId,
            $tenantId
        ]);

        $message = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$message) {
            throw new RuntimeException(
                'Message not found.',
                HTTP_NOT_FOUND
            );
        }

        $stmt = $this->db->prepare("
            UPDATE messages
            SET
                is_read = 1,
                updated_at = NOW()
            WHERE
                id = ?
                AND tenant_id = ?
        ");

        $stmt->execute([
            $messageId,
            $tenantId
        ]);

        return [
            'message_id' => $messageId,
            'is_read'    => 1,
            'updated_by' => $userId
        ];
    }

    // SOFT DELETE MESSAGE

    public function deleteMessage(
        int $messageId,
        array $authUser
    ): array {

        $tenantId = (int)$authUser['tenant_id'];

        $stmt = $this->db->prepare("
            SELECT id
            FROM messages
            WHERE
                id = ?
                AND tenant_id = ?
                AND deleted_at IS NULL
        ");

        $stmt->execute([
            $messageId,
            $tenantId
        ]);

        if (!$stmt->fetch()) {
            throw new RuntimeException(
                'Message not found.',
                HTTP_NOT_FOUND
            );
        }

        $stmt = $this->db->prepare("
            UPDATE messages
            SET
                deleted_at = NOW(),
                updated_at = NOW()
            WHERE
                id = ?
                AND tenant_id = ?
        ");

        $stmt->execute([
            $messageId,
            $tenantId
        ]);

        return [
            'message_id' => $messageId,
            'deleted'    => true
        ];
    }
}