---
document_id: PRMS-DOC-05
title: Delivery, Quality and Operations
version: 1.0.0
status: Approved
---

# Delivery, Quality and Operations

## Executive summary

This document consolidates the PRMS (Property Rental Marketplace System) delivery, quality, operations, customer-facing adoption and product-measurement baseline into a single controlled statement. It merges the former Category 09-13 sets — delivery and engineering, quality assurance, operations and support, customer-facing and adoption, and product measurement and improvement — into one evidence-feasible baseline for the shared platform and all twenty sellable modules.

PRMS is a Zimbabwe-first, USD-priced direct rental marketplace and property-management ERP. Property owners list properties directly, tenants search and apply free of agent commission, owners pay affordable subscriptions, and the platform earns revenue from subscriptions and optional promotion, verification and premium services. Because the platform removes the traditional letting agent, it must itself deliver the services an agent used to supply: trust and verification, tenant enquiries, viewings, applications, lease and rent management, maintenance coordination, and reliable support. Governance for that entire lifecycle is defined here.

The baseline is deliberately non-assertive. On this document's effective date no application, build candidate, software release, executable test, telemetry stream, operational deployment, customer, signed trial or measured outcome exists. Gate 4 remains NO-GO, Gate 5 remains unavailable, and the category acceptances recorded in this consolidation create no build, release, legal, customer, specialist-control or external authority. Every register in Parts 2-5 correctly contains no instances until real evidence exists, and every undecided commercial, legal, operational or prioritisation item is labelled as an open question rather than silently assumed.

## Purpose and audience

The purpose of this consolidated baseline is to provide one controlled view of how PRMS is delivered, assured, operated, adopted and improved, so that strategy, product, engineering, architecture, infrastructure, data, privacy, quality, operations, support, legal, commercial and administrative owners can trace to it. It removes the need to reference five folders of separate category files while preserving document identifiers, requirement IDs, decisions, acceptance scenarios and registers.

The audience is product, engineering, delivery, quality, operations, support, adoption, marketing, commercial, privacy, security, legal, finance and measurement owners, plus authorised customer-facing and administrative staff who must understand what has and has not been decided.

## Scope and exclusions

Included are software delivery planning and estimation, work breakdown, development workflow, coding standards, repository and branching strategy, environment strategy, continuous integration and delivery design, dependency and configuration management, change release and rollback governance, quality management, test strategy and master planning, test-case and test-result families, acceptance, regression, performance, security, accessibility and compatibility assurance, defect management, operational readiness, incident, support, service-level, maintenance, versioning, deprecation and known-issue governance, post-incident review, customer-facing overview and adoption content families, implementation and onboarding, configuration, training, FAQ, knowledge, release-note and data-import-templates families, terms of use and service agreements, and the product measurement set covering KPIs, analytics and event tracking, customer feedback, discovery, feature evaluation, experiments, post-launch reviews and improvement roadmaps.

Excluded are legal or tax advice, framework certification, a guarantee of conformity, evidence that any control, test, deployment, service or metric exists in a released build, and any authority, regulator, customer, price or launch commitment. Nothing in this document authorises production behaviour, data collection, external publication or binding customer engagement; those require later release gates, verified evidence, qualified review and approved commercial and legal positions.

## Evidence conventions and current state

Statements in this baseline follow the approved PRMS practice of keeping verified facts, approved product decisions, assumptions and open questions distinguishable. The governing rule is that an approved governance document, register or plan never proves its own implementation, and an empty register is a truthful description of state, not proof that a control cannot exist in future.

The authoritative current state is:

| Area | Current state |
|---|---|
| Build gate | Gate 4 NO-GO; no implementation of any feature, module or shared platform is authorised |
| Software | No application, candidate, build artefact or release exists |
| Execution evidence | No executable unit/integration/system test, verified test run, deployment or exercise exists |
| Operation | No production environment, operator, support rota, monitoring, backup or recovery evidence exists |
| Customer evidence | No customer, trial, signed user, verified migration or usage data exists |
| Measurement | No telemetry, event, baseline, KPI result, experiment or post-launch review exists |
| Legal/commercial | Operating entity, prices, plans, agreements and counsel positions remain open questions pending qualified review |
| Product authority | Delegated Product Owner may approve product documentation and product decisions supported by evidence |
| Withheld authority | Legal/regulatory opinion, procurement/signature, specialist-control approval, implementation evidence and release/deployment authority |

# Part 1 — Delivery and engineering (Category 09)

Part 1 consolidates the Category 09 delivery and engineering set. It defines how PRMS is planned, estimated, built, integrated, packaged, changed, released and rolled back, with an unbroken emphasis on evidence gates: no feature implementation, build or release decision is created by these governance baselines.

## 1.1 Product delivery plan (PRMS-C09-001)

The product delivery plan defines the delivery outcome, staging principles and the authority model for the MVP. It is not a release schedule.

- MVP-1 module scope is fixed as the ten modules: UAM (user and account management), PRP (property management), MKT (property marketplace and search), FAV (favourites and saved searches), ENQ (enquiries and communication), VEW (viewing management), APL (rental applications), VER (property and owner verification), SUB (subscription management) and ADM (admin and system management).
- The MVP-1 outcome is defined as list, verify, enquire, view, apply: an owner can publish a verified listing, a tenant can discover it, enquire, request a viewing and apply, and the owner can manage verification queues and enquiries from one controlled portfolio.
- Delivery proceeds in stages with explicit gates: product definition governance (Stage 0-3) authorises planning only, Gate 3 authorises delivery and quality planning, and Gate 4 (the build gate) is the single control point that remains NO-GO.
- Unplanned scope, non-MVP modules and provider integrations are excluded from delivery intake until the relevant gates, module decisions and evidence exist.
- The plan names accountable product, delivery, quality, operations and support ownership for each stage; assignment of named holders remains an open question until teams are appointed.

## 1.2 Work breakdown structure (PRMS-C09-002)

The work breakdown structure (WBS) decomposes the MVP and shared platform into manageable, evidence-traceable workstreams so that estimation, sequencing, dependencies and acceptance stay bounded.

- Workstreams cover the shared platform foundation, each MVP module, cross-cutting engineering (CI/CD, environments, repositories, dependencies, configuration), delivery-quality assurance activities and operational readiness preparation.
- The reference technology set is approved as a Laravel modular monolith with React with TypeScript rendered via Inertia, PostgreSQL 18 as the primary relational store, Redis/Valkey for cache and queue infrastructure, S3-compatible object storage for media and artefacts, containerised Linux deployment, and PHP runtime managed to the agreed language standard.
- Database design uses the `sp_*` schema for shared platform data and one schema per module (`uam`, `prp`, `mkt`, `fav`, `enq`, `vew`, `apl`, `lse`, `pmt`, `mtn`, `svc`, `sub`, `fad`, `ver`, `ntf`, `lnd`, `rba`, `adm`, `cmp`, `doc`).
- Sequencing respects hard dependencies: shared platform and UAM before module work that depends on accounts and permissions; verification (VER) before any property can reach Published; and subscription/entitlement (SUB) before fee-bearing behaviour.
- The WBS authors a decomposition baseline only; it does not authorise execution, assign named personnel or commit dates.

| Workstream | Breadcrumb objectives |
|---|---|
| Shared platform foundation | Schema, identities, roles, entitlements, notifications, events, config, media, queues |
| MVP module workstreams (grouped below) | Proof of outcome per module within defined boundaries |
| Delivery infrastructure | CI/CD, environments, artefacts, repositories, dependency and config governance |
| Quality and release preparation | Testability, acceptance evidence, release manifest, rollback rehearsal |
| Operational readiness | Monitoring, support, backup, recovery and runbook preparation |

