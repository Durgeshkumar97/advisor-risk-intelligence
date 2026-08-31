# FIXES.md — Audit Fix Log

Tracks progress against `AUDIT.md` findings. One line per finding, appended as waves run.

## Wave 1 — Regulatory exposure

- F-01: FIXED — `ClientRiskProfile::comparisonMessage()` rewritten to state the gap only, no prescriptive language. Commit `8a1da95`.
- F-15: FIXED — `DashboardController` now reads `meta.next_action` instead of a level-keyed `match()` block; redundant "Recommendation" card removed, "Next Action" card relabelled "Observations" to match the PDF. Commit `8a1da95`.
