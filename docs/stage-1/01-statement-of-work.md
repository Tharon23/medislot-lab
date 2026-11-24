# Statement of Work (SoW)  
## Test penetracyjny środowiska MediSlot dla NovaMed Sp. z o.o.

| Pole | Wartość |
|------|---------|
| Wersja dokumentu | 1.0 |
| Data | 24.10.2026 |
| Status | Roboczy — do prezentacji etapu I |
| Klient | NovaMed Sp. z o.o. |
| Wykonawca | Zespół projektowy SecureTrio (Kamil, Bartek, Kuba) |
| Przedmiot | Test penetracyjny aplikacji MediSlot oraz powiązanego stacku labowego |

---

## 1. Strony porozumienia

### 1.1. Zamawiający (Klient)

| | |
|--|--|
| Nazwa | **NovaMed Sp. z o.o.** |
| Opis | Fikcyjna przychodnia wielospecjalistyczna korzystająca z systemu MediSlot do rejestracji wizyt, prowadzenia syntetycznej dokumentacji pacjentów oraz zarządzania personelem |
| Przedstawiciel ds. bezpieczeństwa / kontakt RoE | Bartek (rola: *Client Security Contact / RoE*) |
| Przedstawiciel biznesowy | Kuba (rola: *Product Owner po stronie klienta*) |

### 1.2. Wykonawca (Testerzy)

| | |
|--|--|
| Nazwa | **SecureTrio** — zespół studencki wykonujący symulowany engagement pentestowy |
| Skład | Kamil, Bartek, Kuba |
| Kierownik techniczny testów | Kamil |
| Forma | Projekt zaliczeniowy z przedmiotu *Testy penetracyjne* (AGH) |

### 1.3. Charakter engagementu

Strony ustalają, że:

1. Środowisko jest **celowo przygotowanym laboratorium** (Docker Compose), a nie produkcją NovaMed.  
2. Dane w systemie są **wyłącznie syntetyczne**.  
3. Część wiedzy o architekturze i podatnościach jest współdzielona (zespół buduje i testuje lab) — w SoW formalnie rozdzielono jednak **wiedzę testera** i **wiedzę obrony (SOC)**, zgodnie z wymaganiami przedmiotu.  
4. Celem jest metodyczne przeprowadzenie testu (plan → discovery → atak → dowody → raport), a nie destabilizacja infrastruktury spoza labu.

---

## 2. Przedmiot i cele testu

### 2.1. Cele biznesowe klienta

- Uzyskanie wiedzy o stanie bezpieczeństwa stacku MediSlot przed ewentualnym wdrożeniem.  
- Identyfikacja podatności umożliwiających nieautoryzowany dostęp do danych medycznych (syntetycznych), eskalację uprawnień oraz przejęcie serwera aplikacji (RCE).  
- Otrzymanie priorytetyzowanej listy findingów z oceną ryzyka i rekomendacjami napraw.

### 2.2. Cele techniczne engagementu

- Przeprowadzenie testu w modelu zbliżonym do **grey-box** (dostęp do dokumentacji architektury i kont testowych; bez pełnego dumpa kodu po stronie „oficjalnej wiedzy testera” w narracji raportu).  
- Pokrycie co najmniej następujących warstw: system operacyjny kontenerów, reverse proxy, aplikacja PHP, baza danych, magazyn obiektów, poczta labowa, magazyn sesji.  
- Użycie narzędzi ofensywnych oraz analizatorów (m.in. skanery zależności/obrazów, audyt konfiguracji OS).  
- Udokumentowanie **co najmniej jednej podatności z roku 2026** (plan: celowo podatna wersja PHP) oraz scenariusza **krytycznego ustalenia** z kontaktem RoE.

---

## 3. Wiedza testera (per system / usługa)

Poniższa tabela opisuje **zakładaną wiedzę strony atakującej** na start engagementu (narracja SoW).

