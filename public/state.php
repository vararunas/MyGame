<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['company_id']) || (int)$_SESSION['company_id'] <= 0) { header('Location: company-create.php'); exit; }
spl_autoload_register(function(string $class):void{$prefix='MyGame\\';if(!str_starts_with($class,$prefix))return;$path=__DIR__.'/../src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($path))require $path;});
use MyGame\Infrastructure\Database\Connection;
use MyGame\Infrastructure\State\DatabaseStateRepository;
$error=null;$notice=null;
$_SESSION['csrf']??=bin2hex(random_bytes(24));$csrf=$_SESSION['csrf'];
$d=['company'=>['name'=>'Įmonė'],'reg'=>null,'emp'=>null,'props'=>[],'utils'=>[],'trade'=>null,'obligations'=>[]];
$costs=['rent'=>0,'utilities'=>0,'payroll'=>0,'payroll_tax'=>0,'total'=>0];
try{
 $db=Connection::make();$repo=new DatabaseStateRepository($db);$companyId=(int)$_SESSION['company_id'];$repo->register($companyId);$engine=new \MyGame\Infrastructure\Economy\GameEconomyEngine($db);$engine->sync();$engine->refreshObligations($companyId);$clock=$engine->clock();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($csrf,(string)($_POST['csrf']??'')))throw new RuntimeException('Neteisinga saugos užklausa.');
  $a=(string)($_POST['action']??'');
  if($a==='pay_obligation'){$engine->payObligation($companyId,(int)($_POST['obligation_id']??0));$notice='Įsipareigojimas apmokėtas.';}
  elseif($a==='property')throw new RuntimeException('Patalpas nuomokitės skiltyje Verslo operacijos.');
  elseif($a==='utility'){$repo->addUtility($companyId,(string)($_POST['utility']??''),(float)str_replace(',','.',(string)($_POST['usage']??0)));$notice='Komunalinė sutartis atnaujinta.';}
  elseif($a==='trade'){$repo->toggleTrade($companyId,isset($_POST['import']),isset($_POST['export']));$notice='Prekybos leidimai atnaujinti.';}
 }
 $d=$repo->dashboard($companyId);$costs=$repo->monthlyEstimate($companyId);$clock=$engine->clock();
}catch(Throwable $e){$error=$e->getMessage();}
require __DIR__.'/../src/Presentation/state.php';
