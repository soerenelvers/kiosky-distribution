#!/bin/sh
# Installed per user. Only the bound HTTPS Kiosky server may provide code.
set -u
extract(){ /usr/bin/plutil -extract "$1" raw -o - "$2" 2>/dev/null; }
version_valid(){ printf '%s\n' "$1" | grep -Eq '^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$'; }
newer(){ version_valid "$1" && version_valid "$2" && awk -v a="$1" -v b="$2" 'BEGIN{split(a,x,".");split(b,y,".");for(i=1;i<=3;i++){if(x[i]+0>y[i]+0)exit 0;if(x[i]+0<y[i]+0)exit 1}exit 1}'; }
fetch(){
    endpoint=$1; destination=$2; payload=$3
    printf '%s\n' "$endpoint" | grep -Eq '^https://[^/?#@]+(/[^?#]*)?/kiosky-player-update/$|^http://(localhost|127\.0\.0\.1|\[::1\])(:[0-9]+)?(/[^?#]*)?/kiosky-player-update/$' || return 1
    address="${endpoint}?platform=macos-universal"; [ "$payload" = 1 ] && address="${address}&file=1"
    # Bound disk writes even on older system curl with a chunked response.
    code=$( (ulimit -f 2048; /usr/bin/curl --silent --fail --proto '=https,http' --connect-timeout 15 --max-time 90 --max-filesize 1048576 -o "$destination" -w '%{http_code}' "$address" 2>/dev/null) ) || return 1
    [ "$code" = 200 ]
}
check_update(){
    [ -f "$root/updates.disabled" ] && return 1
    if [ -f "$root/update.retry-after" ] && [ "$(date +%s)" -lt "$(cat "$root/update.retry-after")" ];then return 1;fi
    endpoint=$(extract endpoint "$config") || return 1
    version=$(extract version "$config") || return 1
    if [ -f "$root/update.version" ]; then saved=$(cat "$root/update.version"); if newer "$saved" "$version";then version=$saved;fi;fi
    fetch "$endpoint" "$root/update.manifest" 0 || return 1
    manifest="$root/update.manifest"
    [ "$(wc -c < "$manifest" | tr -d ' ')" -le 65536 ] || return 1
    [ "$(extract schema "$manifest")" = 1 ] && [ "$(extract product "$manifest")" = kiosky-player ] && [ "$(extract platform "$manifest")" = macos-universal ] || return 1
    next=$(extract version "$manifest") || return 1
    newer "$next" "$version" || return 1
    checksum=$(extract sha256 "$manifest") || return 1
    printf '%s\n' "$checksum" | grep -Eq '^[0-9a-f]{64}$' || return 1
    bytes=$(extract bytes "$manifest") || return 1
    printf '%s\n' "$bytes" | grep -Eq '^[1-9][0-9]{0,6}$' || return 1
    [ "$bytes" -le 1048576 ] || return 1
    fetch "$endpoint" "$root/update.candidate" 1 || return 1
    [ "$(wc -c < "$root/update.candidate" | tr -d ' ')" = "$bytes" ] || return 1
    actual=$(/usr/bin/shasum -a 256 "$root/update.candidate");actual=${actual%% *}
    [ "$actual" = "$checksum" ] && /bin/sh -n "$root/update.candidate" || return 1
    cp "$config" "$root/update.config" || return 1
    /usr/bin/plutil -replace version -string "$next" "$root/update.config" || return 1
    printf '%s' "$next" > "$root/update.pending"
}
# Stop only controllers from this user's managed Kiosky installation tree.
stop_installation(){
    old=$1
    [ -d "$old/running.lock" ] || return 0
    owner=$(cat "$old/running.lock/pid" 2>/dev/null || printf '0')
    case "$owner" in ''|*[!0-9]*)return 1;; esac
    [ "$owner" -gt 1 ] || return 1
    old_state=$(/bin/ps -p "$owner" -o stat= 2>/dev/null || :)
    case "$old_state" in ''|*Z*)rm -f "$old/running.lock/pid";rmdir "$old/running.lock" 2>/dev/null || :;return 0;; esac
    actual_uid=$(/bin/ps -p "$owner" -o uid= | tr -d ' ')
    actual_command=$(/bin/ps -p "$owner" -o args=)
    [ "$actual_uid" = "$(id -u)" ] && [ "$actual_command" = "/bin/sh $old/start.sh" ] || return 1
    managed=$(cat "$old/player.ready" 2>/dev/null || printf '0')
    kill -TERM "$owner" 2>/dev/null || return 1
    attempt=0
    while [ "$attempt" -lt 20 ];do
        state=$(/bin/ps -p "$managed" -o stat= 2>/dev/null || :)
        case "$state" in ''|*Z*)[ ! -d "$old/running.lock" ] && return 0;; esac
        sleep 1;attempt=$((attempt+1))
    done
    return 1
}
acquire_installation(){
    if ! mkdir "$installation_lock" 2>/dev/null;then
        owner=$(cat "$installation_lock/pid" 2>/dev/null || printf '0')
        case "$owner" in ''|*[!0-9]*)return 1;; esac
        if [ "$owner" -gt 1 ] && kill -0 "$owner" 2>/dev/null;then return 1;fi
        rm -f "$installation_lock/pid";rmdir "$installation_lock" && mkdir "$installation_lock" || return 1
    fi
    printf '%s' "$$" > "$installation_lock/pid"
}
release_installation(){ rm -f "$installation_lock/pid";rmdir "$installation_lock" 2>/dev/null || :; }
retire_other_installations(){
    for old in "$players"/*;do
        [ "$old" != "$root" ] && [ -d "$old" ] && [ ! -L "$old" ] || continue
        name=${old##*/};printf '%s' "$name" | grep -Eq '^[0-9a-f]{24}$' || continue
        [ -f "$old/start.sh" ] && [ -f "$old/config.json" ] || continue
        stop_installation "$old" || return 1
        legacy="$HOME/Library/LaunchAgents/de.kiosky.player.$name.plist"
        if [ -f "$legacy" ] && [ "$(extract ProgramArguments.0 "$legacy")" = /bin/sh ] && [ "$(extract ProgramArguments.1 "$legacy")" = "$old/start.sh" ];then
            /bin/launchctl bootout "gui/$(id -u)/de.kiosky.player.$name" >/dev/null 2>&1 || :
            rm -f "$legacy"
        fi
    done
    # Migrate this installation's legacy LaunchAgent as well.
    legacy="$HOME/Library/LaunchAgents/de.kiosky.player.$identity.plist"
    if [ -f "$legacy" ] && [ "$(extract ProgramArguments.1 "$legacy")" = "$root/start.sh" ];then
        /bin/launchctl bootout "gui/$(id -u)/de.kiosky.player.$identity" >/dev/null 2>&1 || :
        rm -f "$legacy"
    fi
}