## 1.3 Estimation and resourcing (PRMS-C09-003)

Estimation in PRMS is capability-based, range-based and evidence-honest. It is used for sequencing and option comparison, not for fixed commitments.

- Estimates are expressed as three-point ranges (low, likely, high) per work item, with explicit assumptions about team capability, environment maturity and dependency availability.
- Capacity is described in capability terms rather than invented headcount; no team size, budget, hourly figure or completion date is approved.
- Estimate ranges must not be presented as commitments, and schedule pressure must never convert an estimate into a promise. Material changes to scope, dependency or evidence trigger re-estimation.
- Comparable-complexity and past-evidence bases do not yet exist for PRMS; estimates therefore carry wide confidence brackets until the first delivery slice provides a calibration point.

## 1.4 Development workflow (PRMS-C09-004)

The development workflow defines how change reaches a releasable candidate. It is trunk-oriented and treats merge as evidence of integration, not acceptance.

- Development is trunk-oriented with short-lived branches: small, independently reviewable changes integrated frequently, avoiding long-lived divergence.
- A merge or passing build never proves that a requirement is met, that quality is achieved or that behaviour is acceptable; acceptance requires the defined Definition of Done and, for release, gate evidence.
- No feature implementation may begin before Gate 4 authorises build for the relevant scope.
- Work items trace from approved requirements through implementation, verification and release evidence; untraced or unapproved change is refused at intake.
- Developer self-verification is mandatory but always supplemented by independent review and quality gates.

## 1.5 Definition of Ready and Definition of Done (PRMS-C09-005)

The Definition of Ready (DoR) and Definition of Done (DoD) govern intake and completion so that work is started with bounded context and finished with verifiable outcomes.

- Ready requires an approved requirement with stable ID, acceptance and test implications, defined data/security/privacy impact, named owner, known dependencies, and a bounded estimate range. Work that is not ready is not started silently.
- Done requires implementation, unit and integration evidence, self-review plus independent review, updated documentation and test assets where the change demands them, no unresolved secret/credential exposure, configuration and schema changes captured, backward-compatibility or deprecation handled, and the change recorded in the release manifest.
- A change that passes DoR/DoD for its slice does not authorise release; release remains subject to the release management and go/no-go controls in 1.13.

## 1.6 Coding standards (PRMS-C09-006)

Coding standards keep the modular monolith coherent, readable and secure across the shared platform and every module.

- PHP code follows PSR-12 and a declared code-style tooling baseline (Pint) enforced in CI; a uniform Lint/code-style gate blocks merges that violate the standard.
- TypeScript is strict and type-safe; component purity, deterministic behaviour and accessibility are part of implementation quality, not afterthoughts.
- Database identifiers and object properties follow the approved convention: owner-qualified unquoted snake_case identifiers so that module and shared-platform namespacing remain unambiguous and injection-safe.
- Row-level security and customer context are first-class concerns: every query, job and report must respect the authenticated principal's scope (tenant, owner, module and entitlement).
- Secrets, credentials, environment-specific configuration and personal data never appear in source, tests, fixtures, logs or documentation.
- Accessibility, privacy, security and locale conventions from Category 05 and 08 baselines bind to implementation standards as applicable.

## 1.7 Repository strategy (PRMS-C09-007)

The repository strategy keeps product code and governance separate and auditable.

- Product application code lives in the `prms` repository; controlled governance and documentation (including this consolidated baseline family) lives in its own `prms-docs` repository.
- Separation prevents documentation churn from triggering application builds and keeps the exact released governance state traceable to product code.
- Both repositories are subject to the same access, review and audit expectations.

## 1.8 Branching and version control (PRMS-C09-008)

Version control protects the integration line and release history.

- The default integration branch is `main`, which is protected: direct pushes are prohibited and all changes require review and passing checks.
- Squash-merge is the default integration style so the history remains readable and atomic.
- Releases are marked with immutable annotated tags; a tag, once created, is never rewritten or deleted.
- Branch names and commit messages identify the change source and intent; sensitive content never enters commit messages or history.

## 1.9 Environment strategy (PRMS-C09-009)

Environments are separated by purpose, data and trust so that testing never contaminates production and production data never leaks into non-production.

- The environment set is local developer, CI, integration/test, pre-production, production and recovery-exercise, with synthetic and masked data as the default across non-production.
- Each environment has an explicit data policy and provisioning rule; real personal or customer data is not copied into non-production environments without approved, minimised and protected pipelines.
- Pre-production is the final gate environment for release candidates, and the recovery-exercise environment exercises restore and continuity routines outside incident time.
- Environment changes are governed changes: they follow the change management controls rather than ad-hoc modification.

## 1.10 CI/CD design (PRMS-C09-010)

Continuous integration and delivery are designed for supply-chain integrity and reproducible promotion.

| Build principle | Meaning |
|---|---|
| Build once | A candidate is built once in isolated, least-privilege CI and never rebuilt during promotion |
| Immutable artefacts | Artefacts are addressed by digest, so the artefact promoted is exactly the artefact tested |
| Promote unchanged | Promotion moves the same digest across environments; no compiled-artefact modification en route |
| Reproducible provenance | Build metadata, source commit and dependency set are captured with each artefact |
| Isolated execution | CI runs with least privilege, disposable runners and no production credential access |

- The design is informed by SLSA 1.2 and OCI/container security guidance; no SLSA level or external attestation is claimed.
- CI enforces lint, static analysis, dependency checks, secrets scanning, unit/integration suites and artefact signing/integrity as approved for the slice.
- CD promotes the approved digest through the environment chain only on passing gates; nothing reaches production without the release management and go/no-go controls.
- A failure in CI/CD never blocks an incident-responsive rollback; emergency change follows its own controlled route.

## 1.11 Dependency management (PRMS-C09-011)

Dependency management keeps the supply chain inspectable and rebuildable.

- Lockfiles are committed for application and build-time dependencies so that builds are reproducible.
- Every build records a software bill of materials (SBOM) of its direct and transitive dependency set.
- New or elevated dependencies pass vulnerability and licensing checks before acceptance; no dependency is silently introduced at urgency.
- Dependency update classes (security fix, patch, minor, major) are distinguished, with major and behavioural changes treated as governed changes.
- No dependency carries provider-hosted credentials or customer data into build output.

## 1.12 Configuration and secrets (PRMS-C09-012)

Configuration and secrets are governed separately from code so that changing a value never requires a code redeploy and leaking a secret never means rewriting history.

- Configuration is typed and schema-bound: every setting has a defined format, allowed values, environment and owner.
- Environment-specific values are injected from controlled sources; source code and repositories contain default or test scaffolding only.
- Secrets (passwords, tokens, keys, credentials) never exist in source, documentation, artefacts, logs or fixtures.
- Secrets are provisioned through a governed secret-management path with short-lived credentials and rotation; long-lived static secrets are avoided by design.
- Configuration and secret changes that affect security, availability or cost are governed changes with review and evidence.

## 1.13 Change management (PRMS-C09-013)

All material change follows a governed change control model rather than ad-hoc modification.

| Change class | Definition | Control |
|---|---|---|
| Standard | Low-risk, pre-approved, repeatable change | Executed to approved procedure with evidence |
| Normal | Bounded, reviewable change with defined risk | Impact, dependency, test and approval checkpoints |
| Emergency | Urgent change needed to protect safety, data or availability | Fast-track authority, retrospective review and full record |

