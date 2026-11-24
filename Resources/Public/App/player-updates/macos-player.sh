#!/bin/sh
# Installed per user. Only the bound HTTPS Kiosky server may provide code.
set -u
umask 077
extract(){ /usr/bin/plutil -extract "$1" raw -o - "$2" 2>/dev/null; }
version_valid(){ printf '%s\n' "$1" | grep -Eq '^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$'; }
newer(){ version_valid "$1" && version_valid "$2" && awk -v a="$1" -v b="$2" 'BEGIN{split(a,x,".");split(b,y,".");for(i=1;i<=3;i++){if(x[i]+0>y[i]+0)exit 0;if(x[i]+0<y[i]+0)exit 1}exit 1}'; }
fetch(){
    endpoint=$1; destination=$2; payload=$3
    printf '%s\n' "$endpoint" | grep -Eq '^https://[^/?#@]+(/[^?#]*)?/kiosky-player-update/$|^http://(localhost|127\.0\.0\.1|\[::1\])(:[0-9]+)?(/[^?#]*)?/kiosky-player-update/$' || return 1
    address="${endpoint}?platform=macos-universal&cohort=$(cat "$root/update.cohort")"; [ "$payload" = 1 ] && address="${address}&file=1"
    # Bound disk writes even on older system curl with a chunked response.
    code=$( (ulimit -f 2048; /usr/bin/curl --silent --fail --proto '=https,http' --connect-timeout 15 --max-time 90 --max-filesize 1048576 -o "$destination" -w '%{http_code}' "$address" 2>/dev/null) ) || return 1
    [ "$code" = 200 ]
}
check_update(){
    if [ ! -f "$root/update.cohort" ];then cohort=$(od -An -N2 -tu2 /dev/urandom | tr -d ' '); printf '%s' "$((cohort % 100))" > "$root/update.cohort";fi
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
# Native display discovery and setup; no Accessibility permission or local web server.
mac_tools(){
    cat > "$root/instances-helper.new" <<'KIOSKY_MAC_HELPER'
ObjC.import('AppKit');
ObjC.import('Foundation');
ObjC.import('CoreGraphics');
ObjC.import('CoreFoundation');
var displayKeys={};
function displays(){
 var result=[],screens=$.NSScreen.screens,primary=screens.count? screens.objectAtIndex(0).frame:null,seen={};
 for(var n=0;n<screens.count;n++){
  var screen=screens.objectAtIndex(n),frame=screen.frame,id=Number(screen.deviceDescription.objectForKey('NSScreenNumber').unsignedIntValue);
  var geometry=[Math.round(frame.origin.x),Math.round(primary.origin.y+primary.size.height-frame.origin.y-frame.size.height),Math.round(frame.size.width),Math.round(frame.size.height)];
  if(seen[geometry.join(',')])continue;seen[geometry.join(',')]=true;
  var key=displayKeys[id];if(!key){var uuid=$.CGDisplayCreateUUIDFromDisplayID(id);var uuidText=$.CFUUIDCreateString(null,uuid);key=ObjC.unwrap(ObjC.castRefToObject(uuidText));if(typeof key!=='string'||!key)throw Error('Die stabile Bildschirmkennung konnte nicht gelesen werden.');if(Object.keys(displayKeys).length>128)displayKeys={};displayKeys[id]=key;}
  result.push({id:key,name:ObjC.unwrap(screen.localizedName)||('Bildschirm '+(n+1)),x:geometry[0],y:geometry[1],width:geometry[2],height:geometry[3],primary:n===0});
 }
 return result;
}
function read(path){var value=$.NSString.stringWithContentsOfFileEncodingError(path,$.NSUTF8StringEncoding,null);if(!value)throw Error('Konfiguration nicht lesbar.');return JSON.parse(ObjC.unwrap(value));}
function exists(path){return $.NSFileManager.defaultManager.fileExistsAtPath(path);}
function write(path,value){if(!$(JSON.stringify(value)).writeToFileAtomicallyEncodingError(path,true,$.NSUTF8StringEncoding,null))throw Error('Konfiguration konnte nicht gespeichert werden.');}
function task(args,wait){var t=$.NSTask.alloc.init;t.launchPath=args[0];t.arguments=args.slice(1);t.standardOutput=$.NSFileHandle.fileHandleWithNullDevice;t.standardError=$.NSFileHandle.fileHandleWithNullDevice;t.launch;if(wait){t.waitUntilExit;if(t.terminationStatus!==0)throw Error('Player konnte nicht eingerichtet werden.');}return t;}
function run(argv){
 if(argv[0]==='run-browser'){
  var pidPath=argv[1],expected=$.NSURL.URLWithString($(argv[2])),expectedOrigin=[ObjC.unwrap(expected.scheme),ObjC.unwrap(expected.host),expected.port?Number(ObjC.unwrap(expected.port)):null].join('|');
  var child=$.NSTask.alloc.init,input=$.NSPipe.pipe,output=$.NSPipe.pipe;
  child.launchPath=argv[3];child.arguments=argv.slice(4,-1).concat(['--remote-debugging-pipe',argv[argv.length-1]]);
  // Chromium expects descriptor 3 (commands) and 4 (responses). Foundation
  // does not map arbitrary descriptors: launch via a tiny system shell, keeping
  // the pipes entirely inside this user-owned child; no listening port.
  var command='exec 3<&0 4>&1; exec "$@"';
  child.launchPath='/bin/sh';child.arguments=['-c',command,'kiosky-browser',argv[3]].concat(argv.slice(4,-1),['--remote-debugging-pipe',argv[argv.length-1]]);
  child.standardInput=input;child.standardOutput=output;child.standardError=$.NSFileHandle.fileHandleWithNullDevice;child.launch;
  write(pidPath,{pid:Number(child.processIdentifier)});
  var sequence=0,callbacks={},sessions={},lastCapture=0,buffer='';
  function send(method,params,session,callback){var message={id:++sequence,method:method,params:params||{}};if(session)message.sessionId=session;if(callback)callbacks[sequence]=callback;input.fileHandleForWriting.writeData($(JSON.stringify(message)+'\0').dataUsingEncoding($.NSUTF8StringEncoding));}
  function frame(session,value){if(value.parentId)return;var url=$.NSURL.URLWithString($(value.url||''));sessions[session].frame=value.id;sessions[session].origin=url?[ObjC.unwrap(url.scheme),ObjC.unwrap(url.host),url.port?Number(ObjC.unwrap(url.port)):null].join('|'):'';}
  function attach(value){var id=value.result&&value.result.sessionId;if(!id)return;sessions[id]={contexts:{}};send('Page.enable',{},id);send('Runtime.enable',{},id);send('Runtime.addBinding',{name:'KioskyNativeCapture'},id);send('Page.getFrameTree',{},id,function(result){if(result.result)frame(id,result.result.frameTree.frame);});}
  function receive(value){var callback=callbacks[value.id];if(callback){delete callbacks[value.id];callback(value);return;}var method=value.method,p=value.params||{},session=value.sessionId,state=sessions[session];
   if(method==='Target.targetCreated'&&p.targetInfo.type==='page')send('Target.attachToTarget',{targetId:p.targetInfo.targetId,flatten:true},null,attach);
   if(!state)return;
   if(method==='Page.frameNavigated')frame(session,p.frame);
   else if(method==='Runtime.executionContextCreated')state.contexts[p.context.id]=p.context.auxData||{};
   else if(method==='Runtime.executionContextDestroyed')delete state.contexts[p.executionContextId];
   else if(method==='Runtime.executionContextsCleared')state.contexts={};
   else if(method==='Runtime.bindingCalled'&&p.name==='KioskyNativeCapture'){
    var context=state.contexts[p.executionContextId]||{},request=p.payload;
    if(state.origin!==expectedOrigin||!context.isDefault||context.frameId!==state.frame||!/^[a-f0-9]{32}$/.test(request)||Date.now()-lastCapture<10000)return;
    lastCapture=Date.now();send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false},session,function(result){var data=result.result&&result.result.data;if(typeof data!=='string'||data.length>23000000)return;send('Runtime.evaluate',{contextId:p.executionContextId,expression:'window.KioskyPlayerCaptureReceive&&window.KioskyPlayerCaptureReceive('+JSON.stringify(request)+','+JSON.stringify('data:image/png;base64,'+data)+')'},session);});
   }
  }
  send('Target.setDiscoverTargets',{discover:true});
  var pending=$.NSMutableData.data,separator=$('\0').dataUsingEncoding($.NSUTF8StringEncoding);
  while(child.isRunning){var bytes=output.fileHandleForReading.availableData;if(!bytes.length)break;pending.appendData(bytes);if(pending.length>24000000)break;while(pending.length){var total=Number(pending.length),found=pending.rangeOfDataOptionsRange(separator,0,$.NSMakeRange(0,total)),end=Number(found.location);if(end>=total)break;var head=pending.subdataWithRange($.NSMakeRange(0,end)),tail=pending.subdataWithRange($.NSMakeRange(end+1,total-end-1));pending=$.NSMutableData.alloc.initWithData(tail);var text=$.NSString.alloc.initWithDataEncoding(head,$.NSUTF8StringEncoding);if(!text)throw Error('Invalid browser response');receive(JSON.parse(ObjC.unwrap(text)));}}
  if(child.isRunning)child.terminate;child.waitUntilExit;return '';
 }
 if(argv[0]==='monitors')return JSON.stringify(displays());
 if(argv[0]==='watch'){
  $.NSApplication.sharedApplication.setActivationPolicy(2);
  while(true){var state={};displays().forEach(function(m){var value=[m.x,m.y,m.width,m.height].join(' ');state[m.id]=value;if(m.primary)state.primary=value;});write(argv[1],state);$.NSRunLoop.currentRunLoop.runUntilDate($.NSDate.dateWithTimeIntervalSinceNow(2));}
 }
 if(argv[0]==='monitor'){var available=displays(),m=argv[1]?available.filter(function(d){return d.id===argv[1]})[0]:available[0];return m?[m.x,m.y,m.width,m.height].join(' '):'';}
 var base=argv[1],home=ObjC.unwrap($.NSProcessInfo.processInfo.environment.objectForKey('HOME'));
 if(typeof base!=='string'||base.indexOf(home+'/Library/Application Support/Kiosky/players/')!==0||!/^[a-f0-9]{24}$/.test(base.split('/').pop()))throw Error('Ungültige Player-Installation.');
 var settings=exists(base+'/instances.json')?read(base+'/instances.json'):{default:{name:'Display 1',monitor:''}},app=Application.currentApplication();app.includeStandardAdditions=true;app.activate();
 function root(id){if(id!=='default'&&!/^[a-f0-9]{32}$/.test(id))throw Error('Ungültige Instanz.');return id==='default'?base:base+'/instances/'+id;}
 function save(){write(base+'/instances.json',settings);}
 function choose(labels,prompt){var answer=app.chooseFromList(labels,{withPrompt:prompt,okButtonName:'Auswählen',cancelButtonName:'Zurück'});return answer?labels.indexOf(answer[0]):-1;}
 function selectInstance(){var keys=Object.keys(settings),index=choose(keys.map(function(id){return settings[id].name;}),'Player auswählen');return index<0?null:keys[index];}
 function availableFor(id){var all=displays(),primary=all[0]?all[0].id:'';return all.filter(function(m){return !Object.keys(settings).some(function(key){return key!==id&&(settings[key].monitor||(key==='default'?primary:''))===m.id;});});}
 function chooseMonitor(id){var free=availableFor(id);if(!free.length){app.displayAlert('Kein freier Bildschirm',{message:'Bildschirme als erweiterte Ausgaben einrichten. Gespiegelte Bildschirme sind ein gemeinsamer Ausspielweg.'});return null;}var n=choose(free.map(function(m){return m.name+' · '+m.width+' × '+m.height+(m.primary?' · primär':'');}),'Bildschirm für diesen Player auswählen');return n<0?null:free[n];}
 function start(id){var r=root(id);if(!exists(r+'/start.sh')){
   if(!$.NSFileManager.defaultManager.createDirectoryAtPathWithIntermediateDirectoriesAttributesError(r,true,null,null))throw Error('Instanzordner konnte nicht angelegt werden.');
   var config=read(base+'/config.json');config.instance_id=id;write(r+'/config.json',config);
   task(['/bin/sh',base+'/player.sh',r+'/config.json','--install-only'],true);
  }
  task(['/usr/bin/nohup','/bin/sh',r+'/start.sh'],false);
 }
 if(argv[0]==='init'){var all=displays();if(all.length<2||exists(base+'/instances.json')||exists(base+'/profile'))return 'skip';settings.default.monitor=all[0].id;save();}
 while(true){
  var options=['Weiteren Bildschirm anlegen','Player starten','Monitorzuordnung ändern','Player beenden','Fertig'],action=choose(options,displays().length+' Bildschirme erkannt. Jeder Player hat einen eigenen Kopplungscode.');
  if(action<0||action===4)return 'done';
  try{
   if(action===0){var m=chooseMonitor('');if(!m)continue;var name=app.displayDialog('Name des zusätzlichen Players',{defaultAnswer:'Display '+(Object.keys(settings).length+1),buttons:['Abbrechen','Anlegen'],defaultButton:'Anlegen',cancelButton:'Abbrechen'}).textReturned;
    if(!name.trim()||name.length>80||/[\x00-\x1f]/.test(name))throw Error('Bitte einen Namen mit höchstens 80 Zeichen eingeben.');
    var id=ObjC.unwrap($.NSUUID.UUID.UUIDString).replace(/-/g,'').toLowerCase();settings[id]={name:name.trim(),monitor:m.id};save();start(id);
   }else{var id=selectInstance();if(!id)continue;if(action===1)start(id);if(action===2){var m=chooseMonitor(id);if(m){settings[id].monitor=m.id;save();}}if(action===3)task(['/bin/sh',base+'/player.sh',base+'/config.json','--stop-instance',id],true);}
  }catch(error){if(String(error).indexOf('-128')<0)app.displayAlert('Kiosky',{message:String(error)});}
 }
}
KIOSKY_MAC_HELPER
    chmod 600 "$root/instances-helper.new" && mv "$root/instances-helper.new" "$root/instances-helper.js"
}
install_manager_app(){
# Reopen setup without a Terminal command; this app always follows the active installation.
application="$HOME/Applications/Kiosky Player-Instanzen.app"
if [ ! -L "$application" ] && { [ ! -e "$application" ] || [ "$(extract CFBundleIdentifier "$application/Contents/Info.plist")" = de.kiosky.instances ]; };then
    mkdir -p "$application/Contents/MacOS" || exit 3
    cat > "$application/Contents/MacOS/manager" <<'KIOSKY_MANAGER_APP'
#!/bin/sh
players="$HOME/Library/Application Support/Kiosky/players"
identity=$(cat "$players/primary") || exit 3
printf '%s' "$identity" | grep -Eq '^[a-f0-9]{24}$' || exit 3
exec /bin/sh "$players/$identity/player.sh" "$players/$identity/config.json" --instances
KIOSKY_MANAGER_APP
    chmod 700 "$application/Contents/MacOS/manager"
    cat > "$application/Contents/Info.plist" <<'KIOSKY_MANAGER_INFO'
<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd"><plist version="1.0"><dict><key>CFBundleIdentifier</key><string>de.kiosky.instances</string><key>CFBundleExecutable</key><string>manager</string><key>CFBundleName</key><string>Kiosky Player-Instanzen</string><key>CFBundlePackageType</key><string>APPL</string><key>LSMinimumSystemVersion</key><string>11.0</string></dict></plist>
KIOSKY_MANAGER_INFO
fi
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
    instance=$(extract instance_id "$config" || printf default)
    base=$root
    if [ "$instance" != default ];then base=$(dirname "$(dirname "$root")");fi
    mac_tools || exit 3
    if [ "$instance" = default ];then install_manager_app || exit 3;fi
    player='';geometry='';watcher=''
    stop_browser(){ if [ -n "$player" ];then native_pid=$(extract pid "$root/browser.capture-pid.json" || :);case "$native_pid" in *[!0-9]*|'') ;; *) native_parent=$(/bin/ps -p "$native_pid" -o ppid= | tr -d ' ');if [ "$native_parent" = "$player" ];then kill "$native_pid" 2>/dev/null || :;fi;; esac;kill "$player" 2>/dev/null || :;wait "$player" 2>/dev/null || :;player='';fi; }
    trap 'stop_browser;[ -z "$watcher" ] || kill "$watcher" 2>/dev/null || :;[ -z "$watcher" ] || wait "$watcher" 2>/dev/null || :' EXIT
    trap 'exit 0' INT TERM
    rm -f "$root/monitor-state.json"
    /usr/bin/osascript -l JavaScript "$root/instances-helper.js" watch "$root/monitor-state.json" >/dev/null 2>&1 &
    watcher=$!;attempt=0
    while [ ! -f "$root/monitor-state.json" ];do
        kill -0 "$watcher" 2>/dev/null || { printf '%s\n' 'Die macOS-Bildschirmabfrage ist fehlgeschlagen.' >&2;exit 3; }
        [ "$attempt" -lt 15 ] || exit 3
        sleep 1;attempt=$((attempt+1))
    done
    next_check=$(( $(date +%s) + 120 ))
    while :;do
        now=$(date +%s)
        if [ "$now" -ge "$next_check" ] || [ -f "$root/update.request" ];then
            rm -f "$root/update.request";next_check=$((now+21600))
            if check_update;then exit 42;fi
        fi
        monitor=$(extract "$instance.monitor" "$base/instances.json" || :)
        kill -0 "$watcher" 2>/dev/null || { printf '%s\n' 'Die macOS-Bildschirmabfrage ist fehlgeschlagen.' >&2;exit 3; }
        current=''
        if [ -n "$monitor" ];then current=$(extract "$monitor" "$root/monitor-state.json" || :)
        elif [ "$instance" = default ];then current=$(extract primary "$root/monitor-state.json" || :);fi
        if [ -z "$current" ];then
            stop_browser;geometry=''
            printf '%s' "$$" > "$root/player.ready"
            sleep 2;continue
        fi
        if [ "$current" != "$geometry" ];then stop_browser;geometry=$current;fi
        if [ -n "$player" ] && ! kill -0 "$player" 2>/dev/null;then wait "$player";exit $?;fi
        if [ -z "$player" ];then
            set -- $geometry
            [ "$#" = 4 ] || exit 3
            kiosky_start_url=$url
            case "$url" in *'#'*) ;; *) case "$url" in *'?'*) kiosky_start_separator='&';; *) kiosky_start_separator='?';; esac
                kiosky_start_url="${url}${kiosky_start_separator}kiosky_start=$(/bin/date +%s)";; esac
            rm -f "$root/browser.capture-pid.json"
            /usr/bin/osascript -l JavaScript "$root/instances-helper.js" run-browser "$root/browser.capture-pid.json" "$url" "$browser" --kiosk --start-fullscreen --new-window --hide-scrollbars --no-first-run --no-default-browser-check --noerrdialogs --disable-session-crashed-bubble --autoplay-policy=no-user-gesture-required --disk-cache-size=33554432 "--window-position=$1,$2" "--window-size=$3,$4" "--user-data-dir=$root/profile" "$kiosky_start_url" >/dev/null 2>&1 &
            player=$!;sleep 2;kill -0 "$player" 2>/dev/null || exit 3
            printf '%s' "$$" > "$root/player.ready"
        fi
        sleep 3
    done
