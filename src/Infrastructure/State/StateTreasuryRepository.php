<?php
declare(strict_types=1);
namespace MyGame\Infrastructure\State;
use PDO;
final class StateTreasuryRepository{
 public function __construct(private PDO $pdo){}
 public function ensureAccounts():void{
  $country=(int)$this->pdo->query("SELECT id FROM countries WHERE code='LT' LIMIT 1")->fetchColumn();$bank=(int)$this->pdo->query("SELECT id FROM banks WHERE country_id={$country} AND code='VB' LIMIT 1")->fetchColumn();if(!$country||!$bank)return;
  $defs=[['VMI','Valstybinė mokesčių inspekcija','tax'],['SODRA','Valstybinio socialinio draudimo fondo valdyba','social'],['NT','Valstybės turto ir nekilnojamojo turto administracija','property'],['UTILITIES','Valstybinis energijos ir komunalinių paslaugų centras','utility'],['CUSTOMS','Lietuvos muitinė','customs'],['TREASURY','Lietuvos Respublikos valstybės iždas','treasury']];
  foreach($defs as $d){$this->pdo->prepare('INSERT INTO state_institutions(country_id,code,name,institution_type) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),institution_type=VALUES(institution_type)')->execute([$country,$d[0],$d[1],$d[2]]);$i=$this->one('SELECT id FROM state_institutions WHERE code=?',[$d[0]]);$id=(int)$i['id'];if(!$this->one('SELECT id FROM state_bank_accounts WHERE institution_id=?',[$id])){$number='LT90VB'.str_pad((string)$bank,4,'0',STR_PAD_LEFT).str_pad((string)$id,8,'0',STR_PAD_LEFT);$this->pdo->prepare('INSERT INTO state_bank_accounts(institution_id,bank_id,account_number) VALUES(?,?,?)')->execute([$id,$bank,$number]);}}
 }
 public function recipient(string $code):array{$this->ensureAccounts();$s=$this->pdo->prepare('SELECT i.id institution_id,i.code,i.name,a.id account_id,a.account_number,a.balance,b.name bank_name FROM state_institutions i JOIN state_bank_accounts a ON a.institution_id=i.id AND a.is_primary=1 JOIN banks b ON b.id=a.bank_id WHERE i.code=? AND i.is_active=1 LIMIT 1');$s->execute([$code]);$r=$s->fetch();if(!$r)throw new \RuntimeException('Valstybės gavėjo sąskaita nerasta: '.$code);return$r;}
 public function institutions():array{$this->ensureAccounts();return$this->pdo->query('SELECT i.*,a.account_number,a.balance,b.name bank_name FROM state_institutions i LEFT JOIN state_bank_accounts a ON a.institution_id=i.id AND a.is_primary=1 LEFT JOIN banks b ON b.id=a.bank_id WHERE i.is_active=1 ORDER BY i.id')->fetchAll();}
 public function credit(int $companyId,string $code,float $amount,string $type,int $referenceId,string $description):array{$r=$this->recipient($code);$balance=(float)$r['balance']+$amount;$this->pdo->prepare('UPDATE state_bank_accounts SET balance=? WHERE id=?')->execute([$balance,$r['account_id']]);$this->pdo->prepare('INSERT INTO state_bank_transactions(account_id,company_id,transaction_type,amount,balance_after,reference_type,reference_id,description) VALUES(?,?,?,?,?,?,?,?)')->execute([$r['account_id'],$companyId,$type,$amount,$balance,'obligation',$referenceId,$description]);return$r+['new_balance'=>$balance];}
 private function one(string $sql,array $p=[]):array|false{$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetch();}
}
