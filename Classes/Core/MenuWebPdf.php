<?php

declare(strict_types=1);
namespace Kiosky\Core;
use RuntimeException;

/** Public HTTPS only. Downloads and renderer output never enter state before all pages succeed. */
final class MenuWebPdf
{
    public const LIMIT = 20971520;
    public static function publicAddress(string $ip): bool
    {
        if (filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)) {
            [$a,$b,$c] = array_map('intval',explode('.',$ip));
            return !(in_array($a,[0,10,127],true) || $a>=224 || ($a===100 && $b>=64 && $b<=127) || ($a===169 && $b===254) || ($a===172 && $b>=16 && $b<=31) || ($a===192 && ($b===168 || ($b===0 && in_array($c,[0,2],true)) || ($b===88 && $c===99))) || ($a===198 && ($b===18 || $b===19 || ($b===51 && $c===100))) || ($a===203 && $b===0 && $c===113));
        }
        if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6))return false;
        $parts=explode(':',$ip);$first=hexdec($parts[0]);$second=hexdec($parts[1] ?: '0');
        return $first>=0x2000 && $first<=0x3fff && $first!==0x2002 && !($first===0x2001 && ($second<0x200 || $second===0xdb8)) && !($first===0x3fff && $second<0x1000);
    }
    public static function url(mixed $value): string
    {
        if (!is_string($value) || strlen($value)>2048 || preg_match('/[\x00-\x20\x7f\\\\]/',$value)) throw new RuntimeException('Ungültige PDF-Adresse.',422);
        $url=parse_url($value);$host=strtolower(trim($url['host'] ?? '','[]'));
        if (!$url || ($url['scheme'] ?? '')!=='https' || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || (isset($url['port']) && $url['port']!==443) || !$host || !str_contains($host,'.') || $host==='localhost' || str_ends_with($host,'.local') || str_ends_with($host,'.localhost') || (filter_var($host,FILTER_VALIDATE_IP) && !self::publicAddress($host))) throw new RuntimeException('Nur öffentliche HTTPS-PDF-Adressen ohne Zugangsdaten, Fragment oder Sonderport sind erlaubt.',422);
        return $value;
    }
    /** Resolve relative Location values before applying the same public URL policy. */
    public static function redirect(string $base,string $location): string
    {
        if(preg_match('#^[a-z][a-z0-9+.-]*:#i',$location))return self::url($location);
        if(str_starts_with($location,'//'))return self::url('https:'.$location);
        $parts=parse_url(self::url($base));$origin='https://'.$parts['host'];$path=$parts['path'] ?? '/';
        if(str_starts_with($location,'?'))return self::url($origin.$path.$location);
        $target=parse_url($location);if(!$target || isset($target['fragment']))throw new RuntimeException('Ungültige PDF-Weiterleitung.',422);
        $relative=$target['path'] ?? '';if(!str_starts_with($relative,'/'))$relative=preg_replace('#[^/]*$#','',$path).$relative;
        $segments=[];foreach(explode('/',$relative) as $segment){if($segment==='..')array_pop($segments);elseif($segment!=='.' && $segment!=='')$segments[]=$segment;}
        return self::url($origin.'/'.implode('/',$segments).(isset($target['query'])?'?'.$target['query']:''));
    }
    public static function download(string $url): string
    {
        if (!extension_loaded('curl')) throw new RuntimeException('Weblink-Abonnements benötigen die PHP-Erweiterung cURL.',503);
        for($hop=0;$hop<4;$hop++) {
            $url=self::url($url);$parts=parse_url($url);$host=trim($parts['host'],'[]');
            $ips=filter_var($host,FILTER_VALIDATE_IP)?[$host]:array_values(array_filter(array_map(static fn(array $r): string=>$r['ip'] ?? $r['ipv6'] ?? '',@dns_get_record($host,DNS_A|DNS_AAAA) ?: [])));
            if (!$ips || array_filter($ips,static fn(string $ip): bool=>!self::publicAddress($ip))) throw new RuntimeException('Die PDF-Adresse verweist auf ein gesperrtes Netzwerk.',422);
            $curl=curl_init($url);$data='';$location='';$sizeExceeded=false;
            curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_RESOLVE=>[$host.':443:'.(str_contains($ips[0],':')?'['.$ips[0].']':$ips[0])],CURLOPT_HTTPHEADER=>['Accept: application/pdf','Accept-Encoding: identity'],CURLOPT_USERAGENT=>'Kiosky PDF subscription',
                CURLOPT_HEADERFUNCTION=>static function($handle,string $line) use (&$location): int {if(strncasecmp($line,'Location:',9)===0)$location=trim(substr($line,9));return strlen($line);},
                CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk) use (&$data,&$sizeExceeded): int {if(strlen($data)+strlen($chunk)>self::LIMIT){$sizeExceeded=true;return 0;}$data.=$chunk;return strlen($chunk);},]);
            $ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
            if ($ok===false || $sizeExceeded) throw new RuntimeException('PDF-Download fehlgeschlagen, Zeitlimit oder mehr als 20 MiB.',422);
            if($status>=300 && $status<400){
                if(!$location || $hop===3)throw new RuntimeException('Zu viele oder ungültige PDF-Weiterleitungen.',422);
                $url=self::redirect($url,$location);continue;
            }
            if($status!==200 || !str_contains(substr($data,0,1024),'%PDF-'))throw new RuntimeException('Die Adresse liefert keine PDF-Datei.',422);
            return $data;
        }
        throw new RuntimeException('PDF-Download fehlgeschlagen.',422);
    }
    private static function execute(array $command,string $cwd,int $timeout=5): string
    {
        if (!function_exists('proc_open')) throw new RuntimeException('Poppler benötigt die freigegebene PHP-Funktion proc_open.',503);
        $pipes=[];$process=@proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$cwd,array_replace(getenv(),['LC_ALL'=>'C']),['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('Poppler konnte nicht gestartet werden.',503);
        fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$output='';$start=microtime(true);$exit=-1;
        try{
            do{$output.=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);$status=proc_get_status($process);if(strlen($output)>100000 || microtime(true)-$start>$timeout){proc_terminate($process,9);throw new RuntimeException('PDF-Verarbeitung hat das Zeitlimit überschritten.',422);}if(!$status['running']){$exit=$status['exitcode'];break;}usleep(20000);}while(true);
            $output.=stream_get_contents($pipes[1]);
        }finally{fclose($pipes[1]);fclose($pipes[2]);proc_close($process);}
        if($exit!==0)throw new RuntimeException('PDF-Verarbeitung fehlgeschlagen. PDF und Poppler-Installation prüfen.',422);
        return $output;
    }
    public static function available(): bool
    {
        try{if(!extension_loaded('curl'))return false;self::execute(['pdfinfo','-v'],sys_get_temp_dir());self::execute(['pdftoppm','-v'],sys_get_temp_dir());return true;}catch(\Throwable){return false;}
    }
    public static function render(string $data): array
    {
        if(!self::available())throw new RuntimeException('Für Weblink-Abonnements benötigt der Server Poppler (pdfinfo, pdftoppm), PHP cURL und proc_open.',503);
        $directory=sys_get_temp_dir().'/kiosky-pdf-'.bin2hex(random_bytes(12));
        if(!mkdir($directory,0700))throw new RuntimeException('Temporäres PDF-Verzeichnis konnte nicht angelegt werden.',503);
        try{
            file_put_contents($directory.'/source.pdf',$data);chmod($directory.'/source.pdf',0600);
            $info=self::execute(['pdfinfo','source.pdf'],$directory,15);preg_match('/^Pages:\s+(\d+)/m',$info,$matches);$count=(int)($matches[1] ?? 0);
            if($count<1 || $count>20 || preg_match('/^Encrypted:\s+yes/m',$info))throw new RuntimeException('Nur unverschlüsselte PDFs mit 1 bis 20 Seiten sind erlaubt.',422);
            self::execute(['pdftoppm','-f','1','-l',(string)$count,'-scale-to','1920','-jpeg','-jpegopt','quality=85','source.pdf','page'],$directory,60);
            $files=glob($directory.'/page-*.jpg') ?: [];natsort($files);
            if(count($files)!==$count)throw new RuntimeException('PDF-Seiten wurden nicht vollständig gerendert.',422);
            $pages=[];$total=0;
            foreach($files as $file){$image=file_get_contents($file);$total+=strlen($image);$size=getimagesizefromstring($image);
                if(!$size || $size[2]!==IMAGETYPE_JPEG || $size[0]>1920 || $size[1]>1920 || strlen($image)>2000000 || $total>self::LIMIT)throw new RuntimeException('Gerenderte PDF-Seiten sind ungültig oder zu groß.',422);
                $pages[]=['src'=>'data:image/jpeg;base64,'.base64_encode($image),'width'=>$size[0],'height'=>$size[1]];
            }
            return $pages;
        }finally{foreach(glob($directory.'/*') ?: [] as $file)unlink($file);rmdir($directory);}
    }
    public static function document(array $page,string $fileName,int $pageNumber,int $pageCount,?array $existing=null): array
    {
        $orientation=$existing['designOrientation'] ?? ($page['height']>$page['width']?'portrait':'landscape');
        $element=['id'=>'menu-pdf-image','type'=>'image','menuPdfImage'=>true,'x'=>0,'y'=>0,'width'=>100,'height'=>100,'src'=>$page['src'],'opacity'=>100,'rotation'=>0];
        if($existing && !array_filter($existing['elements'] ?? [],static fn(array $e): bool=>!empty($e['menuPdfImage'])))throw new RuntimeException('Das PDF-Bildfeld einer abonnierten Slide fehlt. Bitte wiederherstellen.',422);
        return array_replace($existing ?? [],['menuPdf'=>['fileName'=>$fileName,'pageNumber'=>$pageNumber,'pageCount'=>$pageCount],'designOrientation'=>$orientation,'settings'=>$existing['settings'] ?? ['background'=>['mode'=>'color','color1'=>'#ffffff'],'clock'=>['enabled'=>false],'logo'=>['enabled'=>false]],'elements'=>$existing?array_map(static fn(array $e): array=>!empty($e['menuPdfImage'])?array_replace($e,['src'=>$page['src']]):$e,$existing['elements']):[$element]]);
    }
}
