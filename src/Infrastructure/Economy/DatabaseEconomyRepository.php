<?php
declare(strict_types=1);
namespace MyGame\Infrastructure\Economy;
use PDO;
use MyGame\Domain\Economy\EconomicParameter;
final class DatabaseEconomyRepository {
 public function __construct(private PDO $pdo,private string $countryCode='LT'){}
 private function countryId(): int {
  $s=$this->pdo->prepare('SELECT id FROM countries WHERE code=? LIMIT 1');$s->execute([$this->countryCode]);$id=$s->fetchColumn();
  if(!$id) throw new \RuntimeException('Valstybė '.$this->countryCode.' nerasta DB. Importuok database/admin_schema.sql.');
  return (int)$id;
 }
 /** @return EconomicParameter[] */
 public function section(string $section): array {
  $s=$this->pdo->prepare('SELECT parameter_key,label,value,unit,section FROM economic_parameters WHERE country_id=? AND section=? ORDER BY id');
  $s->execute([$this->countryId(),$section]);
  return array_map(fn($r)=>new EconomicParameter($r['parameter_key'],$r['label'],(float)$r['value'],$r['unit'],$r['section']),$s->fetchAll());
 }
 public function updateSection(string $section,array $values): void {
  $countryId=$this->countryId();$this->pdo->beginTransaction();
  try{
   $get=$this->pdo->prepare('SELECT id,value FROM economic_parameters WHERE country_id=? AND section=? AND parameter_key=? FOR UPDATE');
   $upd=$this->pdo->prepare('UPDATE economic_parameters SET value=? WHERE id=?');
   $hist=$this->pdo->prepare('INSERT INTO economic_parameter_history(parameter_id,old_value,new_value) VALUES(?,?,?)');
   foreach($values as $key=>$raw){
    if(!is_scalar($raw)||!is_numeric((string)$raw))continue;$new=(float)$raw;
    $get->execute([$countryId,$section,(string)$key]);$row=$get->fetch();if(!$row)continue;$old=(float)$row['value'];
    if(abs($old-$new)<0.000001)continue;$upd->execute([$new,$row['id']]);$hist->execute([$row['id'],$old,$new]);
   }
   $this->pdo->commit();
  }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
}
