---
document_id: PRMS-DOC-06
title: Coding Standards
version: 1.0.0
status: Approved
category: Delivery and engineering
owner: Engineering and Architecture Owner
approver: Delegated Product Owner (Codex)
created: 2026-08-25
last_updated: 2026-09-08
next_review: Gate 4 toolchain selection, first implementation review, selected release-line change, or material defect pattern
sensitivity: Internal
---

# Coding Standards

## Executive summary

PRMS first-party code will be explicitly typed, automatically formatted, modular by domain ownership, server-authoritative, testable and readable without hidden framework behaviour. Command workflows use application services and aggregate repository ports; read workflows use query handlers and purpose-specific read ports. PHP follows PSR-12-compatible formatting enforced through a pinned Laravel Pint configuration; TypeScript uses strict checking; React follows component/Hook purity and accessibility rules; PostgreSQL uses owner-qualified, unquoted snake-case identifiers and parameterised access under customer-context/RLS controls.

Framework conventions do not override approved PRMS boundaries. Controllers, React components, ORM models, jobs and database schemas cannot become shared business-logic containers. Source-owning modules expose narrow application contracts; other modules use those contracts/events/projections rather than importing models or writing tables.

This document selects standards, not exact formatter/linter/static-analysis packages or thresholds beyond Pint/TypeScript/React core direction. Gate 4 requires pinned compatible tooling, configurations, suppression/exception rules and a clean representative codebase. No implemented conformance is claimed.

## Purpose and audience

Define reviewable source rules for PHP/Laravel, TypeScript/React, SQL/PostgreSQL, tests, errors, security, documentation and generated artefacts. Applies to contributors, reviewers, architecture/data/security/quality owners and automated checks.

## Scope and exclusions

Included: first-party application/client/database/test/tooling code and configuration. Excluded: vendored/generated source beyond verification boundaries; repository layout details; CI implementation; exact package selection; and permission to code before Gate 4.

## Governing sources and precedence

1. Approved PRMS requirements, security/privacy/data and architecture decisions.
2. This Coding Standard and module/API/event/error/configuration standards.
3. Pinned project formatter, linter, compiler and static-analysis configurations.
4. Selected framework/language conventions where they do not conflict.

Conflicts stop the change and require a source decision. "The framework allows it" is not sufficient rationale.

The command, query and persistence structure governed by the application-services / aggregate-repositories / dedicated-read-queries decision (recorded in the architecture baseline, `docs/03-architecture-and-data.md`) overrides archived source-note proposals and arbitrary framework convention.

## General source rules

- UTF-8, LF line endings, final newline and no trailing whitespace.
- Names express domain meaning; abbreviations use approved glossary/module identifiers.
- Functions/classes/components are cohesive and small enough to understand/test; no numerical size rule is invented.
- Mutable state, side effects and dependencies are explicit and narrow.
- Public contracts use stable types and outcome semantics; internal implementation details stay private.
- Booleans name a positive predicate; state machines use explicit values rather than interacting flags.
- Time, money, quantity, identifier and absence use approved domain/value types and semantics.
- No secret, token, password, private key, production personal data or sensitive debug payload in source/tests/examples.
- Disabled code is deleted and recoverable from history, not commented out.
- Deferred-work markers require a work/risk ID, owner/trigger and cannot bypass mandatory acceptance.

## PHP and Laravel

### Formatting and language

- First-party PHP follows PSR-12-compatible style and a repository-pinned Laravel Pint configuration.
- First-party PHP files declare `strict_types=1` unless an approved compatibility boundary documents why not.
- Parameters, returns, properties and constants use precise native types where expressible; mixed/untyped boundaries are validated and contained.
- `final`, immutability/read-only and value objects are preferred where extension/mutation is not a product requirement.
- Exceptions and result objects convey named failures; sentinel strings/booleans do not hide domain outcomes.
- Dynamic properties, global mutable state, service location and magic access that obscures dependencies are prohibited in domain/application code.

### Modular structure and dependency direction

