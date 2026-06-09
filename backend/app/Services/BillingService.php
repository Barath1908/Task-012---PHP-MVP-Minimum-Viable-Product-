<?php

require_once __DIR__ . '/../Config/database.php';

class BillingService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // CREATE INVOICE

    public function createInvoice(array $data, array $authUser): array 
    {
        $tenantId = (int)$authUser['tenant_id'];
        $userId   = (int)$authUser['user_id'];

        $patientId      = (int)$data['patient_id'];
        $appointmentId  = $data['appointment_id'] ?? null;
        $totalAmount    = (float)$data['total_amount'];
        $discountAmount = (float)($data['discount_amount'] ?? 0);
        $taxAmount      = (float)($data['tax_amount'] ?? 0);

        $finalAmount = $totalAmount - $discountAmount + $taxAmount;

        $stmt = $this->db->prepare("
            INSERT INTO invoices
            (
                tenant_id,
                patient_id,
                appointment_id,
                issued_by,
                total_amount,
                discount_amount,
                tax_amount,
                final_amount,
                status,
                due_date,
                notes
            )
            VALUES
            (
                :tenant_id,
                :patient_id,
                :appointment_id,
                :issued_by,
                :total_amount,
                :discount_amount,
                :tax_amount,
                :final_amount,
                :status,
                :due_date,
                :notes
            )
        ");

        $stmt->execute([
            ':tenant_id'       => $tenantId,
            ':patient_id'      => $patientId,
            ':appointment_id'  => $appointmentId,
            ':issued_by'       => $userId,
            ':total_amount'    => $totalAmount,
            ':discount_amount' => $discountAmount,
            ':tax_amount'      => $taxAmount,
            ':final_amount'    => $finalAmount,
            ':status'          => INV_ISSUED,
            ':due_date'        => $data['due_date'] ?? null,
            ':notes'           => $data['notes'] ?? null
        ]);

        $invoiceId = (int)$this->db->lastInsertId();

        return $this->getInvoice( $invoiceId, $tenantId );
    }

    // GET SINGLE INVOICE

    public function getInvoice(int $invoiceId, int $tenantId): array 
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM invoices
            WHERE
                id = ?
                AND tenant_id = ?
                AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([ $invoiceId, $tenantId ]);

        $invoice = $stmt->fetch();

        if (!$invoice) 
        { 
            throw new RuntimeException('Invoice not found.', HTTP_NOT_FOUND);
        }
        return $invoice;
    }

    // GET ALL INVOICES
    
    public function getInvoices(int $tenantId): array 
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM invoices
            WHERE
                tenant_id = ?
                AND deleted_at IS NULL
            ORDER BY id DESC
        ");

        $stmt->execute([$tenantId]);

        return $stmt->fetchAll();
    }

    // RECORD PAYMENT

    public function recordPayment(array $data,array $authUser): array 
    {
        $tenantId = (int)$authUser['tenant_id'];
        $userId   = (int)$authUser['user_id'];

        $invoiceId = (int)$data['invoice_id'];
        $amount    = (float)$data['amount'];

        $invoice = $this->getInvoice( $invoiceId, $tenantId );

        $stmt = $this->db->prepare("
            INSERT INTO payments
            (
                tenant_id,
                invoice_id,
                patient_id,
                amount,
                payment_method,
                transaction_ref,
                status,
                paid_at
            )
            VALUES
            (
                :tenant_id,
                :invoice_id,
                :patient_id,
                :amount,
                :payment_method,
                :transaction_ref,
                :status,
                NOW()
            )
        ");

        $stmt->execute([
            ':tenant_id'      => $tenantId,
            ':invoice_id'     => $invoiceId,
            ':patient_id'     => $invoice['patient_id'],
            ':amount'         => $amount,
            ':payment_method' => $data['payment_method'],
            ':transaction_ref'=> $data['transaction_ref'] ?? null,
            ':status'         => PAY_COMPLETED
        ]);

        // Calculate total paid amount

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount),0)
            FROM payments
            WHERE
                invoice_id = ?
                AND status = ?
        ");

        $stmt->execute([ $invoiceId, PAY_COMPLETED ]);

        $paidAmount = (float)$stmt->fetchColumn();

        $invoiceStatus = INV_PARTIALLY_PAID;

        if ($paidAmount >= (float)$invoice['final_amount']) 
        {
            $invoiceStatus = INV_PAID;
        }

        $stmt = $this->db->prepare("
            UPDATE invoices
            SET
                status = ?
            WHERE id = ?
        ");

        $stmt->execute([ $invoiceStatus, $invoiceId ]);

        return [
            'invoice_id' => $invoiceId,
            'paid_amount' => $paidAmount,
            'invoice_status' => $invoiceStatus
        ];
    }

    // update invoice
     
    public function updateInvoice( int $invoiceId, array $data, array $authUser ): array 
    {
    $tenantId = (int)$authUser['tenant_id'];
    $userId   = (int)$authUser['user_id'];

    // ensure invoice exists
    $invoice = $this->getInvoice($invoiceId, $tenantId);

    $stmt = $this->db->prepare("
        UPDATE invoices
        SET
            discount_amount = ?,
            tax_amount = ?,
            final_amount = ?,
            due_date = ?,
            notes = ?,
            updated_at = NOW()
        WHERE id = ?
        AND tenant_id = ?
        AND deleted_at IS NULL
    ");

    $finalAmount =
        $invoice['total_amount']
        - ($data['discount_amount'] ?? $invoice['discount_amount'])
        + ($data['tax_amount'] ?? $invoice['tax_amount']);

    $stmt->execute([
        $data['discount_amount'] ?? $invoice['discount_amount'],
        $data['tax_amount'] ?? $invoice['tax_amount'],
        $finalAmount,
        $data['due_date'] ?? $invoice['due_date'],
        $data['notes'] ?? $invoice['notes'],
        $invoiceId,
        $tenantId
    ]);

    return $this->getInvoice($invoiceId, $tenantId);
}

    // soft delete invoice
    
    public function deleteInvoice( int $invoiceId, array $authUser ): void 
    {
    $tenantId = (int)$authUser['tenant_id'];
    $userId   = (int)$authUser['user_id'];

    $this->getInvoice($invoiceId, $tenantId);

    $stmt = $this->db->prepare("
        UPDATE invoices
        SET
            deleted_at = NOW()
        WHERE id = ?
        AND tenant_id = ?
    ");

    $stmt->execute([ $invoiceId, $tenantId ]);
}

  // PUT/ auth/billing/payment/{id} - update payment

  public function updatePayment(
    int $paymentId,
    array $data,
    array $authUser
): array
{
    $userId = (int)$authUser['user_id'];

    $stmt = $this->db->prepare("
        SELECT *
        FROM payments
        WHERE id = ?
    ");

    $stmt->execute([$paymentId]);

    $payment = $stmt->fetch();

    if (!$payment)
    {
        throw new RuntimeException(
            'Payment not found.'
        );
    }

    $stmt = $this->db->prepare("
        UPDATE payments
        SET
            status = ?,
    
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $data['status'],
        $paymentId
    ]);

    return [
        'payment_id' => $paymentId,
        'status' => $data['status']
    ];
}


    // BILLING SUMMARY

    public function getSummary(int $tenantId): array 
    {
        $stmt = $this->db->prepare("
            SELECT COUNT id 
            FROM invoices
            WHERE tenant_id = ?
            AND deleted_at IS NULL
        ");

        $stmt->execute([$tenantId]);

        $totalInvoices = (int)$stmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT COUNT id
            FROM invoices
            WHERE
                tenant_id = ?
                AND status = ?
                AND deleted_at IS NULL
        ");

        $stmt->execute([ $tenantId, INV_PAID ]);

        $paid = (int)$stmt->fetchColumn();

        $stmt->execute([ $tenantId, INV_PARTIALLY_PAID ]);

        $partialPaid = (int)$stmt->fetchColumn();

        $stmt->execute([ $tenantId, INV_CANCELLED]);

        $cancelled = (int)$stmt->fetchColumn();

        return [
            'total_invoices' => $totalInvoices,
            'paid' => $paid,
            'partially_paid' => $partialPaid,
            'cancelled' => $cancelled
        ];
    }
}
  

   