- Every change records scope, risk, dependencies, target state, owner, approved window, impact, prechecks, backups or checkpoints, ordered procedure, expected results, safe stops, validation, rollback or forward recovery, communication and closure evidence.
- Emergency change never bypasses the incident command and post-incident review obligations.
- The change register is the authoritative record; nothing is changed outside a registered, approved change.

## 1.14 Release management (PRMS-C09-014)

Release management turns an approved candidate into a released product state with evidence.

- A release manifest binds the exact source commit, artefact digests, configuration/schema versions, module and shared-platform scope, tests, known issues, rollback boundaries and go/no-go decision.
- Releases follow a go/no-go decision at the entry gates using the defined acceptance, operational readiness, security/privacy and rollback evidence; a failure at any gate blocks release, not overrides it.
- Cadence, versioning mechanics and tooling are explicitly unselected until Gate 4 and remain open questions; semantic versioning policy is defined in the operations set (Part 3), to which release management defers.
- No release may proceed when the corresponding release notes and customer-action communication are absent where users or operators are affected.
- Rollback of a release follows the rollback plan; a rolled-back release's status and notes must be updated without erasing history.

## 1.15 Rollback and recovery (PRMS-C09-015)

Every release is designed to be reversible within a defined boundary.

| Recovery phase | Meaning |
|---|---|
| Stop/contain | Halt further change or exposure; protect safety, data and availability |
| Rollback/roll-forward | Revert to the previous known-good state or move forward to a corrected state, whichever is verifiable and approved |
| Disable activation | Neutralise feature-flags and activation points that could re-trigger the event |
| Reconcile | Confirm data, integrations, providers and users reached the intended consistent state |
| Restore | Recover data and state from verified backups where reconciliation demands it |

- Deployment is designed to be atomic and reversible (symbolic-link style swaps) so that a release point can be restored quickly.
- Database change follows expand/migrate/verify/contract: safe additions and migrations are applied, verified, and only then contracted or removed, so that rollback does not require destructive reversal.
- Every release defines its rollback boundary in advance and tests the restore path in the recovery environment; an untested rollback claim is not a claim at all.
- Post-incident review, known issues and the security register capture what the rollback could not fix; rollback is a first response, not the final word.

# Part 2 — Quality assurance (Category 10)

Part 2 consolidates the Category 10 quality assurance set. It defines how PRMS proves that the shared platform and all twenty modules are trustworthy in the trust-critical domain they serve. Because the platform removes the letting agent, quality is the product: verification gates, application approvals, signed leases and rent/payment transitions must be provably correct, and no aspect of quality is treated as a release-time addition.

## 2.1 Quality management plan (PRMS-C10-001)

The quality management plan (QMP) establishes risk-based, traceable quality governance across the shared platform, the twenty modules, their combinations and the full suite.

- Quality is risk-based: effort concentrates where failure harms trust, safety, financial integrity, privacy or availability, not evenly across all surface area.
- Traceability is mandatory: every test condition and quality decision traces to a requirement, a domain state-machine rule or a defined risk.
- Quality applies to `sp` plus every module (UAM, PRP, MKT, FAV, ENQ, VEW, APL, LSE, PMT, MTN, SVC, SUB, FAD, VER, NTF, LND, RBA, ADM, CMP, DOC), including standalone modules, suite combinations and shared governance.
- No tool, coverage threshold or release claim exists; the plan governs how those decisions will be made, not that they have been made.
- Gate 4 (build) remains NO-GO; the QMP authorises planning, not execution.

## 2.2 Test strategy (PRMS-C10-002)

The test strategy defines the layered, risk-based approach to verification so that evidence is acquired at the level where the risk lives.

| Layer | Purpose |
|---|---|
| Static/analysis | Defects and security issues found before execution |
| Unit | Smallest isolated behaviour, fast and deterministic |
| Integration | Contracts, seams and shared platform interactions |
| System/end-to-end | Full journeys across modules, roles and the trust chain |
| Specialised | Performance, security, accessibility, compatibility, regression and recovery |

- Test conditions derive from the PRMS domain state machines, which carry correctness: a property is Published only after verification passes; an application is Approved only after the verification and data gates for it pass; a lease is Active only from a signed lease document; rent is Paid only through the defined reconciliation, with Overdue as a derived financial state.
- Boundary, permission and entitlement conditions are first-class risks: record-scope isolation, role/relationship authorisation and module/subscription entitlements are tested as controls, not conveniences.
- Evidence is the unit of truth: a green build, a merged change or a passing run that cannot be traced to a requirement and environment does not constitute acceptance.
- No executable test, tool or result exists yet; the strategy defines the approach for when Gate 4 authorises build.

## 2.3 Master test plan (PRMS-C10-003)

The master test plan is the approved but unpopulated governing plan that binds module-level planning to the overall release.

- It is baseline approved, defining how the plan family works: the Test Manager must instantiate the release-level plan before Gate 4, and module-level detail lives in the module test plan family (PRMS-C10-MTP), not duplicated here.
- Each instantiated release plan must declare scope, modules and combos, environments, data, tooling per decision, entry/exit and suspension/resumption criteria, roles, risks, evidence artifacts and reporting cadence.
- No instance of the master plan exists: the register is approved, the execution not started.

## 2.4 Acceptance specification (PRMS-C10-006)

The acceptance specification defines scenario-based acceptance evidence tied to PRMS requirement IDs (PRMS-REQ-*).

- Acceptance scenarios are expressed as when-given-then expectations over real user journeys and domain rules, covering the roles Property Owner, Tenant, Applicant, Platform Administrator and Service Provider.
- The trust model is explicitly verified: acceptance evidence must prove that a listing can only reach Published after verification, an owner can only manage properties within their own portfolio, a tenant sees only listings they are permitted to see, and financial state transitions are correct and audit-trailed.
- Acceptance evidence is the input to release go/no-go, not a summary claim; each scenario names its requirement, preconditions, steps and expected persistent state.
- No acceptance scenario has been executed; no release exists.

## 2.5 Test-case family (PRMS-C10-005)

The test-case family standard governs all executable test assets.

- Test cases live under `test-cases/{module-or-area}.md` covering `sp` and the twenty modules.
- Every case has a stable ID `PRMS-{MOD}-TC-NNN` with a stable area prefix, and records: preconditions, steps, expected results and oracle, environment, data, traceability to requirement/risk, and evidence capture.
- Speculative cases are prohibited: no step may be written that assumes unimplemented or unapproved behaviour before design/implementation evidence exists.
- The family standard is Approved; no executable test case instances exist.

## 2.6 Test-results family (PRMS-C10-013)

The test-results family standard governs how results are recorded so that quality reporting can never be fabricated.

- Results live under `test-results/{release}.md` and are bound to an exact release candidate.
- Synthetic or optimistically inferred results are prohibited: a result is recorded only when a real run occurred against a named environment and candidate, with tools, version, data scope and defects captured.
- Since no release candidate exists, the register truthfully contains no result file; creation waits for real execution evidence.
- Results must never embed raw secrets, credentials or personal data.

## 2.7 Regression test suite (PRMS-C10-007)

The regression framework protects the critical control set from erosion while permitting focused change-impact selection.