if [ "${1:-}" = --managed ];then
    [ "${KIOSKY_MANAGED_PARENT:-}" = "$PPID" ] || exit 3
    config=$2;root=$(dirname "$config")
    url=$(extract player_url "$config") || exit 3
    browser=''
    if [ -f "$root/browser.path" ];then browser=$(cat "$root/browser.path");else
        for candidate in '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge' '/Applications/Chromium.app/Contents/MacOS/Chromium';do
            if [ -x "$candidate" ];then browser=$candidate;break;fi
        done
        [ -n "$browser" ] && printf '%s' "$browser" > "$root/browser.path"
    fi
    [ -x "$browser" ] || exit 3
    "$browser" --kiosk --start-fullscreen --no-first-run --no-default-browser-check --noerrdialogs --disable-session-crashed-bubble --autoplay-policy=no-user-gesture-required --disk-cache-size=33554432 "--user-data-dir=$root/profile" "$url" >/dev/null 2>&1 &
    player=$!
    trap 'kill "$player" 2>/dev/null || :; wait "$player" 2>/dev/null || :' EXIT
    trap 'exit 0' INT TERM
    sleep 2
    kill -0 "$player" 2>/dev/null || exit 3
    printf '%s' "$$" > "$root/player.ready"
    next_check=$(( $(date +%s) + 120 ))
    while kill -0 "$player" 2>/dev/null;do
        now=$(date +%s)
        if [ "$now" -ge "$next_check" ] || [ -f "$root/update.request" ];then
            rm -f "$root/update.request"
            next_check=$((now+21600))
            if check_update;then exit 42;fi
        fi
        sleep 5
    done
    wait "$player";exit $?
