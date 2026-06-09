<?php

require_once __DIR__ . '/../Services/BillingService.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class BillingController
{
    private BillingService $service;

    public function __construct()
    {
        $this->service = new BillingService();
    }

    // POST /auth/billing/invoice

    public function createInvoice(array $body): void
    {
        $validator = new Validator($body);

        $validator->required(['patient_id','total_amount'])
                  ->numeric('patient_id')
                  ->numeric('total_amount');

        if ($validator->fails()) 
        {
            Response::validationError( $validator->errors() );
        }

        try {
            $invoice = $this->service->createInvoice($body, AuthMiddleware::user());

            Response::created(
                $invoice,
                'Invoice created successfully.'
            );

        } catch (RuntimeException $e) {

            Response::error(
                $e->getMessage(),
                $e->getCode() ?: HTTP_BAD_REQUEST
            );
        }
    }
    // GET /auth/billing/invoice/{id}
   
    public function viewInvoice(
        int $invoiceId
    ): void {

        try {

            $invoice = $this->service->getInvoice(
                $invoiceId,
                AuthMiddleware::tenantId()
            );

            Response::success(
                $invoice,
                'Invoice fetched successfully.'
            );

        } catch (RuntimeException $e) {

            Response::error(
                $e->getMessage(),
                $e->getCode() ?: HTTP_NOT_FOUND
            );
        }
    }
    // GET /auth/billing/invoices

    public function listInvoices(): void
    {
        try {
            $invoices = $this->service->getInvoices( AuthMiddleware::tenantId());

            Response::success(
                $invoices,
                'Invoices fetched successfully.'
            );

        } catch (RuntimeException $e) {

            Response::error(
                $e->getMessage(),
                $e->getCode() ?: HTTP_BAD_REQUEST
            );
        }
    }
    // POST /auth/billing/payment
    
    public function recordPayment(array $body): void {

        $validator = new Validator($body);

        $validator->required([
                'invoice_id',
                'amount',
                'payment_method'
            ])
            ->numeric('invoice_id')
            ->numeric('amount');

        if ($validator->fails()) 
        {
            Response::validationError($validator->errors());
        }

        try {
            $result = $this->service->recordPayment($body, AuthMiddleware::user());

            Response::success(
                $result,
                'Payment recorded successfully.'
            );

        } catch (RuntimeException $e) {

            Response::error(
                $e->getMessage(),
                $e->getCode() ?: HTTP_BAD_REQUEST
            );
        }
    }

    // PUT /auth/billing/payment/{id}

    public function updatePayment( int $paymentId, array $body): void
{
    try {
        $result = $this->service->updatePayment(
            $paymentId,
            $body,
            AuthMiddleware::user()
        );

        Response::success(
            $result,
            "Payment updated successfully."
        );

    } catch (RuntimeException $e) {

        Response::error(
            $e->getMessage(),
            HTTP_BAD_REQUEST
        );
    }
}
    // PUT /auth/billing/invoice/{id}
   
    public function updateInvoice(int $id, array $body): void
   {
    try {
        $invoice = $this->service->updateInvoice(
            $id,
            $body,
            AuthMiddleware::user()
        );

        Response::success($invoice, "Invoice updated successfully.");

    } catch (RuntimeException $e)
     {
        Response::error(
            $e->getMessage(),
            $e->getCode() ?: HTTP_BAD_REQUEST
        );
    }
}

   // DELETE /auth/billing/invoice/{id}

   public function deleteInvoice(int $id): void
   {
    try {
        $this->service->deleteInvoice( $id, AuthMiddleware::user() );

        Response::success(null, "Invoice deleted successfully.");

    } catch (RuntimeException $e) 
    {
        Response::error($e->getMessage(), HTTP_BAD_REQUEST);
    }
   }

    // GET /auth/billing/summary
    public function summary(): void
    {
        try {
            $summary = $this->service->getSummary(AuthMiddleware::tenantId());

            Response::success(
                $summary,
                'Billing summary fetched successfully.'
            );

        } catch (RuntimeException $e) {

            Response::error(
                $e->getMessage(),
                $e->getCode() ?: HTTP_BAD_REQUEST
            );
        }
    }
}