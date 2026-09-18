<?php
declare(strict_types=1);session_start();
spl_autoload_register(function(string $class):void{$prefix='MyGame\\';if(!str_starts_with($class,$prefix))return;$path=__DIR__.'/../src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($path))require $path;});
use MyGame\Infrastructure\Database\Connection;use MyGame\Infrastructure\Banking\DatabaseLoanRepository;use MyGame\Domain\Banking\LoanRiskCalculator;
$error=null;$offer=null;
try{$repo=new DatabaseLoanRepository(Connection::make());$company=$repo->ensureDemoCompany();$banks=$repo->banks();$policy=$repo->policy();$calc=new LoanRiskCalculator();
 if($_SERVER['REQUEST_METHOD']==='POST'){if(!hash_equals($_SESSION['loan_csrf']??'',(string)($_POST['csrf']??'')))throw new RuntimeException('Neteisinga saugos užklausa.');
  $bankId=(int)($_POST['bank_id']??0);$amount=(float)str_replace(',','.',(string)($_POST['amount']??0));$months=(int)($_POST['months']??0);$bank=null;foreach($banks as $b)if((int)$b['id']===$bankId){$bank=$b;break;}if(!$bank)throw new RuntimeException('Pasirinktas bankas nerastas.');
  if($amount<(float)$bank['min_amount']||$amount>(float)$bank['max_amount'])throw new RuntimeException('Paskolos suma neatitinka banko ribų.');
  $offer=$calc->evaluate($company,$amount,$months,$policy,$bank);$offer['monthly_payment']=$calc->monthlyPayment($amount,$offer['interest_rate'],$months);$offer['bank']=$bank;$offer['amount']=$amount;$offer['months']=$months;$repo->createApplication((int)$company['id'],$bank,$amount,$months,$offer);
 }}catch(Throwable $e){$error=$e->getMessage();$banks=$banks??[];$company=$company??[];}
$_SESSION['loan_csrf']??=bin2hex(random_bytes(24));$csrf=$_SESSION['loan_csrf'];require __DIR__.'/../src/Presentation/loans.php';