Each business module owns domain, application, infrastructure adapters and presentation composition behind its approved public surface. Shared Platform owns only approved cross-product foundations.

- Controllers/commands/listeners are transport adapters: authenticate/contextualise, validate shape, call one application use case or query handler and translate its result.
- Domain rules live with the source-owning domain, not controllers, React clients, jobs, policies or database triggers by convenience.
- Application services coordinate transactions, authority and owner contracts; they do not expose ORM models across modules.
- Eloquent models/repositories are private to their owner. Cross-module model imports, relationship traversal and table writes are prohibited.
- Module collaboration uses typed command/query interfaces, events or approved projections with customer/actor/purpose context.
- Dependency injection is explicit; service-container resolution in arbitrary business code is prohibited.
- Laravel events/observers cannot hide material business transitions or cross-module writes.

### Mandatory command and query architecture

Command and read paths are deliberately different because they optimise for different responsibilities while preserving the same customer, entitlement, permission and module authority.

#### Commands and writes

- Every state-changing business operation enters through a named application service/use case.
- The application service owns authority evaluation, domain orchestration, the coherent transaction/concurrency boundary, aggregate repository ports and required audit/event intent.
- Command-side application and domain code depend on repository interfaces owned by the application/domain boundary; they do not execute Eloquent, Query Builder or raw SQL directly.
- An aggregate repository persists an aggregate root or another approved meaningful persistence boundary. Its contract uses domain language and does not expose Eloquent models, relationships, query builders or generic table CRUD.
- Eloquent repository implementations are private infrastructure adapters in the owning module.
- Child entities, value objects, pivots and reference tables are persisted through their aggregate or an explicitly justified boundary; a model does not automatically receive its own repository.
- A generic `BaseRepository` with table-shaped `all`, `find`, `create`, `update`, `delete` or arbitrary-filter operations is prohibited.

#### Queries and reads

- Every non-trivial screen, API, export or report read enters through a named query handler and purpose-specific read port.
- A read adapter may use an Eloquent projection, Query Builder or reviewed parameterised SQL when it is the clearest safe and measurable implementation.
- Read contracts return immutable typed DTOs/projections, never live Eloquent models, relations or builders across application/module boundaries.
- Query contracts state customer, authority, purpose, filters, sort, page/limit, field projection and freshness semantics; every resource shape is bounded.
- Write repositories are not expanded with generic filtering merely to serve reads. Critical read shapes receive dedicated query contracts and representative PostgreSQL plan/index evidence.
- A projection or cache cannot authorise a command unless its approved consistency and freshness semantics make it authoritative for that decision.

#### Exceptions

Any architectural exception identifies the exact use case and boundary, alternatives, security/audit/data/performance impact, owner, expiry or review trigger and compensating tests. Convenience, framework preference or fewer files alone is not sufficient.

### Requests, validation and authorisation

- Boundary validation rejects malformed/unexpected input and maps to typed application input.
- Validation does not grant authority. Authentication, customer context, entitlement, permission, relationship, record state and assurance are evaluated server-side.
- Client-supplied customer/role/permission identifiers never establish authority.
- Mass assignment, serialisation and resource fields use explicit allowlists.
- Policies/authorisation services call source-owned rules and default deny; UI visibility remains non-authoritative.

### Persistence and transactions

- Application use cases define transaction boundaries around coherent source-owned changes.
- External/provider calls do not occur inside long database transactions unless a specific design proves safety.
- Cross-domain reliable outcomes use outbox/jobs/events and reconciliation, not distributed assumptions.
- Queries avoid unbounded results and hidden N+1 behaviour; load/projection intent is explicit.
- Critical queries select only required fields, declare cardinality and ordering, and retain representative plan/index evidence under the performance requirements and budgets.
- Concurrency uses constraints, version/precondition or locks appropriate to the invariant, with conflict surfaced truthfully.
- Migrations are deterministic, forward-compatible where required and separate schema transition from risky data backfill.

### Jobs, events and providers