| Suite type | Contents |
|---|---|
| Permanent critical core | Trust, security and financial controls that can never be silently removed |
| Module-owned suites | Behaviour suites owned by each module for its own logic |
| Contract/database/migration | Cross-cutting schema, migration and integration contract coverage |
| Supported-combo journeys | Representative journeys across supported combinations |
| Specialised safety checks | Performance, security, accessibility and recovery spot coverage |

- Change-impact selection may add cases or prioritise suites, but it can never silently remove coverage of: record-scope isolation, authorisation boundaries, entitlement enforcement, verification gates, rent/payment data integrity, audit trail, migration and recovery, or the trusted publication/application/lease state machines.
- Covering release priorities does not release the mandatory control core.
- No executable regression tests exist; the framework is the governing rule for when build is authorised.

## 2.8 Performance test plan (PRMS-C10-009)

Performance testing proves that approved user and business clocks are met within the capacity envelope, rather than recording raw throughput or maximum concurrency.

- Performance is defined as latency, completion and freshness outcomes against the approved Performance Requirements and Budgets (PRMS-C06-018) and the Capacity Plan (PRMS-C06-017).
- Conditions are classified and each is designed separately: interactive user journeys, asynchronous jobs, provider calls, report/import/export workloads, billing, notifications and recovery bursts.
- Scenario types cover baseline, load, burst, stress, soak, volume, concurrency, noisy-neighbour, degradation, recovery and scalability.
- Failures, timeouts, rejections, queue age, data shape, provider behaviour and resource saturation are recorded as evidence, not hidden.
- No numerical target or capacity value is approved until representative baselines and cost decisions exist; targets remain open questions pending Gate 4 work.

## 2.9 Security test plan (PRMS-C10-008)

The security test plan is driven by the PRMS-C08 threat model and the security requirements, mapping threat to control to requirement.

- Priority security risks: fake listings and scams (VER, CMP), record-scope isolation across tenants and roles, role- and relationship-based authorisation, module/subscription entitlement, sensitive data handling, payment fraud and callback integrity (PMT, SUB, FAD), audit integrity, file/import/export abuse, queued/event/provider flows, idempotency, WhatsApp and communication channels and their secrets, recovery, and admin/support access.
- Methods include secure-design review, code review, SAST, dependency/secret/config scanning, automated application and API tests, manual business-logic and authorisation tests, infrastructure review, abuse and failure-mode testing, and independent penetration testing where risk requires it.
- The plan is Confidential; no tool, standard version, assessor, environment or finding exists. Gate 4 must map every funded security requirement to a versioned control with verification evidence before any release go/no-go.

## 2.10 Accessibility test plan (PRMS-C10-010)

Accessibility is a deliberate, testable product quality with the WCAG 2.1 AA success criteria as the target baseline.

- Coverage includes keyboard operation, screen-reader compatibility, text resize/zoom, focus order and visibility, contrast, motion sensitivity and orientation.
- Automated checks are acknowledged as necessary but insufficient; representative assistive-technology testing and research with people with disabilities is required.
- Accessible journeys must be verified across the marketplace surface, tenant portfolio views, owner portfolios, verification and application flows, lease, payment, maintenance and staff/admin views.
- No accessibility test evidence or assisted-user research exists; the plan authorises the approach, not the claim.

## 2.11 Compatibility matrix (PRMS-C10-011)

The compatibility matrix makes support commitments explicit and truthful.

- The matrix declares, per supported combination, the browsers, operating systems, devices and screen sizes, assistive technology, API clients, underlying software (including PostgreSQL 18 and Redis/Valkey), provider services and integrations that are supported, deprecated or unsupported.
- Coverage scales by combination value and the support contract in force, so that resources are not spent defending unsupported combinations.
- No combinations or evidence exist yet; Gate 4 populates the matrix from research, representative tests and support decisions — until then every combination is explicitly not yet declared.

## 2.12 Defect management procedure (PRMS-C10-012)

Defect management is a single flow for all findings regardless of source — static analysis, review, testing, staging, production or incident.

- A finding is closed only under one of four proven conditions: no-defect demonstrated, justified non-defect, retired and unreachable-by-design, or verified re-test pass with evidence.
- Defect ownership is by genuine topology: the owning module, shared platform area or contract owner is identified from the system map, so defects are not orphaned.
- Where no owner can be matched, the finding stays open and tracked; it is never silently dropped.
- A committed fix without passing regression evidence does not close a defect; the register is the authoritative record.
- No defect, tool or workflow instance exists.

## 2.13 Module test plans register (PRMS-C10-MTP-INDEX)

The module test plan register governs the twenty module-level test plans.

- Twenty plans are identified (one per module code), all in Draft status: the register is approved, plans are defined, none executed.
- Each module plan is instantiated and bound to the master test plan at Gate 4, following the family standard and the test-case/test-result families.
- No Stage 4 (execution) evidence exists for any module plan.

| Register state | Value |
|---|---|
| Family standard | Approved (PRMS-C10-MTP-INDEX) |
| Module plans | 20 defined (one per module code) |
| Plan status | All Draft |
| Execution evidence | None |

# Part 3 — Operations and support (Category 11)

Part 3 consolidates the Category 11 operations and support set. It defines how PRMS is made operational, kept available and reliable, versioned, maintained, supported, known-issue-tracked and reviewed after incidents — all as governed baselines with no deployed service or exercised evidence behind them yet.

## 3.1 Operational readiness checklist (PRMS-C11-001)

Operational readiness is an evidence-backed decision about a specific release, not a generic sign-off.

- A readiness decision is always for an exact candidate, target environment, change window, deployment cohort, product edition and module combination; readiness for one combination never implies readiness for another.
- Every checklist item is evaluated as Pass, Pass with condition, or Not applicable with rationale. Any Fail, Unknown, unsupported condition, expired certification, missing evidence or unresolved authority makes the decision No-go.
- Readiness applies to the shared platform and all twenty modules; a module not ready blocks that module's participation even if the platform is otherwise ready.
- The checklist covers environment provisioning, monitoring, alerting, backup and restore, incident rota, support staff and channels, security/privacy evidence, data residency and retention, documentation, migration, and communication plans.
- No application, production environment, operations team, monitoring, backup or support service exists, and no readiness exercise has run. Approval of the checklist is not approval of readiness.

## 3.2 Incident management procedure (PRMS-C11-008)

Incidents are handled as one record, with one commander and truthful recovery.

| Principle | Meaning |
|---|---|
| One incident record | Every action, decision and timeline belongs to a single immutable record |
| One Incident Commander | Single accountable decision point for the response, with functional roles around it |
| Evidence preservation | The incident record preserves what happened before, during and after, without rewriting |
| Controlled communication | Internal and external statements are coordinated and timely, not speculative |
| Truthful recovery | Recovery is confirmed by evidence; a service is not declared restored on assumption |

- Severity is based on credible potential impact (safety, data, financial, availability, privacy, trust) and can be changed as facts emerge; a suspected event moves through intake even before classification is confirmed.
- Containment and protection of tenant and owner data come before restoration; safety-critical and financial-integrity issues take priority.
- The procedure covers the full path from suspected event intake, triage, investigation, containment, resolution, recovery, reconciliation, communication and closure, and feeds the post-incident review.
- No service, team, rota, tool, contact list or exercised runbook exists; the procedure defines how these will be stood up and trained.

## 3.3 Support model (PRMS-C11-009)

Support is tiered and role-aware so that owners, tenants and platform operations each have a bounded route.

