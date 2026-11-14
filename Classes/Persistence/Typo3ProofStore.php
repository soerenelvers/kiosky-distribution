<?php
declare(strict_types=1);
namespace Kiosky\Kiosky\Persistence;
use Kiosky\Core\PlaybackProofStore;
use TYPO3\CMS\Core\Database\ConnectionPool;
final readonly class Typo3ProofStore implements PlaybackProofStore {
 private const TABLE='tx_kiosky_playback_receipts';
 public function __construct(private ConnectionPool $pool){}
 public function insert(array $rows):void{
  $db=$this->pool->getConnectionForTable(self::TABLE);
  foreach($rows as $r){try{$db->insert(self::TABLE,['display_id'=>$r['displayId'],'receipt_id'=>$r['id'],'play_id'=>$r['playId'],'slide_id'=>$r['slideId'],'channel_id'=>$r['channelId'],'slide_name'=>$r['slideName'],'channel_name'=>$r['channelName'],'started_at'=>$r['startedAt'],'ended_at'=>$r['endedAt'],'seconds'=>$r['seconds'],'received_at'=>$r['receivedAt']]);}catch(\Doctrine\DBAL\Exception\UniqueConstraintViolationException){}}
  $db->executeStatement('DELETE FROM '.self::TABLE.' WHERE received_at<?',[gmdate('Y-m-d\TH:i:s',time()-90*86400).'.000Z']);
 }
 public function report(string $from,string $until,array $displayIds):array{
  if(!$displayIds)return [];$db=$this->pool->getConnectionForTable(self::TABLE);
  return $db->fetchAllAssociative('SELECT display_id AS displayId,slide_id AS slideId,channel_id AS channelId,MAX(slide_name) AS slideName,MAX(channel_name) AS channelName,MIN(started_at) AS firstAt,MAX(ended_at) AS lastAt,SUM(seconds) AS seconds,COUNT(DISTINCT play_id) AS plays,COUNT(*) AS intervals FROM '.self::TABLE.' WHERE ended_at>=? AND ended_at<? AND display_id IN ('.implode(',',array_fill(0,count($displayIds),'?')).') GROUP BY display_id,slide_id,channel_id ORDER BY display_id,slideName LIMIT 10000',[$from,$until,...$displayIds]);
 }
}