- Jobs/events carry stable identifiers and necessary customer, actor/workload, purpose, correlation, idempotency and version context without secrets.
- Handlers are idempotent for the approved delivery semantics and distinguish retryable, terminal and business outcomes.
- Acknowledgement/HTTP success/provider acceptance never substitutes for authoritative completion.
- Retry/backoff/dead-letter/reconciliation follows the approved error/event/integration contracts; infinite retry is prohibited.

## TypeScript and React

### TypeScript

- TypeScript strict checking remains enabled; project configuration is explicit and pinned rather than relying on floating defaults.
- Implicit `any` is prohibited. Explicit `any` requires a narrow documented boundary, work item and safer follow-up or approved permanence.
- Untrusted values enter as `unknown` and are validated/narrowed before domain use.
- Null/undefined/absent/not-loaded/not-applicable states follow the data dictionary and are not collapsed through truthiness.
- Discriminated unions model finite states/outcomes; impossible combinations are excluded by types where practical.
- Non-null assertions, unchecked casts and suppression comments require local evidence/rationale and reviewer approval.
- API/provider types are generated or defined from governed contracts and validated at runtime; compile-time typing does not validate network data.
- Exported symbols are minimal; modules do not import another context's internal client state/components to bypass contracts.

### React

- Components and Hooks are pure/idempotent with respect to inputs; render does not perform side effects.
- Props/state/Hook inputs are immutable snapshots; mutation uses explicit state/domain transitions.
- Hooks are called only at top level of React components/custom Hooks; components are rendered through JSX, not invoked as functions.
- Effects synchronise with external systems and are not the default place for derived state or business workflow.
- Server/domain truth is not duplicated as client authority. Client caches declare freshness, invalidation, conflict and retry behaviour.
- Components separate accessible presentation/composition from API/domain orchestration at appropriate boundaries.
- Forms preserve input, show field/summary errors, manage focus and prevent duplicate/ambiguous submission.
- Loading, empty, denied, stale, conflict, partial failure, offline/degraded and retry states are explicit.
- Native semantic HTML is preferred; custom widgets implement required keyboard/focus/name/role/value behaviour and tests.
- Entitled module composition does not expose unauthorised routes, data fetches, commands or cached content.

### Frontend component ownership tiers

Frontend reuse uses three ownership tiers. The tier is a dependency and change-control boundary, not merely a folder name or component-size label.

| Tier | Purpose | Permitted dependencies | Prohibited content |
|---|---|---|---|
| Tier 1 — design primitives | Accessible semantic controls and token-bound visual primitives such as button, input, dialog and status. | Platform language/runtime plus an approved primitive library when selected. | Module vocabulary, business rules, API calls, server-state ownership or feature imports. |
| Tier 2 — shared experience patterns | Reusable cross-flow compositions such as record header, error summary, work queue, data state and guided-step shell. | Tier 1 and narrow platform contracts. | Source-module decisions, direct provider/domain persistence or feature-to-feature imports. |
| Tier 3 — module/feature compositions | Module-owned screens and flows that combine data, commands, permissions and domain terminology. | Tier 1/2 plus the owning module's approved client contracts and generated API types. | Importing another module's internal feature state/components to bypass its public contract. |

Dependencies flow from Tier 3 to Tier 2 to Tier 1; reverse and lateral feature coupling require an approved public extraction rather than moving business logic into a shared folder. Tier 1/2 changes are treated as shared API changes and require state, responsive, keyboard, screen-reader and representative visual-regression evidence proportionate to impact. Exact styling, primitive, server-state, form, story and visual-diff libraries remain Gate 4 tool selections.

### Styling and content

- Approved design tokens/components govern colour, spacing, typography, states and motion; arbitrary duplication requires rationale.
- Responsive behaviour is content/workflow-driven; no desktop-only critical path.
- Colour is never the only status indicator; text alternatives, focus visibility, zoom/reflow and reduced motion are supported.
- User text uses the content and terminology guide and avoids leaking stack/provider internals or sensitive identifiers.

## PostgreSQL and SQL

