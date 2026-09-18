<?php
declare(strict_types=1);
session_start();
spl_autoload_register(function(string $class):void{$prefix='MyGame\\';if(!str_starts_with($class,$prefix))return;$path=__DIR__.'/../../src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($path))require $path;});
$admin=require __DIR__.'/../../config/admin.php';
use MyGame\Infrastructure\Database\Connection;
use MyGame\Infrastructure\Economy\DatabaseEconomyRepository;
$section=$_GET['section']??'economy';if(!isset($admin['sections'][$section]))$section='economy';
$error=null;$saved=false;$history=[];$effectiveFrom=date('Y-m-d');
try{
 $repo=new DatabaseEconomyRepository(Connection::make());
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($_SESSION['csrf']??'',(string)($_POST['csrf']??'')))throw new RuntimeException('Neteisinga saugos užklausa. Perkrauk puslapį.');
  $effectiveFrom=(string)($_POST['effective_from']??date('Y-m-d'));
  $repo->updateSection($section,$_POST['values']??[],$effectiveFrom);$saved=true;
 }
 $parameters=$repo->section($section);$history=$repo->history($section);
}catch(Throwable $e){$error=$e->getMessage();$parameters=[];}
$_SESSION['csrf']??=bin2hex(random_bytes(24));$csrf=$_SESSION['csrf'];
require __DIR__.'/../../src/Presentation/admin/country.php';
