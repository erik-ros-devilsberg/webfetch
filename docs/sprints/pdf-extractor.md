---
sprint: PDF Extractor
stories:
  - 14-pdf-extraction
status: planned
created: 2026-06-11
---

## Goal

Read `application/pdf` into the same readable-content JSON webfetch produces for
HTML, behind the Sprint A dispatch seam. PDFs are a large slice of real agent
web traffic (papers, government docs, reports) that webfetch rejects today, so
this is what makes the library actually useful for the agent use case. The
extractor must be safe by construction — a malicious PDF can never crash the
never-throw facade — and honest about quality — scanned/empty PDFs return a
typed error, not silent empty content.

## Acceptance Criteria

- [ ] `application/pdf` is accepted through the fetch gate (`StaticFetcher`, and
      `ChromeFetcher` as appropriate) to extraction instead of being rejected.
- [ ] A `PdfExtractor` (behind the Sprint A interface) parses PDF bytes into
      `content_markdown`; `title` and `byline` come from PDF metadata when
      present and are empty when absent (no fabricated bylines); output carries
      `source_type: "pdf"`.
- [ ] A new `ExtractionStrategy` value records PDF extraction.
- [ ] PDF parsing is isolated so a malformed/malicious PDF (OOM, infinite loop,
      decompression bomb) cannot crash or hang the webfetch process: enforced by
      a pre-parse size cap, a wall-clock timeout, and a memory bound. Proven by
      a hostile-fixture test that returns a typed error within the timeout
      instead of taking the process down.
- [ ] A text-empty / scanned (image-only) PDF returns a typed
      `empty_extraction` error, never silent empty content.
- [ ] PDF fixtures are added to the corpus, scored with the Phase 0 rubric
      against a reader-mode reference, with a PDF-appropriate usable threshold
      set and gated in CI. Fixtures are redistributable and recorded in
      `tests/corpus/ATTRIBUTION.md`.
- [ ] The default PDF path requires no system binary — PDF works on a bare
      `composer install`. No live network in tests (committed PDF bytes only).
- [ ] `composer check` and `composer audit` pass.

## Tasks

- [ ] Write tests: PDF → markdown happy path; metadata-derived title/byline;
      scanned/empty PDF → typed `empty_extraction`; oversized PDF rejected
      pre-parse; hostile PDF (decompression bomb / xref loop) does not crash and
      returns a typed error within the timeout; `source_type: "pdf"` validates
      against the schema; corpus scoring of PDF fixtures.
- [ ] Implement `PdfExtractor` using `smalot/pdfparser`, run inside an isolation
      boundary (short-lived child PHP process with `memory_limit` + wall-clock
      timeout) with a pre-parse size cap.
- [ ] Wire `application/pdf` into the content-type dispatcher; relax the
      `isHtml` gate in `StaticFetcher` accordingly.
- [ ] Add PDF corpus fixtures + threshold; update CHANGELOG and README.

## Risks and Open Questions

- **PDF engine decision (default): `smalot/pdfparser`, process-isolated.**
  Rationale: under webfetch's never-throw facade, "safest" means *process
  isolation*, not fewest CVEs. smalot has zero published CVEs and needs no
  system binary, but its uncatchable fatal OOM / infinite loops on hostile
  input would otherwise crash the facade — so it runs in a subprocess with
  memory + time bounds. This keeps PDF working on a bare composer install while
  neutralizing the crash risk.
- **poppler/pdftotext (via spatie/pdf-to-text) kept as an optional faster
  backend** behind `PdfExtractor`, for deployments that have the binary
  (naturally subprocess-isolated, better layout/table fidelity, but live
  memory-corruption CVEs and a non-composer binary dependency). Backlog, not
  this sprint. Veto point: confirm the smalot default at the Sprint B review.
- **Licensing:** `smalot/pdfparser` is LGPL-3.0; webfetch is MIT. LGPL as a
  Composer (non-linked) dependency is acceptable — flag in licensing notes.
- **Maintenance:** smalot is on "limited maintenance" — acceptable given zero
  CVEs + isolation; the `PdfExtractor` interface makes a backend swap cheap.
- A decompression bomb can exceed a byte-size cap (small file, huge expansion),
  so the subprocess **memory bound** is the real mitigation, not the size cap
  alone. Both are required.
- Subprocess isolation adds per-PDF latency — acceptable; document it.
