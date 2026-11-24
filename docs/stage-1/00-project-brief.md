# MediSlot / NovaMed — Project Brief (locked decisions)

## Course context

- Course: Testy penetracyjne (AGH, Cybersecurity, year 3)
- Team: Kamil, Bartek, Kuba
- Client (fictional): **NovaMed Sp. z o.o.**
- Application under test: **MediSlot** — clinic appointment and patient-record portal
- Engagement model: simulated pen-test (build deliberately vulnerable lab, then test it)

## Delivery stages (lecturer)

1. **Stage I (theory):** Statement of Work + Threat Modeling  
2. **Stage II (practice):** discovery → attack → evidence (≥5 technologies, tools, analyzers, ≥1 vulnerability from 2026, critical finding + RoE contact)  
3. **Stage III:** final report (metrics, executive summary, CVE/CWE/CVSS, remediations, work split)

## Technical decisions

| Decision | Choice |
|----------|--------|
| App language | Plain PHP (no framework) |
| Deployment | Docker Compose, localhost only |
| Stack | Debian, Nginx, PHP-FPM (intentionally vulnerable PHP for CVE 2026), MySQL, MinIO, Mailhog, Redis |
| Roles | Patient, Doctor, Reception, Admin |
| Finding count (target) | ~15–20 |
| Severity mix | Few Critical/High, majority Medium, few Low/Info |
| RCE | Multiple vectors allowed; **primary** = latest relevant PHP CVE; secondary e.g. upload chain |
| Data | Synthetic only; no real PII/PHI |

## Scope summary

- **In:** MediSlot Compose services and lab ports on localhost  
- **Out:** Host OS, Docker daemon as target, non-lab networks, real users, host resource exhaustion DoS

## Document map (Stage I)

| File | Content |
|------|---------|
| `01-statement-of-work.md` | SoW / RoE |
| `02-threat-model.md` | Architecture, DFD, STRIDE, risk, mitigations, conclusions |
