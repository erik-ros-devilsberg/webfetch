# Sprint Status

Maintained by the agile plugin. One row per sprint — updated by `/agile:shape`, `/agile:execute`.

| Sprint | Slug | Status | Description |
|--------|------|--------|-------------|
| Spike — Readability Extraction Feasibility | spike-readability-extraction | done | Validate readability.php + \Dom\HTMLDocument on real pages; record go/no-go. |
| OSS Scaffolding | oss-scaffolding | done | Publishable package skeleton: composer.json, MIT, tooling, CI gates. |
| Static HTTP Fetcher | static-http-fetcher | done | Fetcher interface plus Guzzle implementation with typed failures. |
| HTML Parsing | html-parsing | done | FetchSuccess to \Dom\HTMLDocument with UTF-8 normalization and typed failures. |
| Readable Content Extraction | readable-content-extraction | done | Core feature: readability + fallback extraction to schema-validated JSON. |
| Error JSON Contract | error-json-contract | done | Error schema, ErrorCode enum, and the never-throwing Webfetch facade. |
| CLI Entry Point | cli-entry-point | done | bin/webfetch: URL in, contract JSON on stdout, meaningful exit codes. |
| Phagent WebFetchTool | phagent-webfetch-tool | done | web_fetch tool in phagent wrapping devilsberg/webfetch via path repo. |
| Chrome SPA Fetcher | chrome-spa-fetcher | done | Optional headless-Chrome Fetcher for JS-rendered pages, same contract. |
| Hardening — Safety and Etiquette | hardening-safety-etiquette | done | SSRF guard on by default; cache, rate-limit, robots decorators. |
| Hardening — Corpus Quality Gate | hardening-corpus-gate | done | Committed redistributable corpus scored against the spike rubric in CI. |
| v1.0.0 Release Prep | v1-release-prep | done | CHANGELOG, README completion, clean-clone proof, manual publish checklist. |
| Examples Directory | examples-directory | done | Four runnable, lint-gated example scripts plus README link. |
| Extraction Quality Fixes | extraction-quality-fixes | done | Clean code blocks, markdown tables, honest bylines — pinned by corpus. |
| Extractor Dispatch Seam | extractor-dispatch-seam | done | Content-type-dispatched extractor interface plus source_type schema field. |
| PDF Extractor | pdf-extractor | planned | Read application/pdf into readable JSON, process-isolated, corpus-scored. |
