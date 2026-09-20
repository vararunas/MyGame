<?php
declare(strict_types=1);
namespace MyGame\Infrastructure\Company;
use PDO;
final class DatabaseCompanyRepository{
 public function __construct(private PDO $pdo){}
 public function create(string $name,string $city,string $industry,float $capital,string $legalForm='UAB',string $address='',string $manager=''):int{
  $name=trim($name);$address=trim($address);$manager=trim($manager);$legalForm=strtoupper(trim($legalForm));
  if(!in_array($legalForm,['MB','UAB'],true))throw new \RuntimeException('Pasirinkite juridinę formą: MB arba UAB.');
  if(mb_strlen($name)<3||mb_strlen($name)>150)throw new \RuntimeException('Įmonės pavadinimas turi būti 3–150 simbolių.');
  if(!in_array($city,['Vilnius','Kaunas','Klaipėda','Šiauliai','Panevėžys'],true))throw new \RuntimeException('Pasirinkite galimą registracijos miestą.');
  if(!in_array($industry,['retail','logistics','manufacturing','services'],true))throw new \RuntimeException('Pasirinkite veiklos sritį.');
  if($address==='')throw new \RuntimeException('Įveskite registracijos adresą.');
  if($manager==='')throw new \RuntimeException('Įveskite vadovo vardą ir pavardę.');
  $minCapital=$legalForm==='UAB'?2500:1000;
  if($capital<$minCapital)throw new \RuntimeException($legalForm.' pradinis kapitalas turi būti bent '.number_format($minCapital,0,',',' ').' €.');
  $dupe=$this->pdo->prepare('SELECT id FROM companies WHERE LOWER(name)=LOWER(?) LIMIT 1');$dupe->execute([$name]);if($dupe->fetch())throw new \RuntimeException('Toks įmonės pavadinimas jau registruotas.');
  $this->pdo->beginTransaction();try{
   $country=(int)$this->pdo->query("SELECT id FROM countries WHERE code='LT' LIMIT 1")->fetchColumn();if(!$country)throw new \RuntimeException('Valstybė nerasta.');
   $s=$this->pdo->prepare("INSERT INTO companies(country_id,name,city,industry,starting_capital,company_type,status,cash,assets) VALUES(?,?,?,?,?,'player','active',?,?)");$s->execute([$country,$name,$city,$industry,$capital,$capital,$capital]);$id=(int)$this->pdo->lastInsertId();
   $code='LT'.str_pad((string)$id,9,'0',STR_PAD_LEFT);
   $this->pdo->prepare('INSERT INTO company_registrations(company_id,legal_form,registration_code,registered_address,manager_name,share_capital,incorporation_fee,status,registered_at) VALUES(?,?,?,?,?,?,0,\'active\',(SELECT game_date FROM game_clock WHERE id=1))')->execute([$id,$legalForm,$code,$address,$manager,$capital]);
   $this->pdo->prepare('INSERT INTO company_employment(company_id) VALUES(?)')->execute([$id]);$this->pdo->prepare('INSERT INTO company_trade_profiles(company_id) VALUES(?)')->execute([$id]);
   $bank=$this->pdo->query("SELECT id,code FROM banks WHERE is_active=1 ORDER BY id LIMIT 1")->fetch();if(!$bank)throw new \RuntimeException('Nėra aktyvaus banko pradinei įmonės sąskaitai.');
   $bankCode=strtoupper(substr(preg_replace('/[^A-Za-z0-9]/','',(string)$bank['code']),0,2));$number='LT'.str_pad((string)(10+($id%89)),2,'0',STR_PAD_LEFT).str_pad($bankCode,2,'0').str_pad((string)$bank['id'],4,'0',STR_PAD_LEFT).str_pad((string)$id,8,'0',STR_PAD_LEFT);
   $this->pdo->prepare('INSERT INTO company_bank_accounts(company_id,bank_id,account_number,currency,balance,is_primary) VALUES(?,?,?,?,?,1)')->execute([$id,$bank['id'],$number,'EUR',$capital]);$accountId=(int)$this->pdo->lastInsertId();
   $this->pdo->prepare("INSERT INTO bank_account_transactions(account_id,transaction_type,amount,balance_after,reference_type,description,game_date) VALUES(?,?,?,?,?,?,(SELECT game_date FROM game_clock WHERE id=1))")->execute([$accountId,'capital_contribution',$capital,$capital,'company_creation','Steigėjo įnašas · '.$legalForm.' '.$name]);
   $this->pdo->commit();return$id;
  }catch(\Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
 }
 public function properties():array{return$this->pdo->query("SELECT * FROM property_market WHERE is_available=1 ORDER BY city,property_type,monthly_rent")->fetchAll();}
 public function rent(int $companyId,int $propertyId):void{$this->pdo->beginTransaction();try{$s=$this->pdo->prepare('SELECT * FROM property_market WHERE id=? AND is_available=1 FOR UPDATE');$s->execute([$propertyId]);$p=$s->fetch();if(!$p)throw new \RuntimeException('Objektas jau nepasiekiamas.');$q=$this->pdo->prepare('INSERT INTO company_properties(company_id,market_property_id,property_type,city,area_m2,monthly_rent) VALUES(?,?,?,?,?,?)');$q->execute([$companyId,$p['id'],$p['property_type'],$p['city'],$p['area_m2'],$p['monthly_rent']]);$this->pdo->prepare('UPDATE property_market SET is_available=0 WHERE id=?')->execute([$propertyId]);$this->pdo->commit();}catch(\Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}}
}
