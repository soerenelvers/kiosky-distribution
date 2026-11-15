<?php
declare(strict_types=1);
namespace Kiosky\Core;
use RuntimeException;
require_once __DIR__.'/CustomerVault.php';
/** Customer capabilities never grant CMS identities, content writes or live output. */
final class CustomerContent {
    private array $data;
    public function __construct(private array &$state,private readonly CustomerVault $vault,private readonly \Closure $save,private readonly string $actor='',private readonly ?\Closure $readApproved=null){
        $state['customerContent']??=['invitations'=>[],'uploads'=>[],'proposals'=>[]];
        $this->data=&$state['customerContent'];
    }
    private static function text(mixed $v,string $label,int $max=180):string{
        if(!is_string($v)||trim($v)===''||preg_match_all('/./us',$v)>$max)throw new RuntimeException($label.' fehlt oder ist zu lang.',422);
        return trim($v);
    }
    private static function filename(mixed $value,string $extension):string{
        $stem=basename(str_replace('\\','/',self::text($value,'Dateiname')));
        $stem=preg_replace('/\.[^.]*$/','',$stem);
        $stem=preg_replace('/[^\pL\pN _-]/u','_',$stem)??'';
        $out='';
        foreach(preg_split('//u',$stem,-1,PREG_SPLIT_NO_EMPTY)?:[] as $c){
            if(strlen($out.$c)>90)break;
            $out.=$c;
        }
        return (trim($out)?:'Medium').'.'.$extension;
    }
    private static function date(mixed $v):string{
        if(!is_string($v)||!preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d(?::\d\d(?:\.\d{1,3})?)?(?:Z|[+-]\d\d:\d\d)$/D',$v))throw new RuntimeException('Bitte Datum und Uhrzeit mit Zeitzone angeben.',422);
        try{
            return (new \DateTimeImmutable($v))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
        }
        catch(\Throwable){
            throw new RuntimeException('Ungültiger Zeitpunkt.',422);
        }
    }
    private static function ids(mixed $v,int $max=50):array{
        if(!is_array($v)||!array_is_list($v)||!count($v)||count($v)>$max||count(array_unique($v,SORT_REGULAR))!==count($v))throw new RuntimeException('Bitte gültige, unterschiedliche Einträge auswählen.',422);
        foreach($v as $id)if(!is_string($id)||$id===''||strlen($id)>200)throw new RuntimeException('Ungültiger Eintrag.',422);
        return $v;
    }
    private static function now():string{
        return gmdate('Y-m-d\TH:i:s.000\Z');
    }
    private static function uuid():string{
        return bin2hex(random_bytes(16));
    }
    private static function pick(array $r,array $keys):array{
        return array_intersect_key($r,array_flip($keys));
    }
    private static function fileDto(array $f):array{
        return self::pick($f,['id','invitationId','slideId','name','size','mime','type','status','createdAt']);
    }
    private static function proposalDto(array $p):array{
        unset($p['reviewedBy'],$p['scheduleIds'],$p['requestId']);
        return $p;
    }
    private static function inviteDto(array $i):array{
        unset($i['tokenHash']);
        return $i;
    }
    private function find(string $collection,string $id):array{
        foreach($this->data[$collection] as $r)if($r['id']===$id&&($collection!=='invitations'||empty($r['deletedAt'])))return $r;
        throw new RuntimeException('Eintrag nicht gefunden.',404);
    }
    private function update(string $collection,array $value):void{
        foreach($this->data[$collection] as $k=>$r)if($r['id']===$value['id']){
            $this->data[$collection][$k]=$value;
            return;
        }
        $this->data[$collection][]=$value;
    }
    private function display(string $id):array{
        foreach($this->state['displays'] as $d)if($d['id']===$id&&empty($d['deletedAt'])&&($d['status']??'active')==='active')return $d;
        throw new RuntimeException('Ein ausgewähltes Display ist nicht mehr aktiv.',409);
    }
    private function event(string $id):array{
        foreach($this->state['events'] as $e)if($e['id']===$id&&empty($e['deletedAt'])&&!in_array(EventLifecycle::effective($e),['removed','archived'],true))return self::pick($e,['id','title','status']);
        throw new RuntimeException('Die Veranstaltung ist nicht mehr verfügbar.',409);
    }
    private function active(array $i,bool $customer=true):void{
        if(!empty($i['deletedAt'])||!empty($i['revokedAt'])||$customer&&strtotime($i['editUntil'])<=time()||strtotime($i['endsAt'])<=time())throw new RuntimeException('Dieser Kundenlink ist abgelaufen oder widerrufen.',410);
        $this->event($i['eventId']);
        foreach($i['displayIds'] as $id){
            $d=$this->display($id);
            if(isset($i['displayLocations'])&&($i['displayLocations'][$id]??null)!==($d['locationId']??''))throw new RuntimeException('Eine Display-Zuordnung wurde geändert. Bitte den Mitarbeiter kontaktieren.',409);
        }
    }
    public function authorize(string $token):array{
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('Der Kundenlink ist ungültig.',401);
        $hash=hash('sha256',$token);
        foreach($this->data['invitations'] as $i)if(strlen($i['tokenHash']??'')===64&&hash_equals($i['tokenHash'],$hash)){
            $this->active($i);
            return $i;
        }
        throw new RuntimeException('Der Kundenlink ist ungültig.',401);
    }
    private function fileData(array $f):array{
        if($f['status']==='pending')return ['data'=>$this->vault->read($f),'mime'=>$f['mime']];
        if($f['status']!=='approved')throw new RuntimeException('Datei nicht gefunden.',404);
        CustomerVault::assertDelivery($f['size']);
        if($this->readApproved)return ['data'=>($this->readApproved)($f),'mime'=>$f['mime']];
        foreach($this->state['mediaAssets'] as $asset)if($asset['id']===($f['mediaAssetId']??null)&&empty($asset['deletedAt'])&&str_starts_with((string)$asset['src'],'data:')){
            $media=CustomerVault::decode($asset['src']);
            if(hash_equals($f['hash'],$media['hash']))return ['data'=>$asset['src'],'mime'=>$f['mime']];
        }
        throw new RuntimeException('Die Originaldatei ist nicht mehr verfügbar.',404);
    }
    private function proposalInput(array $input,array $i):array{
        $displayIds=self::ids($input['displayIds']??null);
        foreach($displayIds as $id){
            if(!in_array($id,$i['displayIds'],true))throw new RuntimeException('Dieses Display ist nicht für den Kunden freigegeben.',403);
            $this->display($id);
        }
        $start=self::date($input['startsAt']??null);
        $end=self::date($input['endsAt']??null);
        if($start<$i['startsAt']||$end>$i['endsAt']||$end<=$start||strtotime($end)<=time())throw new RuntimeException('Die Ausspielzeit muss innerhalb des freigegebenen Zeitraums liegen.',422);
        if(!is_array($input['items']??null)||!array_is_list($input['items'])||count($input['items'])<1||count($input['items'])>20)throw new RuntimeException('Bitte 1 bis 20 Slides für den Kanal auswählen.',422);
        $items=[];
        foreach($input['items'] as $item){
            $f=$this->find('uploads',is_array($item)?(string)($item['uploadId']??''):'');
            if($f['invitationId']!==$i['id']||$f['status']==='removed')throw new RuntimeException('Eine ausgewählte Datei gehört nicht zu dieser Freigabe.',403);
            $duration=$item['durationSeconds']??null;
            if(!is_int($duration)||$duration<3||$duration>600)throw new RuntimeException('Die Anzeigedauer muss zwischen 3 und 600 Sekunden liegen.',422);
            $items[]=['uploadId'=>$f['id'],'durationSeconds'=>$duration];
        }
        if(count(array_unique(array_column($items,'uploadId')))!==count($items))throw new RuntimeException('Eine Datei darf im Vorschlag nur einmal vorkommen.',422);
        return ['name'=>self::text($input['name']??null,'Kanalname'),'displayIds'=>$displayIds,'startsAt'=>$start,'endsAt'=>$end,'items'=>$items];
    }
    private function channel(array $p,array $i,string $status):array{
        $items=[];
        foreach($p['items'] as $x)$items[]=['slideId'=>$this->find('uploads',$x['uploadId'])['slideId'],'durationSeconds'=>$x['durationSeconds'],'transition'=>'inherit'];
        return ['name'=>$p['name'],'eventId'=>$i['eventId'],'status'=>$status,'orientation'=>'auto','defaultTransition'=>'fade','repeatEnabled'=>true,'tags'=>['Kundenupload',$i['tag']],'items'=>$items];
    }
    private function record(string $collection,string $id):array{
        foreach($this->state[$collection] as $r)if($r['id']===$id&&empty($r['deletedAt']))return $r;
        throw new RuntimeException('Inhalt nicht mehr verfügbar.',409);
    }
    public function staff(string $method,?string $id,?string $action,array $input):array{
        if($method==='GET'&&!$id){
            $invitations=array_values(array_filter($this->data['invitations'],static fn($i)=>empty($i['deletedAt'])));
            $ids=array_column($invitations,'id');
            return ['invitations'=>array_map(self::inviteDto(...),$invitations),'uploads'=>array_map(self::fileDto(...),array_values(array_filter($this->data['uploads'],static fn($f)=>in_array($f['invitationId'],$ids,true)))),'proposals'=>array_values(array_filter($this->data['proposals'],static fn($p)=>in_array($p['invitationId'],$ids,true)))];
        }
        if($method==='POST'&&!$id){
            if(count(array_filter($this->data['invitations'],static fn($i)=>empty($i['revokedAt'])&&strtotime($i['endsAt'])>time()))>=200)throw new RuntimeException('Es sind bereits 200 Kundenfreigaben vorhanden.',409);
            $displayIds=self::ids($input['displayIds']??null);
            foreach($displayIds as $d)$this->display($d);
            $eventId=self::text($input['eventId']??null,'Veranstaltung');
            $this->event($eventId);
            $start=self::date($input['startsAt']??null);
            $end=self::date($input['endsAt']??null);
            $edit=self::date($input['editUntil']??null);
            if($end<=$start)throw new RuntimeException('Das Ausspielende muss nach dem Ausspielbeginn liegen.',422);
            if(strtotime($end)<=time())throw new RuntimeException('Das Ausspielende muss in der Zukunft liegen.',422);
            if(strtotime($end)-strtotime($start)>366*86400)throw new RuntimeException('Der Ausspielzeitraum darf höchstens ein Jahr betragen.',422);
            if($edit>$end)throw new RuntimeException('„Kundenlink gültig bis“ darf nicht nach dem Ausspielende liegen.',422);
            if(strtotime($edit)<=time())throw new RuntimeException('„Kundenlink gültig bis“ muss in der Zukunft liegen.',422);
            $token=bin2hex(random_bytes(32));
            $i=['id'=>self::uuid(),'customerName'=>self::text($input['customerName']??null,'Kundenname'),'tag'=>'Kunde: '.self::text($input['tag']??($input['customerName']??''),'Tag',90),'eventId'=>$eventId,'displayIds'=>$displayIds,'displayLocations'=>array_combine($displayIds,array_map(fn($id)=>(string)($this->display($id)['locationId']??''),$displayIds)),'startsAt'=>$start,'endsAt'=>$end,'editUntil'=>$edit,'createdAt'=>self::now(),'createdBy'=>$this->actor,'tokenHash'=>hash('sha256',$token)];
            $this->data['invitations'][]=$i;
            return ['invitation'=>self::inviteDto($i),'token'=>$token];
        }
        if($action==='file'&&$method==='GET'){
            $f=$this->find('uploads',(string)$id);
            $this->find('invitations',$f['invitationId']);
            return $this->fileData($f);
        }
        if($action==='revoke'&&$method==='POST'||$id!==null&&$action===null&&$method==='DELETE'){
            $i=$this->find('invitations',(string)$id);
            $i['revokedAt']=self::now();
            $i['tokenHash']='';
            if($method==='DELETE')$i['deletedAt']=$i['revokedAt'];
            $this->update('invitations',$i);
            foreach($this->data['proposals'] as $p)if($p['invitationId']===$i['id']&&$p['status']==='pending'){
                $p['status']='rejected';
                $p['note']='Kundenfreigabe widerrufen';
                $p['revision']++;
                $this->update('proposals',$p);
                foreach($this->state['channels'] as $ch)if($ch['id']===$p['channelId']&&empty($ch['deletedAt']))($this->save)('channels',array_replace($ch,['status'=>'archived']),$ch['id']);
            }
            foreach($this->data['uploads'] as $f)if($f['invitationId']===$i['id']&&$f['status']==='pending'){
                $f['status']='removed';
                $this->update('uploads',$f);
                foreach($this->state['slides'] as $s)if($s['id']===$f['slideId']&&empty($s['deletedAt']))($this->save)('slides',array_replace($s,['status'=>'archived']),$s['id']);
            }
            return ['ok'=>true];
        }
        if(in_array($action,['approve','reject'],true)&&$method==='POST'){
            $p=$this->find('proposals',(string)$id);
            $i=$this->find('invitations',$p['invitationId']);
            if(($input['expectedRevision']??null)!==$p['revision'])throw new RuntimeException('Der Vorschlag wurde inzwischen geändert. Bitte neu laden.',409);
            if($p['status']==='approved'&&$action==='approve')return ['proposal'=>$p];
            if($p['status']!=='pending')throw new RuntimeException('Dieser Vorschlag wurde bereits bearbeitet.',409);
            if($action==='reject'){
                $p['status']='rejected';
                $p['note']=is_string($input['note']??null)?substr(trim($input['note']),0,1000):'';
                $this->update('proposals',$p);
                return ['proposal'=>$p];
            }
            $this->active($i,false);
            $this->proposalInput($p,$i);
            foreach($p['items'] as $item){
                $f=$this->find('uploads',$item['uploadId']);
                $s=$this->record('slides',$f['slideId']);
                if(($s['status']??'')==='archived')throw new RuntimeException('Eine Slide ist nicht mehr verfügbar.',409);
                if($f['status']==='pending'){
                    $source=$this->vault->read($f);
                    CustomerVault::assertImport($source);
                    $asset=($this->save)('mediaAssets',['name'=>$f['name'],'type'=>$f['type'],'src'=>$source,'metadata'=>['customerUpload'=>true]],null);
                    $slide=MediaSlides::inputs([$asset['id']],[$asset])[0];
                    $slide=array_replace($slide,['name'=>$s['name'],'eventId'=>$i['eventId'],'status'=>'published','tags'=>$s['tags']]);
                    $slide['document']['customerUpload']=['id'=>$f['id'],'status'=>'approved'];
                    ($this->save)('slides',$slide,$s['id']);
                    $f['status']='approved';
                    $f['mediaAssetId']=$asset['id'];
                    $this->update('uploads',$f);
                }
                elseif(($s['status']??'')!=='published')throw new RuntimeException('Eine zuvor freigegebene Slide ist nicht mehr veröffentlicht.',409);
            }
            $ch=$this->record('channels',$p['channelId']);
            if(($ch['status']??'')==='archived')throw new RuntimeException('Der Vorschlagskanal ist nicht mehr verfügbar.',409);
            ($this->save)('channels',$this->channel($p,$i,'published'),$p['channelId']);
            $p['scheduleIds']=[];
            foreach($p['displayIds'] as $displayId)$p['scheduleIds'][]=($this->save)('schedules',['targetType'=>'display','targetId'=>$displayId,'contentType'=>'channel','contentId'=>$p['channelId'],'startsAt'=>$p['startsAt'],'endsAt'=>$p['endsAt'],'recurrence'=>'once','priority'=>'normal','status'=>'published','active'=>true,'description'=>'Kundenvorschlag: '.$i['customerName']],null)['id'];
            $p['status']='approved';
            $p['reviewedAt']=self::now();
            $p['reviewedBy']=$this->actor;
            $this->update('proposals',$p);
            return ['proposal'=>$p];
        }
        throw new RuntimeException('Endpunkt nicht gefunden.',404);
    }
    public function guest(string $method,?string $action,?string $id,array $input,string $token):array{
        $i=$this->authorize($token);
        if($method==='GET'&&!$action)return ['invitation'=>array_diff_key(self::inviteDto($i),array_flip(['createdBy','displayLocations'])),'event'=>$this->event($i['eventId']),'displays'=>array_map(fn($id)=>self::pick($this->display($id),['id','name','orientation']),$i['displayIds']),'uploads'=>array_map(self::fileDto(...),array_values(array_filter($this->data['uploads'],static fn($f)=>$f['invitationId']===$i['id']&&$f['status']!=='removed'))),'proposals'=>array_map(self::proposalDto(...),array_values(array_filter($this->data['proposals'],static fn($p)=>$p['invitationId']===$i['id']))),'limits'=>['fileBytes'=>CustomerVault::maximumBytes(),'invitationBytes'=>100000000,'files'=>20],'timezone'=>'Europe/Berlin'];
        if($action==='files'&&$method==='GET'){
            $f=$this->find('uploads',(string)$id);
            if($f['invitationId']!==$i['id']||$f['status']==='removed')throw new RuntimeException('Datei nicht gefunden.',404);
            return $this->fileData($f);
        }
        if($action==='uploads'&&$method==='POST'){
            $request=self::text($input['requestId']??null,'Anfragekennung',100);
            foreach($this->data['uploads'] as $f)if($f['invitationId']===$i['id']&&$f['requestId']===$request)return ['upload'=>self::fileDto($f)];
            $media=CustomerVault::decode($input['data']??null);
            if(strlen($media['bytes'])>CustomerVault::maximumBytes())throw new RuntimeException('Die Datei überschreitet die Uploadgrenze dieses Servers.',413);
            $files=array_values(array_filter($this->data['uploads'],static fn($f)=>$f['invitationId']===$i['id']&&$f['status']!=='removed'));
            foreach($files as $f)if($f['hash']===$media['hash'])return ['upload'=>self::fileDto($f)];
            if(count($files)>=20||array_sum(array_column($files,'size'))+strlen($media['bytes'])>100000000||count(array_filter($this->data['uploads'],static fn($f)=>$f['status']==='pending'))>=2000)throw new RuntimeException('Das Uploadlimit ist erreicht. Bitte den Mitarbeiter kontaktieren.',413);
            $uploadId=self::uuid();
            $name=self::filename($input['name']??null,$media['extension']);
            $privateId=$this->vault->put($i['id'],$media);
            $s=($this->save)('slides',['name'=>$name,'eventId'=>$i['eventId'],'type'=>$media['type'],'orientation'=>'auto','status'=>'draft','tags'=>['Kundenupload',$i['tag']],'document'=>['customerUpload'=>['id'=>$uploadId,'status'=>'pending'],'elements'=>[['id'=>'media-content','type'=>$media['type'],'src'=>'','x'=>0,'y'=>0,'width'=>100,'height'=>100,'mediaFit'=>'contain','muted'=>true]]]],null);
            $f=['id'=>$uploadId,'invitationId'=>$i['id'],'requestId'=>$request,'slideId'=>$s['id'],'name'=>$name,'privateId'=>$privateId,'hash'=>$media['hash'],'mime'=>$media['mime'],'type'=>$media['type'],'size'=>strlen($media['bytes']),'status'=>'pending','createdAt'=>self::now()];
            $this->data['uploads'][]=$f;
            return ['upload'=>self::fileDto($f)];
        }
        if($action==='proposals'&&in_array($method,['POST','PUT'],true)){
            $request=self::text($input['requestId']??null,'Anfragekennung',100);
            $p=null;
            foreach($this->data['proposals'] as $r)if($r['invitationId']===$i['id']&&($id?$r['id']===$id:$r['requestId']===$request)){
                $p=$r;
                break;
            }
            if(!$id&&$p)return ['proposal'=>self::proposalDto($p)];
            if($id&&(!$p||$p['status']!=='pending'||$p['revision']!==($input['expectedRevision']??null)))throw new RuntimeException('Der Vorschlag wurde bereits geändert oder freigegeben. Bitte neu laden.',409);
            if(!$p&&(count(array_filter($this->data['proposals'],static fn($r)=>$r['invitationId']===$i['id']))>=20||count(array_filter($this->data['proposals'],static fn($p)=>$p['status']==='pending'))>=2000))throw new RuntimeException('Das Limit für Kanalvorschläge ist erreicht.',413);
            $values=$this->proposalInput($input,$i);
            $p=array_replace($p??['id'=>self::uuid(),'invitationId'=>$i['id'],'requestId'=>$request,'status'=>'pending','revision'=>0,'createdAt'=>self::now()],$values);
            $p['revision']++;
            $ch=($this->save)('channels',$this->channel($p,$i,'draft'),$p['channelId']??null);
            $p['channelId']=$ch['id'];
            $this->update('proposals',$p);
            return ['proposal'=>self::proposalDto($p)];
        }
        throw new RuntimeException('Endpunkt nicht gefunden.',404);
    }
}
