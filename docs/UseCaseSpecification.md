# Use Case Specification: Docucast (Document Socialization System)

## 1. Introduction
This document specifies the use cases for the Docucast system. It is derived from the Business Requirements Document (BRD) and provides detailed descriptions of how each actor interacts with the system to fulfill the business objectives.

## 2. Actors
- **Admin**: Full access to the system. Can manage Users, Divisions, Documents, and oversee all workflows.
- **Document Owner (Uploader)**: Can upload new documents, assign reviewers, and track the status of their documents.
- **Reviewer (Recipient)**: Can view assigned documents and submit their review (Approve or Request Revision).

---

## 3. Use Case Descriptions

### UC1: Manage Users & Divisions
- **Actor:** Admin
- **Description:** Allows the Admin to create, update, delete, and view users and divisions within the system to maintain the organizational structure.
- **Preconditions:** Admin must be authenticated and logged into the system.
- **Postconditions:** Users and divisions are successfully updated, and changes are reflected in the database.
- **Main Flow:**
  1. Admin navigates to the User or Division management module.
  2. Admin selects an action (Create, Edit, Delete).
  3. Admin fills in the necessary details (e.g., NIK, Employee No, Job Title, Division, Telegram Chat ID for users).
  4. Admin saves the changes.
  5. System updates the data and displays a success message.

### UC2: Upload Document & Set Auto-Approve
- **Actor:** Document Owner
- **Description:** The owner uploads a new document into the system, optionally marking it for auto-approval.
- **Preconditions:** Owner is authenticated and has the necessary permissions to upload documents.
- **Postconditions:** A new document is created with a unique code and stored centrally. Initial status is set to *Pending*.
- **Main Flow:**
  1. Owner navigates to the Document Upload section.
  2. Owner inputs document details (Title, Description) and uploads the file.
  3. Owner optionally checks the 'Auto-Approve' flag.
  4. Owner submits the document.
  5. System generates a unique document code (e.g., `#UploaderID-Date-DocID`).
  6. System saves the document, creates a version snapshot in `DocumentVersions`, and sets the status to *Pending*.

### UC3: Assign Document Recipients
- **Actor:** Document Owner
- **Description:** The document owner selects specific users from the organization to act as mandatory reviewers or recipients for a document.
- **Preconditions:** The document exists, has a *Pending* status, and is owned by the Document Owner.
- **Postconditions:** Reviewers are assigned, the document status changes to *In Review*, and notifications are sent.
- **Main Flow:**
  1. Owner views a specific document they uploaded.
  2. Owner selects the "Assign Recipients" option.
  3. Owner selects users from the system to act as reviewers.
  4. Owner confirms the assignment.
  5. System maps the document to the recipients in the `DocumentRecipients` table.
  6. System updates document status to *In Review*.
  7. System triggers notifications via Telegram and Email to the newly assigned recipients.

### UC4: Submit Document Review
- **Actor:** Reviewer (Recipient)
- **Description:** An assigned reviewer evaluates the document and submits their decision (*Approve* or *Request Revision*) along with optional comments and file attachments.
- **Preconditions:** Reviewer is authenticated and has been assigned to review the document which is currently *In Review*.
- **Postconditions:** The review decision is recorded, the audit trail is updated, and the overall document status may change based on the aggregate reviews.
- **Main Flow:**
  1. Reviewer accesses the assigned document through the system or a link in their notification.
  2. Reviewer evaluates the document content.
  3. Reviewer submits a decision: *Approved* or *Revision*.
  4. Reviewer optionally adds comments or attaches files.
  5. System records the individual review in the `DocumentReviews` table.
  6. System logs the action securely in the `RevisionHistories`.
  7. System recalculates the overall document status.
  8. System notifies the Document Owner of the review submission.
- **Alternative Flow A - All Approved:**
  - If the system calculation determines all assigned recipients have approved, the overall document status is updated to *Approved*.
- **Alternative Flow B - Revision Requested:**
  - If the reviewer requests a revision, the overall document status immediately updates to *Requires Revision*. The document pauses in the review flow until a new version is uploaded.

### UC5: View Document Status & History
- **Actor:** Admin, Document Owner, Reviewer
- **Description:** Allows users to view the current status of documents and the immutable audit history of all review actions and document updates.
- **Preconditions:** User is authenticated. (Visibility rules apply: Reviewers view assigned documents; Owners view their uploads; Admins view all).
- **Postconditions:** The metadata, current status, and history of the document are displayed.
- **Main Flow:**
  1. User navigates to the Document list or detail view.
  2. User selects a specific document to view.
  3. System displays the document metadata and current aggregate status.
  4. System displays the list of assigned reviewers with their individual review statuses.
  5. System displays the `RevisionHistories`, showing an immutable audit trail of all actions (e.g., *APPROVED*, *REVISION_REQUESTED*) linked to the document versions.

### UC6: Upload New Document Version
- **Actor:** Document Owner
- **Description:** When a document receives a "Requires Revision" status, the owner uploads a new version of the file to address the requested changes.
- **Preconditions:** The document exists, is owned by the user, and its current status is *Requires Revision*.
- **Postconditions:** A new version snapshot is created, previous review statuses are reset, and reviewers are notified to evaluate the new version.
- **Main Flow:**
  1. Owner views the document that requires revision.
  2. Owner selects "Upload New Version".
  3. Owner uploads the updated file.
  4. Owner submits the update.
  5. System creates a new snapshot in `DocumentVersions`.
  6. System safely archives the old version.
  7. System resets the existing individual review decisions for this document.
  8. System updates the overall document status back to *In Review*.
  9. System logs the version update in `RevisionHistories`.
  10. System sends notifications via Telegram and Email to the assigned reviewers that a new version is available for review.
