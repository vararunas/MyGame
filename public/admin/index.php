<?php
declare(strict_types=1);
session_start();
spl_autoload_register(function(string $class):void{$prefix='MyGame\\';if(!str_starts_with($class,$prefix))return;$path=__DIR__.'/../../src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($path))require $path;});
$admin=require __DIR__.'/../../config/admin.php';
use MyGame\Infrastructure\Database\Connection;
use MyGame\Infrastructure\Economy\DatabaseEconomyRepository;use MyGame\Infrastructure\Economy\GameEconomyEngine;
$section=$_GET['section']??'economy';if(!isset($admin['sections'][$section]))$section='economy';
$error=null;$saved=false;$history=[];$banks=[];$effectiveFrom=date('Y-m-d');$clock=[];$timeNotice=null;
try{
 $pdo=Connection::make();$repo=new DatabaseEconomyRepository($pdo);$engine=new GameEconomyEngine($pdo);$engine->sync();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($_SESSION['csrf']??'',(string)($_POST['csrf']??'')))throw new RuntimeException('Neteisinga saugos užklausa. Perkrauk puslapį.');
  $timeAction=(string)($_POST['time_action']??'');if($timeAction!==''){$days=['day'=>1,'week'=>7,'month'=>31][$timeAction]??0;if($timeAction==='clear_history'){if((string)($_POST['confirm_clear']??'')!=='YES')throw new RuntimeException('Testinės istorijos išvalymas nepatvirtintas.');$engine->clearTestHistory();$timeNotice='Testinė ekonomikos istorija išvalyta. Įmonės, banko sąskaitos, paskolos, darbuotojai, patalpos ir atsargos paliktos.';}elseif($timeAction==='reset'){$engine->resetTestOffset();$timeNotice='Testinis laikas grąžintas į realią datą.';}elseif($days>0){$engine->advance($days);$timeNotice='Testinis laikas pasuktas pirmyn.';}}else{$effectiveFrom=(string)($_POST['effective_from']??date('Y-m-d'));$repo->updateSection($section,$_POST['values']??[],$effectiveFrom);$saved=true;}
 }
 $clock=$engine->clock();$parameters=$repo->section($section);$history=$repo->history($section);if($section==='banking')$banks=$repo->banks();
}catch(Throwable $e){$error=$e->getMessage();$parameters=[];}
$_SESSION['csrf']??=bin2hex(random_bytes(24));$csrf=$_SESSION['csrf'];
require __DIR__.'/../../src/Presentation/admin/country.php';