fi
config=${1:-}
[ -f "$config" ] || exit 3
url=$(extract player_url "$config") || exit 3
identity=$(printf '%s' "$url" | /usr/bin/shasum -a 256);identity=$(printf '%s' "$identity" | cut -c 1-24)
players="$HOME/Library/Application Support/Kiosky/players"
root="$players/$identity"
installation_lock="$players/installing.lock"
umask 077
mkdir -p "$root" || exit 3
acquire_installation || exit 3
trap 'release_installation' EXIT
incoming=$(extract version "$config") || exit 3
version_valid "$incoming" || exit 3
if [ -f "$root/config.json" ] && [ -f "$root/start.sh" ];then
    current=$(extract version "$root/config.json") || exit 3
    if newer "$current" "$incoming";then release_installation;exec /bin/sh "$root/start.sh";fi
fi
# Validate and stage both files before closing the old working controller.
# plutil -extract accepts our JSON config; -lint only accepts property lists on macOS.
cp "$config" "$root/download.config" && [ "$(extract version "$root/download.config")" = "$incoming" ] && [ "$(extract player_url "$root/download.config")" = "$url" ] && cp "$0" "$root/download.player" && /bin/sh -n "$root/download.player" || exit 3
stop_installation "$root" || exit 3
if ! mkdir "$root/running.lock" 2>/dev/null;then
    owner=$(cat "$root/running.lock/pid" 2>/dev/null || printf '0')
    case "$owner" in ''|*[!0-9]*)exit 3;; esac
    if [ "$owner" -gt 0 ] && kill -0 "$owner" 2>/dev/null;then exit 0;fi
    rm -f "$root/running.lock/pid";rmdir "$root/running.lock" || exit 3
    mkdir "$root/running.lock" || exit 3
fi
printf '%s' "$$" > "$root/running.lock/pid"
trap 'rm -f "$root/running.lock/pid";rmdir "$root/running.lock" 2>/dev/null || :;release_installation' EXIT
mv "$root/download.config" "$root/config.json" && mv "$root/download.player" "$root/player.sh" || exit 3
rm -f "$root/update.installing" "$root/update.pending" "$root/update.candidate" "$root/update.config" "$root/player.ready"
cat > "$root/start.sh" <<'CONTROLLER'
# KIOSKY_CONTROLLER_BEGIN
#!/bin/sh
set -u
root=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
if [ -f "$root/../primary" ] && [ "$(cat "$root/../primary")" != "${root##*/}" ];then exit 0;fi
child=''
# On login the previous installer lock no longer has a live owner.
if ! mkdir "$root/running.lock" 2>/dev/null;then
    owner=$(cat "$root/running.lock/pid" 2>/dev/null || printf '0')
    case "$owner" in ''|*[!0-9]*)exit 3;; esac
    if [ "$owner" != "$$" ] && [ "$owner" -gt 0 ] && kill -0 "$owner" 2>/dev/null;then exit 0;fi
fi
printf '%s' "$$" > "$root/running.lock/pid"
trap '[ -z "$child" ] || kill "$child" 2>/dev/null || :;rm -f "$root/running.lock/pid";rmdir "$root/running.lock" 2>/dev/null || :' EXIT
trap 'exit 0' INT TERM
if [ -f "$root/update.installing" ];then
    expected=$(cat "$root/update.installing")
    confirmed=$(cat "$root/update.version" 2>/dev/null || :)
    if [ "$expected" != "$confirmed" ] && [ -f "$root/player.previous" ] && [ -f "$root/config.previous" ];then
        cp "$root/player.previous" "$root/player.recovery" && mv "$root/player.recovery" "$root/player.sh" || exit 3
        cp "$root/config.previous" "$root/config.recovery" && mv "$root/config.recovery" "$root/config.json" || exit 3
        printf '%s' "$(( $(date +%s)+21600 ))" > "$root/update.retry-after"
    fi
    rm -f "$root/update.installing" "$root/update.pending"