| System / usługa | Wiedza testera na start |
|-----------------|-------------------------|
| **MediSlot (PHP app)** | URL labu na `localhost`, opis ról (pacjent, lekarz, recepcja, admin), happy path biznesowy, konta testowe z hasłami labowymi, ogólny opis endpointów z dokumentacji API/UI (bez wskazania lokalizacji luk) |
| **Nginx** | Fakt użycia jako reverse proxy; brak pełnej konfiguracji `nginx.conf` na start |
| **PHP-FPM** | Informacja, że runtime to PHP; **wersja dokładna nie jest ujawniana** w briefie — discovery przez fingerprinting / skan obrazu |
| **MySQL** | Fakt istnienia RDBMS; brak credentials produkcyjnych w briefie testera (credentials labowe mogą być w osobnym *Test Data Pack* po stronie klienta na życzenie grey-box) |
| **MinIO** | Wiedza, że załączniki pacjentów są w object storage zgodnym z S3; endpoint labowy do discovery |
| **Mailhog** | Wiedza, że poczta nie wychodzi na świat — UI Mailhog dostępny w labie (przydatne przy resetcie hasła) |
| **Redis** | Wiedza o użyciu do sesji; brak wymogu znajomości `REDIS_PASSWORD` na start |
| **Sieć Docker** | Nazwa projektu Compose; zakaz ataków na hosta (patrz wyłączenia) |

**Kontakty testowe (lab):** zestaw kont dla 4 ról zostanie dostarczony w *Appendix A — Test Accounts* przed startem fazy praktycznej (etap II).

---

## 4. Wiedza obrony / SOC (per system / usługa)

Poniższa tabela opisuje **zakładaną widoczność i kontrole po stronie klienta** (narracja SoW).

| System / usługa | Widoczność SOC / obrony |
|-----------------|-------------------------|
| **MediSlot** | Logi aplikacji (logowania, błędy, upload); możliwość tymczasowego wyłączenia konta; brak WAF klasy produkcyjnej w labie |
| **Nginx** | Access/error logi proxy; możliwość ograniczenia rate (jeśli skonfigurowane) |
| **PHP-FPM / obraz** | Możliwość skanowania obrazów (Trivy) w pipeline „klienta”; alert przy krytycznym CVE runtime — w labie: procedura eskalacji do RoE |
| **MySQL** | Logi połączeń (jeśli włączone); brak ekspozycji portu DB na host poza sieć Compose (docelowa konfiguracja) |
| **MinIO** | Audit dostępu do bucketów (jeśli włączony); polityka prywatności obiektów |
| **Mailhog** | Podgląd wiadomości w UI labowym (nie jest to SIEM) |
| **Redis** | Brak zaawansowanego monitoringu w labie; założenie: sesje niewidoczne dla klasycznego IDS |
| **Procedura RoE** | Przy findingach Critical (zwłaszcza RCE / masowy wyciek danych kart) — natychmiastowy kontakt z przedstawicielem RoE, wstrzymanie dalszej eksploatacji do potwierdzenia |

---

## 5. Zakres (Scope) i wyłączenia

### 5.1. In scope

1. Aplikacja webowa **MediSlot** (UI + ewentualne API HTTP).  
2. Kontenery i usługi Docker Compose:  
   - Debian (baza obrazów / warstwa OS kontenerów)  
   - Nginx  
   - PHP-FPM (MediSlot)  
   - MySQL  
   - MinIO  
   - Mailhog  
   - Redis  
3. Ruch sieciowy **wyłącznie** w sieci labowej Compose oraz na portach opublikowanych na `localhost` maszyny testera.  
4. Konfiguracja ww. usług w kontekście projektu MediSlot.  
5. Procesy: rejestracja/logowanie, zarządzanie wizytami, karty/notatki, upload załączników, reset hasła, panel admina/recepcji.  
6. Identyfikacja i (w granicach RoE) eksploatacja podatności, w tym łańcuchów prowadzących do RCE oraz błędów kontroli dostępu między rolami.

### 5.2. Out of scope (wyłączenia)

