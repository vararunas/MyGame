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
 public function updateSection(string $section,array $values,string $effectiveFrom): void {
  $countryId=$this->countryId();$date=\DateTimeImmutable::createFromFormat('Y-m-d',$effectiveFrom);
  if(!$date||$date->format('Y-m-d')!==$effectiveFrom)throw new \RuntimeException('Neteisinga įsigaliojimo data.');
  $effective=$date->format('Y-m-d 00:00:00');$this->pdo->beginTransaction();
  try{
   $get=$this->pdo->prepare('SELECT id,value FROM economic_parameters WHERE country_id=? AND section=? AND parameter_key=? FOR UPDATE');
   $upd=$this->pdo->prepare('UPDATE economic_parameters SET value=? WHERE id=?');
   $hist=$this->pdo->prepare('INSERT INTO economic_parameter_history(parameter_id,old_value,new_value,effective_from) VALUES(?,?,?,?)');
   foreach($values as $key=>$raw){
    if(!is_scalar($raw))continue;$raw=str_replace(',','.',trim((string)$raw));if(!is_numeric($raw))throw new \RuntimeException('Reikšmė „'.$key.'“ turi būti skaičius.');$new=(float)$raw;
    $get->execute([$countryId,$section,(string)$key]);$row=$get->fetch();if(!$row)continue;$old=(float)$row['value'];
    if(abs($old-$new)<0.000001)continue;$upd->execute([$new,$row['id']]);$hist->execute([$row['id'],$old,$new,$effective]);
   }
   $this->pdo->commit();
  }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
 public function banks(): array {
  $countryId=$this->countryId();
  $params=[];foreach($this->section('banking') as $p)$params[$p->key]=$p->value;
  $base=(float)($params['base_rate']??0);$stateMargin=(float)($params['business_loan']??0);
  $s=$this->pdo->prepare('SELECT b.id,b.name,b.code,b.capital,b.liquidity_index,b.risk_appetite,b.loan_margin,b.deposit_margin,b.is_active,p.name product_name,p.min_amount,p.max_amount,p.max_term_months,p.margin product_margin,p.min_equity_percent FROM banks b LEFT JOIN bank_loan_products p ON p.bank_id=b.id AND p.is_active=1 WHERE b.country_id=? ORDER BY b.id');
  $s->execute([$countryId]);$rows=$s->fetchAll();
  foreach($rows as &$r){$r['effective_loan_rate']=$base+$stateMargin+(float)$r['loan_margin']+(float)($r['product_margin']??0);$r['effective_deposit_rate']=max(0,(float)($params['deposit_rate']??0)+(float)$r['deposit_margin']);}
  unset($r);return $rows;
 }
 public function history(string $section,int $limit=30): array {
  $limit=max(1,min(100,$limit));
  $sql='SELECT p.label,p.unit,h.old_value,h.new_value,h.effective_from,h.changed_at FROM economic_parameter_history h JOIN economic_parameters p ON p.id=h.parameter_id WHERE p.country_id=? AND p.section=? ORDER BY h.changed_at DESC,h.id DESC LIMIT '.$limit;
  $s=$this->pdo->prepare($sql);$s->execute([$this->countryId(),$section]);return $s->fetchAll();
 }
}
