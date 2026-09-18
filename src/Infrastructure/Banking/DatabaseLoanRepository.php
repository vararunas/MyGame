<?php
declare(strict_types=1);
namespace MyGame\Infrastructure\Banking;
use PDO;
final class DatabaseLoanRepository {
 public function __construct(private PDO $pdo){}
 public function company(int $id): ?array {$s=$this->pdo->prepare('SELECT * FROM companies WHERE id=?');$s->execute([$id]);return $s->fetch()?:null;}
 public function ensureDemoCompany(): array {$c=$this->company(1);if($c)return $c;$country=(int)$this->pdo->query("SELECT id FROM countries WHERE code='LT' LIMIT 1")->fetchColumn();$s=$this->pdo->prepare('INSERT INTO companies(country_id,name,cash,assets,liabilities,monthly_revenue,monthly_profit,age_months) VALUES(?,?,?,?,?,?,?,?)');$s->execute([$country,'Mano įmonė',25000,40000,5000,18000,3200,18]);return $this->company((int)$this->pdo->lastInsertId());}
 public function banks(): array {$sql="SELECT b.*,p.id product_id,p.name product_name,p.min_amount,p.max_amount,p.max_term_months,p.margin product_margin,p.min_equity_percent FROM banks b JOIN bank_loan_products p ON p.bank_id=b.id AND p.is_active=1 WHERE b.is_active=1 ORDER BY b.id";return $this->pdo->query($sql)->fetchAll();}
 public function policy(): array {$s=$this->pdo->query("SELECT parameter_key,value FROM economic_parameters WHERE section='banking'");$r=[];foreach($s as $p)$r[$p['parameter_key']]=(float)$p['value'];return $r;}
 public function createApplication(int $companyId,array $bank,float $amount,int $months,array $result): int {$status=$result['approved']?'approved':'rejected';$s=$this->pdo->prepare('INSERT INTO loan_applications(company_id,bank_id,loan_product_id,requested_amount,requested_term_months,equity_percent,risk_score,risk_margin,offered_interest_rate,status,decision_reason,decided_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,NOW())');$s->execute([$companyId,$bank['id'],$bank['product_id'],$amount,$months,$result['equity_percent'],$result['risk_score'],$result['risk_margin'],$result['interest_rate'],$status,$result['reason']]);return (int)$this->pdo->lastInsertId();}
}