| Customer | Support posture |
|---|---|
| Property owner | Tiered support per subscription plan (Free, Basic, Professional, Business) |
| Tenant | Free self-service and help centre, plus direct routes for safety-critical issues (e.g., scam, fraud, data) |
| Platform admin | Internal operations route |
| General | Self-service first, then intake/triage, product/ops investigation, engineering/escalation and provider coordination |

- One case is the source of truth: duplicated inbound channels converge on a single case record, and a case never silently disappears.
- Case ownership is explicit, and escalation between product, engineering and known-provider paths is recorded.
- Support channels, operating hours, staffing, languages, response mechanics and measurable targets are explicitly not yet selected and remain open questions.
- Support discovery feeds defect, incident, known-issue and product-improvement queues rather than being treated as isolated answers.

## 3.4 Service-level objectives (PRMS-C11-010)

Service-level objectives (SLOs) are internal product controls: an SLO is defined by its SLI, eligible population, success rule, measurement source, target, evaluation window, segmentation, exclusions and accountable response.

- Availability alone is insufficient; PRMS SLOs are defined over named business outcomes, not infrastructure status.
- Candidate SLO families (each recorded as Assumption/Growth-lane, not an active target): platform availability; severity-graded incident response; rent-payment processing integrity/timeliness; and listing verification turnaround.
- No numerical target is approved until representative testing, cost/capacity understanding, staffing, deployment choices and commercial packaging are all approved; targets remain open questions.
- An SLO with no SLI measured against a real system is a definition, not a commitment.

## 3.5 Maintenance policy (PRMS-C11-011)

All maintenance is governed change with a stable record.

- Every maintenance event has a stable record: scope, risk, dependencies, target state, owner, approved window, impact, prechecks, backups or checkpoints, ordered procedure, expected results, safe stops, validation, rollback or forward recovery, communication and closure evidence.
- Maintenance windows and impact notifications are announced through approved communication routes (the NTF notification governance) before work begins.
- Covered maintenance areas include PostgreSQL 18 database maintenance, Redis (Valkey) housekeeping, S3-compatible object-storage lifecycle, container images, and TLS and dependency patching.
- Anything that changes behaviour, configuration, data or exposure is a governed change even when it looks like housekeeping.
- No maintenance operation has been performed; no environment exists.

## 3.6 Product versioning policy (PRMS-C11-012)

Versioning gives the whole product one release identity, not independent version numbers per component.

- A product release identity is the tested compatible state of: backend, frontend, database migrations, configuration schema, shared platform, enabled modules, APIs and events, documentation and provider contracts, taken together.
- Versioning follows Semantic Versioning (MAJOR.MINOR.PATCH).
- Version 1.0.0 is reserved for the first production-supported public contract; it requires the Rent and Payment Management (PMT) and Lease (LSE) modules with the MVP module set certified compatible — so the public version number only exists when the full commercial-rent lifecycle is supported.
- Before 1.0.0 the product is 0.y.z and is never claimed as production-supported or publicly contracted.
- Breaking versus non-breaking change is defined against the public contract, and compatibility is documented per release.

## 3.7 Deprecation and end-of-life (PRMS-C11-013)

Capabilities are never withdrawn silently.

- A capability passes through a lifecycle: supported, deprecated, end-of-life, retired — with a published deprecation notice, an affected-scope statement and a replacement/migration path before withdrawal.
- A deprecated capability remains supported within its published boundary until end-of-life; deprecation is not abandonment.
- End-of-life does not erase customer data, audit, retention, contractual, legal, rent/financial or safety obligations; those persist under the applicable policies.
- Deprecation at the sub-model level is permitted where useful — for example a legacy manual rent-schedule flow within PMT or an outbound enquiry channel variant within ENQ — and follows the same notice, evidence, migration and retirement controls.
- No capability is today deprecated or withdrawn; the policy is the rule that will govern future announcements.

## 3.8 Known issues register (PRMS-C11-014)

The known issues register is the authoritative product-facing index of known limitations.

- The register complements (does not duplicate) the defect system, incident records, the security register, the release quality report and release notes.
- Every entry carries the owning module ID (any of the twenty modules or `sp`), severity, the safe workaround if any, and current status.
- An empty register is the verified current state and is not proof that no defects exist; it is proof that none have been recorded.
- Known issues influence release go/no-go, manuals, quick starts, knowledge articles, release notes and support content.

## 3.9 Post-incident review (PRMS-C11-015)

A post-incident review (PIR) turns each incident into owned, verifiable learning.

- Every PIR instance is bound to its incident ID and records the exact product, environment and module state plus retained evidence.
- The review uses a zoned timeline, impact sections, what worked and what failed, contributing conditions, and recovery/reconciliation evidence.
- Outputs are risk-ranked actions with named owners, due dates and verification criteria; closed labels and empty placeholders are never permitted in a PIR.
- Closing the review does not close the actions, defects, risks or obligations it produced; those are separately tracked.
- No incident, and therefore no PIR instance, exists.

# Part 4 — Customer-facing and adoption (Category 12)

Part 4 consolidates the Category 12 customer-facing and adoption set: how PRMS describes itself to owners and tenants, how customers and tenants are onboarded and configured, how users are trained and supported with manuals, quick starts and knowledge content, how templates and release notes are governed, and how the legal and commercial agreements are framed. All public-facing content families are Approved governance baselines with no instances, and all agreement documents are Stage 5 drafts awaiting qualified legal review and commercial decision.

## 4.1 Product overview (PRMS-C12-001)

The product overview is the internal controlled description baseline of PRMS; it is not an external brochure or an offer.

- PRMS is a modular platform that connects property owners directly to tenants, removing the letting-agent commission layer: owners publish and manage listings, tenants search and apply, and the platform supplies trust, verification, application, lease, rent and maintenance services.
- Zimbabwe-first: Harare is phase 1, with Bulawayo, Mutare and Gweru as phase 2 candidates.
- Owners pay affordable subscriptions (Free, Basic, Professional, Business plans); tenants use the platform free.
- Public publication of the overview requires a verified release scope, approved branding and claims, approved pricing and packaging, operational and support readiness, an accessibility review, and a qualified legal and compliance review.
- No production software, public price, provider integration, service level, compliance certification or commercial offer exists.

## 4.2 Module capability guides (PRMS-C12-002)

Module capability guides are one customer-facing guide per commercially approved module and edition, derived from tested evidence.

- Each guide explains customer outcomes, users, included and excluded capabilities, standalone and suite behaviour, dependencies, data, configuration, security, limitations, implementation and support — mapping every claim to exact release evidence.
- Twenty module definition packs exist at requirements level, but no module is built, tested, packaged, priced, released or commercially approved; the guide register therefore has no instances and is marked Not started.
- Planned guide files follow `module-capability-guides/{module-slug}.md` for all twenty module codes (UAM, PRP, MKT, FAV, ENQ, VEW, APL, LSE, PMT, MTN, SVC, SUB, FAD, VER, NTF, LND, RBA, ADM, CMP, DOC).
- The MVP module set (UAM, PRP, MKT, FAV, ENQ, VEW, APL, VER, SUB, ADM) is the highest publication priority for first capability guides.
- Dependency tables distinguish required, included, shared, optional and failure behaviour; an edition limit is never disguised as missing product capability.

| Claim type | Minimum evidence |
|---|---|
| Capability exists | Exact released requirement plus passing acceptance evidence |
| Standalone | Tested installation/entitlement/workflow/data/export/failure without unlicensed modules |
| Integrated | Exact compatible module versions and contract/end-to-end/failure evidence |
| Secure/private/compliant | Bounded control/test/legal evidence and limitations; no absolute guarantee |
| Performance/availability | Defined profile/window/environment and measured evidence |
| Saves money/time | Approved method, baseline and sample; no unsupported projection |