- First-party schemas, tables, columns, constraints and indexes use unquoted lowercase snake case and approved owner prefixes/schemas.
- Objects are schema-qualified in migrations, privileged/operational queries and security-sensitive code.
- Dynamic SQL identifiers require allowlisted construction; all values use parameters/bindings.
- Application roles do not own tables or bypass RLS; elevated migration/operations roles are separate.
- Every customer-owned row carries the approved customer key and applicable same-customer constraints/RLS policies.
- Database constraints enforce universal integrity; domain services provide contextual rules and usable errors.
- Money uses exact numeric/integer-minor-unit design as approved, never floating point.
- Time uses approved instants/local-date/time-zone semantics; application/database defaults do not silently decide business time.
- JSONB stores approved variable/provider payloads, not convenient replacement for owned relational concepts.
- Raw SQL is acceptable when clearer/safer/measurably necessary, but remains owner-scoped, parameterised, tested and reviewed.

## Errors, logging and audit

- Errors use the approved taxonomy/problem/outcome contracts and never expose stack traces, queries, secrets or protected record existence.
- Catch only when translating, compensating, adding safe context or making a deliberate retry/terminal decision; swallowed exceptions are prohibited.
- Logs are structured and purpose-bound with correlation/customer context where authorised; secrets and unnecessary personal data are excluded.
- Audit evidence is emitted at the authoritative decision/change boundary and is not reconstructed from debug logs.
- Protected application services and sensitive query handlers create semantic audit intent; Eloquent observers or package-default row diffs cannot be the sole audit source.
- Required audit/event evidence is committed with protected state or through a transactional outbox and monitored reconciliation; rollback cannot leave false success evidence.
- Assertions defend programmer invariants in appropriate environments; they do not replace runtime validation/authorisation.

## Tests and testability

- Tests use Arrange/Act/Assert or comparably clear structure and name behaviour/condition/outcome.
- Domain tests are deterministic and do not depend on network, wall clock, randomness or global state without controlled fakes.
- Boundary/integration tests use real framework/database behaviour where mocks would conceal contracts, RLS, transactions or serialisation.
- Mocks represent owned ports/contracts, not internal implementation chains.
- Aggregate repository implementations pass contract tests and real PostgreSQL integration tests for constraints, RLS/customer isolation, transactions, concurrency and rollback.
- Critical read repositories are tested for authority scope, bounded results, N+1 absence and representative plan/index behaviour.
- Tests cover negative/abuse/concurrency/failure/correction and package absence, not only happy paths.
- Test data uses builders/fixtures with explicit semantics and no production copies.
- A failing test is repaired through product code or an approved requirement change; weakening assertions requires explanation/review.

## Comments and documentation

- Code explains what; comments explain non-obvious why, invariant, trade-off, authority or external constraint.
- Public contracts and complex domain/value semantics receive concise durable documentation; obvious code is not paraphrased.
- Commented-out code, stale history narratives and author initials are prohibited.
- Architecture/API/event/schema decisions live in governed documents/contracts, not only comments.
- Examples compile/test where practical and never contain real credentials/data.

## Generated code and dependencies

Generated artefacts identify source generator/version and reproducible command. Human edits to regenerated files are prohibited unless the artefact is explicitly adopted as maintained source. Generated output is reviewed for contract/security/licence impact and checked for drift.

Third-party code is not copied into first-party source to avoid dependency/licence controls. Forks/patches require provenance, licence, owner, update/exit plan and security review.

## Automated enforcement and exceptions

Gate 4 must pin and prove formatter, PHP static analysis, TypeScript compiler, React/JS lint, architecture fitness, SQL/migration, secret and test checks. New/changed code cannot lower the approved baseline. Suppressions are narrow, local, explained, reviewed and searchable; broad exclusions require an approved exception.

Architecture fitness checks must exercise prohibited cross-module imports, ORM-model exposure, direct table access, dependency cycles, controller-to-Eloquent and command-service-to-Eloquent shortcuts, generic repositories, missing command/query boundaries, shared-folder business rules and forbidden frontend tier direction. A test that only confirms preferred directory names is insufficient. The representative vertical slice must prove that deliberately introduced violations fail the correct gate with actionable results.