1. System operacyjny **hosta** testera/klienta (Windows), hypervisor, Docker Desktop / dokerd jako cel ataku.  
2. Inne kontenery, projekty i usługi spoza pliku Compose MediSlot.  
3. Sieci poza labem (LAN domowa/AGH, Internet) — **zakaz** skanowania i eksploatacji.  
4. Ataki socjotechniczne na osoby spoza zespołu projektowego.  
5. Użycie lub pozyskiwanie **prawdziwych** danych medycznych / PII.  
6. Ataki typu DoS/DDoS mające na celu wyczerpanie CPU/RAM/dysku **hosta** lub trwałe unieruchomienie środowiska bez wartości dowodowej (krótkie, kontrolowane testy dostępności — tylko za zgodą RoE).  
7. Modyfikacja ocen / systemów uczelnianych oraz infrastruktury AGH niezwiązanej z labem.  
8. Publikacja exploita 0-day poza raportem projektowym bez uzgodnienia z prowadzącym.

### 5.3. Środowisko

| Parametr | Ustalenie |
|----------|-----------|
| Typ | Lab lokalny (Docker Compose) |
| Dostęp zewnętrzny | Brak — aplikacja niewystawiona publicznie |
| Dane | Syntetyczne |
| Okno testów (etap II) | Do uzgodnienia przed zajęciami stacjonarnymi etapu II (szacunek: 2–3 tygodnie kalendarzowe) |

---

## 6. Rules of Engagement (RoE)

### 6.1. Zasady ogólne

1. Testy wyłącznie w zakresie z §5.  
2. Każde działanie destrukcyjne wykraczające poza niezbędny dowód (np. drop bazy, wipe bucketów MinIO) wymaga **uprzedniej zgody RoE**.  
3. Hasła i sekrety labowe nie są przenoszone poza zespół i dokumentację projektową.  
4. Dowody (screenshoty, request/response, logi narzędzi) przechowuje się w repozytorium / katalogu dowodów projektu.

### 6.2. Klasyfikacja ustaleń i eskalacja

| Severity | Przykład | Działanie |
|----------|----------|-----------|
| **Critical** | RCE, pełne przejęcie kontenera app, masowy wyciek kart pacjentów | **Natychmiastowy kontakt RoE**, wstrzymanie dalszej eksploatacji tego wektora do potwierdzenia, spisanie notatki z datą/godziną |
| **High** | IDOR na dane wielu pacjentów, przejęcie konta admina | Kontakt RoE w ciągu 24 h labowych; kontynuacja innych torów dozwolona |
| **Medium / Low / Info** | XSS stored, braki nagłówków, findingi skanera bez exploita | Bez bieżącej eskalacji; zbiorczo w raporcie |

### 6.3. Krytyczne ustalenie w trakcie testu (wymóg przedmiotu)

Strony ustalają procedurę ćwiczeniową:

1. Tester dokumentuje timing, wektor, wpływ.  
2. Kontaktuje się z osobą RoE (Bartek) kanałem uzgodnionym (Teams / wiadomość w repo).  
3. RoE potwierdza odbiór i ewentualnie wydaje dyspozycję (kontynuuj / wstrzymaj / zbierz dodatkowe logi SOC).  
4. Zdarzenie trafia do raportu etapu II jako osobna sekcja „Critical finding — RoE contact”.

### 6.4. Konta i dane testowe

- Dozwolone jest tworzenie dodatkowych kont w ramach funkcji aplikacji.  
- Zabronione jest celowe niszczenie kont innych członków zespołu bez uzgodnienia.  
- Przy eksploatacji RCE dozwolone jest utworzenie **minimalnego** dowodu (np. plik marker / komenda `id`), bez cryptominingu i bez lateral movement na hosta.

---

## 7. Warunki re-testów

1. Po dostarczeniu przez „klienta” poprawek (patch / nowa wersja Compose) Wykonawca przeprowadza **re-test** findingów oznaczonych jako Critical oraz High.  
2. Re-test Medium — wybiórczo (uzgodnienie listy max. 5 szt.).  
3. Okno re-testu: **do 7 dni** od zgłoszenia gotowości środowiska przez klienta (w kalendarzu projektu).  
4. Wynik re-testu: *Fixed* / *Partially fixed* / *Not fixed* / *Accept risk* — z krótkim dowodem.  
5. Low/Info nie podlegają obowiązkowemu re-testowi, o ile nie wynikają z residual risk po Critical.

