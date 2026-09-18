<?php
declare(strict_types=1);
namespace MyGame\Infrastructure\Economy;
use MyGame\Domain\Economy\EconomicParameter;
final class EconomyRepository {
 /** @return EconomicParameter[] */
 public function all(): array { return array_map(fn($r)=>new EconomicParameter($r['key'],$r['label'],(float)$r['value'],$r['unit'],$r['section']), require __DIR__.'/../../../database/seeds/economy.php'); }
 /** @return EconomicParameter[] */
 public function section(string $section): array { return array_values(array_filter($this->all(),fn($p)=>$p->section===$section)); }
}