An optional module scaffold/generator may create approved namespace, contract, test and ownership skeletons after the first vertical slice proves them. Its templates and output are versioned, tested and reviewed like source. A generator accelerates approved application-service, aggregate-repository and query boundaries; it never decides where an aggregate belongs or generates a repository merely because an Eloquent model exists.

An exception records standard clause, scope, reason, alternatives, risk, authority, owner, expiry and removal test. Style convenience alone is not an exception basis. Formatter/tool configuration changes are reviewed like source and applied separately when broad churn would obscure logic.

## Coding requirements

| ID | Requirement | Priority |
|---|---|---|
| CDS-001 | First-party source shall use UTF-8, LF, final newline and no trailing whitespace. | Must |
| CDS-002 | PHP shall follow pinned PSR-12-compatible Pint formatting. | Must |
| CDS-003 | First-party PHP shall use strict types except approved compatibility boundaries. | Must |
| CDS-004 | PHP/TypeScript contracts shall use precise explicit types and validated boundaries. | Must |
| CDS-005 | TypeScript strict checking and no implicit any shall remain enabled. | Must |
| CDS-006 | Untrusted client/provider data shall enter as unknown and be runtime validated. | Must |
| CDS-007 | Casts, non-null assertions and suppressions shall be narrow and justified. | Must |
| CDS-008 | Domain names/types shall follow approved glossary/data semantics. | Must |
| CDS-009 | Secrets and production personal data shall never enter source/tests/examples. | Must |
| CDS-010 | Disabled code shall be deleted; deferred-work markers shall be governed. | Must |
| CDS-011 | Framework convention shall not override PRMS module/data authority. | Must |
| CDS-012 | Controllers/listeners/commands shall remain thin transport adapters. | Must |
| CDS-013 | Business rules shall live with the source-owning domain. | Must |
| CDS-014 | Cross-module ORM imports/relationship traversal/table writes shall be prohibited. | Must |
| CDS-015 | Module collaboration shall use approved typed contracts/events/projections. | Must |
| CDS-016 | Dependencies shall be explicit rather than arbitrary service location/global state. | Must |
| CDS-017 | Validation shall remain separate from authentication/authorisation. | Must |
| CDS-018 | Customer/role/permission client fields shall never establish authority. | Must |
| CDS-019 | Mass assignment/serialisation/resource fields shall use explicit allowlists. | Must |
| CDS-020 | Application use cases shall own coherent transaction/concurrency boundaries. | Must |
| CDS-021 | External calls shall not create unsafe long database transactions. | Must |
| CDS-022 | Queries shall be bounded and avoid hidden N+1/unowned access. | Must |
| CDS-023 | Jobs/events shall preserve customer/actor/purpose/correlation/idempotency/version context. | Must |
| CDS-024 | Handlers shall distinguish retryable, terminal and business outcomes. | Must |
| CDS-025 | Provider/transport acceptance shall not represent domain completion. | Must |
| CDS-026 | React components/Hooks shall follow purity, immutability and Hook-call rules. | Must |
| CDS-027 | Effects shall synchronise external systems, not conceal derived/business state. | Must |
| CDS-028 | Client state/cache shall not become authoritative domain/security state. | Must |
| CDS-029 | UI shall implement explicit loading/empty/denied/stale/conflict/failure/degraded states. | Must |
| CDS-030 | Semantic accessible HTML and supported interaction shall be default. | Must |
| CDS-031 | Entitlement shall be enforced beyond UI/routes across requests/caches/actions. | Must |
| CDS-032 | Design tokens/content standards shall govern styling/user language. | Must |
| CDS-033 | PostgreSQL identifiers shall use owner-qualified unquoted snake case. | Must |
| CDS-034 | SQL values shall be parameterised and dynamic identifiers allowlisted. | Must |
| CDS-035 | Application roles shall not own tables or bypass RLS. | Must |
| CDS-036 | Customer rows/constraints/policies shall enforce approved isolation. | Must |
| CDS-037 | Exact money and explicit time semantics shall be used. | Must |
| CDS-038 | Errors/logs shall follow approved semantics and exclude sensitive internals/data. | Must |
| CDS-039 | Exceptions shall not be swallowed; audit shall not be reconstructed from debug logs. | Must |
| CDS-040 | Tests shall be deterministic, behaviour-named and cover negative/failure/concurrency/package cases. | Must |
| CDS-041 | Mocks shall target owned ports; real boundary behaviour shall be tested where material. | Must |
| CDS-042 | Comments shall explain durable why/invariant rather than paraphrase or retain dead code. | Must |
| CDS-043 | Generated artefacts shall be reproducible, versioned, reviewed and not hand-edited silently. | Must |
| CDS-044 | Tooling/suppressions/exclusions shall be pinned, narrow, reviewed and searchable. | Must |
| CDS-045 | Gate 4 shall prove the selected coding toolchain on representative modular code. | Must |
| CDS-046 | Material defect/tool/release-line evidence shall trigger standard review. | Must |
| CDS-047 | Frontend components shall declare Tier 1 primitive, Tier 2 shared-pattern or Tier 3 module/feature ownership. | Must |
| CDS-048 | Tier 1 primitives shall contain no module vocabulary, domain rule, API call or feature import. | Must |
| CDS-049 | Tier 2 shared patterns shall remain domain-neutral and shall not own source-module decisions or persistence. | Must |
| CDS-050 | Tier 3 compositions shall use approved owning-module contracts and shall not import another module's internal feature state. | Must |
| CDS-051 | Frontend tier dependencies shall flow Tier 3 to Tier 2 to Tier 1 unless an approved public extraction changes ownership. | Must |
| CDS-052 | Shared Tier 1/2 changes shall receive proportional state, responsive, accessibility and visual-regression evidence. | Must |
| CDS-053 | Gate 4 architecture fitness checks shall prove prohibited backend module and frontend tier dependencies fail. | Must |
| CDS-054 | Any scaffold/generator shall be versioned and tested and shall not determine domain boundaries. | Conditional |
| CDS-055 | Every state-changing business operation shall enter through a named application service/use case. | Must |
| CDS-056 | Command application/domain code shall use aggregate repository ports and shall not execute Eloquent, Query Builder or raw SQL directly. | Must |
| CDS-057 | Repository ports shall model aggregates or another approved meaningful persistence boundary using domain language. | Must |
| CDS-058 | Repository contracts shall not expose Eloquent models, relationships, query builders or generic table CRUD. | Must |
| CDS-059 | Repository interfaces shall not be generated mechanically per Eloquent model or through a generic base repository. | Must |
| CDS-060 | Non-trivial reads shall use named query handlers and purpose-specific read ports returning typed DTOs/projections. | Must |
| CDS-061 | Read contracts shall enforce customer, authority, purpose, filter, sort, bound and freshness semantics. | Must |
| CDS-062 | Critical reads shall retain representative query-plan/index evidence and tests for bounded shape and N+1 absence. | Must |
| CDS-063 | Protected services/query handlers shall create semantic audit intent; model observers/package-default diffs shall not be the sole source. | Must |
| CDS-064 | Required audit/event evidence shall be atomic with protected state or reconciled through a transactional outbox. | Must |
| CDS-065 | Aggregate repositories shall have contract and real PostgreSQL integration evidence for material database behaviour. | Must |
| CDS-066 | Architecture checks shall reject controller/service persistence bypass, generic repositories and missing approved command/query boundaries. | Must |
| CDS-067 | Architectural exceptions shall record scope, rationale, impacts, owner, review/expiry and compensating tests. | Must |