## 4.3 Implementation and onboarding guide (PRMS-C12-003)

Implementation and onboarding is a controlled lifecycle, not a self-serve click-through.

- The lifecycle: qualify scope and governance; readiness review; bounded configuration, data and integration design; user and operations preparation; rehearsal; cutover; verify outcomes; handover; and close and stabilise.
- Every phase has entry and exit evidence; a customer is onboarded only when the bounded scope, data contracts and acceptance are agreed.
- The guide is a framework only: no customer, signed scope, implementation team, environment, import schema, integration, training cohort, cutover or acceptance evidence exists.

## 4.4 Customer configuration workbook (PRMS-C12-004)

The configuration workbook governs customer and tenant configuration as a controlled decision schema (Confidential).

- Each customer and each tenant receives a separate controlled instance; a shared platform is centralised, while module decisions are scoped per customer.
- Configuring is not code customisation: every value has an owner, source, classification, validation rule, environment, effective date, approval and change history.
- Shared Platform decisions and module decisions are recorded separately to prevent hidden dependencies and accidental activation of paid or sensitive behaviour.
- The file is a reusable template specification; no real customer configuration exists.

## 4.5 User manuals (PRMS-C12-005)

User manuals are task- and role-oriented instructions bound to exact releases.

- Manuals are written per materially different role, module set, configuration and supported release; planned files cover property owner, tenant, platform administrator and support/operations audiences.
- Every procedure records the goal, who may perform it, prerequisites, start state, numbered observable steps, decision points, expected persistent result, downstream/audit/notification effects, recoverable errors, reversible/irreversible warnings and support evidence capture.
- Screenshots are supplementary; instructions must never rely on colour, position, shape or pointer-only interaction.
- No executable interface, workflow, release or usability result exists, so the manual register is empty and Not started.

## 4.6 Quick-start guides (PRMS-C12-006)

Quick starts are one-outcome, verified job aids — not a substitute for the manual, training or permissions.

- Each quick start covers one high-value or high-frequency outcome for property owners, tenants or administrators: for example owner account creation and subscription, first listing, verification, enquiry and viewing management, application review, tenant search/favourite/alert/enquiry/viewing/apply flows, and administrator verification and moderation.
- Structure is a bounded pattern: outcome and role; before-you-start; five to twelve numbered steps with exact visible control names; confirm-it-worked; two to five common problems with safe corrections; safe stop and duplicate prevention; links to the full manual and support.
- No released or usability-validated task exists, so no quick-start instance exists.

## 4.7 Training plan and materials (PRMS-C12-007)

Training is role-based learning that measures competency, not attendance.

- Learning paths are defined for property owners, tenants, platform administrators, support and operations staff, and technical/integration audiences.
- Attendee attendance never equals competency: each path has observable learning outcomes, accessible practice in a non-production environment, performance assessment and reinforcement.
- Training never grants production permissions; training evidence never substitutes for authorisation review.
- No product, training environment, cohort, trainer, manual, lesson or competency result exists.

## 4.8 FAQ (PRMS-C12-008)

The FAQ family governs approved answers, not improvisation.

- Each answer identifies its audience, exact product and commercial scope, supporting evidence, limitations, related guidance, owner and review trigger.
- The FAQ excludes troubleshooting procedures, legal or professional property/tenancy/accounting advice, confidential or security details, release notes, unsupported comparisons and guaranteed outcomes.
- No FAQ entries beyond a seed set are published; the register governs how future entries are approved.

## 4.9 Knowledge base (PRMS-C12-009)

The knowledge base is searchable, evidence-backed help and troubleshooting content for exact releases and configurations.

- Article types include how-to clarification, troubleshooting, known-issue/workaround and status/concept content, each with stable ID, version, status, owner, audience and scope.
- A repeated support answer is not automatically true: behaviour, cause and resolution must be reproduced against the stated release and configuration, with uncertainty labelled.
- Workarounds require a registered known-issue entry with owner and expiry; destructive or privileged actions are routed to authorised support, never published as generic instructions.
- No article, support case, reproduced symptom or verified workaround exists; the article register is empty.

## 4.10 Release notes (PRMS-C12-010)

Release notes are authoritative, immutable records per released version and channel.

- A note binds an immutable artefact and date to customer-visible changes: new and changed behaviour, fixed defects, required actions, migrations, compatibility, deprecations, safe security disclosure, known issues, support and rollback/withdrawal state.
- Every change class carries minimum evidence: new/changed requires approved traced scope and passing release evidence; fixed requires a reproduced failure plus passing regression in the exact candidate; migration/action requires a tested procedure with backup/recovery/rollback boundary.
- No executable candidate, immutable artefact or release exists, so the register has no note instances; absence of a note blocks a release where users or operators are affected.

## 4.11 Data import templates (PRMS-C12-011)

Data import templates are exact-version contracts for customer or owner import work, with lossless fields and deterministic validation.

- Two approved CSV template concepts exist: the property import template (columns such as property_title, property_type, bedrooms, bathrooms, rent_amount, deposit_amount, currency USD, address fields, suburb, city, description, furnished, available_from, amenities, status) and the contact import template (first_name, last_name, email, phone, contact_type, property_ref).
- Format expectations are explicit: CSV with UTF-8, comma delimiter, dates YYYY-MM-DD, USD with two decimal places, lower-case booleans true/false, no quoted booleans, leading zeros preserved in text, empty required fields rejected, and a 10 MB maximum file size.
- Validation is staged from file/encoding through header, field, allowed values, relationship/business rules, permissions and tenant/module ownership, duplicates/idempotency, and reconciliation; a technically parsed file can still be rejected for business, security, privacy or authority reasons.
- Spreadsheet formula and executable content are neutralised or rejected; secrets and credential material are never importable through ordinary templates.
- No implemented import service, physical data model, released field contract, migration tool, downloadable template instance or customer dataset exists; the two template entries are Approved concept only.
- Acceptance scenarios cover leading-zero loss (e.g., property ID 00123 converting to 123) being detected and blocked, ambiguous dates being rejected, missing referential values being reported, duplicate submission being prevented, formula injection being neutralised, unsupported secret columns being rejected, dry-run defects blocking approval, financial-total mismatches failing reconciliation, and opt-out of optional fields being valid where the contract permits.

## 4.12 Terms of use (PRMS-C12-015)

The Terms of Use is a Stage 5 public-facing draft, Confidential, and not legally approved, published or offered for acceptance.

- It would govern property owners (paying), tenants (free), applicants and platform administrators; it complements the service agreement, privacy notice, data processing agreement and internal policies.
- Qualified Zimbabwean counsel must approve applicability, capacity and minor/guardian provisions, electronic assent, consumer rights, communications, intellectual property, acceptable use, disclaimers, liability, dispute resolution and governing law, and accessibility of publication.
- Regulatory statements are recorded as Assumptions pending that review.
- No website, application, account, tenant service, payment flow or content licence exists against which the Terms of Use could take effect.

## 4.13 Service agreement (PRMS-C12-012)

The service agreement is a Stage 5 Confidential draft and is not for signature, quotation, procurement or commercial use.

- It scopes PRMS services for the Harare phase-1 market and separates distinct customer audiences and tiers; the agreement also defines the minimum commercial and operational subjects a future agreement must cover.
- Qualified counsel must confirm the parties and capacity, governing law, consumer/competition/electronic-transaction/tax/data-protection obligations, warranties, liability, indemnities, dispute terms, execution formalities and jurisdiction variations.
- No legal supplier entity, customer offer, production service, signed customer, supported release or operational deployment exists.

