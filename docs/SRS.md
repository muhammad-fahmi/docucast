# Software Requirements Specification (SRS)
## Project: Docucast (Document Socialization System)

### 1. Introduction
#### 1.1 Purpose
This Software Requirements Specification (SRS) document details the software requirements for the Docucast system. It consolidates information from the Business Requirements Document (BRD), Product Requirements Document (PRD), and Use Case Specification to provide a comprehensive description of the system's intended behavior, features, interfaces, and constraints. This document is intended for developers, testers, project managers, and stakeholders to ensure a shared understanding of the software to be built.

#### 1.2 Scope
Docucast is a centralized document management and socialization platform designed to streamline the distribution, review, and approval of internal documents. It tracks document versions, manages formal review workflows, and provides an immutable audit trail for compliance, ensuring critical documents are signed off by the correct personnel.

---

### 2. Overall Description
#### 2.1 Product Perspective
Docucast operates as a standalone web-based application. It serves as the primary tool for document compliance and review within the organization, replacing manual tracking, disjointed email threads, and ad-hoc communication.

#### 2.2 Product Functions
- User and Division management with Role-Based Access Control (RBAC).
- Document upload, automatic version control, and secure archiving.
- Automated review and approval workflows with mandatory recipient assignments.
- Real-time automated notifications via Telegram and Email.
- Immutable audit trail generation (Revision History) for compliance.

#### 2.3 User Characteristics
- **Admin**: Technical or managerial users who manage system configurations, users, and oversee the entire system for compliance and troubleshooting.
- **Document Owner (Uploader)**: Employees responsible for publishing documents and ensuring they are reviewed by the correct parties. They need frictionless workflows and real-time status tracking.
- **Reviewer (Recipient)**: Employees required to read, evaluate, and provide formal feedback (Approve/Revision) on assigned documents. They require easy access and clear notification channels.

#### 2.4 Operating Environment
- **Platform**: Web-based application accessible via modern desktop and mobile browsers.
- **Server Environment**: PHP 8.4 environment (Laravel 12).
- **Database**: Relational database (e.g., MySQL or PostgreSQL).

#### 2.5 Design and Implementation Constraints
- **Frameworks**: Must be built using Laravel 12, Livewire 4, Filament v5, and Tailwind CSS v4.
- **Security**: Strict role-based access control (Filament Shield) must be implemented. Passwords must be hashed.
- **File Storage**: Secure local or cloud file storage utilizing Laravel Storage disks. Old document versions must be moved to an archive path (e.g., `documents/archive/`).

---

### 3. System Features
#### 3.1 Document Management & Versioning
- **Description**: The system must allow users to upload PDF documents, automatically generating a unique tracking code (`#UploaderID-Date-DocID`). Uploading updates to existing documents must automatically increment the version and safely archive the old file. The system must support an "Auto-Approve" flag for informal memos.
- **Associated Use Cases**: UC2 (Upload Document), UC6 (Upload New Version).

#### 3.2 Review and Approval Workflow
- **Description**: Document owners must be able to assign specific users as required reviewers from the directory. Reviewers must be able to submit "Approved" or "Revision" statuses along with optional comments and file attachments. The system calculates the aggregate status: transitioning to `Approved` only when *all* assigned reviewers approve, and `Requires Revision` immediately if *any* reviewer requests changes.
- **Associated Use Cases**: UC3 (Assign Recipients), UC4 (Submit Review).

#### 3.3 Notifications System
- **Description**: The system must proactively notify users via Telegram and Email when they are assigned a document, when a review is submitted, and when a new version is uploaded. It must also support automated reminders for pending reviews to minimize delays.

#### 3.4 Audit & History Tracking
- **Description**: The system must maintain an immutable, chronological `RevisionHistory` log of all actions taken on a document version (e.g., assignment, review submission, status changes) to satisfy strict audit and compliance requirements.
- **Associated Use Cases**: UC5 (View Status & History).

#### 3.5 Administration
- **Description**: The system must provide an administrative interface to manage users, map them to divisions, and configure their extended profiles (NIK, Employee No, Job Title, Telegram Chat ID).
- **Associated Use Cases**: UC1 (Manage Users & Divisions).

---

### 4. Data Requirements
The system requires the following core data entities:
- **Users**: Extended user profiles (NIK, Employee No, Job Title, Telegram Chat ID, Division mapping).
- **Divisions**: Organizational units mapping.
- **Documents**: Core metadata (Title, Description, File path, Unique Code, Auto-Approve flag, Aggregate Status).
- **DocumentVersions**: Historical snapshots linked to the parent Document.
- **DocumentRecipients**: Pivot table mapping Documents to required Reviewer Users.
- **DocumentReviews**: Individual review records containing decision status, comments, and file attachments.
- **RevisionHistories**: Immutable audit logs of all workflow transitions and actions.

---

### 5. External Interface Requirements
#### 5.1 User Interfaces
- The application will utilize a mobile-responsive admin panel interface built on Filament v5, incorporating a mobile bottom navigation bar for ease of access on smaller devices.
- A built-in PDF Viewer (Filament PDF Viewer) must be integrated so reviewers can read documents directly within the browser without downloading the file.

#### 5.2 Communications Interfaces
- **Telegram API**: Integration required for pushing real-time notification alerts to user Chat IDs.
- **SMTP/Email**: Standard integration for dispatching email notifications and reminders via Laravel Notifications.

---

### 6. Non-Functional Requirements
#### 6.1 Performance
- Document uploads and file serving must be optimized for speed.
- The system must calculate aggregate document statuses instantly upon review submission.
- The PDF viewer integration must load smoothly without significant latency.

#### 6.2 Security
- Implementation of Filament Shield for robust RBAC.
- Reviewers must be restricted to acting only on documents explicitly assigned to them.
- All files must be stored securely, preventing unauthorized direct URL access without authentication.

#### 6.3 Auditability
- The system guarantees that once a review decision or status transition is recorded in the history log, it cannot be tampered with or deleted by standard users or owners, ensuring a verifiable compliance trail.