## Acceptance scenarios

| ID | Scenario | Expected result |
|---|---|---|
| CDS-ACC-001 | Laravel relation reaches another module's model directly. | Architecture check/review rejects it; owner contract/projection is required. |
| CDS-ACC-002 | Controller validates `customer_id` then trusts it. | Rejected; server-resolved customer/authority context is mandatory. |
| CDS-ACC-003 | TypeScript boundary casts provider JSON to a domain type. | Rejected until runtime validation/narrowing exists. |
| CDS-ACC-004 | React component mutates props during render. | Lint/review/test fails under React purity rules. |
| CDS-ACC-005 | Effect copies easily derived props into state. | Simplified to derived render logic unless external synchronisation need is proven. |
| CDS-ACC-006 | Hidden button is the only entitlement control. | Rejected; server/job/report paths require authority tests. |
| CDS-ACC-007 | Raw SQL interpolates a user value. | Rejected; binding/parameterisation required. |
| CDS-ACC-008 | ORM query returns every tenant to render a dropdown. | Rejected; bounded search/pagination contract required. |
| CDS-ACC-009 | Job marks invoice paid when provider request returns HTTP 200. | Rejected; source-owned final outcome/reconciliation required. |
| CDS-ACC-010 | Catch block logs exception then returns success. | Rejected; truthful translation/retry/terminal outcome required. |
| CDS-ACC-011 | Test mocks RLS/database transaction and never uses PostgreSQL. | Insufficient for isolation/transaction contract; real integration evidence required. |
| CDS-ACC-012 | Developer disables strict check for one library boundary. | Requires narrow adapter, rationale, test and governed suppression/exception. |
| CDS-ACC-013 | Generated OpenAPI client differs from source contract. | Drift check fails; regenerate/reconcile before merge. |
| CDS-ACC-014 | A deferred-work marker says "fix security later" without a work ID. | Rejected and mandatory control blocks Done. |
| CDS-ACC-015 | Pint reformats many files with a logic change. | Formatting/config change is separated where needed for reliable review. |
| CDS-ACC-016 | Shared component fetches tenant data and contains application decisions. | Rejected as a Tier 1/2 component; logic remains in the source-owned Tier 3 composition/contracts. |
| CDS-ACC-017 | Feature imports another module's internal store because both use React. | Architecture check fails; an approved public client/API contract or shared-platform extraction is required. |
| CDS-ACC-018 | Architecture test passes only because files use expected directories. | Insufficient; deliberate forbidden imports/table access/tier direction must fail the gate. |
| CDS-ACC-019 | Generator creates a repository/service interface for every model without a proven need. | Generator/template is rejected or revised; scaffolding follows approved boundaries and use-case evidence. |
| CDS-ACC-020 | Controller calls an Eloquent model to create a rental application. | Architecture check fails; a named application service and aggregate repository port are required. |
| CDS-ACC-021 | Application service calls `Model::query()` or `save()` during a command. | Architecture check fails; persistence moves behind the owning aggregate repository port. |
| CDS-ACC-022 | Generic base repository exposes arbitrary CRUD/filter methods to all modules. | Rejected; replace with aggregate-domain commands and purpose-specific read ports. |
| CDS-ACC-023 | Repository returns an Eloquent builder for a controller to finish filtering. | Rejected; the owner exposes a bounded typed query contract and DTO/projection. |
| CDS-ACC-024 | Dashboard rebuilds multiple write aggregates and triggers N+1 queries. | Dedicated read handler/repository supplies a bounded projection with representative plan evidence. |
| CDS-ACC-025 | Model observer records a row update but misses the denied attempt and business reason. | Audit coverage fails; semantic evidence originates at the protected application decision boundary. |
| CDS-ACC-026 | Transaction rolls back after an audit package records success independently. | Release test fails; business and required audit/event outcome must be atomic or reconciled without false success. |
| CDS-ACC-027 | Aggregate persists an internal child model without a separate repository. | Accepted when the aggregate repository preserves ownership/invariants and no separate persistence boundary is justified. |

