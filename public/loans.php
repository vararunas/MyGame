<?php
declare(strict_types=1);session_start();
spl_autoload_register(function(string $class):void{$prefix='MyGame\\';if(!str_starts_with($class,$prefix))return;$path=__DIR__.'/../src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($path))require $path;});
use MyGame\Infrastructure\Database\Connection;use MyGame\Infrastructure\Banking\DatabaseLoanRepository;use MyGame\Domain\Banking\LoanRiskCalculator;
$error=null;$offer=null;$notice=null;
try{$repo=new DatabaseLoanRepository(Connection::make());$company=$repo->ensureDemoCompany();$accounts=$repo->accounts((int)$company['id']);$account=$accounts[0]??null;$transactions=$account?$repo->transactions((int)$account['id']):[];$banks=$repo->banks();$policy=$repo->policy();$calc=new LoanRiskCalculator();
 $repo->refreshLoanPayments((int)$company['id']);
 if($_SERVER['REQUEST_METHOD']==='POST'){if(!hash_equals($_SESSION['loan_csrf']??'',(string)($_POST['csrf']??'')))throw new RuntimeException('Neteisinga saugos užklausa.');
  $action=(string)($_POST['action']??'loan');$bankId=(int)($_POST['bank_id']??0);
  if($action==='pay_installment'){$repo->payInstallment((int)$company['id'],(int)($_POST['payment_id']??0));$notice='Paskolos įmoka sumokėta.';$company=$repo->company((int)$company['id']);$accounts=$repo->accounts((int)$company['id']);$account=$accounts[0]??null;}
  elseif($action==='set_primary'){$repo->setPrimaryAccount((int)$company['id'],(int)($_POST['account_id']??0));$notice='Pagrindinė įmonės sąskaita pakeista.';$accounts=$repo->accounts((int)$company['id']);$account=$accounts[0]??null;}
  elseif($action==='accept_loan'){$appId=(int)($_POST['application_id']??0);$app=$repo->application($appId,(int)$company['id']);if(!$app)throw new RuntimeException('Paskolos pasiūlymas nerastas.');$payment=$calc->monthlyPayment((float)$app['requested_amount'],(float)$app['offered_interest_rate'],(int)$app['requested_term_months']);$repo->acceptApplication($appId,(int)$company['id'],$payment);$notice='Paskolos pasiūlymas priimtas. Lėšos pervestos į '.$app['bank_name'].' sąskaitą.';$company=$repo->company((int)$company['id']);$accounts=$repo->accounts((int)$company['id']);$account=$accounts[0]??null;$offer=null;}
  elseif($action==='open_account'){$opened=$repo->openAccount((int)$company['id'],$bankId);$notice='Sąskaita atidaryta banke „'.$opened['bank_name'].'“. Paskolos paraišką jau galite teikti.';$accounts=$repo->accounts((int)$company['id']);$account=$accounts[0]??$opened;}
  else {$amount=(float)str_replace(',','.',(string)($_POST['amount']??0));$months=(int)($_POST['months']??0);$bank=null;foreach($banks as $b)if((int)$b['id']===$bankId){$bank=$b;break;}if(!$bank)throw new RuntimeException('Pasirinktas bankas nerastas.');
  if(!$repo->accountAtBank((int)$company['id'],$bankId))throw new RuntimeException('Pirmiausia atidarykite įmonės sąskaitą pasirinktame banke.');
  if($amount<(float)$bank['min_amount']||$amount>(float)$bank['max_amount'])throw new RuntimeException('Paskolos suma neatitinka banko ribų.');
  $offer=$calc->evaluate($company,$amount,$months,$policy,$bank);$offer['monthly_payment']=$calc->monthlyPayment($amount,$offer['interest_rate'],$months);$offer['bank']=$bank;$offer['amount']=$amount;$offer['months']=$months;$applicationId=$repo->createApplication((int)$company['id'],$bank,$amount,$months,$offer);$offer['application_id']=$applicationId;}
 }}catch(Throwable $e){$error=$e->getMessage();$banks=$banks??[];$company=$company??[];}
$activeLoans=isset($repo,$company['id'])?$repo->activeLoans((int)$company['id']):[];$loanSchedules=[];if(isset($repo)){foreach($activeLoans as $l)$loanSchedules[(int)$l['id']]=$repo->paymentSchedule((int)$l['id']);}$transactions=$account&&isset($repo)?$repo->transactions((int)$account['id'],10):[];
$_SESSION['loan_csrf']??=bin2hex(random_bytes(24));$csrf=$_SESSION['loan_csrf'];require __DIR__.'/../src/Presentation/loans.php';
