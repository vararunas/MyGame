<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
echo '<!doctype html><meta charset="utf-8"><body style="background:#080d15;color:#e8edf5;font-family:Arial;padding:30px"><h2>STATE diagnostika</h2>';
flush();
$steps=[];
try{
 $steps[]='PHP veikia';
 session_start(); $steps[]='Sesija veikia';
 spl_autoload_register(function($class){$prefix='MyGame\\';if(strpos($class,$prefix)!==0)return;$p=__DIR__.'/../src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($p))require $p;});
 $steps[]='Autoload veikia';
 $db=\MyGame\Infrastructure\Database\Connection::make(); $steps[]='DB prisijungimas veikia';
 $repo=new \MyGame\Infrastructure\State\DatabaseStateRepository($db); $steps[]='State repository veikia';
 $companyId=1; $repo->register($companyId); $steps[]='Registracija veikia';
 $d=$repo->dashboard($companyId); $steps[]='Dashboard užklausa veikia';
 $costs=$repo->monthlyEstimate($companyId); $steps[]='Kaštų skaičiavimas veikia';
 echo '<div style="padding:18px;background:#10281f;border:1px solid #285c49;border-radius:8px">Visi testai praėjo. Problema buvo presentation sluoksnyje.</div>';
 foreach($steps as $s)echo '<p style="color:#67dbaa">✓ '.htmlspecialchars($s).'</p>';
}catch(Throwable $e){
 foreach($steps as $s)echo '<p style="color:#67dbaa">✓ '.htmlspecialchars($s).'</p>';
 echo '<div style="margin-top:20px;padding:18px;background:#29151a;border:1px solid #65313a;border-radius:8px;color:#ff9aa8"><b>KLAIDA:</b><br>'.htmlspecialchars($e->getMessage()).'<br><br><b>Failas:</b> '.htmlspecialchars($e->getFile()).'<br><b>Eilutė:</b> '.(int)$e->getLine().'</div>';
}
echo '</body>';