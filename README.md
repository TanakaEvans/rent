# PRMS Documentation

Property Rental Marketplace System (PRMS) is a Zimbabwe-first, modular platform that combines a direct property
rental marketplace with a property-management ERP. It removes the letting-agent middleman and its commission: owners
list and manage their properties directly, tenants search, enquire, view, apply and pay rent free of charge, and the
platform is funded by affordable owner subscriptions (Free, Basic, Professional, Business) and optional paid services.

Stack: Laravel modular monolith + React + TypeScript via Inertia.js, PostgreSQL, Redis/Valkey, S3-compatible storage,
containerised Linux.

## Documentation

The full documentation baseline is consolidated into six documents under `docs/`:

| Document | Covers |
|---|---|
| [Vision and Business](docs/01-vision-and-business.md) | Strategy, governance, business model, pricing/packaging, the 20-module catalogue and dependencies |
| [Requirements and Modules](docs/02-requirements-and-modules.md) | Domain model, business rules, MVP definition, functional/non-functional requirements, per-module specs, UX/design |
| [Architecture and Data](docs/03-architecture-and-data.md) | Architecture vision and approved decisions, modular/bounded contexts, integration, event catalogue, API standards, data models, dictionaries, ownership/classification |
| [Security and Compliance](docs/04-security-and-compliance.md) | Security requirements, IAM, threat model, privacy/consent, compliance matrix, continuity/DR/incident response |
| [Delivery, Quality and Operations](docs/05-delivery-quality-operations.md) | Delivery plan, CI/CD, test strategy/plans, support/SLOs/incidents, customer adoption, KPIs/improvement |
| [Coding Standards](docs/06-coding-standards.md) | PHP/Laravel, TypeScript/React, PostgreSQL rules, command/query architecture, tests, errors/audit, generated code, Gate 4 enforcement |

Doc 05's `1.6 Coding standards` section summarises the rules; `docs/06-coding-standards.md` is the full standard and is authoritative.

## Current state

- MVP (`PRMS-MVP-1`) = UAM, PRP, MKT, FAV, ENQ, VEW, APL, VER, SUB, ADM. MVP outcome: list - verify - enquire -
  view - apply. LSE, PMT, MTN, SVC are defined but Phase 2 candidates.
- All 20 modules have approved definitions. Gate 4 is formally **NO-GO**: development remains prohibited until
  implementation evidence exists. Operational guides in `05` are pending tested system behaviour.

## Repository layout

```
README.md            This index
CONTRIBUTING.md      How to work on this repository
AGENTS.md            Working instructions for AI agents
docs/                Consolidated documentation (6 documents)
```