## 4.14 Support and maintenance agreement (PRMS-C12-013)

The support and maintenance agreement is a Stage 5 Confidential draft.

- It will frame owners' eligibility, channels, responsibilities, severity classifications, response and escalation, maintenance and supported versions, and exclusions; tenants rely on self-service and in-product help with the limited entitlements described in the Terms of Use.
- The final maintenance package, service targets, remedies, jurisdiction, price and wording require commercial and legal approval.
- No support desk, maintenance service, rota, service objective, release or entitlement exists.

## 4.15 Data processing agreement (PRMS-C12-014)

The Data Processing Agreement (DPA) is a Stage 5 Confidential draft and is not legal advice.

- Qualified counsel must determine the controller and processor (or joint) roles, applicable law, licensing and data-protection-officer duties, lawful bases, sensitive-data handling, cross-border conditions, incident notification, data-subject rights and execution terms.
- Regulatory statements in the draft are recorded as Assumptions.
- No legal entity, customer, production data, hosting region, subprocessor, transfer mechanism, DPA signature, data-protection licence or processing service exists.

# Part 5 — Product measurement and improvement (Category 13)

Part 5 consolidates the Category 13 product measurement and improvement set: how PRMS measures real outcomes, tracks events lawfully and proportionately, collects and uses customer feedback, runs discovery on unanswered evidence, evaluates feature opportunities, records experiments and post-launch reviews, and keeps an honest improvement roadmap. Every register is empty and every KPI/target is unmeasured.

## 5.1 Product KPI framework (PRMS-C13-001)

The KPI framework measures whether PRMS creates trustworthy rental-marketplace and property-management outcomes, not how many features or screens are produced.

- The north-star direction is formalised as Verified Rental Journey Completion (VRJC): the proportion of active property owners with at least one published listing who complete all due rental-journey steps — enquiry response, viewing, application, lease and first rent payment — with verified data and no unresolved critical trust exception, within a defined period.
- VRJC is not listing count, enquiry volume, page views or revenue; it must not reward fake listings, coerced applications, premature lease signing or status manipulation.
- Release-specific eligible stages, due rules, verification requirements, exception definitions, cohort and window must be approved before any calculation; none have been.
- The balanced portfolio spans discovery/market evidence, owner value, tenant value, marketplace and adoption, commercial sustainability, trust/quality/operations, and improvement-loop health, each with a module owner (LND, RBA, PMT, SUB, VER, MKT, APL inter alia) and target status mostly Unselected or Baseline required.
- Every positive KPI has safety, security, privacy, accessibility, quality and cost guardrails; a positive result cannot compensate for a breached non-waivable guardrail, and aggregate results may not hide harmed tenants, owners, modules, roles, devices or cohorts.
- Missing instrumentation is unknown, not zero and not success; a Defined metric is not a measured metric.
- Correlation, reported perception, experiment effect and causation remain distinct; customer or individual league tables are prohibited by default; small samples are suppressed, aggregated and labelled with uncertainty.
- No customer, event stream, baseline, target, dashboard or observed result exists.

| KPI group | Example KPIs |
|---|---|
| Discovery/market evidence | Problem-evidence coverage, decision-evidence traceability, assumption disposition |
| Owner value | Time to first published listing, enquiry response time, listings per owner, owner MRR/ARPU, communication outcome rate |
| Tenant value | Listing-to-viewing, viewing-to-application, application-to-lease, favourites-per-listing, activation, repeat search |
| Marketplace/adoption | Verified property rate, listing coverage by location, search-to-detail, owner subscription conversion, featured uplift |
| Commercial | Subscription revenue, platform revenue split, gross/net revenue retention, owner lifetime value |
| Trust/quality/operations | Critical SLO attainment, escaped defects, security/privacy outcomes, rent collection rate, payment overdue, occupancy, maintenance resolution, complaint resolution |
| Improvement loop | Evidence-to-decision lead time, improvement-action verification, repeated problem/incident rate, learning yield |

## 5.2 Analytics and event-tracking plan (PRMS-C13-002)

Tracking is a governed data product, not an unrestricted copy of application or audit logs.

- Server-side domain outcomes are authoritative for listing verification, enquiries, viewings, applications, lease creation, payment allocation, subscription billing, maintenance and complaint resolution; client (browser) events may describe interaction context but cannot claim a business outcome.
- Analytics, domain events, audit records and observability signals remain distinct even when correlated; an analytics failure must never break a business transaction, and analytics can never become the authoritative financial, property, consent or identity record.
- Event names use the `prms.*` namespace with stable past-tense business outcomes (for example `prms.mkt.listing.published`, `prms.ver.badge.granted`, `prms.pmt.payment.received`, `prms.lse.lease.signed`, `prms.apl.application.approved`), one namespace per module, and never encode customers, UI labels or providers.
- Data minimisation occurs at the producer: no passwords, tokens, message bodies, document contents, free text, payment credentials or unnecessary personal identifiers belong in analytics properties.
- Tenant-facing tracking requires explicit consent before collection; owners' dashboard/reporting analytics rely on a service-delivery basis separated from optional analytics; login is never consent.
- The pipeline separates restricted raw intake, validated/quarantined records, transformed governed facts, metric aggregates and approved reporting; invalid, duplicate, wrong-environment, unauthorised-tenant or privacy-prohibited events are quarantined and alerted, never silently dropped.
- Reconciliation samples analytics against authoritative source records, and an end-to-end KPI calculation must pass controlled scenarios before a metric activates; any material data-quality gap marks the KPI unknown/suspended and blocks claims and experiments.
- Kill switches can disable optional tracking without touching transactions; retiring an event requires confirming no active consumer and preserving history.
- No analytics vendor, SDK, event pipeline, consent mechanism, telemetry data or dashboard exists.

## 5.3 Customer feedback process (PRMS-C13-003)

Feedback is contextual evidence about owner, tenant and user experience — not a vote, promise, defect verdict or requirement by itself.

- Every item records who experienced what, in which role/customer/module/version/workflow/context, what they expected, the evidence and the impact, with identity minimised, consent and notice respected and direct quotes restricted.
- Feedback sources are research, onboarding, in-product prompts, account reviews, complaints and disputes (CMP), public channels, owner surveys and exit conversations; each channel carries a documented bias and control.
- Credible safety, security, privacy, data, financial, harassment or legal issues route immediately to the incident or specialist process; feedback handling never delays containment.
- Synthesis groups by underlying problem, job or outcome rather than keyword or requested feature alone; "add WhatsApp" might represent a reach, self-service, cost or workflow problem and cannot define the solution by itself.
- A repeated support answer is not automatically true; satisfaction, NPS, CSAT or CES-style scores require an explicit method, scale, sampling and interpretation, and a score alone is never product success.
- Feedback from owners, tenants, administrators and non-buying affected users is analysed separately so paying-power does not erase user harm; absence of feedback is not satisfaction when access, literacy, disability, language or power suppresses response.
- No customer, feedback channel, case, survey, score, theme or product result exists.

## 5.4 Product discovery backlog (PRMS-C13-004)

The discovery backlog is the authoritative queue of PRMS uncertainties that require evidence before a product, market, module, workflow or investment decision — it is not a feature backlog, sprint commitment or roadmap promise.