---

## 8. Metodyka i narzędzia (ramowo)

- Przybliżona metodyka: elementy **PTES** / **OWASP Testing Guide** + mapowanie na **STRIDE** z threat modelu.  
- Narzędzia (lista niewyczerpująca, etap II): przeglądarka + proxy (np. Burp / ZAP), klasyczne narzędzia HTTP, sqlmap (w razie potrzeby), Trivy, Lynis, ewentualnie Nuclei / własne skrypty.  
- Każdy finding w raporcie końcowym: opis, reprodukcja, dowód, CWE, CVSS, odniesienie CVE (jeśli dotyczy), remedacja.

---

## 9. Dostarczalne (Deliverables)

| Etap | Dostarczalne |
|------|----------------|
| I | Niniejszy SoW + Threat Model (dokument + prezentacja ~10 min) |
| II | Dokumentacja przebiegu testów, dowody, opis narzędzi, notatka RoE |
| III | Raport końcowy (SoW+TM+praktyka, streszczenie zarządcze, metryki, mitigacje, podział prac) |

---

## 10. Harmonogram (orientacyjny)

| Faza | Orientacja |
|------|------------|
| Etap I — dokumenty i prezentacja | do zajęć stacjonarnych etapu I (wg grupy projektowej) |
| Budowa labu MediSlot | równolegle / tuż po akceptacji SoW |
| Etap II — testy | okno uzgodnione przed zajęciami etapu II |
| Etap III — raport | przed zajęciami etapu III |

---

## 11. Podział prac (fikcyjny — wymóg raportu końcowego)

Podział procentowy do wykorzystania w dokumentacji zaliczeniowej (nie odzwierciedla faktycznego wkładu narzędziowego):

| Osoba | SoW | Threat Model | Etap II (praktyka)* | Raport końcowy* |
|-------|-----|--------------|---------------------|-----------------|
| Kamil | 40% | 35% | 35% | 40% |
| Bartek | 30% | 35% | 35% | 30% |
| Kuba | 30% | 30% | 30% | 30% |

\*Wstępny podział; uszczegółowienie w etapie III.

**Elementy odpowiedzialności (narracja):**

- **Kamil:** redakcja SoW, RoE, koordynacja techniczna stacku PHP/Nginx.  
- **Bartek:** kontakt RoE, sekcja wiedzy SOC, udział w STRIDE (spoofing/tampering).  
- **Kuba:** zakres biznesowy MediSlot, aktywa/aktorzy, DFD (przepływy wizyt/dokumentów).

---

## 12. Akceptacja

| Strona | Imię | Rola | Data | Podpis (projektowy) |
|--------|------|------|------|---------------------|
| Klient | Bartek | RoE / Security Contact | ________ | ________ |
| Klient | Kuba | Product Owner | ________ | ________ |
| Wykonawca | Kamil | Lead Tester | ________ | ________ |

---

## Appendix A — Test Accounts (szablon na etap II)

| Rola | Login | Hasło labowe | Uwagi |
|------|-------|--------------|-------|
| Pacjent | `pacjent1@novamed.lab` | *(uzupełnić przy starcie labu)* | Własne wizyty |
| Lekarz | `lekarz1@novamed.lab` | *(uzupełnić)* | Pacjenci przypisani |
| Recepcja | `recepcja@novamed.lab` | *(uzupełnić)* | Terminarz |
| Admin | `admin@novamed.lab` | *(uzupełnić)* | Pełny dostęp |

## Appendix B — Porty labowe (szablon)

| Usługa | Port hosta (plan) | Uwagi |
|--------|-------------------|--------|
| MediSlot via Nginx | 8080 | HTTP lab |
| Mailhog UI | 8025 | Podgląd maili |
| MinIO API / Console | 9000 / 9001 | Object storage |
| MySQL | *(opcjonalnie niepublikowany)* | Preferowane tylko w sieci Compose |
| Redis | *(opcjonalnie niepublikowany)* | j.w. |

---

*Dokument stanowi treść merytoryczną etapu I (Statement of Work) zgodnie z wymaganiami zajęć projektowych.*
