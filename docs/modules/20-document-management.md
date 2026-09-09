# Module 20 - Document Management

> Phase: Phase 2 | Primary actors: Owner (uploader), Tenant (uploader), Admin (registry & retention)

## 1. Purpose

Central repository for every record on the platform - leases, verification evidence, receipts, property documents, maintenance paperwork. A single source of truth that other modules attach their files to.

## 2. Roles & Responsibilities

| Actor | Actions |
|---|---|
| **Owner** | Upload property documents, signed leases, receipts, verification evidence; download anytime. |
| **Tenant** | Upload ID/references for applications (Phase 2), signed lease copies, receipts, maintenance photos. |
| **Admin** | Manage the registry, set retention policies, grant access where needed, archive/expunge per law. |

## 3. Functional Requirements

- FR-01 Document record: name, type, storage path, mime, size, visibility scope, tags.
- FR-02 Access-scoped visibility: public documents (e.g. lease snippets for tenant), private (verification evidence), admin-only.
- FR-03 Versioning: re-upload creates a new version, old retained.
- FR-04 Retention policy by type (e.g. verification evidence 3 years, leases 7 years after termination).
- FR-05 Pre-signed download links; no public directory listing.
- FR-06 Integration points: property documents, lease files, receipt PDFs, verification evidence, maintenance photos, dispute evidence.

## 4. Non-Functional Requirements

- NFR-01 Validate size and mime; quarantine executable types.
- NFR-02 Store outside the web root (storage) - serve via controller.
- NFR-03 Encryption at rest for sensitive types.
- NFR-04 Auto-expiry of pre-signed links.

## 5. Workflows & Pseudo Sentences

1. **Upload** - When a user uploads a file, the system validates type/size; then the system stores the binary and creates a `documents` row with scope; when the file is sensitive, the system encrypts it at rest.
2. **Attach** - When another module saves a record (lease, evidence, receipt), the system links the `document_id`; when the parent is deleted, the system leaves the file under retention policy.
3. **Access** - When a user requests a document, the system resolves visibility rules; when allowed, the system issues a short-lived pre-signed URL; when denied, the system returns 403 and logs the attempt.
4. **Retention** - When the retention date passes, the system archives the document; when the legal hold expires, the system purges the binary and keeps only metadata.
5. **Version** - When a user re-uploads, the system creates a new version row and marks the old one superseded; when the current version is deleted, the system offers the previous version.

## 6. Data Model

| Table | Key Columns |
|---|---|
| `documents` | id, uploader_id FK, name, type, path, mime, size, visibility (public/private/admin), tags, retention_until, purged_at, timestamps |
| `document_versions` | id, document_id FK, version_no, path, mime, size, uploaded_by, created_at |
| `document_links` | id, document_id FK, model_type, model_id, purpose, created_at |

Relationships: `documents` hasMany versions; polymorphic links to any model (property, lease, case, invoice).

## 7. Integrations & Dependencies

- Modules 02 (property docs), 07 (references), 08 (leases), 09 (receipts), 10 (maintenance photos), 14 (verification evidence), 19 (dispute evidence).

## 8. Acceptance Criteria

AC-01 Files are never publicly browsable.
AC-02 A document's visibility scope is enforced on every request.
AC-03 Versions are retained and retrievable.
AC-04 Retention purge removes binaries but keeps metadata audit trail.