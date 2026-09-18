CREATE TABLE IF NOT EXISTS countries (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code CHAR(2) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'EUR', is_active TINYINT(1) NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS economic_parameters (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, country_id INT UNSIGNED NOT NULL, section VARCHAR(50) NOT NULL, parameter_key VARCHAR(100) NOT NULL, label VARCHAR(150) NOT NULL, value DECIMAL(14,4) NOT NULL, unit VARCHAR(30) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_country_parameter(country_id,parameter_key), CONSTRAINT fk_parameter_country FOREIGN KEY(country_id) REFERENCES countries(id)
);
CREATE TABLE IF NOT EXISTS economic_parameter_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, parameter_id BIGINT UNSIGNED NOT NULL, old_value DECIMAL(14,4) NOT NULL, new_value DECIMAL(14,4) NOT NULL,
 effective_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_history_parameter FOREIGN KEY(parameter_id) REFERENCES economic_parameters(id)
);
ALTER TABLE economic_parameter_history ADD COLUMN IF NOT EXISTS effective_from DATETIME NULL AFTER new_value;
UPDATE economic_parameter_history SET effective_from=changed_at WHERE effective_from IS NULL;
INSERT INTO countries(code,name,currency) VALUES('LT','Lietuva','EUR') ON DUPLICATE KEY UPDATE name=VALUES(name),currency=VALUES(currency);
SET @lt=(SELECT id FROM countries WHERE code='LT');
INSERT INTO economic_parameters(country_id,section,parameter_key,label,value,unit) VALUES
(@lt,'economy','inflation','Infliacija',2.8,'%'),
(@lt,'energy','electricity','Elektros bazinė kaina',0.21,'€/kWh'),
(@lt,'energy','gas','Dujų bazinė kaina',0.09,'€/kWh'),
(@lt,'energy','electricity_capacity','Elektros tiekimo pajėgumas',100,'ind.'),
(@lt,'energy','gas_capacity','Dujų tiekimo pajėgumas',100,'ind.'),
(@lt,'energy','price_volatility','Leistinas kainos svyravimas',10,'%'),
(@lt,'energy','supply_stability','Tiekimo stabilumas',100,'%'),
(@lt,'energy','automatic_pricing','Automatinis kainų valdymas',0,'0/1'),
(@lt,'water','water','Vanduo',1.20,'€/m³'),(@lt,'water','sewerage','Nuotekos',1.45,'€/m³'),
(@lt,'banking','base_rate','Bazinė palūkanų norma',3.5,'%'),(@lt,'banking','business_loan','Verslo paskolos nuo',5.9,'%'),
(@lt,'taxes','vat','PVM',21,'%'),(@lt,'taxes','profit_tax','Pelno mokestis',17,'%'),
(@lt,'transport','diesel','Dyzelinas',1.62,'€/l'),(@lt,'labour','minimum_wage','Minimalus atlyginimas',1250,'€'),
(@lt,'real_estate','commercial_rent','Komercinė nuoma (bazė)',9.5,'€/m²'),(@lt,'trade','import_index','Importo kaštų indeksas',100,'ind.'),
(@lt,'population','purchasing_power','Perkamosios galios indeksas',100,'ind.')
ON DUPLICATE KEY UPDATE label=VALUES(label),unit=VALUES(unit);