## Decisions

**Approved product decision — PD-CDS-001:** PRMS selects PSR-12-compatible Laravel Pint formatting, PHP strict types, TypeScript strict checking and React purity/Hook rules as core source baselines. Approved by the Delegated Product Owner on 2026-09-08.

**Approved product decision — PD-CDS-002:** PRMS domain/module/data/security authority overrides permissive framework conventions. Approved by the Delegated Product Owner on 2026-09-08.

**Approved product decision — PD-CDS-003:** Exact static-analysis/lint/test packages and thresholds require pinned Gate 4 tooling evidence. Approved by the Delegated Product Owner on 2026-09-08.

**Approved product decision — PD-CDS-004:** PRMS mandates application services and aggregate repository ports for state-changing use cases, and named query handlers with purpose-specific read ports for non-trivial reads. A repository is not generated per Eloquent model, generic base repositories are prohibited, and protected audit intent originates at the application decision boundary. Approved by the Delegated Product Owner on 2026-09-08 through the architecture decision recorded in `docs/03-architecture-and-data.md`.

## Assumptions, risks and open questions

| Type and ID | Statement | Owner | Resolution |
|---|---|---|---|
| Assumption ASM-CDS-001 | Selected current tools support PHP 8.5, Laravel 13, React 19.2 and TypeScript 6.x. | Technology Owner | Compatibility spike and lockfile before Gate 4. |
| Risk RSK-CDS-001 | Standards become stylistic ceremony while ownership/security issues pass. | Architecture/Security Owners | Prioritise fitness, type, contract and negative checks. |
| Risk RSK-CDS-002 | Strict baseline is weakened through accumulating suppressions. | Engineering Owner | Suppression register/budget and review. |
| Risk RSK-CDS-003 | Services and repositories become forwarding ceremony without aggregate/use-case meaning. | Architecture Owner | Domain-language contracts, representative slice review and generic CRUD prohibition. |
| Risk RSK-CDS-004 | Optimised read adapters bypass customer, permission or audit scope. | Security/Data Owners | Typed authority context, RLS, negative isolation and sensitive-access audit tests. |
| Open question OQ-CDS-001 | Which PHP static analyser, JS linter/formatter, architecture-test and SQL tools pass evaluation? | Technology Owner | CI/CD/dependency decisions before Gate 4. |

