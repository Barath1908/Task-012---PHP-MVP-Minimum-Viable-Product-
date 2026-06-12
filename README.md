# Healthcare Management System Backend API (PHP MVP)

A secure multi-tenant Healthcare Management System Backend API built using Core PHP and MySQL.

The project follows an MVC-inspired architecture and provides RESTful APIs for authentication, patient management, appointment scheduling, prescriptions, billing, staff management, communication, and reporting.

The system is designed to serve healthcare organizations such as hospitals and clinics while maintaining strict tenant-based data isolation and security.

---

# Project Overview

The backend provides secure APIs for managing healthcare operations across multiple tenants (hospitals/clinics), ensuring that data belonging to one tenant is isolated from another.

Key objectives:

- Secure Authentication & Authorization
- Multi-Tenant Data Isolation
- Role-Based Access Control (RBAC)
- Patient & Appointment Management
- Prescription & Pharmacy Workflows
- Billing & Payment Tracking
- Staff Management
- Internal Communication
- Dashboard Analytics
- Security Best Practices

---

# Core Features

## Authentication & Tenant Management

- User Registration
- User Login
- JWT Access Token Generation
- JWT Refresh Token Generation
- Refresh Token Storage
- Refresh Token Rotation
- Tenant Validation
- Session Management
- Secure Logout
- Password Change
- CSRF Protection

---

## User & Role Management

- Role-Based Access Control (RBAC)
- Role Assignment
- Middleware-Based Authorization
- Secure User Profiles

Supported Roles:

- Admin
- Provider
- Nurse
- Patient
- Pharmacist
- Receptionist

---

## Patient Management

- Create Patient
- View Patient Records
- Update Patient Information
- Delete Patient
- Tenant-Based Access
- Appointment Linking
- Encrypted Sensitive Information

---

## Appointment & Scheduling

- Create Appointment
- Update Appointment
- Cancel Appointment
- Appointment Status Tracking
- Upcoming Appointment APIs
- Scheduling Validation
- Tenant Isolation

---

## Prescription & Pharmacy

- Create Prescriptions
- Prescription Status Tracking
- Pharmacy Verification Workflow
- Encrypted Prescription Information

---

## Dashboard & Reports

- Total Patients Count
- Appointment Statistics
- Prescription Summary
- Tenant-Based Analytics

---

## Communication

- Appointment Notes
- Internal Messaging
- Message History
- Role-Based Visibility

---

## Billing & Payments

- Invoice Generation
- Payment Tracking
- Pending Payment Summary
- Paid Payment Summary
- Tenant-Based Billing Data

---

## Staff Management

- Add Staff
- Update Staff
- Delete Staff
- Active / Inactive Status
- Role Assignment
- Tenant Segregation

---

## Security Settings

- Change Password
- Logout
- Refresh Token Rotation
- CSRF Regeneration

---

# Security Architecture

## JWT Authentication

The application uses JWT-based authentication for secure API access.

### Access Token

Purpose:

```text
Authenticate Protected API Requests
```

Contains:

```text
User ID
Tenant ID
Role
Issued Time
Expiration Time
```

### Refresh Token

Purpose:

```text
Generate New Access Tokens
```

Features:

- Stored in Database
- Rotated on Refresh
- Revoked During Logout
- Long-Lived Token Strategy

---

## Password Security

Passwords are never stored in plain text.

Uses:

```php
password_hash(..., PASSWORD_BCRYPT)
```

Verification:

```php
password_verify(...)
```

---

## AES Encryption

Sensitive information is encrypted before storage.

Examples:

- User Information
- Patient Information
- Messages
- Prescriptions

Encryption Standard:

```text
AES-256-CBC
```

---

## CSRF Protection

CSRF protection is implemented for state-changing requests.

Protected Methods:

```text
POST
PUT
PATCH
DELETE
```

CSRF tokens are regenerated during:

- Login
- Token Refresh

---

# Authentication Flow

```text
User Registration
        │
        ▼
Store User Information
        │
        ▼
User Login
        │
        ▼
Generate Access Token
Generate Refresh Token
        │
        ▼
Store Refresh Token
        │
        ▼
Access Protected APIs
        │
        ▼
Access Token Expires
        │
        ▼
Refresh Token API
        │
        ▼
Issue New Tokens
        │
        ▼
Logout
        │
        ▼
Revoke Refresh Tokens
Clear Session
Clear CSRF Token
```