- Each item states the decision at risk, problem or hypothesis, existing and contradictory evidence, affected roles/segments/modules, risk of being wrong, the smallest credible next method, success/disconfirm/stop criteria, owner and due gate.
- Priority is P0 (decision blocker), P1 (high leverage), P2 (important later) or Parked; mandatory safety, legal, security and data work is a constraint and is never scored away, and priority never becomes a release promise.
- Sixteen items are approved and constitute the initial backlog: P0 items cover representative owner problem and willingness-to-pay in Harare (DISC-001), authoritative Zimbabwean rental regulations and landlord-tenant law (DISC-002), the bounded MVP journey's safety and usability (DISC-003), and the numerical performance/capacity/recovery/compatibility/service assumptions for first build (DISC-004). P1 items cover segment and location choice, subscription tier value and willingness-to-pay, MVP property-type references, comms channels, property verification design, device/connectivity/accessibility contexts, owner data quality and import effort, deployment modes, and tenant self-service scope. P2/Parked items cover rent-collection gateway integration, back-office module sequencing, and featured-listings fairness.
- A discovery item may proceed within approved ethical scope without build authority, but Gate 4 no-go is never bypassed by discovery priority, and no item claims a participant, study or validated hypothesis.

## 5.5 Feature evaluation framework (PRMS-C13-005)

The feature evaluation framework decides whether an opportunity is rejected, discovered, deferred, prepared for delivery, or stopped, using gates plus evidence rather than a single score.

- An evaluation begins with the problem and the affected outcome, not a proposed solution; verified evidence, approved decisions, assumptions and open questions stay distinguishable.
- Lifecycle states are Submitted, Discovery required, Ready for evaluation, Conditionally endorsed, Endorsed, Deferred, Rejected and Withdrawn, with permitted transitions and named evidence.
- Mandatory gates (legal/regulatory, safety and harm, security and privacy, accessibility and inclusion, product boundary, evidence integrity) block endorsement when failed; commercial value, stakeholder seniority, revenue or a score can never offset a gate failure.
- ICE-style scoring (Impact, Confidence, Ease) is optional for portfolio comparison and only after the minimum evidence gate is satisfied: impact and confidence reflect evidence grade, ease reflects implementation cost, complexity, risk and reversibility; unknowns remain visible and are never silently turned to zero or into optimistic scores.
- Fourteen evaluation dimensions (problem/evidence, strategic fit, customer and owner/tenant value, reach and equity, modularity, commercial viability, usability and accessibility, feasibility and architecture, data and integration, security/privacy/compliance, quality and operability, economics and capacity, opportunity cost, reversibility and learning) each receive an evidence grade (A/B/C/D/U) and a directional assessment; grade and value are never merged into one number.
- Endorsement authorises delivery intake only; release scope follows delivery and gate governance.
- No feature has passed evaluation or entered a release.

## 5.6 Experiment register (PRMS-C13-006)

The experiment family register records experiment plans and results for the product lifecycle.

- Each experiment is pre-registered before exposure, protects participants and mandatory controls, preserves deviations and null/negative results, and ends with a decision rather than a convenient narrative.
- Files follow `experiments/EXP-{number}-{slug}.md`; the register lists proposed, cancelled, concluded and invalidated experiments.
- No PRMS experiment has been approved or run; the register contains no instance, and approval of the register does not authorise recruitment, production exposure, telemetry or any feature.

## 5.7 Post-launch review register (PRMS-C13-007)

The post-launch review family register records reviews that bind an exact release and operating context.

- Each review compares expected scope and outcomes with actual evidence, preserves failures and limitations, and converts learning into owned product decisions.
- Files follow `post-launch-reviews/{version}-{YYYY-MM-DD}.md` and cover every required review including rolled-back or failed launches.
- No software release exists, so no review instance exists; approval of the register implies no launch readiness, market validation or product success.

## 5.8 Product improvement roadmap (PRMS-C13-008)

The improvement roadmap converts verified evidence into product decisions; it is outcome-based and evidence-triggered, not a calendar of feature promises.

- The roadmap is in the Pre-operation state: Gate 4 is NO-GO, there is no application, candidate or release, and there is no customer, telemetry, support, incident, commercial or post-launch evidence. Its only active direction is to close the Gate 4 conditions and complete priority discovery.
- Horizons are evidence-gated, not quarterly: H0 Evidence and build readiness; H1 First release validation (PRMS-MVP-1); H2 Outcome and adoption learning; H3 Portfolio improvement and expansion (PMT, LSE, MTN, SVC, DOC, CMP, FAD, NTF, RBA, LND); H4 Regional expansion (Bulawayo, Mutare, Gweru); H5 Commercial property and advanced services (offices, shops, warehouses, land); H6 Platform maturity and ecosystem.
- Each stream defines its intended outcome, current evidence, next decision trigger and guardrails — for example owner/tenant validation, MVP-1 and critical journeys, modular value and packaging, build and quality readiness, trustworthy measurement, operational resilience, communication channels, trust and verification, rent and payment, the learning loop, and future portfolio expansion.
- Capacity bands (core outcomes, trust/reliability, customer evidence, debt/maintenance, bounded exploration) are only assigned after Gate 4 GO; expedites require reason, authority, displaced work and expiry; over-allocation and ageing trigger re-planning rather than silent carry-over.
- A roadmap lane authorises discovery and evaluation only; delivery requires requirements, traceability and later gate authority, and no measured improvement is claimed.

# Cross-cutting adoption, operations and measurement considerations

The following themes bind the five parts together and are called out because they are the most common places where status and evidence are confused.

- **Registers are truth, not marketing:** a register with no instances describes current state accurately; it is never evidence that the underlying capability is absent by design or that it will be absent in future.
- **Publication is gated, not free:** product overview, capability guides, manuals, quick starts, knowledge articles, release notes, feedback answers, marketing claims and roadmap statements each require exact release, audience, channel and legal/commercial authority before any external use.
- **The trust chain is the quality chain:** verification-before-published, application gates, signed-lease activation and reconciled rent transitions are the same behaviours that test strategy, acceptance, regression, security, operations, KPIs and analytics all defend; they are implemented once and defended everywhere.
- **Nothing measured is claimed:** VRJC, SLO targets, performance numbers, KPI results, satisfaction scores, experiment conclusions and post-launch findings all require representative, privacy-safe, quality-verified data with published definitions and limits before they can be stated.

## Current-state open questions (consolidated)

The most consequential undecided items are consolidated here for traceability; each carries its owning source and gate.

| Open question | Owning gate |
|---|---|
| Exact MVP release journey steps and due rules for VRJC | Before any instrumentation |
| Numerical performance, capacity, recovery, compatibility and service-level targets | Gate 4 build-readiness evidence |
| Support channels, hours, staffing, languages and response targets | Operational readiness |
| Versioning cadence, release tooling and deployment packaging mechanics | Gate 4 delivery work |
| Operating legal entity, consumer/tenant/owner offer, pricing and packaging | Qualified Zimbabwean legal and commercial review |
| Analytics vendor, pipeline and storage selection | Technology implementation decision |
| Which discovery capacity, budget and participant recruitment authority exists | Before primary research |
| Which feature evaluations and delivery candidates enter MVP scope after Gate 4 | Gate 4 and release-scope governance |

## Approval history

| Version | Date | Approver | Decision |
|---|---|---|---|
| 1.0.0 | 2026-09-08 | Delegated Product Owner | Consolidated from the Category 09-13 sets into a single PRMS delivery, quality, operations and adoption baseline |