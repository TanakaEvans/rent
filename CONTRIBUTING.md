# Contributing to PRMS Documentation

## Principles

1. The five documents under `docs/` are the single source of truth. Edit them directly; do not reintroduce
   per-category files.
2. Use PRMS terminology and module codes (UAM, PRP, MKT, FAV, ENQ, VEW, APL, LSE, PMT, MTN, SVC, SUB, FAD, VER, NTF,
   LND, RBA, ADM, CMP, DOC). Never reintroduce DMS or driving-school terms.
3. Do not write documentation to make the repository look complete. Record only what is decided, evidence-based, or
   a labelled open question. Do not claim implementation, production operation, or measured results unless evidence exists.
4. Gate 4 for implementation is **NO-GO** until implementation evidence is produced and reviewed.

## Editions and approval

- Meaningful content changes should be reflected in the document version and its `## Approval history` table.
- Non-material corrections (spelling, formatting, links) may use a PATCH-level change without reapproval.
- Distinguish decisions, assumptions, risks, and open questions clearly in the text.

## Branch and commit style

Use conventional commit messages:

```text
docs(strategy): update pricing section
docs(architecture): record data retention change
```

Keep documentation changes separate from implementation work in branches.

## Legal content

Legal instruments (terms of use, agreements) are summaries only and require qualified legal review before commercial
use even after this repository approves them as documents.