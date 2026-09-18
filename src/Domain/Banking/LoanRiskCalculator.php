<?php
declare(strict_types=1);
namespace MyGame\Domain\Banking;

final class LoanRiskCalculator
{
 public function evaluate(array $company,float $amount,int $termMonths,array $policy,array $bank): array
 {
  $assets=max(0.0,(float)($company['assets']??0));$cash=max(0.0,(float)($company['cash']??0));
  $liabilities=max(0.0,(float)($company['liabilities']??0));$revenue=max(0.0,(float)($company['monthly_revenue']??0));
  $profit=(float)($company['monthly_profit']??0);$age=max(0,(int)($company['age_months']??0));
  $equity=$assets+$cash-$liabilities;$totalAfter=max(1.0,$assets+$cash+$amount);
  $equityPct=max(0.0,100*$equity/$totalAfter);
  $score=50.0;
  $score+=min(20.0,$equityPct*0.5);
  $score+=min(10.0,$age/6);
  if($revenue>0){$score+=max(-15.0,min(15.0,($profit/$revenue)*75));$score+=max(-15.0,min(10.0,(($revenue*12)/max(1.0,$amount))*5));}
  else{$score-=20;}
  if($equity<0)$score-=20;
  $score=max(0.0,min(100.0,$score));
  $riskMargin=round(max(0.5,6.0-(($score/100)*5.5)),4);
  $base=(float)($policy['base_rate']??0);$bankMargin=(float)($bank['loan_margin']??0);$productMargin=(float)($bank['product_margin']??0);
  $rate=round($base+$bankMargin+$productMargin+$riskMargin,4);
  $minEquity=(float)($bank['min_equity_percent']??($policy['min_equity']??20));
  $availability=(float)($policy['credit_availability']??100);$requiredScore=max(35.0,65.0-(($availability-100)*0.15));
  $approved=true;$reasons=[];
  if($amount<=0){$approved=false;$reasons[]='Paskolos suma turi būti didesnė už nulį.';}
  if($equityPct<$minEquity){$approved=false;$reasons[]='Nepakanka nuosavo kapitalo.';}
  if($score<$requiredScore){$approved=false;$reasons[]='Per aukšta įmonės kredito rizika.';}
  if($profit<0){$reasons[]='Įmonė šiuo metu dirba nuostolingai.';}
  if($termMonths<1||$termMonths>(int)($bank['max_term_months']??120)){$approved=false;$reasons[]='Netinkamas paskolos terminas.';}
  return ['approved'=>$approved,'risk_score'=>round($score,2),'risk_margin'=>$riskMargin,'interest_rate'=>$rate,'equity_percent'=>round($equityPct,2),'reason'=>$reasons?implode(' ',$reasons):'Įmonė atitinka banko kreditavimo kriterijus.'];
 }
 public function monthlyPayment(float $principal,float $annualRate,int $months): float
 {
  if($months<=0)return 0.0;$r=$annualRate/100/12;
  if(abs($r)<0.0000001)return round($principal/$months,2);
  return round($principal*($r*pow(1+$r,$months))/(pow(1+$r,$months)-1),2);
 }
}