---

# Project Structure

```text
backend/
│
├── public/
│   └── index.php
│
├── app/
│   ├── Config/
│   │   ├── config.php
│   │   ├── database.php
│   │   └── constants.php
│   │
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── PatientController.php
│   │   ├── AppointmentController.php
│   │   ├── PrescriptionController.php
│   │   ├── BillingController.php
│   │   ├── DashboardController.php
│   │   ├── MessageController.php
│   │   └── StaffController.php
│   │
│   ├── Services/
│   │   ├── AuthService.php
│   │   ├── PatientService.php
│   │   ├── AppointmentService.php
│   │   ├── PrescriptionService.php
│   │   ├── BillingService.php
│   │   ├── DashboardService.php
│   │   ├── MessageService.php
│   │   └── StaffService.php
│   │
│   ├── Security/
│   │   ├── JWT.php
│   │   ├── AES.php
│   │   ├── CSRF.php
│   │   └── Hash.php
│   │
│   ├── Middleware/
│   │   ├── AuthMiddleware.php
│   │   └── CsrfMiddleware.php
│   │
│   ├── Helpers/
│   │   ├── Response.php
│   │   └── Validator.php
│   │
│   └── Routes/
│       └── api.php
│
├── storage/
│   ├── logs/
│   └── sessions/
│
├── schema.sql
│
└── .env
```

---

# Database Overview

The system is organized around the following core entities:

### Tenant Management

```text
tenants
roles
users
refresh_tokens
```

### Patient Management

```text
patients
```

### Appointment Management

```text
appointments
```

### Prescription Management

```text
prescriptions
```

### Communication

```text
messages
```

### Billing

```text
invoices
payments
```

### Staff Management

```text
staff
```

---

# API Modules

| Module                  | Description                               |
| ----------------------- | ----------------------------------------- |
| Authentication          | User Registration, Login, Logout, Refresh |
| User Management         | User & Role Administration                |
| Patient Management      | Patient CRUD Operations                   |
| Appointment Management  | Scheduling & Tracking                     |
| Prescription Management | Prescription Lifecycle                    |
| Dashboard & Reports     | Statistics & Analytics                    |
| Communication           | Notes & Messaging                         |
| Billing & Payments      | Invoice & Payment Tracking                |
| Staff Management        | Staff Administration                      |
| Security Settings       | Password & Token Management               |

---

# Installation

## 1. Clone Repository

```bash
git clone <repository-url>
cd Task-012---PHP-MVP-Minimum-Viable-Product-
```

---

## 2. Configure Environment Variables

Create or update:

```text
backend/.env
```

Example:

```env
DB_HOST=localhost
DB_NAME=healthcare_mvp
DB_USER=root
DB_PASS=

JWT_SECRET=your_jwt_secret

AES_KEY=your_aes_key
AES_IV=your_aes_iv
```

---

## 3. Import Database

Import:

```text
schema.sql
```

into MySQL.

---

## 4. Configure Web Server

Set the document root to:

```text
backend/public
```

---

## 5. Run Application

Example URL:

```text
http://localhost/backend/public
```

---

# API Testing

The backend APIs can be tested using:

- Postman
- Insomnia
- Thunder Client

Recommended workflow:

```text
Register User
    ↓
Login
    ↓
Receive Access Token
    ↓
Call Protected APIs
    ↓
Refresh Token
    ↓
Logout
```

---

# Development Principles

- MVC-Inspired Architecture
- Service Layer Pattern
- Multi-Tenant Design
- RESTful API Development
- Separation of Concerns
- Reusable Middleware
- Secure Authentication
- Data Encryption
- Scalable Backend Structure

---

# Future Enhancements

- React Frontend Application
- Calendar UI Integration
- Real-Time Notifications
- Audit Logging
- Email Notifications
- SMS Notifications
- File Upload Support
- Medical Document Management
- API Rate Limiting
- Swagger/OpenAPI Documentation

---

# Author

**Barath, Hemadharshini A, Sonika E**

PHP MVP (Minimum Viable Product)

Healthcare Management System Backend API
