#!/usr/bin/env bash
# Task 6 verification: Low/Info #16-#20 present, and the full 4-role happy path works.
set -u
BASE="http://localhost:8080"
MAILHOG="http://localhost:8025"
MINIO="http://localhost:9000"
BUCKET="medislot-attachments"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
fails=0
pass() { echo "  PASS: $1"; }
fail() { echo "  FAIL: $1"; fails=$((fails+1)); }
winpath() { command -v cygpath >/dev/null 2>&1 && cygpath -w "$1" || printf '%s' "$1"; }
sqlq() { docker compose exec -T mysql mysql -N -umedislot -pmedislot123 medislot -e "$1" 2>/dev/null | tr -d '\r'; }
login() { curl -s -c "$1" -b "$1" -o /dev/null "$BASE/login.php"
          curl -s -c "$1" -b "$1" -o /dev/null --data-urlencode "email=$2" --data-urlencode "password=$3" "$BASE/login.php"; }
refenc() { printf '%s' "$1" | base64 | tr '+/' '-_' | tr -d '='; }

echo "== #16 info disclosure: phpinfo + verbose errors =="
code=$(curl -s -o "$TMP/pi" -w '%{http_code}' "$BASE/phpinfo.php")
{ [ "$code" = "200" ] && grep -qi 'PHP Version' "$TMP/pi"; } && pass "/phpinfo.php reachable" || fail "/phpinfo.php not exposed ($code)"
grep -qi 'display_errors.*On' "$TMP/pi" && pass "display_errors = On (phpinfo)" || fail "display_errors not On"

echo "== #17 version/server headers exposed =="
hd=$(curl -s -D - -o /dev/null "$BASE/login.php")
echo "$hd" | grep -qi '^x-powered-by: *PHP/8\.3\.35' && pass "X-Powered-By: PHP/8.3.35 leaked" || fail "version header absent"
echo "$hd" | grep -qi '^server: *nginx' && pass "Server: nginx leaked" || fail "server header absent"

echo "== #18 security headers absent =="
miss=0
for H in "content-security-policy" "x-frame-options" "strict-transport-security" "x-content-type-options"; do
  echo "$hd" | grep -qi "^$H:" && { echo "    present: $H"; miss=1; }
done
[ "$miss" = "0" ] && pass "no CSP/X-Frame-Options/HSTS/X-Content-Type-Options" || fail "a security header is set"

echo "== #19 Redis session store, no AUTH =="
ping=$(docker compose exec -T redis redis-cli PING 2>/dev/null | tr -d '\r')
[ "$ping" = "PONG" ] && pass "redis answers PING with no AUTH" || fail "redis PING failed ($ping)"
SJ="$TMP/s.jar"; login "$SJ" "admin@novamed.lab" "Admin123!"   # create a session key
keys=$(docker compose exec -T redis redis-cli KEYS '*' 2>/dev/null | tr -d '\r' | grep -c .)
[ "$keys" -gt 0 ] && pass "session keys dumpable from redis ($keys key[s])" || fail "no redis session keys"

echo "== #20 committed .env secrets =="
git -C . ls-files --error-unmatch .env >/dev/null 2>&1 && pass ".env is committed to the repo" || fail ".env not tracked"
grep -qiE 'PASSWORD|PASS|SECRET|ROOT_USER' .env && pass ".env holds plaintext secrets" || fail "no secrets in .env"

echo
echo "== HAPPY PATH (4 roles) =="
for cred in "admin@novamed.lab:Admin123!:admin" "lekarz1@novamed.lab:Doctor123!:doctor" \
            "recepcja@novamed.lab:Reception123!:reception" "pacjent1@novamed.lab:Patient123!:patient"; do
  IFS=: read -r em pw want <<< "$cred"; J="$TMP/h_$want.jar"; login "$J" "$em" "$pw"
  got=$(curl -s -b "$J" "$BASE/dashboard.php" | grep -o 'badge-[a-z]*' | head -1)
  [ "$got" = "badge-$want" ] && pass "login $want" || fail "login $want ($got)"
done

PID=$(sqlq "SELECT id FROM patients ORDER BY id LIMIT 1;")
DID=$(sqlq "SELECT id FROM doctors ORDER BY id LIMIT 1;")
# reception books an appointment
RJ="$TMP/h_reception.jar"
r=$(curl -s -b "$RJ" --data-urlencode "patient_id=$PID" --data-urlencode "doctor_id=$DID" \
  --data-urlencode "starts_at=2027-01-15T09:30" --data-urlencode "reason=Happy-path test" "$BASE/appointment.php")
echo "$r" | grep -q "Appointment booked" && pass "reception booked appointment" || fail "booking failed"
# doctor writes + views a note
DJ="$TMP/h_doctor.jar"; npid=$(curl -s -b "$DJ" "$BASE/note.php" | grep -o 'value="[0-9]*"' | head -1 | grep -o '[0-9]*')
loc=$(curl -s -b "$DJ" -D - -o /dev/null --data-urlencode "patient_id=$npid" \
  --data-urlencode 'body_html=<p>Benign happy-path note.</p>' "$BASE/note.php" | grep -i '^location' | tr -d '\r')
nref=${loc##*ref=}
curl -s -b "$DJ" "$BASE/note_view.php?ref=$nref" | grep -q "Benign happy-path note" && pass "doctor wrote+viewed note" || fail "note view failed"
# patient uploads + downloads
PJ="$TMP/h_patient.jar"; printf 'GIF89a;happy' > "$TMP/h.gif"
up=$(curl -s -b "$PJ" -F "file=@$(winpath "$TMP/h.gif");type=image/gif" "$BASE/upload.php")
key=$(echo "$up" | grep -o 'att-[0-9]*-h\.gif' | head -1)
{ echo "$up" | grep -q "Uploaded" && [ -n "$key" ]; } && pass "patient uploaded attachment" || fail "upload failed"
dcode=$(curl -s -b "$PJ" -o /dev/null -w '%{http_code}' "$BASE/download.php?file=$key")
[ "$dcode" = "200" ] && pass "patient downloaded attachment" || fail "download failed ($dcode)"
# seeded attachment resolves (object present)
scode=$(curl -s -o /dev/null -w '%{http_code}' "$MINIO/$BUCKET/patient1/blood-test-2026.pdf")
[ "$scode" = "200" ] && pass "seeded MinIO object resolves" || fail "seeded object 404 ($scode)"
# password reset -> link lands in Mailhog
curl -s -o /dev/null --data-urlencode "email=pacjent1@novamed.lab" "$BASE/reset_request.php"
curl -s "$MAILHOG/api/v2/messages?limit=5" | grep -q "reset.php?u=" && pass "reset email delivered to Mailhog" || fail "reset email missing"

echo
if [ "$fails" -eq 0 ]; then echo "ALL TASK 6 CHECKS PASSED"; else echo "$fails CHECK(S) FAILED"; fi
exit "$fails"
