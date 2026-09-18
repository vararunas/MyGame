<?php
declare(strict_types=1);
namespace MyGame\Infrastructure\Database;
use PDO;
final class Connection {
 public static function make(): PDO {
  $file=__DIR__.'/../../../config/database.local.php';
  if(!is_file($file)) throw new \RuntimeException('Nerastas config/database.local.php');
  $c=require $file;
  $dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',$c['host'],$c['port'],$c['database'],$c['charset']??'utf8mb4');
  return new PDO($dsn,$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 }
}
