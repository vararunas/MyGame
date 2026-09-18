<?php
declare(strict_types=1);
// Prototype values for game balancing, not asserted as current real-world tariffs.
return [
 ['key'=>'inflation','label'=>'Infliacija','value'=>2.8,'unit'=>'%','section'=>'economy'],
 ['key'=>'electricity','label'=>'Elektra','value'=>0.21,'unit'=>'€/kWh','section'=>'energy'],
 ['key'=>'gas','label'=>'Dujos','value'=>0.09,'unit'=>'€/kWh','section'=>'energy'],
 ['key'=>'water','label'=>'Vanduo','value'=>1.20,'unit'=>'€/m³','section'=>'water'],
 ['key'=>'sewerage','label'=>'Nuotekos','value'=>1.45,'unit'=>'€/m³','section'=>'water'],
 ['key'=>'base_rate','label'=>'Bazinė palūkanų norma','value'=>3.5,'unit'=>'%','section'=>'banking'],
 ['key'=>'business_loan','label'=>'Verslo paskolos nuo','value'=>5.9,'unit'=>'%','section'=>'banking'],
 ['key'=>'vat','label'=>'PVM','value'=>21,'unit'=>'%','section'=>'taxes'],
 ['key'=>'profit_tax','label'=>'Pelno mokestis','value'=>17,'unit'=>'%','section'=>'taxes'],
 ['key'=>'diesel','label'=>'Dyzelinas','value'=>1.62,'unit'=>'€/l','section'=>'transport'],
 ['key'=>'minimum_wage','label'=>'Minimalus atlyginimas','value'=>1250,'unit'=>'€','section'=>'labour'],
 ['key'=>'commercial_rent','label'=>'Komercinė nuoma (bazė)','value'=>9.5,'unit'=>'€/m²','section'=>'real_estate'],
 ['key'=>'import_index','label'=>'Importo kaštų indeksas','value'=>100,'unit'=>'ind.','section'=>'trade'],
 ['key'=>'purchasing_power','label'=>'Perkamosios galios indeksas','value'=>100,'unit'=>'ind.','section'=>'population'],
];
