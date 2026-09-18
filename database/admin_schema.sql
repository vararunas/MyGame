SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS countries (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code CHAR(2) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'EUR', is_active TINYINT(1) NOT NULL DEFAULT 1
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS economic_parameters (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, country_id INT UNSIGNED NOT NULL, section VARCHAR(50) NOT NULL, parameter_key VARCHAR(100) NOT NULL, label VARCHAR(150) NOT NULL, value DECIMAL(14,4) NOT NULL, unit VARCHAR(30) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_country_parameter(country_id,parameter_key), CONSTRAINT fk_parameter_country FOREIGN KEY(country_id) REFERENCES countries(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS economic_parameter_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, parameter_id BIGINT UNSIGNED NOT NULL, old_value DECIMAL(14,4) NOT NULL, new_value DECIMAL(14,4) NOT NULL,
 effective_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_history_parameter FOREIGN KEY(parameter_id) REFERENCES economic_parameters(id)
);
ALTER TABLE countries CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE economic_parameters CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE economic_parameter_history CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE economic_parameter_history ADD COLUMN IF NOT EXISTS effective_from DATETIME NULL AFTER new_value;
UPDATE economic_parameter_history SET effective_from=changed_at WHERE effective_from IS NULL;
INSERT INTO countries(code,name,currency) VALUES('LT','Lietuva','EUR') ON DUPLICATE KEY UPDATE name=VALUES(name),currency=VALUES(currency);
SET @lt=(SELECT id FROM countries WHERE code='LT');
INSERT INTO economic_parameters(country_id,section,parameter_key,label,value,unit) VALUES
(@lt,'economy','inflation','Infliacija',2.8,'%'),
(@lt,'economy','gdp_growth','BVP augimas',2.0,'%'),
(@lt,'economy','consumer_confidence','Vartotojų pasitikėjimas',100,'ind.'),
(@lt,'economy','business_confidence','Verslo pasitikėjimas',100,'ind.'),
(@lt,'energy','electricity','Elektros bazinė kaina',0.21,'€/kWh'),
(@lt,'energy','gas','Dujų bazinė kaina',0.09,'€/kWh'),
(@lt,'energy','electricity_capacity','Elektros tiekimo pajėgumas',100,'ind.'),
(@lt,'energy','gas_capacity','Dujų tiekimo pajėgumas',100,'ind.'),
(@lt,'energy','price_volatility','Leistinas kainos svyravimas',10,'%'),
(@lt,'energy','supply_stability','Tiekimo stabilumas',100,'%'),
(@lt,'energy','automatic_pricing','Automatinis kainų valdymas',0,'0/1'),
(@lt,'water','water','Vandens bazinė kaina',1.20,'€/m³'),
(@lt,'water','sewerage','Nuotekų bazinė kaina',1.45,'€/m³'),
(@lt,'water','water_capacity','Vandens tiekimo pajėgumas',100,'ind.'),
(@lt,'water','sewerage_capacity','Nuotekų sistemos pajėgumas',100,'ind.'),
(@lt,'water','water_stability','Tiekimo stabilumas',100,'%'),
(@lt,'water','water_price_volatility','Leistinas kainos svyravimas',5,'%'),
(@lt,'water','automatic_water_pricing','Automatinis kainų valdymas',0,'0/1'),
(@lt,'banking','base_rate','Bazinė palūkanų norma',3.5,'%'),
(@lt,'banking','business_loan','Verslo paskolos marža nuo',2.4,'%'),
(@lt,'banking','deposit_rate','Indėlių palūkanos',2.0,'%'),
(@lt,'banking','max_loan_term','Maksimalus verslo paskolos terminas',10,'metai'),
(@lt,'banking','min_equity','Minimalus nuosavas kapitalas paskolai',20,'%'),
(@lt,'banking','credit_availability','Kreditavimo prieinamumas',100,'ind.'),
(@lt,'taxes','vat','PVM',21,'%'),
(@lt,'taxes','profit_tax','Pelno mokestis',17,'%'),
(@lt,'taxes','dividend_tax','Dividendų mokestis',15,'%'),
(@lt,'taxes','payroll_burden','Darbo mokesčių našta',20,'%'),
(@lt,'taxes','property_tax','Komercinio NT mokestis',1,'%'),
(@lt,'transport','diesel','Dyzelino bazinė kaina',1.62,'€/l'),
(@lt,'transport','petrol','Benzino bazinė kaina',1.55,'€/l'),
(@lt,'transport','road_cost_index','Kelių transporto kaštai',100,'ind.'),
(@lt,'transport','rail_cost_index','Geležinkelių transporto kaštai',100,'ind.'),
(@lt,'transport','sea_cost_index','Jūrų transporto kaštai',100,'ind.'),
(@lt,'transport','transport_capacity','Transporto pajėgumas',100,'ind.'),
(@lt,'labour','minimum_wage','Minimalus atlyginimas',1250,'€'),
(@lt,'labour','average_wage','Vidutinis atlyginimas',2300,'€'),
(@lt,'labour','unemployment','Nedarbo lygis',7,'%'),
(@lt,'labour','labour_supply','Darbo jėgos pasiūla',100,'ind.'),
(@lt,'labour','productivity','Darbo našumas',100,'ind.'),
(@lt,'real_estate','commercial_rent','Komercinė nuoma',9.5,'€/m²'),
(@lt,'real_estate','warehouse_rent','Sandėlių nuoma',6.0,'€/m²'),
(@lt,'real_estate','industrial_rent','Gamybinių patalpų nuoma',7.0,'€/m²'),
(@lt,'real_estate','property_price_index','NT kainų indeksas',100,'ind.'),
(@lt,'real_estate','vacancy_rate','Laisvų komercinių patalpų dalis',8,'%'),
(@lt,'trade','import_index','Importo kaštų indeksas',100,'ind.'),
(@lt,'trade','export_index','Eksporto kaštų indeksas',100,'ind.'),
(@lt,'trade','customs_rate','Bendras muito tarifas',0,'%'),
(@lt,'trade','border_efficiency','Sienų ir muitinės efektyvumas',100,'ind.'),
(@lt,'population','population_total','Gyventojų skaičius',2890000,'žm.'),
(@lt,'population','purchasing_power','Perkamosios galios indeksas',100,'ind.'),
(@lt,'population','population_growth','Gyventojų pokytis',0,'%'),
(@lt,'population','working_age_share','Darbingo amžiaus gyventojai',64,'%'),
(@lt,'population','urbanisation','Urbanizacija',68,'%')
ON DUPLICATE KEY UPDATE label=VALUES(label),unit=VALUES(unit);