fi
config=${1:-}
[ -f "$config" ] || exit 3
url=$(extract player_url "$config") || exit 3
identity=$(printf '%s' "$url" | /usr/bin/shasum -a 256);identity=$(printf '%s' "$identity" | cut -c 1-24)
players="$HOME/Library/Application Support/Kiosky/players"
base="$players/$identity"
instance=$(extract instance_id "$config" || printf default)
[ "$instance" = default ] || printf '%s' "$instance" | grep -Eq '^[a-f0-9]{32}$' || exit 3
root=$base
[ "$instance" = default ] || root="$base/instances/$instance"
installation_lock="$players/installing.lock"
umask 077
mkdir -p "$root" || exit 3
if [ "${2:-}" = --instances ];then mac_tools || exit 3;exec /usr/bin/osascript -l JavaScript "$root/instances-helper.js" manage "$base";fi
if [ "${2:-}" = --stop-instance ];then
    target=${3:-default}
    [ "$target" = default ] || printf '%s' "$target" | grep -Eq '^[a-f0-9]{32}$' || exit 3
    wanted=$base;[ "$target" = default ] || wanted="$base/instances/$target"
    stop_installation "$wanted";exit $?
fi
acquire_installation || exit 3
trap 'release_installation' EXIT
incoming=$(extract version "$config") || exit 3
version_valid "$incoming" || exit 3
if [ -f "$root/config.json" ] && [ -f "$root/start.sh" ];then
    current=$(extract version "$root/config.json") || exit 3
    if newer "$current" "$incoming";then release_installation;exec /bin/sh "$root/start.sh";fi
fi
mac_tools || exit 3
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
if [ "$instance" = default ];then
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
install_manager_app || exit 3
else
    mkdir -p "$HOME/Library/LaunchAgents" || exit 3
    escaped=$(printf '%s' "$root/start.sh" | sed -e 's/\&/\&amp;/g' -e 's/</\&lt;/g' -e 's/>/\&gt;/g')
    label="de.kiosky.player.$identity.$instance"
    agent="$HOME/Library/LaunchAgents/$label.plist"
    cat > "$agent.new" <<PLIST
<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd"><plist version="1.0"><dict><key>Label</key><string>$label</string><key>ProgramArguments</key><array><string>/bin/sh</string><string>$escaped</string></array><key>RunAtLoad</key><true/></dict></plist>
PLIST
    /usr/bin/plutil -lint "$agent.new" >/dev/null && mv "$agent.new" "$agent" || exit 3
fi
release_installation
[ "${2:-}" != --install-only ] || exit 0
if [ "$instance" = default ];then
    /usr/bin/osascript -l JavaScript "$root/instances-helper.js" init "$base" >/dev/null 2>&1 || :
fi
exec /bin/sh "$root/start.sh"
