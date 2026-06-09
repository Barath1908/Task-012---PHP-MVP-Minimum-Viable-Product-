<?php

require_once __DIR__ . '/../Services/MessageService.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class MessageController
{
    private MessageService $service;

    public function __construct()
    {
        $this->service = new MessageService();
    }

    // CREATE MESSAGE

    public function createMessage(array $body): void
    {
        $validator = new Validator($body);

        $validator->required([
            'content'
        ]);

        if ($validator->fails()) {
            Response::validationError(
                $validator->errors()
            );
        }

        try {

            $result = $this->service->createMessage(
                $body,
                AuthMiddleware::user()
            );

            Response::created(
                $result,
                'Message created successfully.'
            );

        } catch (RuntimeException $e) {

            Response::error(
                $e->getMessage(),
                $e->getCode() ?: HTTP_BAD_REQUEST
            );
        }
    }

    // GET MESSAGE

    public function getMessage(int $messageId): void
    {
        try {

            $result = $this->service->getMessage(
                $messageId,
                AuthMiddleware::tenantId()
            );

            Response::success(
                $result,
                'Message fetched successfully.'
            );

        } catch (RuntimeException $e) {

            Response::error(
                $e->getMessage(),
                $e->getCode() ?: HTTP_BAD_REQUEST
            );
        }
    }

    // APPOINTMENT MESSAGE HISTORY

    public function getAppointmentMessages(
        int $appointmentId
    ): void {

        try {

            $result = $this->service->getAppointmentMessages(
                $appointmentId,
                AuthMiddleware::tenantId()
            );

            Response::success(
                $result,
                'Messages fetched successfully.'
            );

        } catch (RuntimeException $e) {

            Response::error(
                $e->getMessage(),
                $e->getCode() ?: HTTP_BAD_REQUEST
            );
        }
    }

    // MARK AS READ

    public function markAsRead(
        int $messageId
    ): void {

        try {

            $result = $this->service->markAsRead(
                $messageId,
                AuthMiddleware::user()
            );

            Response::success(
                $result,
                'Message marked as read.'
            );

        } catch (RuntimeException $e) {

            Response::error(
                $e->getMessage(),
                $e->getCode() ?: HTTP_BAD_REQUEST
            );
        }
    }

    // DELETE MESSAGE

    public function deleteMessage(
        int $messageId
    ): void {

        try {

            $result = $this->service->deleteMessage(
                $messageId,
                AuthMiddleware::user()
            );

            Response::success(
                $result,
                'Message deleted successfully.'
            );

        } catch (RuntimeException $e) {

            Response::error(
                $e->getMessage(),
                $e->getCode() ?: HTTP_BAD_REQUEST
            );
        }
    }
}