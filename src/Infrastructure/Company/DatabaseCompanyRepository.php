<?php
declare(strict_types=1);
namespace MyGame\Infrastructure\Company;
use PDO;
final class DatabaseCompanyRepository{
 public function __construct(private PDO $pdo){}
 public function create(string $name,string $city,string $industry,float $capital):int{
  if(trim($name)===''||$capital<1000)throw new \RuntimeException('Įveskite pavadinimą ir bent 1 000 € kapitalą.');
  $this->pdo->beginTransaction();try{
   $country=(int)$this->pdo->query("SELECT id FROM countries WHERE code='LT' LIMIT 1")->fetchColumn();
   $s=$this->pdo->prepare('INSERT INTO companies(country_id,name,city,industry,starting_capital,cash,assets) VALUES(?,?,?,?,?,?,?)');$s->execute([$country,trim($name),$city,$industry,$capital,$capital,$capital]);$id=(int)$this->pdo->lastInsertId();
   $code='LT'.str_pad((string)$id,9,'0',STR_PAD_LEFT);$this->pdo->prepare('INSERT INTO company_registrations(company_id,registration_code) VALUES(?,?)')->execute([$id,$code]);$this->pdo->prepare('INSERT INTO company_employment(company_id) VALUES(?)')->execute([$id]);$this->pdo->prepare('INSERT INTO company_trade_profiles(company_id) VALUES(?)')->execute([$id]);
   $bank=$this->pdo->query("SELECT id,code FROM banks WHERE is_active=1 ORDER BY id LIMIT 1")->fetch();if(!$bank)throw new \RuntimeException('Nėra aktyvaus banko pradinei įmonės sąskaitai.');
   $bankCode=strtoupper(substr(preg_replace('/[^A-Za-z0-9]/','',(string)$bank['code']),0,2));$number='LT'.str_pad((string)(10+($id%89)),2,'0',STR_PAD_LEFT).str_pad($bankCode,2,'0').str_pad((string)$bank['id'],4,'0',STR_PAD_LEFT).str_pad((string)$id,8,'0',STR_PAD_LEFT);
   $this->pdo->prepare('INSERT INTO company_bank_accounts(company_id,bank_id,account_number,currency,balance,is_primary) VALUES(?,?,?,?,?,1)')->execute([$id,$bank['id'],$number,'EUR',$capital]);$accountId=(int)$this->pdo->lastInsertId();
   $this->pdo->prepare("INSERT INTO bank_account_transactions(account_id,transaction_type,amount,balance_after,reference_type,description,game_date) VALUES(?,?,?,?,?,?,(SELECT game_date FROM game_clock WHERE id=1))")->execute([$accountId,'capital_contribution',$capital,$capital,'company_creation','Pradinis įmonės kapitalas']);
   $this->pdo->commit();return$id;
  }catch(\Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
 }
 public function properties():array{return$this->pdo->query("SELECT * FROM property_market WHERE is_available=1 ORDER BY city,property_type,monthly_rent")->fetchAll();}
 public function rent(int $companyId,int $propertyId):void{$this->pdo->beginTransaction();try{$s=$this->pdo->prepare('SELECT * FROM property_market WHERE id=? AND is_available=1 FOR UPDATE');$s->execute([$propertyId]);$p=$s->fetch();if(!$p)throw new \RuntimeException('Objektas jau nepasiekiamas.');$q=$this->pdo->prepare('INSERT INTO company_properties(company_id,market_property_id,property_type,city,area_m2,monthly_rent) VALUES(?,?,?,?,?,?)');$q->execute([$companyId,$p['id'],$p['property_type'],$p['city'],$p['area_m2'],$p['monthly_rent']]);$this->pdo->prepare('UPDATE property_market SET is_available=0 WHERE id=?')->execute([$propertyId]);$this->pdo->commit();}catch(\Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}}
}