## Evidence and authoritative sources

External sources support only their stated language/framework behaviour:

- [PHP-FIG PSR-12](https://www.php-fig.org/psr/psr-12/) — shared PHP formatting baseline.
- [Laravel contribution coding style](https://laravel.com/docs/contributions) and [Laravel Pint](https://laravel.com/docs/pint) — Laravel style/tool direction.
- [Rules of React](https://react.dev/reference/rules), [Rules of Hooks](https://react.dev/reference/eslint-plugin-react-hooks/lints/rules-of-hooks) and [Using TypeScript](https://react.dev/learn/typescript) — purity, Hook and React typing direction.
- [TypeScript strict option](https://www.typescriptlang.org/tsconfig/strict) and [TypeScript compiler options](https://www.typescriptlang.org/docs/handbook/compiler-options.html) — strict checking semantics.
- [PostgreSQL lexical structure](https://www.postgresql.org/docs/current/sql-syntax-lexical.html) and [schemas](https://www.postgresql.org/docs/current/ddl-schemas.html) — identifiers and schema resolution.

## Evidence limitations

No application source, formatter/linter/static-analysis configuration, architecture test, migration, generated contract, suppression register or CI result exists. Exact packages and thresholds remain Gate 4 decisions. Approval is a coding contract, not conformance evidence.

## Approval history

| Version | Date | Approver | Decision | Evidence |
|---|---|---|---|---|
| 1.0.0 | 2026-09-08 | Delegated Product Owner (Codex) | Restored the full PRMS coding standards baseline during repository consolidation: PSR-12/Pint, PHP strict types, TypeScript strict, React purity and tiered component ownership, application-service/aggregate-repository commands, dedicated read ports, PostgreSQL/RLS, error/audit, testing, generated-code and Gate 4 enforcement rules, with the stack version references updated to the current Laravel/React/TypeScript baseline | Original approved coding standards baseline; PRMS architecture and delivery baselines; repository consolidation; stale-term and link scan |

## Change log

| Version | Date | Change | Author |
|---|---|---|---|
| 1.0.0 | 2026-09-08 | Restored the full coding standards baseline into the consolidated documentation set; converted legacy product references to PRMS and updated stack/version references | Documentation Steward |