fi
updating=0
while :;do
    rm -f "$root/player.ready"
    KIOSKY_MANAGED_PARENT=$$ /bin/sh "$root/player.sh" --managed "$root/config.json" &
    child=$!
    if [ "$updating" = 1 ];then
        healthy=0;attempt=0
        while [ "$attempt" -lt 30 ];do
            kill -0 "$child" 2>/dev/null || break
            if [ "$(cat "$root/player.ready" 2>/dev/null || :)" = "$child" ];then healthy=1;break;fi
            sleep 1;attempt=$((attempt+1))
        done
        if [ "$healthy" = 0 ];then
            kill "$child" 2>/dev/null || :;wait "$child" 2>/dev/null || :;child=''
            printf '%s' "$(( $(date +%s)+21600 ))" > "$root/update.retry-after"
            cp "$root/player.previous" "$root/player.recovery" && mv "$root/player.recovery" "$root/player.sh" || exit 3
            cp "$root/config.previous" "$root/config.recovery" && mv "$root/config.recovery" "$root/config.json" || exit 3
            rm -f "$root/update.pending" "$root/update.installing";updating=0;continue
        fi
        cat "$root/update.pending" > "$root/update.version"
        awk '/^# KIOSKY_CONTROLLER_BEGIN$/{p=1;next}/^# KIOSKY_CONTROLLER_END$/{p=0}p' "$root/player.sh" > "$root/start.candidate"
        if [ -s "$root/start.candidate" ] && /bin/sh -n "$root/start.candidate";then chmod 700 "$root/start.candidate";mv "$root/start.candidate" "$root/start.sh";fi
        rm -f "$root/update.pending" "$root/update.installing";updating=0
    fi
    wait "$child";result=$?;child=''
    [ "$result" = 42 ] && [ -f "$root/update.pending" ] || exit "$result"
    cp "$root/player.sh" "$root/player.previous" && cp "$root/config.json" "$root/config.previous" || exit 3
    cp "$root/update.pending" "$root/update.installing" || exit 3
    mv "$root/update.candidate" "$root/player.sh" && mv "$root/update.config" "$root/config.json" || exit 3
    updating=1
done
# KIOSKY_CONTROLLER_END
CONTROLLER
chmod 700 "$root/start.sh" "$root/player.sh"
/bin/sh -n "$root/start.sh" || exit 3
retire_other_installations || exit 3
primary="$players/primary"
if [ -f "$primary" ];then
    previous=$(cat "$primary")
    if [ "$previous" != "$identity" ] && printf '%s' "$previous" | grep -Eq '^[0-9a-f]{24}$' && [ -f "$players/$previous/autostart.disabled" ];then : > "$root/autostart.disabled";fi
fi
canonical="$HOME/Library/LaunchAgents/de.kiosky.player.plist"
if [ -f "$canonical" ];then
    previous_script=$(extract ProgramArguments.1 "$canonical") || exit 3
    case "$previous_script" in "$players/"*/start.sh)/bin/launchctl bootout "gui/$(id -u)/de.kiosky.player" >/dev/null 2>&1 || :;; *)exit 3;; esac
    [ ! -f "$root/autostart.disabled" ] || rm -f "$canonical"
fi
printf '%s' "$identity" > "$primary.new" && mv "$primary.new" "$primary" || exit 3
if [ ! -f "$root/autostart.disabled" ];then
    mkdir -p "$HOME/Library/LaunchAgents" || exit 3
    escaped=$(printf '%s' "$root/start.sh" | sed -e 's/\&/\&amp;/g' -e 's/</\&lt;/g' -e 's/>/\&gt;/g')
    agent="$HOME/Library/LaunchAgents/de.kiosky.player.plist"
    cat > "$agent.new" <<PLIST
<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd"><plist version="1.0"><dict><key>Label</key><string>de.kiosky.player</string><key>ProgramArguments</key><array><string>/bin/sh</string><string>$escaped</string></array><key>RunAtLoad</key><true/></dict></plist>
PLIST
    /usr/bin/plutil -lint "$agent.new" >/dev/null && mv "$agent.new" "$agent" || exit 3
fi
release_installation
exec /bin/sh "$root/start.sh"
